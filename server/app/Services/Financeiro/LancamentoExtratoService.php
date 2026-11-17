<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Repositories\ClienteRepository;
use App\Repositories\Financeiro\ConciliacaoCandidatosRepository;
use App\Repositories\Financeiro\ExtratosMovimentosRepository;
use App\Repositories\FornecedorRepository;
use App\Services\Financeiro\Extrato\ConciliacaoConflitoException;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use App\Services\Financeiro\Extrato\ExtratoNaoEncontradoException;
use App\Services\FornecedorService;
use Throwable;

/**
 * Lançamento no financeiro a partir de um movimento de extrato que não tem lançamento correspondente.
 *
 * Reaproveita os serviços existentes (DespesasService/ReceitasService, PagamentoDespesasService/
 * PagamentoReceitasService e TransferenciasService), que mantêm as validações, a auditoria, o rateio e o
 * bloqueio de mês fechado. Aqui ficam só a ligação com o movimento e as garantias de atomicidade:
 *
 * - tudo (fornecedor novo, despesa/receita, pagamento, vínculo) acontece numa única transação;
 * - a linha do movimento é travada e o vínculo só é gravado se ele ainda estiver `pendente`, então
 *   chamadas repetidas ou concorrentes criam um único pagamento;
 * - mês fechado recusa antes de qualquer gravação, com a mensagem do `verificarBloqueioMensal`.
 *
 * Os logs trazem só IDs, nunca memo nem nome da contraparte.
 */
class LancamentoExtratoService
{
    public const LIMITE_LOTE = 50;

    private const DESCRICAO_MAX = 150;

    private ExtratosMovimentosRepository $movimentos;
    private ConciliacaoCandidatosRepository $candidatos;
    private DespesasService $despesas;
    private ReceitasService $receitas;
    private PagamentoDespesasService $pagamentosDespesas;
    private PagamentoReceitasService $pagamentosReceitas;
    private TransferenciasService $transferencias;
    private ExtratoConfig $config;
    private SaldoMensalService $saldoMensal;
    private RegrasExtratoService $regras;
    private ?FornecedorService $fornecedores;
    private ?FornecedorRepository $fornecedoresRepo;
    private ?ClienteRepository $clientesRepo;
    /** Conexão com transBegin/transCommit/transRollback (padrão: a conexão do CodeIgniter). */
    private ?object $db;

    public function __construct(
        ?ExtratosMovimentosRepository $movimentos = null,
        ?ConciliacaoCandidatosRepository $candidatos = null,
        ?DespesasService $despesas = null,
        ?ReceitasService $receitas = null,
        ?PagamentoDespesasService $pagamentosDespesas = null,
        ?PagamentoReceitasService $pagamentosReceitas = null,
        ?TransferenciasService $transferencias = null,
        ?SaldoMensalService $saldoMensal = null,
        ?RegrasExtratoService $regras = null,
        ?object $db = null,
        ?FornecedorService $fornecedores = null,
        ?FornecedorRepository $fornecedoresRepo = null,
        ?ClienteRepository $clientesRepo = null,
        ?ExtratoConfig $config = null
    ) {
        $this->config             = $config ?? new ExtratoConfig();
        $this->movimentos         = $movimentos ?? new ExtratosMovimentosRepository();
        $this->candidatos         = $candidatos ?? new ConciliacaoCandidatosRepository();
        $this->despesas           = $despesas ?? new DespesasService();
        $this->receitas           = $receitas ?? new ReceitasService();
        $this->pagamentosDespesas = $pagamentosDespesas ?? new PagamentoDespesasService();
        $this->pagamentosReceitas = $pagamentosReceitas ?? new PagamentoReceitasService();
        $this->transferencias     = $transferencias ?? new TransferenciasService();
        $this->saldoMensal        = $saldoMensal ?? new SaldoMensalService();
        $this->regras             = $regras ?? new RegrasExtratoService();
        $this->db                 = $db;
        $this->fornecedores       = $fornecedores;
        $this->fornecedoresRepo   = $fornecedoresRepo;
        $this->clientesRepo       = $clientesRepo;
    }

    // ------------------------------------------------------------------
    // Sugestão
    // ------------------------------------------------------------------

    /**
     * O que o formulário de lançamento deve pré-preencher para um movimento: o tipo (despesa para saída,
     * receita para entrada), a regra de classificação que casou (se houver) e fornecedores/clientes cujo nome
     * lembra o da contraparte. Só sugestão: nada é gravado.
     *
     * @return array<string,mixed>
     * @throws ExtratoNaoEncontradoException
     */
    public function sugestao(int $movimentoId): array
    {
        $mov   = $this->obterMovimento($movimentoId);
        $saida = (int) $mov['valor_centavos'] < 0;

        return [
            'movimento'          => $this->resumoMovimento($mov),
            'tipo'               => $saida ? 'despesa' : 'receita',
            'pode_lancar'        => $mov['status'] === 'pendente',
            'descricao_sugerida' => $this->descricaoPadrao($mov),
            'regra'              => $this->regras->sugerir($mov),
            'contrapartes'       => $this->contrapartesPorNome($mov, $saida),
        ];
    }

    // ------------------------------------------------------------------
    // Lançamento (despesa/receita + pagamento)
    // ------------------------------------------------------------------

    /**
     * Cria a despesa e o pagamento (saída) ou a receita e o recebimento (entrada) e vincula o movimento.
     *
     * Dados aceitos:
     * - despesa: `fornecedor_id` ou `novo_fornecedor` {nome, documento?};
     * - receita: `cliente_id` (opcional);
     * - `categorias` [{categoria_id, valor?}] ou `categoria_id` (uma só, com o valor todo). Obrigatória na despesa;
     * - `rateio` [{id, percentual}] entre os advogados, somando 100% (opcional);
     * - `descricao`, `comentario` (opcionais);
     * - `criar_regra` (bool ou {nome, padrao, campo}): grava uma regra de classificação a partir do lançamento.
     *
     * @param array<string,mixed> $dados
     * @return array<string,mixed> movimento, pagamento_tipo, pagamento_id e, se pedido, regra
     * @throws ExtratoNaoEncontradoException Movimento inexistente.
     * @throws ExtratoInvalidoException      Dados inválidos ou mês fechado.
     * @throws ConciliacaoConflitoException  Movimento não está pendente (inclusive se outra requisição lançou antes).
     */
    public function lancar(int $movimentoId, array $dados, ?int $userId = null): array
    {
        $mov = $this->obterMovimento($movimentoId);
        $this->exigirPendente($mov);

        $plano = $this->planejar($mov, $dados);
        $this->bloqueioMensal((int) $mov['conta_id'], (string) $mov['data']);

        $r = $this->transacao(function () use ($movimentoId, $plano, $userId): array {
            $mov = $this->travar($movimentoId);
            [$tipo, $pagamentoId, $itemId] = $this->criarLancamento($mov, $plano);
            $this->gravarVinculo($movimentoId, $tipo, $pagamentoId, $userId);

            return ['pagamento_tipo' => $tipo, 'pagamento_id' => $pagamentoId, 'lancamento_id' => $itemId];
        });

        log_message('info', sprintf(
            '[LancamentoExtrato] movimento=%d lançado como %s=%d (lançamento=%d) por usuario=%s',
            $movimentoId,
            $r['pagamento_tipo'],
            $r['pagamento_id'],
            $r['lancamento_id'],
            $userId ?? '-'
        ));

        $resposta = ['movimento' => $this->movimentos->obter($movimentoId) ?? $mov] + $r;
        $resposta += $this->criarRegraSePedido($mov, $plano, $dados['criar_regra'] ?? null, $userId);

        return $resposta;
    }

    /**
     * Quita uma despesa (ou receita) em aberto com o movimento, em vez de criar outra: registra o pagamento
     * dela na data e na conta do movimento. O valor da despesa precisa ser exatamente o do movimento.
     *
     * @param string              $tipo   `despesa` (saída) ou `receita` (entrada)
     * @param array<string,mixed> $dados  `rateio` [{id, percentual}] (opcional; sem ele vale o rateio da própria despesa/receita), `comentario`
     * @return array<string,mixed>
     * @throws ExtratoNaoEncontradoException
     * @throws ExtratoInvalidoException
     * @throws ConciliacaoConflitoException
     */
    public function quitar(int $movimentoId, string $tipo, int $itemId, array $dados = [], ?int $userId = null): array
    {
        $mov = $this->obterMovimento($movimentoId);
        $this->exigirPendente($mov);

        $saida = (int) $mov['valor_centavos'] < 0;
        if (!in_array($tipo, ['despesa', 'receita'], true) || ($tipo === 'despesa') !== $saida) {
            throw new ExtratoInvalidoException($saida ? 'Saída só quita despesa em aberto.' : 'Entrada só quita receita em aberto.');
        }

        $item = $this->candidatos->obterEmAberto($tipo, $itemId);
        if (!$item) {
            throw new ExtratoNaoEncontradoException($tipo === 'despesa' ? 'Despesa não encontrada.' : 'Receita não encontrada.');
        }
        if ($item['paga']) {
            throw new ConciliacaoConflitoException($tipo === 'despesa' ? 'Esta despesa já tem pagamento.' : 'Esta receita já tem recebimento.');
        }
        $centavos = abs((int) $mov['valor_centavos']);
        if ((int) $item['valor_centavos'] !== $centavos) {
            throw new ExtratoInvalidoException('O valor do lançamento em aberto é diferente do valor do movimento.');
        }

        $rateioPercentual = RegrasExtratoService::validarRateioPercentual($dados['rateio'] ?? null);
        $this->bloqueioMensal((int) $mov['conta_id'], (string) $mov['data']);

        $r = $this->transacao(function () use ($movimentoId, $tipo, $itemId, $centavos, $rateioPercentual, $dados, $userId): array {
            $mov = $this->travar($movimentoId);

            $servico = $tipo === 'despesa' ? $this->pagamentosDespesas : $this->pagamentosReceitas;
            if ($rateioPercentual) {
                $rateio = $this->ratear($centavos, $rateioPercentual);
            } else {
                // Rateio que a própria despesa/receita já traz (em R$), como no pagamento feito na tela.
                $preparo = $servico->prepararPagamento($itemId);
                $rateio  = array_values(array_filter(
                    (array) ($preparo['rateio'] ?? []),
                    static fn ($i): bool => is_array($i) && !empty($i['id'])
                ));
            }

            $comentario = $this->comentario($dados, (int) $mov['id_movimento']);
            $pagamento  = [
                'conta_id' => (int) $mov['conta_id'],
                'valor'    => $centavos / 100,
                'comentario' => $comentario,
                'rateio'   => $rateio,
            ];
            if ($tipo === 'despesa') {
                $pagamentoId = $this->pagamentosDespesas->create($pagamento + ['despesa_id' => $itemId, 'pagamento_despesa_dt' => $mov['data']]);
            } else {
                $pagamentoId = $this->pagamentosReceitas->create($pagamento + ['receita_id' => $itemId, 'pagamento_receita_dt' => $mov['data']]);
            }
            $this->gravarVinculo($movimentoId, $tipo, (int) $pagamentoId, $userId);

            return ['pagamento_tipo' => $tipo, 'pagamento_id' => (int) $pagamentoId, 'lancamento_id' => $itemId];
        });

        log_message('info', sprintf(
            '[LancamentoExtrato] movimento=%d quitou %s=%d com pagamento=%d por usuario=%s',
            $movimentoId,
            $tipo,
            $itemId,
            $r['pagamento_id'],
            $userId ?? '-'
        ));

        return ['movimento' => $this->movimentos->obter($movimentoId) ?? $mov] + $r;
    }

    // ------------------------------------------------------------------
    // Transferência entre contas internas
    // ------------------------------------------------------------------

    /**
     * Cria uma transferência a partir de um par de movimentos pendentes de contas diferentes, com valores
     * opostos: a conta do movimento de saída é a origem e a do de entrada é o destino. Os dois movimentos
     * ficam vinculados à mesma transferência.
     *
     * Dados: `data` (opcional; padrão: a data da saída), `descricao`, `comentario`, `rateio` [{id, percentual}].
     *
     * @param array<string,mixed> $dados
     * @return array<string,mixed> movimento, movimento_par, pagamento_tipo (transferencia), pagamento_id
     * @throws ExtratoNaoEncontradoException
     * @throws ExtratoInvalidoException
     * @throws ConciliacaoConflitoException
     */
    public function lancarTransferencia(int $movimentoId, int $movimentoParId, array $dados = [], ?int $userId = null): array
    {
        if ($movimentoId === $movimentoParId) {
            throw new ExtratoInvalidoException('Informe dois movimentos diferentes.');
        }
        $a = $this->obterMovimento($movimentoId);
        $b = $this->obterMovimento($movimentoParId);
        $this->exigirPendente($a);
        $this->exigirPendente($b);

        $this->validarPar($a, $b);
        [$saida, $entrada] = (int) $a['valor_centavos'] < 0 ? [$a, $b] : [$b, $a];
        $centavos          = abs((int) $saida['valor_centavos']);

        $data = (string) ($dados['data'] ?? $saida['data']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !checkdate((int) substr($data, 5, 2), (int) substr($data, 8, 2), (int) substr($data, 0, 4))) {
            throw new ExtratoInvalidoException('Data da transferência inválida (use AAAA-MM-DD).');
        }

        $rateioPercentual = RegrasExtratoService::validarRateioPercentual($dados['rateio'] ?? null);

        // Mês fechado em qualquer ponta recusa antes de gravar.
        foreach ([[$saida, $saida['data']], [$entrada, $entrada['data']], [$saida, $data], [$entrada, $data]] as [$m, $d]) {
            $this->bloqueioMensal((int) $m['conta_id'], (string) $d);
        }

        $r = $this->transacao(function () use ($saida, $entrada, $centavos, $data, $dados, $rateioPercentual, $userId): array {
            $saidaTrav   = $this->travar((int) $saida['id_movimento']);
            $entradaTrav = $this->travar((int) $entrada['id_movimento']);

            $transferenciaId = $this->transferencias->criar([
                'transferencia'      => mb_substr(trim((string) ($dados['descricao'] ?? '')) ?: 'Transferência entre contas (extrato)', 0, self::DESCRICAO_MAX),
                'data_transferencia' => $data,
                'id_conta_origem'    => (int) $saidaTrav['conta_id'],
                'id_conta_destino'   => (int) $entradaTrav['conta_id'],
                'valor'              => $centavos / 100,
                'comentarios'        => $this->comentario($dados, (int) $saidaTrav['id_movimento']),
                'rateio'             => $rateioPercentual ? $this->ratear($centavos, $rateioPercentual) : [],
            ]);

            $this->gravarVinculo((int) $saidaTrav['id_movimento'], 'transferencia', (int) $transferenciaId, $userId);
            $this->gravarVinculo((int) $entradaTrav['id_movimento'], 'transferencia', (int) $transferenciaId, $userId);

            return ['pagamento_tipo' => 'transferencia', 'pagamento_id' => (int) $transferenciaId, 'lancamento_id' => (int) $transferenciaId];
        });

        log_message('info', sprintf(
            '[LancamentoExtrato] movimentos=%d,%d lançados como transferencia=%d por usuario=%s',
            $saida['id_movimento'],
            $entrada['id_movimento'],
            $r['pagamento_id'],
            $userId ?? '-'
        ));

        return [
            'movimento'     => $this->movimentos->obter($movimentoId) ?? $a,
            'movimento_par' => $this->movimentos->obter($movimentoParId) ?? $b,
        ] + $r;
    }

    // ------------------------------------------------------------------
    // Lote
    // ------------------------------------------------------------------

    /**
     * Lança vários movimentos. Cada item é tratado à parte (uma transação por item): os válidos são lançados e
     * os inválidos voltam com o motivo.
     *
     * Cada item: `movimento_id` e os dados do lançamento. Por padrão cria despesa/receita; com `quitar`
     * {tipo, id} quita um lançamento em aberto; com `movimento_par_id` cria uma transferência.
     *
     * @param list<array<string,mixed>> $itens
     * @return array{total:int,lancados:int,rejeitados:int,itens:list<array<string,mixed>>}
     * @throws ExtratoInvalidoException Lista vazia ou acima do limite.
     */
    public function lancarLote(array $itens, ?int $userId = null): array
    {
        if (!$itens) {
            throw new ExtratoInvalidoException('Nenhum item informado.');
        }
        $limite = $this->config->limiteLote();
        if (count($itens) > $limite) {
            throw new ExtratoInvalidoException('Máximo de ' . $limite . ' itens por lote.');
        }

        $relatorio = [];
        $lancados  = 0;

        foreach ($itens as $item) {
            $item = is_array($item) ? $item : [];
            $id   = (int) ($item['movimento_id'] ?? 0);

            try {
                if (isset($item['movimento_par_id']) && (int) $item['movimento_par_id'] > 0) {
                    $r = $this->lancarTransferencia($id, (int) $item['movimento_par_id'], $item, $userId);
                } elseif (isset($item['quitar']) && is_array($item['quitar'])) {
                    $r = $this->quitar($id, (string) ($item['quitar']['tipo'] ?? ''), (int) ($item['quitar']['id'] ?? 0), $item, $userId);
                } else {
                    $r = $this->lancar($id, $item, $userId);
                }
                $lancados++;
                $relatorio[] = [
                    'movimento_id'   => $id,
                    'ok'             => true,
                    'pagamento_tipo' => $r['pagamento_tipo'],
                    'pagamento_id'   => $r['pagamento_id'],
                    'motivo'         => null,
                ];
            } catch (ExtratoInvalidoException | ExtratoNaoEncontradoException | ConciliacaoConflitoException $e) {
                $relatorio[] = $this->rejeitado($id, $e->getMessage());
            } catch (Throwable $e) {
                log_message('error', sprintf('[LancamentoExtrato] lote: movimento=%d falhou: %s', $id, $e->getMessage()));
                $relatorio[] = $this->rejeitado($id, 'Não foi possível lançar este movimento.');
            }
        }

        return ['total' => count($relatorio), 'lancados' => $lancados, 'rejeitados' => count($relatorio) - $lancados, 'itens' => $relatorio];
    }

    // ------------------------------------------------------------------
    // Planejamento (validação sem gravar)
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $mov
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    private function planejar(array $mov, array $dados): array
    {
        $centavos = abs((int) $mov['valor_centavos']);
        if ($centavos <= 0) {
            throw new ExtratoInvalidoException('Movimento com valor zero não pode ser lançado.');
        }
        $saida = (int) $mov['valor_centavos'] < 0;

        $plano = [
            'tipo'       => $saida ? 'despesa' : 'receita',
            'centavos'   => $centavos,
            'descricao'  => $this->descricao($dados, $mov),
            'comentario' => $this->comentario($dados, (int) $mov['id_movimento']),
        ];

        $plano['rateio_percentual'] = RegrasExtratoService::validarRateioPercentual($dados['rateio'] ?? null);
        $plano['rateio']            = $plano['rateio_percentual'] ? $this->ratear($centavos, $plano['rateio_percentual']) : [];
        $plano['categorias']        = $this->categorias($dados, $centavos, $saida);

        if ($saida) {
            $fornecedor = (int) ($dados['fornecedor_id'] ?? 0);
            $novo       = $dados['novo_fornecedor'] ?? null;
            if ($fornecedor <= 0 && !(is_array($novo) && trim((string) ($novo['nome'] ?? '')) !== '')) {
                throw new ExtratoInvalidoException('Informe o fornecedor (fornecedor_id) ou os dados de um novo (novo_fornecedor.nome).');
            }
            $plano['fornecedor_id']   = $fornecedor > 0 ? $fornecedor : null;
            $plano['novo_fornecedor'] = $fornecedor > 0 ? null : [
                'nome'      => mb_substr(trim((string) $novo['nome']), 0, 255),
                'documento' => trim((string) ($novo['documento'] ?? '')),
            ];
        } else {
            $cliente             = (int) ($dados['cliente_id'] ?? 0);
            $plano['cliente_id'] = $cliente > 0 ? $cliente : null;
        }

        return $plano;
    }

    /**
     * @param array<string,mixed> $dados
     * @return list<array{categoria_id:int,valor:float}>
     */
    private function categorias(array $dados, int $centavos, bool $obrigatoria): array
    {
        $total = $centavos / 100;
        $lista = $dados['categorias'] ?? null;

        if (!is_array($lista) || !$lista) {
            $lista = (int) ($dados['categoria_id'] ?? 0) > 0 ? [['categoria_id' => (int) $dados['categoria_id']]] : [];
        }
        if (!$lista) {
            if ($obrigatoria) {
                throw new ExtratoInvalidoException('Selecione ao menos uma categoria.');
            }

            return [];
        }

        $saida = [];
        foreach ($lista as $item) {
            $item = is_array($item) ? $item : [];
            // Uma categoria só sem valor leva o valor todo.
            if (!isset($item['valor']) && count($lista) === 1) {
                $item['valor'] = $total;
            }
            $saida[] = ['categoria_id' => (int) ($item['categoria_id'] ?? 0), 'valor' => $item['valor'] ?? null];
        }

        // A validação detalhada (valores, soma, categoria repetida) é a dos serviços de despesa/receita; aqui
        // só se barra o que eles tratariam como erro interno.
        foreach ($saida as $s) {
            if ($s['categoria_id'] <= 0 || !is_numeric($s['valor']) || (float) $s['valor'] <= 0) {
                throw new ExtratoInvalidoException('Cada categoria precisa de categoria_id e de valor maior que zero.');
            }
        }
        $soma = array_sum(array_map(static fn (array $s): float => (float) $s['valor'], $saida));
        if (abs($soma - $total) > 0.01) {
            throw new ExtratoInvalidoException(sprintf(
                'A soma das categorias (R$ %s) precisa ser igual ao valor do movimento (R$ %s).',
                number_format($soma, 2, ',', '.'),
                number_format($total, 2, ',', '.')
            ));
        }

        return array_map(static fn (array $s): array => ['categoria_id' => $s['categoria_id'], 'valor' => round((float) $s['valor'], 2)], $saida);
    }

    /**
     * Converte percentuais em valores em R$ (centavos exatos; o último item leva a diferença do arredondamento).
     *
     * @param list<array{id:int,percentual:float}> $percentuais
     * @return list<array{id:int,valor:float}>
     */
    private function ratear(int $centavos, array $percentuais): array
    {
        $saida     = [];
        $acumulado = 0;
        $n         = count($percentuais);

        foreach ($percentuais as $i => $p) {
            $parte = $i === $n - 1 ? $centavos - $acumulado : (int) round($centavos * $p['percentual'] / 100);
            if ($parte <= 0) {
                throw new ExtratoInvalidoException('O rateio deixou um dos usuários sem valor; ajuste os percentuais.');
            }
            $acumulado += $parte;
            $saida[]    = ['id' => $p['id'], 'valor' => $parte / 100];
        }

        return $saida;
    }

    /**
     * Rateio no formato da própria despesa/receita (percentual em `valor`, como na tela de lançamento);
     * o pagamento leva o mesmo rateio já convertido em R$ por ratear().
     *
     * @param list<array{id:int,percentual:float}> $percentuais
     * @return list<array{id:int,valor:float}>
     */
    private function rateioDoLancamento(array $percentuais): array
    {
        return array_map(static fn (array $p): array => ['id' => $p['id'], 'valor' => $p['percentual']], $percentuais);
    }

    // ------------------------------------------------------------------
    // Criação dentro da transação
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $mov   movimento já travado
     * @param array<string,mixed> $plano
     * @return array{0:string,1:int,2:int} tipo do pagamento, id do pagamento, id da despesa/receita
     */
    private function criarLancamento(array $mov, array $plano): array
    {
        $valor = $plano['centavos'] / 100;

        try {
            if ($plano['tipo'] === 'despesa') {
                $fornecedorId = $plano['fornecedor_id'] ?? $this->criarFornecedor($plano['novo_fornecedor']);
                $despesaId    = $this->despesas->create([
                    'despesa'       => $plano['descricao'],
                    'vencimento_dt' => $mov['data'],
                    'valor'         => $valor,
                    'fornecedor'    => $fornecedorId,
                    'comentario'    => $plano['comentario'],
                    'categorias'    => $plano['categorias'],
                    'rateio'        => $this->rateioDoLancamento($plano['rateio_percentual']),
                ]);
                $pagamentoId = $this->pagamentosDespesas->create([
                    'despesa_id'           => $despesaId,
                    'conta_id'             => (int) $mov['conta_id'],
                    'valor'                => $valor,
                    'pagamento_despesa_dt' => $mov['data'],
                    'comentario'           => $plano['comentario'],
                    'rateio'               => $plano['rateio'],
                ]);

                return ['despesa', (int) $pagamentoId, (int) $despesaId];
            }

            $receitaId = $this->receitas->create([
                'receita'       => $plano['descricao'],
                'vencimento_dt' => $mov['data'],
                'valor'         => $valor,
                'cliente_id'    => $plano['cliente_id'],
                'comentario'    => $plano['comentario'],
                'tipo'          => 'receita',
                'categorias'    => $plano['categorias'],
                'rateio'        => $this->rateioDoLancamento($plano['rateio_percentual']),
            ]);
            $pagamentoId = $this->pagamentosReceitas->create([
                'receita_id'           => $receitaId,
                'conta_id'             => (int) $mov['conta_id'],
                'valor'                => $valor,
                'pagamento_receita_dt' => $mov['data'],
                'comentario'           => $plano['comentario'],
                'rateio'               => $plano['rateio'],
            ]);

            return ['receita', (int) $pagamentoId, (int) $receitaId];
        } catch (\InvalidArgumentException $e) {
            // Validações de categoria/rateio dos serviços financeiros: mensagem própria para o usuário.
            throw new ExtratoInvalidoException($e->getMessage(), 0, $e);
        }
    }

    /** @param array{nome:string,documento:string} $novo */
    private function criarFornecedor(array $novo): int
    {
        $digitos = preg_replace('/\D/', '', $novo['documento']) ?? '';
        try {
            return $this->fornecedores()->salvarFornecedor([
                'nome'        => $novo['nome'],
                'documento'   => $novo['documento'],
                'tipo_pessoa' => strlen($digitos) === 14 ? 'J' : 'F',
            ]);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'CPF ou CNPJ') || str_contains($e->getMessage(), 'documento')) {
                throw new ExtratoInvalidoException($e->getMessage(), 0, $e);
            }
            throw $e;
        }
    }

    /**
     * Grava o vínculo do movimento. Só passa se ele ainda estiver pendente.
     *
     * @throws ConciliacaoConflitoException
     */
    private function gravarVinculo(int $movimentoId, string $tipo, int $pagamentoId, ?int $userId): void
    {
        $ok = $this->movimentos->marcarLancado($movimentoId, [
            'status'          => 'lancado',
            'pagamento_tipo'  => $tipo,
            'pagamento_id'    => $pagamentoId,
            'motivo_ignorado' => null,
            'conciliado_por'  => $userId,
            'conciliado_em'   => date('Y-m-d H:i:s'),
        ]);

        if (!$ok) {
            throw new ConciliacaoConflitoException('O movimento já foi lançado ou mudou de situação; nenhum lançamento foi criado.');
        }
    }

    /**
     * Relê o movimento travando a linha e exige que ainda esteja pendente.
     *
     * @return array<string,mixed>
     */
    private function travar(int $movimentoId): array
    {
        $mov = $this->movimentos->obterTravando($movimentoId);
        if (!$mov) {
            throw new ExtratoNaoEncontradoException('Movimento do extrato não encontrado.');
        }
        $this->exigirPendente($mov);

        return $mov;
    }

    /**
     * @param array<string,mixed> $mov
     * @param array<string,mixed> $plano
     * @param mixed               $pedido
     * @return array<string,mixed> `regra` (a criada) ou `regra_erro`; vazio se não foi pedida
     */
    private function criarRegraSePedido(array $mov, array $plano, $pedido, ?int $userId): array
    {
        if (!$pedido) {
            return [];
        }
        $pedido = is_array($pedido) ? $pedido : [];

        $contraparte = trim((string) ($mov['nome_contraparte'] ?? ''));
        $padrao      = trim((string) ($pedido['padrao'] ?? ($contraparte !== '' ? $contraparte : ($mov['memo'] ?? ''))));
        $campo       = (string) ($pedido['campo'] ?? ($contraparte !== '' ? 'contraparte' : 'memo'));

        try {
            $regra = $this->regras->criar([
                'nome'          => $pedido['nome'] ?? mb_substr($padrao, 0, 100),
                'padrao'        => $padrao,
                'campo'         => $campo,
                'sinal'         => (int) $mov['valor_centavos'] < 0 ? 'saida' : 'entrada',
                'conta_id'      => (int) $mov['conta_id'],
                'fornecedor_id' => $plano['fornecedor_id'] ?? null,
                'cliente_id'    => $plano['cliente_id'] ?? null,
                'categoria_id'  => $plano['categorias'][0]['categoria_id'] ?? null,
                'rateio'        => $plano['rateio_percentual'],
            ], $userId);

            return ['regra' => $regra];
        } catch (ExtratoInvalidoException $e) {
            // O lançamento já foi feito; a regra é um extra e não desfaz nada.
            return ['regra_erro' => $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------
    // Apoio
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $a
     * @param array<string,mixed> $b
     */
    private function validarPar(array $a, array $b): void
    {
        if ((int) $a['conta_id'] === (int) $b['conta_id']) {
            throw new ExtratoInvalidoException('Os dois movimentos precisam ser de contas diferentes.');
        }
        if ((int) $a['valor_centavos'] === 0 || (int) $a['valor_centavos'] !== -(int) $b['valor_centavos']) {
            throw new ExtratoInvalidoException('Os movimentos precisam ter valores iguais e de sinais opostos (uma saída e uma entrada).');
        }
    }

    /** @throws ExtratoInvalidoException Mês fechado (mensagem do verificarBloqueioMensal). */
    private function bloqueioMensal(int $contaId, string $data): void
    {
        try {
            $this->saldoMensal->verificarBloqueioMensal('CONTA', $contaId, $data);
        } catch (\Exception $e) {
            throw new ExtratoInvalidoException($e->getMessage(), 0, $e);
        }
    }

    /** @param array<string,mixed> $mov */
    private function exigirPendente(array $mov): void
    {
        if ($mov['status'] !== 'pendente') {
            throw new ConciliacaoConflitoException('O movimento não está pendente (situação atual: ' . $mov['status'] . ').');
        }
    }

    /** @return array<string,mixed> */
    private function obterMovimento(int $id): array
    {
        $mov = $id > 0 ? $this->movimentos->obter($id) : null;
        if (!$mov) {
            throw new ExtratoNaoEncontradoException('Movimento do extrato não encontrado.');
        }

        return $mov;
    }

    /**
     * Executa numa transação: confirma se terminar bem e desfaz tudo se lançar qualquer exceção.
     *
     * @template T
     * @param callable():T $acao
     * @return T
     */
    private function transacao(callable $acao)
    {
        $db = $this->db ?? \Config\Database::connect();
        $db->transBegin();
        try {
            $r = $acao();
            $db->transCommit();

            return $r;
        } catch (Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    /** @param array<string,mixed> $mov */
    private function descricaoPadrao(array $mov): string
    {
        $texto = trim((string) ($mov['nome_contraparte'] ?? ''));
        if ($texto === '') {
            $texto = trim((string) ($mov['memo'] ?? ''));
        }

        return mb_substr($texto !== '' ? $texto : 'Lançamento do extrato', 0, self::DESCRICAO_MAX);
    }

    /**
     * @param array<string,mixed> $dados
     * @param array<string,mixed> $mov
     */
    private function descricao(array $dados, array $mov): string
    {
        $d = trim((string) ($dados['descricao'] ?? ''));

        return $d !== '' ? mb_substr($d, 0, self::DESCRICAO_MAX) : $this->descricaoPadrao($mov);
    }

    /** @param array<string,mixed> $dados */
    private function comentario(array $dados, int $movimentoId): string
    {
        $c = trim((string) ($dados['comentario'] ?? ''));

        return $c !== '' ? $c : "Lançado a partir do extrato (movimento #{$movimentoId}).";
    }

    /** @return array<string,mixed> */
    private function rejeitado(int $id, string $motivo): array
    {
        return ['movimento_id' => $id, 'ok' => false, 'pagamento_tipo' => null, 'pagamento_id' => null, 'motivo' => $motivo];
    }

    /**
     * Fornecedores (saída) ou clientes (entrada) cujo nome lembra o da contraparte.
     *
     * @param array<string,mixed> $mov
     * @return list<array{id:int,nome:string}>
     */
    private function contrapartesPorNome(array $mov, bool $saida): array
    {
        $nome = trim((string) ($mov['nome_contraparte'] ?? ''));
        if (mb_strlen($nome) < 2) {
            return [];
        }

        $busca = function (string $termo) use ($saida): array {
            return $saida
                ? ($this->fornecedoresRepo ??= new FornecedorRepository())->search($termo, 5)
                : ($this->clientesRepo ??= new ClienteRepository())->search($termo, 5);
        };

        try {
            $achados = $busca($nome);
            if (!$achados) {
                // Cai para a palavra mais longa do nome (ex.: o sobrenome ou a razão social).
                $palavras = array_filter(preg_split('/\s+/', $nome) ?: [], static fn (string $p): bool => mb_strlen($p) >= 4);
                usort($palavras, static fn (string $x, string $y): int => mb_strlen($y) <=> mb_strlen($x));
                $achados = $palavras ? $busca(reset($palavras)) : [];
            }
        } catch (Throwable $e) {
            log_message('error', '[LancamentoExtrato] falha ao sugerir contraparte: ' . $e->getMessage());

            return [];
        }

        return array_map(
            static fn (array $a): array => ['id' => (int) ($a['id_fornecedor'] ?? $a['id_cliente']), 'nome' => (string) $a['nome']],
            array_slice($achados, 0, 5)
        );
    }

    private function fornecedores(): FornecedorService
    {
        return $this->fornecedores ??= new FornecedorService();
    }

    /**
     * @param array<string,mixed> $mov
     * @return array<string,mixed>
     */
    private function resumoMovimento(array $mov): array
    {
        return [
            'id_movimento'     => (int) $mov['id_movimento'],
            'conta_id'         => (int) $mov['conta_id'],
            'data'             => $mov['data'],
            'valor_centavos'   => (int) $mov['valor_centavos'],
            'memo'             => $mov['memo'] ?? null,
            'nome_contraparte' => $mov['nome_contraparte'] ?? null,
            'status'           => $mov['status'],
        ];
    }
}
