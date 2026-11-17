<?php

namespace Tests\App\Services\Liquidacao;

use App\Repositories\LiquidacoesRepository;
use App\Repositories\Relatorios\LegalOpsRepository;
use App\Services\AcessoEntidadeService;
use App\Services\LiquidacaoRelatorioService;
use App\Services\LiquidacoesService;
use App\Services\ProcessosService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Liquidacao\ComTabelasLiquidacao;

/**
 * Liquidação 5/6: PDF, resumo no processo e valor atualizado no BI (Legal Ops).
 */
class LiquidacaoIntegracoesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use ComTabelasLiquidacao;

    protected LiquidacoesRepository $repo;
    protected LiquidacoesService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelasLiquidacao();
        // Legal Ops lê risco e valor da causa
        $this->db->query('DROP TABLE db_processos');
        $this->db->query('CREATE TABLE db_processos (id_processo INTEGER PRIMARY KEY AUTOINCREMENT, numero_processo VARCHAR(50), cliente_id INTEGER, responsavel_id INTEGER, risco VARCHAR(20), valorCausa BIGINT, arquived_at DATETIME, deleted_at DATETIME)');
        $this->db->table('processos')->insertBatch([
            ['id_processo' => 1, 'numero_processo' => '0001', 'cliente_id' => 10, 'risco' => 'Provável', 'valorCausa' => 50000],
            ['id_processo' => 2, 'numero_processo' => '0002', 'cliente_id' => 10, 'risco' => 'Provável', 'valorCausa' => 30000],
            ['id_processo' => 3, 'numero_processo' => '0003', 'cliente_id' => 10, 'risco' => 'Possível', 'valorCausa' => 20000],
            ['id_processo' => 4, 'numero_processo' => '0004', 'cliente_id' => 10, 'risco' => 'Possível', 'valorCausa' => 10000],
        ]);

        $acesso = $this->createMock(AcessoEntidadeService::class);
        $acesso->method('podeAcessar')->willReturn(true);
        $acesso->method('aplicarEscopo')->willReturnArgument(0);

        $this->repo    = new LiquidacoesRepository(null, null, null, null, null, null, $acesso);
        $this->service = new class ($this->repo, null, $acesso) extends LiquidacoesService {
            public function escopoClienteId(): ?int
            {
                return null;
            }
        };
    }

    private function criar(int $processoId, string $status, int $valor): int
    {
        return $this->service->criar([
            'processo_id' => $processoId,
            'status'      => $status,
            'itens'       => [['tipo_item' => 'principal', 'descricao' => 'Verba', 'valor_base' => $valor]],
        ]);
    }

    public function testResumoDoProcessoUsaALiquidacaoMaisRecente(): void
    {
        $this->criar(1, 'apresentada', 1000);
        $id = $this->criar(1, 'em_pagamento', 100000);
        $this->db->table('liquidacoes_pagamentos')->insertBatch([
            ['liquidacao_id' => $id, 'numero_parcela' => 1, 'valor_parcela' => 40000, 'data_vencimento' => '2026-01-10', 'data_pagamento' => '2026-01-10', 'status_pagamento' => 'paga'],
            ['liquidacao_id' => $id, 'numero_parcela' => 2, 'valor_parcela' => 60000, 'data_vencimento' => '2026-02-10', 'data_pagamento' => null, 'status_pagamento' => 'pendente'],
        ]);

        $resumo = $this->service->resumoDoProcesso(1);

        $this->assertSame($id, $resumo['liquidacao_id']);
        $this->assertSame(100000, $resumo['valor_atualizado']);
        $this->assertSame(40000, $resumo['total_pago']);
        $this->assertSame(60000, $resumo['saldo_a_receber']);
        $this->assertSame('2026-02-10', $resumo['proxima_parcela_vencimento']);
        $this->assertNull($this->service->resumoDoProcesso(2));
    }

    public function testLegalOpsTrocaValorDaCausaPeloValorAtualizado(): void
    {
        $this->criar(1, 'homologada', 123400);   // substitui 500,00 por 1.234,00
        $this->criar(3, 'rascunho', 999900);     // rascunho não substitui
        $this->criar(4, 'quitada', 777700);      // quitada: exposição zero

        $acesso = $this->createMock(AcessoEntidadeService::class);
        $acesso->method('aplicarEscopo')->willReturnArgument(0);
        $acesso->method('ignoraEscopo')->willReturn(true);
        $legalOps = new LegalOpsRepository();
        $propriedade = new \ReflectionProperty($legalOps, 'acessos');
        $propriedade->setValue($legalOps, $acesso);

        $valores = array_column($legalOps->getValoresPorRisco(), 'total_causa', 'risco');

        $this->assertEqualsWithDelta(1234.00 + 300.00, (float) $valores['Provável'], 0.001);
        $this->assertEqualsWithDelta(200.00 + 0.0, (float) $valores['Possível'], 0.001);
    }

    public function testPdfEhGeradoParaALiquidacao(): void
    {
        $id = $this->criar(1, 'apresentada', 100000);

        $processos = $this->createMock(ProcessosService::class);
        $processos->method('buscarDetalhesProcesso')->willReturn([
            'processo' => ['nome_cliente' => 'Cliente X'], 'poloAtivo' => [['nome' => 'Autor']], 'poloPassivo' => [['nome' => 'Réu']],
        ]);

        $relatorios = new LiquidacaoRelatorioService($this->service, $processos);
        $relatorio  = $relatorios->relatorio($id);

        $this->assertSame('Cliente X', $relatorio['cabecalho']['cliente']);
        $this->assertSame(['Autor'], $relatorio['cabecalho']['poloAtivo']);
        $this->assertStringStartsWith('%PDF', $relatorios->pdf($id));
        $this->assertStringContainsString('Verba', $relatorios->csv($id));
    }
}
