<?php

namespace Tests\Unit\Services;

use App\Repositories\CalendarioForenseRepository;
use App\Repositories\IntimacoesRepository;
use App\Services\CalendarioForenseService;
use App\Services\IntimacoesService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class IntimacoesResolverPrazoTest extends TestCase
{
    private function resolver(array $dadosIA, ?string $dataDisp, ?array $processo = null): array
    {
        $repo = $this->createMock(CalendarioForenseRepository::class);
        $repo->method('feriadosNoPeriodo')->willReturn([]);
        $repo->method('suspensoesNoPeriodo')->willReturn([]);

        $service = new IntimacoesService(
            $this->createMock(IntimacoesRepository::class),
            null, null, null, null, null, null, null,
            new CalendarioForenseService($repo)
        );

        $m = new ReflectionMethod($service, 'resolverPrazo');
        $m->setAccessible(true);

        return $m->invoke($service, $dadosIA, $dataDisp, $processo);
    }

    public function testComDiasIdentificadosCalculaEmDiasUteis(): void
    {
        $r = $this->resolver(['categoria' => 'prazo', 'prazo_dias' => '15', 'tipo_ato' => 'sentença', 'data_critica' => '2026-01-01'], '2026-08-14', ['siglaTribunal' => 'TJSP']);

        // Ignora a data da IA: publicação 17/08, dia 1 = 18/08, 15 dias úteis => 07/09 é feriado => 08/09? contagem abaixo
        $this->assertFalse($r['a_conferir']);
        $this->assertSame('dias_uteis', $r['memoria']['metodo']);
        $this->assertSame('sentença', $r['memoria']['tipo_ato']);
        $this->assertSame('2026-09-08', $r['prazo']);
    }

    public function testSemDiasUsaDataDaIaEMarcaAConferir(): void
    {
        $r = $this->resolver(['categoria' => 'prazo', 'data_critica' => '2026-12-31'], '2026-08-14');

        $this->assertTrue($r['a_conferir']);
        $this->assertSame('2026-12-31', $r['prazo']);
        $this->assertSame('estimativa', $r['memoria']['metodo']);
    }

    public function testAudienciaComDataCertaNaoFicaAConferir(): void
    {
        $r = $this->resolver(['categoria' => 'audiencia', 'data_critica' => '2026-10-20'], '2026-08-14');

        $this->assertFalse($r['a_conferir']);
        $this->assertSame('2026-10-20', $r['prazo']);
        $this->assertSame('data_informada', $r['memoria']['metodo']);
    }

    public function testSemNadaAplicaPadraoDeDoisDiasUteisEMarcaAConferir(): void
    {
        // Sexta 14/08: publicação 17/08, dias 18/08 e 19/08
        $r = $this->resolver(['categoria' => 'prazo'], '2026-08-14');

        $this->assertTrue($r['a_conferir']);
        $this->assertSame('2026-08-19', $r['prazo']);
        $this->assertSame('padrao', $r['memoria']['metodo']);
        $this->assertStringContainsString('Conferir manualmente', $r['memoria']['avisos'][0]);
    }
}
