<?php

namespace Tests\Unit\Services;

use App\Repositories\ProcessosImportacaoRepository;
use App\Services\DatajudService;
use App\Services\DjenService;
use App\Services\EtiquetasService;
use App\Services\ProcessosImportacaoService;
use App\Services\ProcessosService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Pesquisa de processos no DJEN pelo nome da parte e vínculo de contrato na
 * importação.
 */
class ProcessosImportacaoPesquisaParteTest extends TestCase
{
    private const NUM_1 = '0801234-04.2025.8.26.0100';
    private const NUM_2 = '1004567-38.2024.5.02.0001';
    private const DIG_1 = '08012340420258260100';
    private const DIG_2 = '10045673820245020001';

    private function repo(array $situacao = [], ?array $carteira = null): ProcessosImportacaoRepository
    {
        $repo = $this->createMock(ProcessosImportacaoRepository::class);
        $repo->method('clientes')->willReturn([['id_cliente' => 10, 'nome' => 'Maria da Silva', 'razao_social' => null, 'documento' => null]]);
        $repo->method('usuarios')->willReturn([]);
        $repo->method('ramos')->willReturn([]);
        $repo->method('contratos')->willReturn([['id_contrato' => 7, 'numero_contrato' => '2025/01', 'cliente_id' => 10, 'cliente_nome' => 'Maria da Silva']]);
        $repo->method('situacaoNaCarteira')->willReturn($situacao);
        $repo->method('processoPorNumero')->willReturn($carteira);
        $repo->method('partesDoProcesso')->willReturn([]);
        $repo->method('etiquetasDoProcesso')->willReturn([]);

        return $repo;
    }

    private function service(?DjenService $djen = null, ?ProcessosImportacaoRepository $repo = null): ProcessosImportacaoService
    {
        return new ProcessosImportacaoService(
            $djen ?? $this->createMock(DjenService::class),
            new DatajudService(),
            $repo ?? $this->repo(),
            $this->createMock(ProcessosService::class),
            $this->createMock(EtiquetasService::class)
        );
    }

    private function item(string $numero, string $data, array $destinatarios, string $classe = 'PROCEDIMENTO COMUM CÍVEL'): array
    {
        return [
            'numero_processo'          => preg_replace('/\D/', '', $numero),
            'numeroprocessocommascara' => $numero,
            'data_disponibilizacao'    => $data,
            'siglaTribunal'            => 'TJSP',
            'nomeOrgao'                => '1ª Vara Cível',
            'nomeClasse'               => $classe,
            'destinatarios'            => $destinatarios,
        ];
    }

    // --------------------------------------------------------------- pesquisa

    public function testMontarPesquisaAgrupaPorProcessoEMarcaCorrespondencia(): void
    {
        $resposta = [
            'status'   => 'encontrado',
            'mensagem' => null,
            'total'    => 4,
            'truncado' => false,
            'itens'    => [
                // NUM_2: nome contido em outro ("Maria Juraci da Silva").
                $this->item(self::NUM_2, '2026-10-20', [['polo' => 'A', 'nome' => 'MARIA JURACI DA SILVA'], ['polo' => 'P', 'nome' => 'Banco X']]),
                // NUM_1: duas publicações, nome idêntico (sem acento e pontuação).
                $this->item(self::NUM_1, '2026-09-01', [['polo' => 'A', 'nome' => 'JURACÍ DA SILVA.'], ['polo' => 'P', 'nome' => 'Banco Y']]),
                $this->item(self::NUM_1, '2026-10-05', [['polo' => 'A', 'nome' => 'Juraci da Silva'], ['polo' => 'P', 'nome' => 'Banco Y']]),
                // Número inválido: ignorado.
                $this->item('123', '2026-10-01', [['polo' => 'A', 'nome' => 'Juraci da Silva']]),
            ],
        ];

        $r = $this->service(null, $this->repo([self::DIG_2 => ['id_processo' => 5, 'deleted_at' => null]]))
            ->montarPesquisa('Juraci da Silva', $resposta);

        $this->assertSame('encontrado', $r['status']);
        $this->assertCount(2, $r['processos']);

        [$primeiro, $segundo] = $r['processos'];

        // Exata vem antes da parcial, mesmo com publicação mais antiga.
        $this->assertSame(self::DIG_1, $primeiro['numero_processo']);
        $this->assertSame(self::NUM_1, $primeiro['numeroprocessocommascara']);
        $this->assertSame('exata', $primeiro['correspondencia']);
        $this->assertSame('novo', $primeiro['situacao']);
        $this->assertSame(2, $primeiro['total_publicacoes']);
        $this->assertSame('2026-10-05', $primeiro['ultima_publicacao']);
        $this->assertCount(1, $primeiro['partes_encontradas'], 'A mesma parte em duas publicações aparece uma vez.');
        $this->assertSame('A', $primeiro['partes_encontradas'][0]['polo']);

        $this->assertSame('parcial', $segundo['correspondencia']);
        $this->assertSame('cadastrado', $segundo['situacao']);
        $this->assertSame('MARIA JURACI DA SILVA', $segundo['partes_encontradas'][0]['nome']);
    }

    public function testMontarPesquisaSituacaoExcluidoEAvisoDeResultadoTruncado(): void
    {
        $r = $this->service(null, $this->repo([self::DIG_1 => ['id_processo' => 3, 'deleted_at' => '2026-01-01 00:00:00']]))
            ->montarPesquisa('Fulano', [
                'status'   => 'encontrado',
                'mensagem' => null,
                'total'    => 900,
                'truncado' => true,
                'itens'    => [$this->item(self::NUM_1, '2026-10-01', [['polo' => 'P', 'nome' => 'Outra Pessoa']])],
            ]);

        $this->assertSame('excluido', $r['processos'][0]['situacao']);
        $this->assertSame('nenhuma', $r['processos'][0]['correspondencia']);
        $this->assertTrue($r['truncado']);
        $this->assertSame(900, $r['total_comunicacoes']);
        $this->assertStringContainsString('Reduza o período', $r['mensagem']);
    }

    public function testPesquisarPorParteRepassaFiltrosAoDjen(): void
    {
        $djen = $this->createMock(DjenService::class);
        $djen->expects($this->once())
            ->method('pesquisarPorNomeParte')
            ->with('Juraci da Silva', '2026-01-01', '2026-03-31', 'TRT2')
            ->willReturn(['status' => 'nao_encontrado', 'mensagem' => 'Nada.', 'total' => 0, 'truncado' => false, 'itens' => []]);

        $r = $this->service($djen)->pesquisarPorParte('  Juraci   da Silva ', '2026-01-01', '2026-03-31', 'trt2');

        $this->assertSame('nao_encontrado', $r['status']);
        $this->assertSame([], $r['processos']);
        $this->assertSame(['nome' => 'Juraci da Silva', 'data_inicio' => '2026-01-01', 'data_fim' => '2026-03-31', 'tribunal' => 'TRT2'], $r['filtros']);
    }

    public function testPesquisarPorParteUsaUltimoAnoComoPeriodoPadrao(): void
    {
        $djen = $this->createMock(DjenService::class);
        $djen->expects($this->once())
            ->method('pesquisarPorNomeParte')
            ->with('Maria', date('Y-m-d', strtotime('-1 year')), date('Y-m-d'), null)
            ->willReturn(['status' => 'nao_encontrado', 'mensagem' => null, 'total' => 0, 'truncado' => false, 'itens' => []]);

        $this->service($djen)->pesquisarPorParte('Maria');
    }

    public function testPesquisarPorParteValidaEntrada(): void
    {
        $djen = $this->createMock(DjenService::class);
        $djen->expects($this->never())->method('pesquisarPorNomeParte');
        $s = $this->service($djen);

        foreach ([
            ['A. B', null, null, null],                       // poucas letras
            [str_repeat('a', 151), null, null, null],         // longo demais
            ['Maria', '2026-02-30', null, null],              // data inexistente
            ['Maria', '01/01/2026', null, null],              // formato errado
            ['Maria', '2026-05-01', '2026-04-01', null],      // início depois do fim
            ['Maria', null, null, 'TJ SP;'],                  // tribunal inválido
        ] as $args) {
            try {
                $s->pesquisarPorParte(...$args);
                $this->fail('Deveria rejeitar ' . json_encode($args));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // --------------------------------------------------------------- contrato

    public function testConferenciaTrazContratoDaCarteira(): void
    {
        $carteira = [
            'id_processo' => 99, 'numero_processo' => self::DIG_1, 'cliente_id' => 10, 'cliente_nome' => 'Maria da Silva',
            'contrato_id' => 7, 'contrato_numero' => '2025/01', 'deleted_at' => null, 'partes' => [], 'etiquetas' => [],
        ];
        $vazio = ['status' => 'nao_encontrado', 'mensagem' => null, 'dados' => []];

        $conf = $this->service()->montarConferencia(self::DIG_1, [], $vazio, $vazio, $carteira);

        $contrato = array_values(array_filter($conf['campos'], fn ($c) => $c['campo'] === 'contrato_id'))[0];
        $this->assertSame('contrato', $contrato['tipo']);
        $this->assertSame(7, $contrato['sugestao']);
        $this->assertSame(7, $contrato['atual']);
        $this->assertSame('Contrato #2025/01', $contrato['opcoes'][0]['rotulo']);
    }

    public function testSanitizarCamposValidaContrato(): void
    {
        $s = $this->service();

        $this->assertSame(['contrato_id' => 7], $s->sanitizarCampos(['contrato_id' => '7']));
        $this->assertSame(['contrato_id' => null], $s->sanitizarCampos(['contrato_id' => '']));

        $this->expectException(InvalidArgumentException::class);
        $s->sanitizarCampos(['contrato_id' => 8]);
    }

    public function testConfirmarGravaContratoNoProcessoNovo(): void
    {
        $processos = $this->createMock(ProcessosService::class);
        $processos->expects($this->once())
            ->method('salvarProcesso')
            ->with($this->callback(fn (array $d) => $d['contrato_id'] === 7 && $d['cliente_id'] === 10), null)
            ->willReturn(123);

        $s = new ProcessosImportacaoService(
            $this->createMock(DjenService::class),
            new DatajudService(),
            $this->repo(),
            $processos,
            $this->createMock(EtiquetasService::class)
        );

        $r = $s->confirmar([['numero_processo' => self::NUM_1, 'campos' => ['cliente_id' => 10, 'contrato_id' => 7]]]);

        $this->assertSame(1, $r['criados']);
        $this->assertSame(123, $r['resultados'][0]['id_processo']);
    }

    public function testRotuloContrato(): void
    {
        $this->assertNull(ProcessosImportacaoService::rotuloContrato(null, '1'));
        $this->assertSame('Contrato #15', ProcessosImportacaoService::rotuloContrato(15, ''));
        $this->assertSame('Contrato #A-1', ProcessosImportacaoService::rotuloContrato(15, 'A-1'));
    }
}
