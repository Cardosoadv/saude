<?php

namespace Tests\Unit\Services\Financeiro;

use App\Repositories\Financeiro\ConciliacaoCandidatosRepository;
use App\Repositories\Financeiro\ExtratosMovimentosRepository;
use App\Services\Financeiro\ConciliacaoExtratoService;
use App\Services\Financeiro\Extrato\ConciliacaoConflitoException;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use App\Services\Financeiro\Extrato\ExtratoNaoEncontradoException;
use App\Services\Financeiro\SaldoMensalService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Conciliação de extratos com repositórios em memória. Todos os dados são fictícios.
 */
class ConciliacaoExtratoServiceTest extends CIUnitTestCase
{
    private FakeMovimentosConciliacao $movimentos;
    private FakeCandidatos $candidatos;
    private FakeSaldoConciliacao $saldo;
    private FakeDbConciliacao $banco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->movimentos = new FakeMovimentosConciliacao();
        $this->candidatos = new FakeCandidatos($this->movimentos);
        $this->saldo      = new FakeSaldoConciliacao();
        $this->banco         = new FakeDbConciliacao($this->movimentos);
    }

    private function servico(?\App\Services\Financeiro\ExtratoConfig $config = null): ConciliacaoExtratoService
    {
        return new ConciliacaoExtratoService($this->movimentos, $this->candidatos, $this->saldo, $this->banco, $config);
    }

    public function testLimiteDoLoteVemDasConfiguracoes(): void
    {
        $config = new \App\Services\Financeiro\ExtratoConfig(new class () extends \App\Services\ConfiguracoesService {
            public function __construct()
            {
            }

            public function getValor(string $nome, $default = null)
            {
                return $nome === 'EXTRATOS_LIMITE_LOTE_CONCILIACAO' ? '2' : $default;
            }
        });

        try {
            $this->servico($config)->conciliarLote(array_fill(0, 3, ['movimento_id' => 1, 'pagamento_tipo' => 'despesa', 'pagamento_id' => 1]), true);
            $this->fail('Era esperada ExtratoInvalidoException.');
        } catch (\App\Services\Financeiro\Extrato\ExtratoInvalidoException $e) {
            $this->assertStringContainsString('Máximo de 2 itens', $e->getMessage());
        }
    }

    private function movimento(int $conta, string $data, int $centavos, string $memo = 'Pagamento teste', array $extra = []): int
    {
        return $this->movimentos->adicionar($extra + [
            'conta_id'         => $conta,
            'data'             => $data,
            'valor_centavos'   => $centavos,
            'memo'             => $memo,
            'nome_contraparte' => null,
            'status'           => 'pendente',
        ]);
    }

    private function pagtoDespesa(int $id, int $conta, string $data, int $centavos, string $descricao = 'Despesa teste', ?string $contraparte = null): void
    {
        $this->candidatos->pagamentos['despesa'][$id] = [
            'id' => $id, 'conta_id' => $conta, 'data' => $data, 'valor_centavos' => $centavos,
            'descricao' => $descricao, 'contraparte' => $contraparte, 'comentario' => null,
        ];
    }

    public function testUmCandidatoExatoTemConfiancaAltaENoLote(): void
    {
        $m = $this->movimento(1, '2026-10-10', -15000);
        $this->pagtoDespesa(11, 1, '2026-10-10', 15000);

        $s = $this->servico()->sugestoes($m);

        $this->assertSame('alta', $s['confianca']);
        $this->assertCount(1, $s['pagamentos']);
        $this->assertSame(['despesa', 11], [$s['pagamentos'][0]['tipo'], $s['pagamentos'][0]['id']]);

        $lote = $this->servico()->sugestoesLote(1);
        $this->assertCount(1, $lote);
        $this->assertSame(11, $lote[0]['pagamento_id']);
    }

    public function testDoisCandidatosSaoAmbiguosENaoEntramNoLote(): void
    {
        $m = $this->movimento(1, '2026-10-10', -15000);
        $this->pagtoDespesa(11, 1, '2026-10-10', 15000);
        $this->pagtoDespesa(12, 1, '2026-10-11', 15000);

        $s = $this->servico()->sugestoes($m);

        $this->assertSame('media', $s['confianca']);
        $this->assertCount(2, $s['pagamentos']);
        $this->assertSame(11, $s['pagamentos'][0]['id'], 'o de data exata vem primeiro');
        $this->assertSame([], $this->servico()->sugestoesLote(1));
    }

    public function testNenhumCandidato(): void
    {
        $m = $this->movimento(1, '2026-10-10', -15000);
        $this->pagtoDespesa(11, 1, '2026-10-10', 14999);   // valor diferente
        $this->pagtoDespesa(12, 1, '2026-10-20', 15000);   // fora da janela
        $this->pagtoDespesa(13, 2, '2026-10-10', 15000);   // outra conta

        $s = $this->servico()->sugestoes($m);

        $this->assertNull($s['confianca']);
        $this->assertSame([], $s['pagamentos']);
        $this->assertSame([], $s['em_aberto']);
    }

    public function testCandidatoJaVinculadoNaoEhSugerido(): void
    {
        $this->pagtoDespesa(11, 1, '2026-10-10', 15000);
        $this->movimento(1, '2026-10-10', -15000, 'Outro', ['status' => 'conciliado', 'pagamento_tipo' => 'despesa', 'pagamento_id' => 11]);
        $m = $this->movimento(1, '2026-10-10', -15000);

        $s = $this->servico()->sugestoes($m);

        $this->assertSame([], $s['pagamentos']);
    }

    public function testJanelaConfiguravel(): void
    {
        $m = $this->movimento(1, '2026-10-10', -15000);
        $this->pagtoDespesa(11, 1, '2026-10-17', 15000);

        $this->assertSame([], $this->servico()->sugestoes($m)['pagamentos']);
        $this->assertCount(1, $this->servico()->sugestoes($m, 7)['pagamentos']);
    }

    public function testNomeQueBateAumentaAPontuacao(): void
    {
        $m = $this->movimento(1, '2026-10-10', -15000, 'PIX ENVIADO Papelaria Modelo');
        $this->pagtoDespesa(11, 1, '2026-10-10', 15000, 'Material de clínica', 'Papelaria Modelo Ltda');
        $this->pagtoDespesa(12, 1, '2026-10-10', 15000, 'Outra despesa', 'Fornecedor Qualquer');

        $s = $this->servico()->sugestoes($m);

        $this->assertSame(11, $s['pagamentos'][0]['id']);
        $this->assertTrue($s['pagamentos'][0]['nome_confere']);
        $this->assertFalse($s['pagamentos'][1]['nome_confere']);
        $this->assertGreaterThan($s['pagamentos'][1]['pontos'], $s['pagamentos'][0]['pontos']);
    }

    public function testEntradaUsaPagamentosDeReceita(): void
    {
        $m = $this->movimento(1, '2026-10-10', 20000);
        $this->candidatos->pagamentos['receita'][21] = ['id' => 21, 'conta_id' => 1, 'data' => '2026-10-09', 'valor_centavos' => 20000, 'descricao' => 'Honorários', 'contraparte' => null, 'comentario' => null];
        $this->pagtoDespesa(11, 1, '2026-10-10', 20000);

        $s = $this->servico()->sugestoes($m);

        $this->assertSame([['receita', 21]], array_map(fn ($p) => [$p['tipo'], $p['id']], $s['pagamentos']));
        $this->assertSame('media', $s['confianca'], 'data com 1 dia de diferença não é confiança alta');
    }

    public function testSugereDespesaEmAbertoParaQuitar(): void
    {
        $m = $this->movimento(1, '2026-10-10', -9000);
        $this->candidatos->abertas['despesa'][31] = ['id' => 31, 'data' => '2026-10-12', 'valor_centavos' => 9000, 'descricao' => 'Aluguel', 'contraparte' => null, 'comentario' => null];

        $s = $this->servico()->sugestoes($m);

        $this->assertSame([], $s['pagamentos']);
        $this->assertSame('despesa_em_aberto', $s['em_aberto'][0]['tipo']);
        $this->assertSame('media', $s['confianca']);
    }

    public function testDetectaParDeTransferencia(): void
    {
        $a = $this->movimento(1, '2026-10-10', -50000);
        $b = $this->movimento(2, '2026-10-11', 50000);
        $this->movimento(2, '2026-10-11', 40000); // valor diferente

        $s = $this->servico()->sugestoes($a);

        $this->assertCount(1, $s['pares_transferencia']);
        $this->assertSame($b, $s['pares_transferencia'][0]['id_movimento']);
    }

    public function testTransferenciaExistenteEhSugeridaComOPar(): void
    {
        $a = $this->movimento(1, '2026-10-10', -50000);
        $b = $this->movimento(2, '2026-10-10', 50000);
        $this->candidatos->transferencias[41] = ['id' => 41, 'data' => '2026-10-10', 'valor_centavos' => 50000, 'conta_origem' => 1, 'conta_destino' => 2, 'descricao' => 'Reserva', 'comentario' => null];

        $s = $this->servico()->sugestoes($a);

        $this->assertSame('transferencia', $s['pagamentos'][0]['tipo']);
        $this->assertSame($b, $s['pagamentos'][0]['movimento_par_id']);
        $this->assertSame('media', $s['confianca'], 'transferência nunca é lote');
    }

    public function testConciliarVinculaEMarcaConciliado(): void
    {
        $m = $this->movimento(1, '2026-10-10', -15000);
        $this->pagtoDespesa(11, 1, '2026-10-10', 15000);

        $r = $this->servico()->conciliar($m, 'despesa', 11, 7);

        $this->assertSame('conciliado', $r['status']);
        $this->assertSame('despesa', $r['pagamento_tipo']);
        $this->assertSame(11, $r['pagamento_id']);
        $this->assertSame(7, $r['conciliado_por']);
        $this->assertSame(1, $this->banco->commits);
    }

    public function testVincularOMesmoPagamentoADoisMovimentosEhRejeitado(): void
    {
        $m1 = $this->movimento(1, '2026-10-10', -15000);
        $m2 = $this->movimento(1, '2026-10-10', -15000);
        $this->pagtoDespesa(11, 1, '2026-10-10', 15000);

        $this->servico()->conciliar($m1, 'despesa', 11);

        $this->expectException(ConciliacaoConflitoException::class);
        $this->servico()->conciliar($m2, 'despesa', 11);
    }

    public function testIndiceUnicoDoBancoBarraACorridaEDesfazATransacao(): void
    {
        $m = $this->movimento(1, '2026-10-10', -15000);
        $this->pagtoDespesa(11, 1, '2026-10-10', 15000);
        $this->movimentos->rejeitarGravacao = true; // o banco recusa: outra requisição vinculou antes

        try {
            $this->servico()->conciliar($m, 'despesa', 11);
            $this->fail('deveria rejeitar');
        } catch (ConciliacaoConflitoException) {
            $this->assertSame(1, $this->banco->rollbacks);
            $this->assertSame('pendente', $this->movimentos->linhas[$m]['status']);
        }
    }

    /** @dataProvider vinculosInvalidos */
    public function testVinculosInvalidosSaoRejeitados(string $tipo, int $conta, int $centavos): void
    {
        $m = $this->movimento(1, '2026-10-10', -15000);
        $this->candidatos->pagamentos['despesa'][11] = ['id' => 11, 'conta_id' => $conta, 'data' => '2026-10-10', 'valor_centavos' => $centavos, 'descricao' => null, 'contraparte' => null, 'comentario' => null];
        $this->candidatos->pagamentos['receita'][11] = $this->candidatos->pagamentos['despesa'][11];

        $this->expectException(ExtratoInvalidoException::class);
        $this->servico()->conciliar($m, $tipo, 11);
    }

    public static function vinculosInvalidos(): array
    {
        return [
            'valor diferente'          => ['despesa', 1, 14999],
            'outra conta'              => ['despesa', 2, 15000],
            'saída com receita'        => ['receita', 1, 15000],
            'tipo desconhecido'        => ['outro', 1, 15000],
        ];
    }

    public function testPagamentoInexistente(): void
    {
        $m = $this->movimento(1, '2026-10-10', -15000);

        $this->expectException(ExtratoNaoEncontradoException::class);
        $this->servico()->conciliar($m, 'despesa', 999);
    }

    public function testMesFechadoBloqueiaVincularEDesvincular(): void
    {
        $m = $this->movimento(1, '2026-10-10', -15000);
        $this->pagtoDespesa(11, 1, '2026-10-10', 15000);
        $this->saldo->fechados = ['2026-10'];

        try {
            $this->servico()->conciliar($m, 'despesa', 11);
            $this->fail('deveria bloquear');
        } catch (ExtratoInvalidoException $e) {
            $this->assertStringContainsString('Mês fechado', $e->getMessage());
        }

        $this->saldo->fechados = [];
        $this->servico()->conciliar($m, 'despesa', 11);
        $this->saldo->fechados = ['2026-10'];

        $this->expectException(ExtratoInvalidoException::class);
        $this->servico()->desconciliar($m);
    }

    public function testDesconciliarVoltaParaPendenteSemApagarOPagamento(): void
    {
        $m = $this->movimento(1, '2026-10-10', -15000);
        $this->pagtoDespesa(11, 1, '2026-10-10', 15000);
        $this->servico()->conciliar($m, 'despesa', 11, 7);

        $r = $this->servico()->desconciliar($m, 7);

        $this->assertSame('pendente', $r['status']);
        $this->assertNull($r['pagamento_tipo']);
        $this->assertNull($r['pagamento_id']);
        $this->assertNull($r['conciliado_por']);
        $this->assertArrayHasKey(11, $this->candidatos->pagamentos['despesa'], 'o pagamento continua existindo');

        // e pode ser vinculado de novo
        $this->assertSame('conciliado', $this->servico()->conciliar($m, 'despesa', 11)['status']);
    }

    public function testDesconciliarPendenteOuLancadoEhRejeitado(): void
    {
        $p = $this->movimento(1, '2026-10-10', -100);
        $l = $this->movimento(1, '2026-10-10', -200, 'x', ['status' => 'lancado']);

        foreach ([$p, $l] as $id) {
            try {
                $this->servico()->desconciliar($id);
                $this->fail('deveria rejeitar');
            } catch (ConciliacaoConflitoException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testIgnorarExigeMotivoEDesconciliarReabre(): void
    {
        $m = $this->movimento(1, '2026-10-10', -100);

        try {
            $this->servico()->ignorar($m, '   ');
            $this->fail('motivo vazio');
        } catch (ExtratoInvalidoException) {
            $this->addToAssertionCount(1);
        }

        $r = $this->servico()->ignorar($m, 'Estorno já refletido', 7);
        $this->assertSame('ignorado', $r['status']);
        $this->assertSame('Estorno já refletido', $r['motivo_ignorado']);

        $r = $this->servico()->desconciliar($m);
        $this->assertSame('pendente', $r['status']);
        $this->assertNull($r['motivo_ignorado']);
    }

    public function testConciliarTransferenciaVinculaOsDoisLados(): void
    {
        $a = $this->movimento(1, '2026-10-10', -50000);
        $b = $this->movimento(2, '2026-10-10', 50000);
        $this->candidatos->transferencias[41] = ['id' => 41, 'data' => '2026-10-10', 'valor_centavos' => 50000, 'conta_origem' => 1, 'conta_destino' => 2, 'descricao' => 'Reserva', 'comentario' => null];

        $this->servico()->conciliar($a, 'transferencia', 41, 7, $b);

        $this->assertSame('conciliado', $this->movimentos->linhas[$a]['status']);
        $this->assertSame('conciliado', $this->movimentos->linhas[$b]['status']);
        $this->assertSame(41, $this->movimentos->linhas[$b]['pagamento_id']);
    }

    public function testTransferenciaComSentidoOuContaErradosEhRejeitada(): void
    {
        $entrada = $this->movimento(1, '2026-10-10', 50000); // a transferência saiu da conta 1
        $this->candidatos->transferencias[41] = ['id' => 41, 'data' => '2026-10-10', 'valor_centavos' => 50000, 'conta_origem' => 1, 'conta_destino' => 2, 'descricao' => null, 'comentario' => null];

        $this->expectException(ExtratoInvalidoException::class);
        $this->servico()->conciliar($entrada, 'transferencia', 41);
    }

    public function testLoteExigeConfirmacao(): void
    {
        $this->expectException(ExtratoInvalidoException::class);
        $this->servico()->conciliarLote([['movimento_id' => 1, 'pagamento_tipo' => 'despesa', 'pagamento_id' => 11]], false);
    }

    public function testLoteConciliaSoOQueAindaEhConfiancaAlta(): void
    {
        $ok  = $this->movimento(1, '2026-10-10', -15000);
        $amb = $this->movimento(1, '2026-10-12', -7000);
        $this->pagtoDespesa(11, 1, '2026-10-10', 15000);
        $this->pagtoDespesa(21, 1, '2026-10-12', 7000);
        $this->pagtoDespesa(22, 1, '2026-10-13', 7000);

        $r = $this->servico()->conciliarLote([
            ['movimento_id' => $ok, 'pagamento_tipo' => 'despesa', 'pagamento_id' => 11],
            ['movimento_id' => $amb, 'pagamento_tipo' => 'despesa', 'pagamento_id' => 21],
        ], true, 7);

        $this->assertSame([$ok], $r['conciliados']);
        $this->assertSame($amb, $r['rejeitados'][0]['movimento_id']);
        $this->assertSame('pendente', $this->movimentos->linhas[$amb]['status']);
    }

    public function testLoteNaoUsaCandidatoDisputadoPorDoisMovimentos(): void
    {
        $this->movimento(1, '2026-10-10', -1000, 'Tarifa');
        $this->movimento(1, '2026-10-10', -1000, 'Tarifa');
        $this->pagtoDespesa(11, 1, '2026-10-10', 1000);

        $this->assertSame([], $this->servico()->sugestoesLote(1));
    }
}

class FakeMovimentosConciliacao extends ExtratosMovimentosRepository
{
    /** @var array<int,array> */
    public array $linhas = [];
    public bool $rejeitarGravacao = false;

    public function __construct()
    {
    }

    public function adicionar(array $dados): int
    {
        $id = count($this->linhas) + 1;
        $this->linhas[$id] = $dados + ['id_movimento' => $id, 'pagamento_tipo' => null, 'pagamento_id' => null, 'motivo_ignorado' => null, 'conciliado_por' => null, 'conciliado_em' => null];
        $this->linhas[$id]['id_movimento'] = $id;

        return $id;
    }

    public function obter(int $id): ?array
    {
        return $this->linhas[$id] ?? null;
    }

    public function listarPorConta(int $contaId, ?string $dataInicio = null, ?string $dataFim = null, ?string $status = null): array
    {
        return array_values(array_filter(
            $this->linhas,
            static fn (array $l): bool => $l['conta_id'] === $contaId && ($status === null || $l['status'] === $status)
        ));
    }

    public function buscarPorPagamento(int $contaId, string $tipo, int $pagamentoId): ?array
    {
        foreach ($this->linhas as $l) {
            if ($l['conta_id'] === $contaId && $l['pagamento_tipo'] === $tipo && $l['pagamento_id'] === $pagamentoId) {
                return $l;
            }
        }

        return null;
    }

    public function buscarPorPagamentoQualquerConta(string $tipo, int $pagamentoId): ?array
    {
        foreach ($this->linhas as $l) {
            if ($l['pagamento_tipo'] === $tipo && $l['pagamento_id'] === $pagamentoId) {
                return $l;
            }
        }

        return null;
    }

    public function atualizarVinculo(int $id, array $dados): bool
    {
        if ($this->rejeitarGravacao) {
            return false;
        }
        $this->linhas[$id] = $dados + $this->linhas[$id];

        return true;
    }
}

class FakeCandidatos extends ConciliacaoCandidatosRepository
{
    /** @var array{despesa:array,receita:array} */
    public array $pagamentos = ['despesa' => [], 'receita' => []];
    public array $abertas = ['despesa' => [], 'receita' => []];
    public array $transferencias = [];

    public function __construct(private FakeMovimentosConciliacao $movimentos)
    {
    }

    private function filtrar(array $itens, ?int $conta, int $centavos, string $de, string $ate, string $tipo = null): array
    {
        $r = [];
        foreach ($itens as $i) {
            if (($conta === null || $i['conta_id'] === $conta) && $i['valor_centavos'] === $centavos && $i['data'] >= $de && $i['data'] <= $ate) {
                if ($tipo !== null && $this->movimentos->buscarPorPagamentoQualquerConta($tipo, $i['id'])) {
                    continue;
                }
                $r[] = $i;
            }
        }

        return $r;
    }

    public function pagamentosDespesaLivres(int $contaId, int $centavos, string $de, string $ate): array
    {
        return $this->filtrar($this->pagamentos['despesa'], $contaId, $centavos, $de, $ate, 'despesa');
    }

    public function pagamentosReceitaLivres(int $contaId, int $centavos, string $de, string $ate): array
    {
        return $this->filtrar($this->pagamentos['receita'], $contaId, $centavos, $de, $ate, 'receita');
    }

    public function despesasEmAberto(int $centavos, string $de, string $ate): array
    {
        return $this->filtrar($this->abertas['despesa'], null, $centavos, $de, $ate);
    }

    public function receitasEmAberto(int $centavos, string $de, string $ate): array
    {
        return $this->filtrar($this->abertas['receita'], null, $centavos, $de, $ate);
    }

    public function transferenciasLivres(int $contaOrigem, int $contaDestino, int $centavos, string $de, string $ate, int $ladoContaId): array
    {
        $r = [];
        foreach ($this->transferencias as $t) {
            if ($t['conta_origem'] === $contaOrigem && $t['conta_destino'] === $contaDestino && $t['valor_centavos'] === $centavos
                && $t['data'] >= $de && $t['data'] <= $ate && !$this->movimentos->buscarPorPagamento($ladoContaId, 'transferencia', $t['id'])) {
                $r[] = $t;
            }
        }

        return $r;
    }

    public function movimentosParaTransferencia(int $contaId, int $valorCentavosOposto, string $de, string $ate): array
    {
        return array_values(array_filter(
            $this->movimentos->linhas,
            static fn (array $l): bool => $l['status'] === 'pendente' && $l['conta_id'] !== $contaId && $l['valor_centavos'] === $valorCentavosOposto && $l['data'] >= $de && $l['data'] <= $ate
        ));
    }

    public function obterPagamento(string $tipo, int $id): ?array
    {
        return $this->pagamentos[$tipo][$id] ?? null;
    }

    public function obterTransferencia(int $id): ?array
    {
        return $this->transferencias[$id] ?? null;
    }
}

class FakeSaldoConciliacao extends SaldoMensalService
{
    /** @var string[] AAAA-MM */
    public array $fechados = [];

    public function __construct()
    {
    }

    public function verificarBloqueioMensal(string $tipo, ?int $contaId, string $data): void
    {
        if (in_array(substr($data, 0, 7), $this->fechados, true)) {
            throw new \Exception('fechado');
        }
    }
}

class FakeDbConciliacao
{
    public int $commits = 0;
    public int $rollbacks = 0;
    private array $copia = [];

    public function __construct(private FakeMovimentosConciliacao $movimentos)
    {
    }

    public function transBegin(): bool
    {
        $this->copia = $this->movimentos->linhas;

        return true;
    }

    public function transCommit(): bool
    {
        $this->commits++;

        return true;
    }

    public function transRollback(): bool
    {
        $this->rollbacks++;
        $this->movimentos->linhas = $this->copia;

        return true;
    }
}
