<?php

namespace Tests\Unit\Services\Financeiro;

use App\Repositories\Financeiro\ConciliacaoCandidatosRepository;
use App\Repositories\Financeiro\ExtratosMovimentosRepository;
use App\Services\Financeiro\DespesasService;
use App\Services\Financeiro\Extrato\ConciliacaoConflitoException;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use App\Services\Financeiro\Extrato\ExtratoNaoEncontradoException;
use App\Services\Financeiro\LancamentoExtratoService;
use App\Services\Financeiro\PagamentoDespesasService;
use App\Services\Financeiro\PagamentoReceitasService;
use App\Services\Financeiro\ReceitasService;
use App\Services\Financeiro\RegrasExtratoService;
use App\Services\Financeiro\SaldoMensalService;
use App\Services\Financeiro\TransferenciasService;
use App\Services\FornecedorService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Lançamento a partir do extrato com serviços e repositórios em memória. Todos os dados são fictícios.
 */
class LancamentoExtratoServiceTest extends CIUnitTestCase
{
    private MundoLancamento $mundo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mundo = new MundoLancamento();
    }

    private function servico(?\App\Services\Financeiro\ExtratoConfig $config = null): LancamentoExtratoService
    {
        $m = $this->mundo;

        return new LancamentoExtratoService(
            new FakeMovimentosLancamento($m),
            new FakeCandidatosLancamento($m),
            new FakeDespesasLancamento($m),
            new FakeReceitasLancamento($m),
            new FakePagDespesasLancamento($m),
            new FakePagReceitasLancamento($m),
            new FakeTransferenciasLancamento($m),
            new FakeSaldoLancamento($m),
            new RegrasExtratoService(new FakeRegrasRepo($m)),
            new FakeDbLancamento($m),
            new FakeFornecedorServiceLancamento($m),
            null,
            null,
            $config
        );
    }

    private function movimento(int $conta, string $data, int $centavos, array $extra = []): int
    {
        return $this->mundo->adicionarMovimento($extra + [
            'conta_id' => $conta, 'data' => $data, 'valor_centavos' => $centavos,
            'memo' => 'Pagamento teste', 'nome_contraparte' => 'Fulano de Tal Teste', 'status' => 'pendente',
        ]);
    }

    private function dadosDespesa(array $extra = []): array
    {
        return $extra + ['fornecedor_id' => 7, 'categoria_id' => 3];
    }

    // ---------------------------------------------------------------- despesa

    public function testSaidaCriaDespesaEPagamentoEVinculaOMovimento(): void
    {
        $id = $this->movimento(1, '2026-10-10', -15050);

        $r = $this->servico()->lancar($id, $this->dadosDespesa(['rateio' => [['id' => 1, 'percentual' => 60], ['id' => 2, 'percentual' => 40]]]), 9);

        $this->assertSame('despesa', $r['pagamento_tipo']);
        $this->assertCount(1, $this->mundo->despesas);
        $this->assertCount(1, $this->mundo->pagDespesas);
        $d = reset($this->mundo->despesas);
        $p = reset($this->mundo->pagDespesas);
        $this->assertSame(7, $d['fornecedor']);
        $this->assertEqualsWithDelta(150.50, $d['valor'], 0.001);
        $this->assertSame('2026-10-10', $d['vencimento_dt']);
        $this->assertSame([['categoria_id' => 3, 'valor' => 150.5]], $d['categorias']);
        $this->assertSame(1, $p['conta_id']);
        $this->assertSame('2026-10-10', $p['pagamento_despesa_dt']);
        $this->assertEqualsWithDelta(90.30, $p['rateio'][0]['valor'], 0.001);
        $this->assertEqualsWithDelta(60.20, $p['rateio'][1]['valor'], 0.001);
        // A própria despesa guarda o rateio em percentual (formato da tela); o pagamento, em R$.
        $this->assertEquals([['id' => 1, 'valor' => 60.0], ['id' => 2, 'valor' => 40.0]], $d['rateio']);

        $mov = $this->mundo->movimentos[$id];
        $this->assertSame('lancado', $mov['status']);
        $this->assertSame(['despesa', $r['pagamento_id']], [$mov['pagamento_tipo'], $mov['pagamento_id']]);
        $this->assertSame(9, $mov['conciliado_por']);
        $this->assertSame('lancado', $r['movimento']['status']);
    }

    public function testRateioQueNaoFecha100EhRecusadoSemEfeitoColateral(): void
    {
        $id = $this->movimento(1, '2026-10-10', -10000);

        $this->expectException(ExtratoInvalidoException::class);
        try {
            $this->servico()->lancar($id, $this->dadosDespesa(['rateio' => [['id' => 1, 'percentual' => 60], ['id' => 2, 'percentual' => 30]]]));
        } finally {
            $this->assertSame([], $this->mundo->despesas);
            $this->assertSame('pendente', $this->mundo->movimentos[$id]['status']);
        }
    }

    public function testSemFornecedorOuSemCategoriaEhRecusado(): void
    {
        $id = $this->movimento(1, '2026-10-10', -10000);

        foreach ([['categoria_id' => 3], ['fornecedor_id' => 7], ['fornecedor_id' => 7, 'categoria_id' => 3, 'categorias' => [['categoria_id' => 3, 'valor' => 50]]]] as $dados) {
            try {
                $this->servico()->lancar($id, $dados);
                $this->fail('deveria recusar');
            } catch (ExtratoInvalidoException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame([], $this->mundo->despesas);
    }

    public function testCriaFornecedorNovoNaMesmaTransacao(): void
    {
        $id = $this->movimento(1, '2026-10-10', -10000);

        $this->servico()->lancar($id, ['novo_fornecedor' => ['nome' => 'Papelaria Exemplo'], 'categoria_id' => 3]);

        $this->assertCount(1, $this->mundo->fornecedores);
        $fornecedorId = array_key_first($this->mundo->fornecedores);
        $this->assertSame($fornecedorId, reset($this->mundo->despesas)['fornecedor']);
    }

    public function testFalhaNoPagamentoDesfazTudoInclusiveODespesaEOFornecedorNovo(): void
    {
        $id = $this->movimento(1, '2026-10-10', -10000);
        $this->mundo->falharNoPagamento = true;

        try {
            $this->servico()->lancar($id, ['novo_fornecedor' => ['nome' => 'Papelaria Exemplo'], 'categoria_id' => 3]);
            $this->fail('deveria falhar');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame([], $this->mundo->despesas);
        $this->assertSame([], $this->mundo->fornecedores);
        $this->assertSame([], $this->mundo->pagDespesas);
        $this->assertSame('pendente', $this->mundo->movimentos[$id]['status']);
        $this->assertSame(1, $this->mundo->rollbacks);
    }

    public function testLancarDuasVezesCriaUmSoPagamento(): void
    {
        $id = $this->movimento(1, '2026-10-10', -10000);
        $this->servico()->lancar($id, $this->dadosDespesa());

        $this->expectException(ConciliacaoConflitoException::class);
        try {
            $this->servico()->lancar($id, $this->dadosDespesa());
        } finally {
            $this->assertCount(1, $this->mundo->pagDespesas);
            $this->assertCount(1, $this->mundo->despesas);
        }
    }

    public function testRequisicaoConcorrenteQueChegaDepoisDeLerOMovimentoNaoCriaSegundoPagamento(): void
    {
        $id = $this->movimento(1, '2026-10-10', -10000);
        // Outra requisição lança entre a leitura inicial e o travamento da linha.
        $this->mundo->aoTravar = function () use ($id): void {
            $this->mundo->movimentos[$id]['status'] = 'lancado';
        };

        try {
            $this->servico()->lancar($id, $this->dadosDespesa());
            $this->fail('deveria recusar');
        } catch (ConciliacaoConflitoException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame([], $this->mundo->pagDespesas);
        $this->assertSame([], $this->mundo->despesas);
    }

    public function testGravacaoCondicionalDoVinculoBarraCorridaEDesfazOLancamento(): void
    {
        $id = $this->movimento(1, '2026-10-10', -10000);
        $this->mundo->recusarMarcacao = true;

        try {
            $this->servico()->lancar($id, $this->dadosDespesa());
            $this->fail('deveria recusar');
        } catch (ConciliacaoConflitoException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame([], $this->mundo->pagDespesas);
        $this->assertSame([], $this->mundo->despesas);
    }

    public function testMovimentoQueNaoEstaPendenteOuNaoExiste(): void
    {
        $id = $this->movimento(1, '2026-10-10', -10000, ['status' => 'ignorado']);

        try {
            $this->servico()->lancar($id, $this->dadosDespesa());
            $this->fail();
        } catch (ConciliacaoConflitoException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(ExtratoNaoEncontradoException::class);
        $this->servico()->lancar(999, $this->dadosDespesa());
    }

    public function testMesFechadoBloqueiaSemEfeitoColateralComAMensagemPadrao(): void
    {
        $id = $this->movimento(1, '2026-09-10', -10000);
        $this->mundo->fechados = ['2026-09'];

        try {
            $this->servico()->lancar($id, ['novo_fornecedor' => ['nome' => 'Papelaria Exemplo'], 'categoria_id' => 3]);
            $this->fail('deveria bloquear');
        } catch (ExtratoInvalidoException $e) {
            $this->assertSame('O período 09/2026 já está fechado financeiramente. Não é possível realizar lançamentos.', $e->getMessage());
        }

        $this->assertSame([], $this->mundo->despesas);
        $this->assertSame([], $this->mundo->fornecedores);
        $this->assertSame([], $this->mundo->pagDespesas);
        $this->assertSame('pendente', $this->mundo->movimentos[$id]['status']);
        $this->assertSame(0, $this->mundo->commits);
    }

    // ---------------------------------------------------------------- receita

    public function testEntradaCriaReceitaERecebimento(): void
    {
        $id = $this->movimento(2, '2026-10-12', 50000);

        $r = $this->servico()->lancar($id, ['cliente_id' => 4, 'categorias' => [['categoria_id' => 8, 'valor' => 300], ['categoria_id' => 9, 'valor' => 200]]]);

        $this->assertSame('receita', $r['pagamento_tipo']);
        $rec = reset($this->mundo->receitas);
        $pag = reset($this->mundo->pagReceitas);
        $this->assertSame(4, $rec['cliente_id']);
        $this->assertEqualsWithDelta(500.0, $rec['valor'], 0.001);
        $this->assertSame('2026-10-12', $pag['pagamento_receita_dt']);
        $this->assertSame(2, $pag['conta_id']);
        $this->assertSame('lancado', $this->mundo->movimentos[$id]['status']);
    }

    public function testReceitaSemClienteNemCategoriaEhPermitida(): void
    {
        $id = $this->movimento(2, '2026-10-12', 50000);

        $r = $this->servico()->lancar($id, []);

        $this->assertSame('receita', $r['pagamento_tipo']);
        $this->assertNull(reset($this->mundo->receitas)['cliente_id']);
    }

    // ---------------------------------------------------------------- quitar

    public function testQuitaDespesaEmAbertoSemCriarOutra(): void
    {
        $id = $this->movimento(1, '2026-10-10', -20000);
        $this->mundo->emAberto['despesa'][30] = ['id' => 30, 'data' => '2026-10-08', 'valor_centavos' => 20000, 'paga' => false];

        $r = $this->servico()->quitar($id, 'despesa', 30, [], 9);

        $this->assertSame([], $this->mundo->despesas, 'não cria outra despesa');
        $p = reset($this->mundo->pagDespesas);
        $this->assertSame(30, $p['despesa_id']);
        $this->assertSame('2026-10-10', $p['pagamento_despesa_dt']);
        $this->assertSame(1, $p['conta_id']);
        $this->assertSame([['id' => 5, 'valor' => 200.0]], $p['rateio'], 'usa o rateio da própria despesa');
        $this->assertSame(['lancado', 'despesa', $r['pagamento_id']], [
            $this->mundo->movimentos[$id]['status'], $this->mundo->movimentos[$id]['pagamento_tipo'], $this->mundo->movimentos[$id]['pagamento_id'],
        ]);
    }

    public function testQuitarRecusaValorDiferenteSentidoErradoEJaPaga(): void
    {
        $saida   = $this->movimento(1, '2026-10-10', -20000);
        $entrada = $this->movimento(1, '2026-10-10', 20000);
        $this->mundo->emAberto['despesa'][30] = ['id' => 30, 'data' => '2026-10-08', 'valor_centavos' => 20001, 'paga' => false];
        $this->mundo->emAberto['despesa'][31] = ['id' => 31, 'data' => '2026-10-08', 'valor_centavos' => 20000, 'paga' => true];

        $casos = [
            [$saida, 'despesa', 30, ExtratoInvalidoException::class],
            [$saida, 'despesa', 31, ConciliacaoConflitoException::class],
            [$saida, 'despesa', 99, ExtratoNaoEncontradoException::class],
            [$saida, 'receita', 30, ExtratoInvalidoException::class],
            [$entrada, 'despesa', 30, ExtratoInvalidoException::class],
        ];
        foreach ($casos as [$mov, $tipo, $item, $classe]) {
            try {
                $this->servico()->quitar($mov, $tipo, $item);
                $this->fail("deveria lançar {$classe}");
            } catch (\Throwable $e) {
                $this->assertInstanceOf($classe, $e);
            }
        }
        $this->assertSame([], $this->mundo->pagDespesas);
    }

    public function testQuitarEmMesFechadoBloqueia(): void
    {
        $id = $this->movimento(1, '2026-09-10', -20000);
        $this->mundo->emAberto['despesa'][30] = ['id' => 30, 'data' => '2026-09-08', 'valor_centavos' => 20000, 'paga' => false];
        $this->mundo->fechados = ['2026-09'];

        $this->expectException(ExtratoInvalidoException::class);
        try {
            $this->servico()->quitar($id, 'despesa', 30);
        } finally {
            $this->assertSame([], $this->mundo->pagDespesas);
        }
    }

    // ---------------------------------------------------------------- transferência

    public function testParDeMovimentosViraUmaTransferenciaVinculadaAosDoisLados(): void
    {
        $saida   = $this->movimento(1, '2026-10-10', -80000);
        $entrada = $this->movimento(2, '2026-10-11', 80000);

        $r = $this->servico()->lancarTransferencia($entrada, $saida, ['rateio' => [['id' => 1, 'percentual' => 50], ['id' => 2, 'percentual' => 50]]], 9);

        $this->assertSame('transferencia', $r['pagamento_tipo']);
        $this->assertCount(1, $this->mundo->transferencias);
        $t = reset($this->mundo->transferencias);
        $this->assertSame([1, 2], [$t['id_conta_origem'], $t['id_conta_destino']], 'origem é a conta da saída');
        $this->assertEqualsWithDelta(800.0, $t['valor'], 0.001);
        $this->assertSame('2026-10-10', $t['data_transferencia']);
        $this->assertEqualsWithDelta(400.0, $t['rateio'][0]['valor'], 0.001);
        foreach ([$saida, $entrada] as $m) {
            $this->assertSame(['lancado', 'transferencia', $r['pagamento_id']], [
                $this->mundo->movimentos[$m]['status'], $this->mundo->movimentos[$m]['pagamento_tipo'], $this->mundo->movimentos[$m]['pagamento_id'],
            ]);
        }
    }

    public function testParInvalidoEhRecusado(): void
    {
        $a = $this->movimento(1, '2026-10-10', -80000);
        $mesmaConta = $this->movimento(1, '2026-10-10', 80000);
        $valorDiferente = $this->movimento(2, '2026-10-10', 79999);
        $doisSaidas = $this->movimento(2, '2026-10-10', -80000);

        foreach ([[$a, $mesmaConta], [$a, $valorDiferente], [$a, $doisSaidas], [$a, $a]] as [$x, $y]) {
            try {
                $this->servico()->lancarTransferencia($x, $y);
                $this->fail('deveria recusar');
            } catch (ExtratoInvalidoException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame([], $this->mundo->transferencias);
    }

    public function testTransferenciaComUmLadoJaLancadoOuMesFechadoNaoGravaNada(): void
    {
        $saida   = $this->movimento(1, '2026-10-10', -80000);
        $entrada = $this->movimento(2, '2026-10-10', 80000);

        $this->mundo->fechados = ['2026-10'];
        try {
            $this->servico()->lancarTransferencia($saida, $entrada);
            $this->fail();
        } catch (ExtratoInvalidoException) {
            $this->addToAssertionCount(1);
        }
        $this->mundo->fechados = [];

        $this->mundo->movimentos[$entrada]['status'] = 'conciliado';
        try {
            $this->servico()->lancarTransferencia($saida, $entrada);
            $this->fail();
        } catch (ConciliacaoConflitoException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame([], $this->mundo->transferencias);
        $this->assertSame('pendente', $this->mundo->movimentos[$saida]['status']);
    }

    public function testFalhaNoSegundoLadoDesfazATransferenciaEOPrimeiroVinculo(): void
    {
        $saida   = $this->movimento(1, '2026-10-10', -80000);
        $entrada = $this->movimento(2, '2026-10-10', 80000);
        $this->mundo->recusarMarcacaoDoMovimento = $entrada;

        try {
            $this->servico()->lancarTransferencia($saida, $entrada);
            $this->fail();
        } catch (ConciliacaoConflitoException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame([], $this->mundo->transferencias);
        $this->assertSame('pendente', $this->mundo->movimentos[$saida]['status']);
        $this->assertNull($this->mundo->movimentos[$saida]['pagamento_id']);
    }

    // ---------------------------------------------------------------- lote

    public function testLoteParcialLancaOsValidosEExplicaOsInvalidos(): void
    {
        $ok1     = $this->movimento(1, '2026-10-10', -10000);
        $ok2     = $this->movimento(2, '2026-10-11', 25000);
        $semForn = $this->movimento(1, '2026-10-12', -5000);
        $fechado = $this->movimento(1, '2026-09-12', -5000);
        $feito   = $this->movimento(1, '2026-10-13', -5000, ['status' => 'lancado']);
        $this->mundo->fechados = ['2026-09'];

        $r = $this->servico()->lancarLote([
            ['movimento_id' => $ok1] + $this->dadosDespesa(),
            ['movimento_id' => $ok2, 'cliente_id' => 4],
            ['movimento_id' => $semForn, 'categoria_id' => 3],
            ['movimento_id' => $fechado] + $this->dadosDespesa(),
            ['movimento_id' => $feito] + $this->dadosDespesa(),
            ['movimento_id' => 999] + $this->dadosDespesa(),
        ], 9);

        $this->assertSame(['total' => 6, 'lancados' => 2, 'rejeitados' => 4], array_intersect_key($r, array_flip(['total', 'lancados', 'rejeitados'])));
        $porId = array_column($r['itens'], null, 'movimento_id');
        $this->assertTrue($porId[$ok1]['ok']);
        $this->assertSame('despesa', $porId[$ok1]['pagamento_tipo']);
        $this->assertTrue($porId[$ok2]['ok']);
        $this->assertFalse($porId[$semForn]['ok']);
        $this->assertStringContainsString('fornecedor', $porId[$semForn]['motivo']);
        $this->assertStringContainsString('fechado', $porId[$fechado]['motivo']);
        $this->assertStringContainsString('não está pendente', $porId[$feito]['motivo']);
        $this->assertStringContainsString('não encontrado', $porId[999]['motivo']);
        $this->assertCount(1, $this->mundo->pagDespesas);
        $this->assertCount(1, $this->mundo->pagReceitas);
    }

    public function testLoteMisturaLancamentoQuitacaoETransferencia(): void
    {
        $a = $this->movimento(1, '2026-10-10', -10000);
        $b = $this->movimento(1, '2026-10-10', -20000);
        $c = $this->movimento(1, '2026-10-11', -30000);
        $d = $this->movimento(2, '2026-10-11', 30000);
        $this->mundo->emAberto['despesa'][30] = ['id' => 30, 'data' => '2026-10-08', 'valor_centavos' => 20000, 'paga' => false];

        $r = $this->servico()->lancarLote([
            ['movimento_id' => $a] + $this->dadosDespesa(),
            ['movimento_id' => $b, 'quitar' => ['tipo' => 'despesa', 'id' => 30]],
            ['movimento_id' => $c, 'movimento_par_id' => $d],
        ]);

        $this->assertSame(3, $r['lancados']);
        $this->assertCount(2, $this->mundo->pagDespesas);
        $this->assertCount(1, $this->mundo->transferencias);
    }

    public function testLoteVazioOuAcimaDoLimite(): void
    {
        foreach ([[], array_fill(0, 51, ['movimento_id' => 1])] as $itens) {
            try {
                $this->servico()->lancarLote($itens);
                $this->fail();
            } catch (ExtratoInvalidoException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(50, LancamentoExtratoService::LIMITE_LOTE);
    }

    public function testLimiteDoLoteVemDasConfiguracoes(): void
    {
        $config = new \App\Services\Financeiro\ExtratoConfig(new class () extends \App\Services\ConfiguracoesService {
            public function __construct()
            {
            }

            public function getValor(string $nome, $default = null)
            {
                return $nome === 'EXTRATOS_LIMITE_LOTE' ? '2' : $default;
            }
        });

        try {
            $this->servico($config)->lancarLote(array_fill(0, 3, ['movimento_id' => 1]));
            $this->fail('Era esperada ExtratoInvalidoException.');
        } catch (ExtratoInvalidoException $e) {
            $this->assertStringContainsString('Máximo de 2 itens', $e->getMessage());
        }
    }

    public function testMesmoMovimentoDuasVezesNoLoteLancaUmaVez(): void
    {
        $id = $this->movimento(1, '2026-10-10', -10000);

        $r = $this->servico()->lancarLote([['movimento_id' => $id] + $this->dadosDespesa(), ['movimento_id' => $id] + $this->dadosDespesa()]);

        $this->assertSame([1, 1], [$r['lancados'], $r['rejeitados']]);
        $this->assertCount(1, $this->mundo->pagDespesas);
    }

    // ---------------------------------------------------------------- regra e sugestão

    public function testCriarRegraAPartirDoLancamento(): void
    {
        $id = $this->movimento(1, '2026-10-10', -10000);

        $r = $this->servico()->lancar($id, $this->dadosDespesa(['criar_regra' => true, 'rateio' => [['id' => 1, 'percentual' => 100]]]), 9);

        $regra = reset($this->mundo->regras);
        $this->assertSame('Fulano de Tal Teste', $regra['padrao']);
        $this->assertSame(['contraparte', 'saida', 1, 7, 3], [$regra['campo'], $regra['sinal'], $regra['conta_id'], $regra['fornecedor_id'], $regra['categoria_id']]);
        $this->assertSame(7, $r['regra']['fornecedor_id']);
        $this->assertEquals([['id' => 1, 'percentual' => 100]], $r['regra']['rateio']);
    }

    public function testRegraComPadraoCurtoNaoDesfazOLancamento(): void
    {
        $id = $this->movimento(1, '2026-10-10', -10000);

        $r = $this->servico()->lancar($id, $this->dadosDespesa(['criar_regra' => ['padrao' => 'ab']]));

        $this->assertArrayHasKey('regra_erro', $r);
        $this->assertSame('lancado', $this->mundo->movimentos[$id]['status']);
    }

    public function testSugestaoTrazARegraEAsContrapartes(): void
    {
        $id = $this->movimento(1, '2026-10-10', -10000);
        $this->mundo->regras[1] = [
            'id_regra' => 1, 'nome' => 'Fulano', 'conta_id' => null, 'campo' => 'contraparte', 'padrao' => 'fulano de tal', 'sinal' => 'saida',
            'fornecedor_id' => 7, 'cliente_id' => null, 'categoria_id' => 3, 'rateio' => '[{"id":1,"percentual":100}]', 'ativo' => 1,
        ];

        $s = $this->servico()->sugestao($id);

        $this->assertSame('despesa', $s['tipo']);
        $this->assertTrue($s['pode_lancar']);
        $this->assertSame(7, $s['regra']['fornecedor_id']);
        $this->assertSame(3, $s['regra']['categoria_id']);
        $this->assertSame('Fulano de Tal Teste', $s['descricao_sugerida']);
    }
}

// ============================================================== fakes

class MundoLancamento
{
    public array $movimentos = [];
    public array $despesas = [];
    public array $receitas = [];
    public array $pagDespesas = [];
    public array $pagReceitas = [];
    public array $transferencias = [];
    public array $fornecedores = [];
    public array $regras = [];
    public array $emAberto = ['despesa' => [], 'receita' => []];
    public array $fechados = [];
    public bool $falharNoPagamento = false;
    public bool $recusarMarcacao = false;
    public ?int $recusarMarcacaoDoMovimento = null;
    public ?\Closure $aoTravar = null;
    public int $commits = 0;
    public int $rollbacks = 0;
    private int $seq = 100;

    public function adicionarMovimento(array $dados): int
    {
        $id = count($this->movimentos) + 1;
        $this->movimentos[$id] = $dados + ['id_movimento' => $id, 'pagamento_tipo' => null, 'pagamento_id' => null, 'conciliado_por' => null, 'conciliado_em' => null];
        $this->movimentos[$id]['id_movimento'] = $id;

        return $id;
    }

    public function proximoId(): int
    {
        return ++$this->seq;
    }

    public function instantaneo(): array
    {
        return [$this->movimentos, $this->despesas, $this->receitas, $this->pagDespesas, $this->pagReceitas, $this->transferencias, $this->fornecedores, $this->regras];
    }

    public function restaurar(array $i): void
    {
        [$this->movimentos, $this->despesas, $this->receitas, $this->pagDespesas, $this->pagReceitas, $this->transferencias, $this->fornecedores, $this->regras] = $i;
    }
}

class FakeDbLancamento
{
    private array $copia = [];

    public function __construct(private MundoLancamento $m)
    {
    }

    public function transBegin(): bool
    {
        $this->copia = $this->m->instantaneo();

        return true;
    }

    public function transCommit(): bool
    {
        $this->m->commits++;

        return true;
    }

    public function transRollback(): bool
    {
        $this->m->rollbacks++;
        $this->m->restaurar($this->copia);

        return true;
    }
}

class FakeMovimentosLancamento extends ExtratosMovimentosRepository
{
    public function __construct(private MundoLancamento $m)
    {
    }

    public function obter(int $id): ?array
    {
        return $this->m->movimentos[$id] ?? null;
    }

    public function obterTravando(int $id): ?array
    {
        if ($this->m->aoTravar) {
            ($this->m->aoTravar)();
            $this->m->aoTravar = null;
        }

        return $this->m->movimentos[$id] ?? null;
    }

    public function marcarLancado(int $id, array $dados): bool
    {
        if ($this->m->recusarMarcacao || $this->m->recusarMarcacaoDoMovimento === $id || ($this->m->movimentos[$id]['status'] ?? '') !== 'pendente') {
            return false;
        }
        $this->m->movimentos[$id] = $dados + $this->m->movimentos[$id];

        return true;
    }
}

class FakeCandidatosLancamento extends ConciliacaoCandidatosRepository
{
    public function __construct(private MundoLancamento $m)
    {
    }

    public function obterEmAberto(string $tipo, int $id): ?array
    {
        return $this->m->emAberto[$tipo][$id] ?? null;
    }
}

class FakeSaldoLancamento extends SaldoMensalService
{
    public function __construct(private MundoLancamento $m)
    {
    }

    public function verificarBloqueioMensal(string $tipo, ?int $contaId, string $data): void
    {
        if (in_array(substr($data, 0, 7), $this->m->fechados, true)) {
            $mes = substr($data, 5, 2) . '/' . substr($data, 0, 4);
            throw new \Exception("O período {$mes} já está fechado financeiramente. Não é possível realizar lançamentos.");
        }
    }
}

class FakeDespesasLancamento extends DespesasService
{
    public function __construct(private MundoLancamento $m)
    {
    }

    public function create(array $data): int
    {
        $id = $this->m->proximoId();
        $this->m->despesas[$id] = $data;

        return $id;
    }
}

class FakeReceitasLancamento extends ReceitasService
{
    public function __construct(private MundoLancamento $m)
    {
    }

    public function create(array $data): int
    {
        $id = $this->m->proximoId();
        $this->m->receitas[$id] = $data;

        return $id;
    }
}

class FakePagDespesasLancamento extends PagamentoDespesasService
{
    public function __construct(private MundoLancamento $m)
    {
    }

    public function create(array $data): int
    {
        if ($this->m->falharNoPagamento) {
            throw new \RuntimeException('falha simulada');
        }
        $id = $this->m->proximoId();
        $this->m->pagDespesas[$id] = $data;

        return $id;
    }

    public function prepararPagamento(int $despesaId): ?array
    {
        return ['despesa_id' => $despesaId, 'valor' => 200.0, 'rateio' => [['id' => 5, 'valor' => 200.0]]];
    }
}

class FakePagReceitasLancamento extends PagamentoReceitasService
{
    public function __construct(private MundoLancamento $m)
    {
    }

    public function create(array $data): int
    {
        $id = $this->m->proximoId();
        $this->m->pagReceitas[$id] = $data;

        return $id;
    }

    public function prepararPagamento(int $receitaId): ?array
    {
        return ['receita_id' => $receitaId, 'valor' => 0, 'rateio' => []];
    }
}

class FakeTransferenciasLancamento extends TransferenciasService
{
    public function __construct(private MundoLancamento $m)
    {
    }

    public function criar(array $data): int
    {
        $id = $this->m->proximoId();
        $this->m->transferencias[$id] = $data;

        return $id;
    }
}

class FakeFornecedorServiceLancamento extends FornecedorService
{
    public function __construct(private MundoLancamento $m)
    {
    }

    public function salvarFornecedor(array $data, ?int $id = null): int
    {
        $novo = $this->m->proximoId();
        $this->m->fornecedores[$novo] = $data;

        return $novo;
    }
}

class FakeRegrasRepo extends \App\Repositories\Financeiro\ExtratosRegrasRepository
{
    public function __construct(private MundoLancamento $m)
    {
    }

    public function obter(int $id): ?array
    {
        return $this->m->regras[$id] ?? null;
    }

    public function listarAtivasDaConta(int $contaId): array
    {
        return array_values(array_filter(
            $this->m->regras,
            static fn (array $r): bool => (int) $r['ativo'] === 1 && ($r['conta_id'] === null || $r['conta_id'] === $contaId)
        ));
    }

    public function listar(?int $contaId = null, bool $apenasAtivas = false): array
    {
        return array_values($this->m->regras);
    }

    public function criar(array $dados): int
    {
        if (mb_strlen($dados['padrao'] ?? '') < 3) {
            throw new \InvalidArgumentException('O campo padrao precisa ter ao menos 3 caracteres.');
        }
        $id = $this->m->proximoId();
        $this->m->regras[$id] = $dados + ['id_regra' => $id, 'rateio' => null, 'fornecedor_id' => null, 'cliente_id' => null, 'categoria_id' => null, 'conta_id' => null, 'ativo' => 1];
        $this->m->regras[$id]['id_regra'] = $id;

        return $id;
    }

    public function atualizar(int $id, array $dados): bool
    {
        $this->m->regras[$id] = $dados + $this->m->regras[$id];

        return true;
    }

    public function excluir(int $id): bool
    {
        unset($this->m->regras[$id]);

        return true;
    }
}
