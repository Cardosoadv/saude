<?php

namespace Tests\Unit\Services;

use App\Repositories\CalendarioForenseRepository;
use App\Services\CalendarioForenseService;
use App\Services\FeriadosNacionais;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CalendarioForenseServiceTest extends TestCase
{
    private function service(array $feriados = [], array $suspensoes = []): CalendarioForenseService
    {
        $repo = $this->createMock(CalendarioForenseRepository::class);
        $repo->method('feriadosNoPeriodo')->willReturn($feriados);
        $repo->method('suspensoesNoPeriodo')->willReturn($suspensoes);

        return new CalendarioForenseService($repo);
    }

    public function testPascoaEFeriadosMoveis(): void
    {
        $this->assertSame('2026-04-05', FeriadosNacionais::pascoa(2026)->format('Y-m-d'));
        $this->assertSame('2025-04-20', FeriadosNacionais::pascoa(2025)->format('Y-m-d'));
        $this->assertArrayHasKey('2026-04-03', FeriadosNacionais::doAno(2026)); // Sexta-feira da Paixão
        $this->assertArrayHasKey('2026-11-20', FeriadosNacionais::doAno(2026));
    }

    public function testContagemSimplesExcluiDiaDoComecoEIncluiVencimento(): void
    {
        // Quarta 2026-09-02: publicação quinta 03/09, dia 1 = sexta 04/09, 5 dias úteis => sexta 11/09? (07/09 é feriado)
        $m = $this->service()->memoriaCalculo('2026-09-02', 5);

        $this->assertSame('2026-09-03', $m['data_publicacao']);
        $this->assertSame('2026-09-04', $m['inicio_contagem']);
        // 04, 08, 09, 10, 11 (07/09 feriado, 05-06 fim de semana)
        $this->assertSame('2026-09-11', $m['vencimento']);
        $this->assertSame(5, $m['dias_uteis']);
    }

    public function testViradaDeFimDeSemana(): void
    {
        // Sexta 2026-08-14 => publicação segunda 17/08; dia 1 = terça 18/08; 1 dia => 18/08
        $m = $this->service()->memoriaCalculo('2026-08-14', 1);
        $this->assertSame('2026-08-17', $m['data_publicacao']);
        $this->assertSame('2026-08-18', $m['vencimento']);

        $motivos = array_column($m['descontados'], 'motivo');
        $this->assertSame(['fim_de_semana', 'fim_de_semana'], $motivos);
    }

    public function testFeriadoNoDiaDoVencimentoPassaParaOProximoDiaUtil(): void
    {
        // Prazo de 2 dias: publicação 03/09 (qui), dia 1 = 04/09 (sex), dia 2 cairia em 07/09 (feriado), vai para 08/09
        $m = $this->service()->memoriaCalculo('2026-09-02', 2);
        $this->assertSame('2026-09-08', $m['vencimento']);

        $feriado = array_values(array_filter($m['descontados'], fn ($d) => $d['motivo'] === 'feriado'));
        $this->assertCount(1, $feriado);
        $this->assertSame('2026-09-07', $feriado[0]['data']);
        $this->assertSame('Independência do Brasil', $feriado[0]['descricao']);
    }

    public function testFeriadoMunicipalSoValeNaComarca(): void
    {
        $f = [['data' => '2026-08-18', 'nome' => 'Aniversário da cidade', 'abrangencia' => 'municipal',
               'uf' => 'SP', 'municipio' => 'Campinas', 'tribunal' => null, 'recorrente' => 0]];

        $comMunicipio = $this->service($f)->calcularVencimento('2026-08-14', 1, 'TJSP', null, 'campínas');
        $semMunicipio = $this->service($f)->calcularVencimento('2026-08-14', 1, 'TJSP', null, 'Santos');

        $this->assertSame('2026-08-19', $comMunicipio);
        $this->assertSame('2026-08-18', $semMunicipio);
    }

    public function testFeriadoEstadualUsaUfDoTribunal(): void
    {
        $f = [['data' => '2026-08-18', 'nome' => 'Data magna', 'abrangencia' => 'estadual',
               'uf' => 'SP', 'municipio' => null, 'tribunal' => null, 'recorrente' => 0]];

        $this->assertSame('2026-08-19', $this->service($f)->calcularVencimento('2026-08-14', 1, 'TJSP'));
        $this->assertSame('2026-08-18', $this->service($f)->calcularVencimento('2026-08-14', 1, 'TJMG'));
        $this->assertSame('2026-08-18', $this->service($f)->calcularVencimento('2026-08-14', 1, 'TRF3'));
    }

    public function testFeriadoRecorrenteValeEmTodoAno(): void
    {
        $f = [['data' => '2020-08-18', 'nome' => 'Feriado local', 'abrangencia' => 'nacional',
               'uf' => null, 'municipio' => null, 'tribunal' => null, 'recorrente' => 1]];

        $this->assertSame('2026-08-19', $this->service($f)->calcularVencimento('2026-08-14', 1));
    }

    public function testRecessoForenseSuspendeOPrazo(): void
    {
        // Disponibilização 2026-12-18 (sex): publicação seg 21/12 está no recesso, então só 21/01/2027 (qui).
        $m = $this->service()->memoriaCalculo('2026-12-18', 5);

        $this->assertSame('2027-01-21', $m['data_publicacao']);
        // 22, 25, 26... => 22/01 (sex) dia1; 25, 26, 27, 28 => vencimento 28/01
        $this->assertSame('2027-01-22', $m['inicio_contagem']);
        $this->assertSame('2027-01-28', $m['vencimento']);
        $this->assertContains('recesso', array_column($m['descontados'], 'motivo'));
    }

    public function testRecessoNoMeioDoPrazo(): void
    {
        // Disponibilização 2026-12-11 (sex): publicação seg 14/12; dia 1 = 15/12; 10 dias:
        // 15,16,17,18 (4) | 19 sáb, 20 dom | 21/12 a 20/01 recesso | 21,22,25,26,27,28/01 (6 restantes)
        $m = $this->service()->memoriaCalculo('2026-12-11', 10);
        $this->assertSame('2027-01-28', $m['vencimento']);
    }

    public function testSuspensaoDeExpedienteNoMeioDoPrazoSoDoTribunalIndicado(): void
    {
        $s = [['tribunal' => 'TJSP', 'data_inicio' => '2026-08-19', 'data_fim' => '2026-08-20', 'motivo' => 'Instabilidade do sistema']];

        $m = $this->service([], $s)->memoriaCalculo('2026-08-14', 3, 'TJSP');
        // dia 1 = 18/08, 19 e 20 suspensos, 21 e 24 => vencimento 24/08
        $this->assertSame('2026-08-24', $m['vencimento']);
        $this->assertSame(['suspensao', 'suspensao'], array_values(array_filter(
            array_column($m['descontados'], 'motivo'), fn ($x) => $x === 'suspensao'
        )));

        $outro = $this->service([], $s)->calcularVencimento('2026-08-14', 3, 'TJMG');
        $this->assertSame('2026-08-20', $outro);
    }

    public function testAdicionarDiasUteisParaMargemDeSeguranca(): void
    {
        // Sexta 14/08 + 2 dias úteis => terça 18/08
        $this->assertSame('2026-08-18', $this->service()->adicionarDiasUteis('2026-08-14', 2));
        $this->assertSame('2026-08-14', $this->service()->adicionarDiasUteis('2026-08-14', 0));
    }

    public function testMemoriaPrazoAConferir(): void
    {
        $m = $this->service()->memoriaPrazoAConferir('2026-12-31', 'estimativa_ia');
        $this->assertTrue($m['a_conferir']);
        $this->assertSame('estimativa', $m['metodo']);
    }

    public function testEntradasInvalidas(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->calcularVencimento('2026-02-30', 5);
    }

    public function testDiasForaDoLimite(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->calcularVencimento('2026-08-14', 0);
    }

    public function testUfDoTribunal(): void
    {
        $s = $this->service();
        $this->assertSame('SP', $s->ufDoTribunal('tjsp'));
        $this->assertNull($s->ufDoTribunal('TRF3'));
        $this->assertNull($s->ufDoTribunal('TJXX'));
    }
}
