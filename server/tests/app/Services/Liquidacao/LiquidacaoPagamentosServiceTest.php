<?php

namespace Tests\App\Services\Liquidacao;

use App\Libraries\Liquidacao\LiquidacaoException;
use App\Repositories\LiquidacoesRepository;
use App\Services\AcessoEntidadeService;
use App\Services\LiquidacaoFinanceiroService;
use App\Services\LiquidacaoPagamentosService;
use App\Services\LiquidacoesArquivosService;
use App\Services\LiquidacoesService;
use App\Services\NotificacaoService;
use App\Services\TarefasService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Liquidacao\ComTabelasLiquidacao;

/**
 * Liquidação 4/6: parcelamento, baixa, cancelamento, honorários no Financeiro e vencimentos.
 */
class LiquidacaoPagamentosServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use ComTabelasLiquidacao;

    protected LiquidacoesRepository $repo;
    protected LiquidacoesService $liquidacoes;
    protected $arquivos;
    protected $financeiro;
    protected $notificacao;
    protected $tarefas;
    protected LiquidacaoPagamentosService $service;
    protected int $liquidacaoId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->criarTabelasLiquidacao();
        $this->db->table('processos')->insert(['id_processo' => 1, 'numero_processo' => '0001', 'cliente_id' => 10, 'responsavel_id' => 7]);

        $acesso = $this->createMock(AcessoEntidadeService::class);
        $acesso->method('podeAcessar')->willReturn(true);
        $acesso->method('aplicarEscopo')->willReturnArgument(0);

        $this->repo        = new LiquidacoesRepository(null, null, null, null, null, null, $acesso);
        $this->liquidacoes = new class ($this->repo, null, $acesso) extends LiquidacoesService {
            public function escopoClienteId(): ?int
            {
                return null;
            }
        };

        $this->arquivos    = $this->createMock(LiquidacoesArquivosService::class);
        $this->financeiro  = $this->createMock(LiquidacaoFinanceiroService::class);
        $this->notificacao = $this->createMock(NotificacaoService::class);
        $this->tarefas     = $this->createMock(TarefasService::class);

        $this->service = new LiquidacaoPagamentosService(
            $this->liquidacoes, $this->repo, $this->arquivos, $this->financeiro, $this->notificacao, $this->tarefas
        );

        $this->liquidacaoId = $this->liquidacoes->criar([
            'processo_id' => 1,
            'status'      => 'homologada',
            'itens'       => [['tipo_item' => 'principal', 'descricao' => 'Verba', 'valor_base' => 10000]],
        ]);
    }

    private function parcelas(): array
    {
        return $this->service->gerarParcelas($this->liquidacaoId, ['numero_parcelas' => 2, 'primeiro_vencimento' => '2026-01-10']);
    }

    private function statusAtual(): string
    {
        return $this->repo->findById($this->liquidacaoId)['status'];
    }

    public function testGerarParcelasCriaPlanoEMudaStatus(): void
    {
        $parcelas = $this->parcelas();

        $this->assertCount(2, $parcelas);
        $this->assertSame(10000, array_sum(array_column($parcelas, 'valor_parcela')));
        $this->assertSame('em_pagamento', $this->statusAtual());
        $this->assertSame(2, $this->liquidacoes->getById($this->liquidacaoId)['resumo_pagamentos']['total_parcelas']);
    }

    public function testRefazerExigeSubstituir(): void
    {
        $this->parcelas();

        try {
            $this->parcelas();
            $this->fail('Deveria recusar sem "substituir".');
        } catch (LiquidacaoException $e) {
            $this->assertSame(409, $e->status());
        }

        $novas = $this->service->gerarParcelas($this->liquidacaoId, ['numero_parcelas' => 4, 'primeiro_vencimento' => '2026-01-10', 'substituir' => true]);
        $this->assertCount(4, $novas);
        $this->assertCount(4, $this->repo->getPagamentosByLiquidacao($this->liquidacaoId));
    }

    public function testNaoRefazComParcelaPaga(): void
    {
        $parcelas = $this->parcelas();
        $this->service->baixar($parcelas[0]['pagamento_id'], ['data_pagamento' => '2026-01-10']);

        $this->expectException(LiquidacaoException::class);
        $this->service->gerarParcelas($this->liquidacaoId, ['numero_parcelas' => 3, 'primeiro_vencimento' => '2026-03-10', 'substituir' => true]);
    }

    public function testBaixarTodasQuitaALiquidacao(): void
    {
        $parcelas = $this->parcelas();

        $r1 = $this->service->baixar($parcelas[0]['pagamento_id'], ['data_pagamento' => '2026-01-10', 'forma_pagamento' => 'pix']);
        $this->assertFalse($r1['quitada']);
        $this->assertSame('paga', $r1['pagamento']['status_pagamento']);
        $this->assertSame('em_pagamento', $this->statusAtual());

        $r2 = $this->service->baixar($parcelas[1]['pagamento_id'], ['data_pagamento' => '2026-02-10']);
        $this->assertTrue($r2['quitada']);
        $this->assertSame('quitada', $this->statusAtual());
    }

    public function testBaixarRecusaDataFuturaEParcelaJaPaga(): void
    {
        $parcelas = $this->parcelas();

        try {
            $this->service->baixar($parcelas[0]['pagamento_id'], ['data_pagamento' => date('Y-m-d', strtotime('+2 days'))]);
            $this->fail('Data futura deveria ser recusada.');
        } catch (LiquidacaoException $e) {
            $this->assertSame(422, $e->status());
        }

        $this->service->baixar($parcelas[0]['pagamento_id'], ['data_pagamento' => '2026-01-10']);
        $this->expectException(LiquidacaoException::class);
        $this->service->baixar($parcelas[0]['pagamento_id'], ['data_pagamento' => '2026-01-11']);
    }

    public function testComprovanteEnviadoGuardaLinkNaParcela(): void
    {
        $parcelas = $this->parcelas();
        $this->arquivos->expects($this->once())->method('enviar')->willReturn([
            'enviados' => [['url_arquivo' => 'https://exemplo.test/comprovante', 'documento_id' => 1]],
            'erros'    => [],
        ]);

        $r = $this->service->baixar($parcelas[0]['pagamento_id'], ['data_pagamento' => '2026-01-10'], [['path' => '/tmp/x', 'nome' => 'x.pdf']], 5);

        $this->assertSame('https://exemplo.test/comprovante', $r['pagamento']['comprovante_url']);
    }

    public function testFalhaNoDriveNaoBaixaAParcela(): void
    {
        $parcelas = $this->parcelas();
        $this->arquivos->method('enviar')->willReturn(['enviados' => [], 'erros' => [['arquivo' => 'x.pdf', 'mensagem' => 'Drive fora do ar']]]);

        try {
            $this->service->baixar($parcelas[0]['pagamento_id'], ['data_pagamento' => '2026-01-10'], [['path' => '/tmp/x', 'nome' => 'x.pdf']]);
            $this->fail('Deveria falhar.');
        } catch (LiquidacaoException $e) {
            $this->assertSame(502, $e->status());
        }

        $this->assertSame('pendente', $this->repo->getPagamento($parcelas[0]['pagamento_id'])['status_pagamento']);
    }

    public function testHonorariosLancadosNaBaixaEGuardados(): void
    {
        $this->repo->getModel()->update($this->liquidacaoId, ['lancar_honorarios' => 1, 'honorarios_percentual' => 30]);
        $parcelas = $this->parcelas();
        $this->financeiro->expects($this->once())->method('lancarHonorarios')->willReturn([55, 56]);

        $r = $this->service->baixar($parcelas[0]['pagamento_id'], ['data_pagamento' => '2026-01-10']);

        $this->assertSame([55, 56], $r['lancamentos']);
        $this->assertSame([55, 56], json_decode($r['pagamento']['lancamentos_financeiros'], true));
    }

    public function testFalhaNoFinanceiroDesfazABaixa(): void
    {
        $this->repo->getModel()->update($this->liquidacaoId, ['lancar_honorarios' => 1]);
        $parcelas = $this->parcelas();
        $this->financeiro->method('lancarHonorarios')->willThrowException(LiquidacaoException::invalida('Mês fechado.'));

        try {
            $this->service->baixar($parcelas[0]['pagamento_id'], ['data_pagamento' => '2026-01-10']);
            $this->fail('Deveria falhar.');
        } catch (LiquidacaoException $e) {
            $this->assertStringContainsString('Mês fechado', $e->getMessage());
        }

        $this->assertSame('pendente', $this->repo->getPagamento($parcelas[0]['pagamento_id'])['status_pagamento']);
    }

    public function testCancelarParcelaUltimaAbertaQuitaPelasPagas(): void
    {
        $parcelas = $this->parcelas();
        $this->service->baixar($parcelas[0]['pagamento_id'], ['data_pagamento' => '2026-01-10']);

        $cancelada = $this->service->cancelar($parcelas[1]['pagamento_id'], 'Acordo desfeito');

        $this->assertSame('cancelada', $cancelada['status_pagamento']);
        $this->assertSame('Acordo desfeito', $cancelada['observacao']);
        $this->assertSame('quitada', $this->statusAtual());
    }

    public function testProcessarVencimentosMarcaAvisaEAbreTarefaUmaVez(): void
    {
        $this->parcelas(); // vencem em 2026-01-10 e 2026-02-10

        $this->notificacao->expects($this->exactly(2))->method('enviarNotificacao')
            ->with(7, 'LAYOUT_LIQUIDACAO_PARCELA', $this->anything())->willReturn(true);
        $this->tarefas->expects($this->once())->method('salvarTarefa')->willReturn(['success' => true]);

        // 1ª parcela vencida; a 2ª vence em 2 dias (dentro do aviso de 3 dias)
        $r = $this->service->processarVencimentos(3, '2026-02-08');
        $this->assertSame(1, $r['vencidas']);
        $this->assertSame(1, $r['avisos_a_vencer']);
        $this->assertSame(1, $r['tarefas']);

        // segunda execução no mesmo dia não repete nada
        $r2 = $this->service->processarVencimentos(3, '2026-02-08');
        $this->assertSame(0, $r2['vencidas']);
        $this->assertSame(0, $r2['avisos_a_vencer']);
    }

    public function testSemResponsavelNaoAvisaMasMarcaVencida(): void
    {
        $this->db->table('processos')->update(['responsavel_id' => null], ['id_processo' => 1]);
        $this->parcelas();
        $this->notificacao->expects($this->never())->method('enviarNotificacao');

        $r = $this->service->processarVencimentos(3, '2026-01-20');

        $this->assertSame(1, $r['vencidas']);
        $this->assertGreaterThanOrEqual(1, $r['sem_responsavel']);
    }
}
