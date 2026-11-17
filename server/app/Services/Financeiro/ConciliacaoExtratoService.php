<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Repositories\Financeiro\ConciliacaoCandidatosRepository;
use App\Repositories\Financeiro\ExtratosMovimentosRepository;
use App\Services\Financeiro\Extrato\ConciliacaoConflitoException;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use App\Services\Financeiro\Extrato\ExtratoNaoEncontradoException;
use DateTimeImmutable;
use Throwable;

/**
 * Conciliação de movimentos importados de extratos com o que já foi lançado no
 * financeiro. O objetivo é nunca criar um lançamento novo para uma operação que
 * já foi lançada manualmente (a outra face da idempotência da importação).
 *
 * Este serviço só sugere e vincula: não cria pagamentos nem transferências.
 * Um pagamento só pode estar vinculado a um movimento (índice único no banco).
 *
 * Os logs trazem apenas IDs, nunca memo ou nome da contraparte.
 */
class ConciliacaoExtratoService
{
    public const JANELA_PADRAO_DIAS = 3;
    public const JANELA_MAXIMA_DIAS = 30;
    public const LIMITE_LOTE        = 500;

    public const CONFIANCA_ALTA  = 'alta';
    public const CONFIANCA_MEDIA = 'media';

    private const PONTOS_VALOR      = 50;
    private const PONTOS_DATA_MAX   = 30;
    private const PONTOS_POR_DIA    = 10;
    private const PONTOS_NOME       = 20;

    /** Palavras comuns de extrato que não identificam ninguém. */
    private const PALAVRAS_IGNORADAS = [
        'pagamento', 'pagto', 'transferencia', 'transf', 'recebida', 'recebido', 'enviada', 'enviado', 'compra',
        'debito', 'credito', 'pix', 'ted', 'doc', 'ltda', 'eireli', 'tarifa', 'cartao', 'conta', 'banco', 'para', 'from',
    ];

    private ExtratosMovimentosRepository $movimentos;
    private ConciliacaoCandidatosRepository $candidatos;
    private ExtratoConfig $config;
    private SaldoMensalService $saldoMensal;
    /** Conexão com transBegin/transCommit/transRollback (padrão: a conexão do CodeIgniter). */
    private ?object $db;

    public function __construct(
        ?ExtratosMovimentosRepository $movimentos = null,
        ?ConciliacaoCandidatosRepository $candidatos = null,
        ?SaldoMensalService $saldoMensal = null,
        ?object $db = null,
        ?ExtratoConfig $config = null
    ) {
        $this->config      = $config ?? new ExtratoConfig();
        $this->movimentos  = $movimentos ?? new ExtratosMovimentosRepository();
        $this->candidatos  = $candidatos ?? new ConciliacaoCandidatosRepository();
        $this->saldoMensal = $saldoMensal ?? new SaldoMensalService();
        $this->db          = $db;
    }

    // ------------------------------------------------------------------
    // Sugestões
    // ------------------------------------------------------------------

    /**
     * Candidatos para um movimento.
     *
     * - `pagamentos`: pagamentos (ou transferências) já lançados, livres, que podem ser vinculados;
     * - `em_aberto`: despesas/receitas sem pagamento, para quitar em vez de criar outra;
     * - `pares_transferencia`: movimentos pendentes de outra conta que parecem o outro lado de uma transferência.
     *
     * @return array<string,mixed>
     * @throws ExtratoNaoEncontradoException
     */
    public function sugestoes(int $movimentoId, int $janelaDias = self::JANELA_PADRAO_DIAS): array
    {
        $mov    = $this->obterMovimento($movimentoId);
        $janela = $this->janela($janelaDias);

        if ($mov['status'] !== 'pendente') {
            return [
                'movimento'           => $this->resumoMovimento($mov),
                'janela_dias'         => $janela,
                'pagamentos'          => [],
                'em_aberto'           => [],
                'pares_transferencia' => [],
                'confianca'           => null,
            ];
        }

        $s = $this->calcular($mov, $janela);

        return [
            'movimento'           => $this->resumoMovimento($mov),
            'janela_dias'         => $janela,
            'pagamentos'          => $s['pagamentos'],
            'em_aberto'           => $s['em_aberto'],
            'pares_transferencia' => $s['pares'],
            'confianca'           => $s['confianca'],
        ];
    }

    /**
     * Sugestões de confiança alta para os pendentes de uma conta, que podem ser conciliadas em lote
     * depois que o usuário confirmar. Candidato disputado por dois movimentos não entra.
     *
     * @return list<array<string,mixed>> movimento, pagamento_tipo, pagamento_id, pagamento
     */
    public function sugestoesLote(int $contaId, int $janelaDias = self::JANELA_PADRAO_DIAS): array
    {
        $janela = $this->janela($janelaDias);
        $itens  = [];
        $usos   = [];

        foreach ($this->movimentos->listarPorConta($contaId, null, null, 'pendente') as $mov) {
            if (count($itens) >= $this->config->limiteLoteConciliacao()) {
                break;
            }
            $s = $this->calcular($mov, $janela);
            if ($s['confianca'] !== self::CONFIANCA_ALTA) {
                continue;
            }
            $c       = $s['pagamentos'][0];
            $chave   = $c['tipo'] . ':' . $c['id'];
            $usos[$chave] = ($usos[$chave] ?? 0) + 1;
            $itens[] = [
                'movimento'      => $this->resumoMovimento($mov),
                'pagamento_tipo' => $c['tipo'],
                'pagamento_id'   => $c['id'],
                'pagamento'      => $c,
                '_chave'         => $chave,
            ];
        }

        $itens = array_values(array_filter($itens, static fn (array $i): bool => $usos[$i['_chave']] === 1));
        foreach ($itens as &$i) {
            unset($i['_chave']);
        }

        return $itens;
    }

    // ------------------------------------------------------------------
    // Vínculo
    // ------------------------------------------------------------------

    /**
     * Vincula o movimento a um pagamento já lançado ou a uma transferência existente.
     *
     * Para transferência, `$movimentoParId` (opcional) vincula também o outro lado, na mesma operação.
     *
     * @return array<string,mixed> Movimento atualizado.
     * @throws ExtratoNaoEncontradoException Movimento, pagamento ou transferência inexistente.
     * @throws ExtratoInvalidoException      Valor, conta, sentido ou mês fechado não permitem o vínculo.
     * @throws ConciliacaoConflitoException  Movimento não está pendente ou o pagamento já está vinculado.
     */
    public function conciliar(int $movimentoId, string $pagamentoTipo, int $pagamentoId, ?int $userId = null, ?int $movimentoParId = null): array
    {
        $mov = $this->obterMovimento($movimentoId);
        $this->exigirPendente($mov);

        $par = null;
        if ($movimentoParId !== null) {
            if ($pagamentoTipo !== 'transferencia') {
                throw new ExtratoInvalidoException('O movimento par só vale para transferência.');
            }
            $par = $this->obterMovimento($movimentoParId);
            $this->exigirPendente($par);
        }

        $dataPagamento = $this->validarAlvo($mov, $pagamentoTipo, $pagamentoId, $par);

        $this->bloqueioMensal($mov['conta_id'], $mov['data']);
        $this->bloqueioMensal($mov['conta_id'], $dataPagamento);
        if ($par) {
            $this->bloqueioMensal($par['conta_id'], $par['data']);
            $this->bloqueioMensal($par['conta_id'], $dataPagamento);
        }

        $db = $this->db ?? \Config\Database::connect();
        $db->transBegin();
        try {
            $this->gravarVinculo($mov, $pagamentoTipo, $pagamentoId, $userId);
            if ($par) {
                $this->gravarVinculo($par, $pagamentoTipo, $pagamentoId, $userId);
            }
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        log_message('info', sprintf(
            '[Conciliacao] movimento=%d%s vinculado a %s=%d por usuario=%s',
            $movimentoId,
            $par ? ' e par=' . $movimentoParId : '',
            $pagamentoTipo,
            $pagamentoId,
            $userId ?? '-'
        ));

        return $this->movimentos->obter($movimentoId) ?? $mov;
    }

    /**
     * Desfaz o vínculo (ou o "ignorado"): o movimento volta a `pendente` e o pagamento não é apagado.
     *
     * @return array<string,mixed>
     * @throws ConciliacaoConflitoException Movimento já está pendente ou foi lançado a partir do extrato.
     * @throws ExtratoInvalidoException     Mês fechado.
     */
    public function desconciliar(int $movimentoId, ?int $userId = null): array
    {
        $mov = $this->obterMovimento($movimentoId);

        if ($mov['status'] === 'pendente') {
            throw new ConciliacaoConflitoException('O movimento já está pendente.');
        }
        if ($mov['status'] === 'lancado') {
            throw new ConciliacaoConflitoException('Este movimento gerou um lançamento; desfaça o lançamento em vez de desconciliar.');
        }

        if ($mov['status'] === 'conciliado') {
            $this->bloqueioMensal($mov['conta_id'], $mov['data']);
        }

        if (!$this->movimentos->atualizarVinculo($movimentoId, [
            'status'          => 'pendente',
            'pagamento_tipo'  => null,
            'pagamento_id'    => null,
            'motivo_ignorado' => null,
            'conciliado_por'  => null,
            'conciliado_em'   => null,
        ])) {
            throw new \RuntimeException('Não foi possível desfazer o vínculo do movimento.');
        }

        log_message('info', sprintf(
            '[Conciliacao] movimento=%d voltou para pendente (era %s) por usuario=%s',
            $movimentoId,
            $mov['status'],
            $userId ?? '-'
        ));

        return $this->movimentos->obter($movimentoId) ?? $mov;
    }

    /**
     * Marca o movimento como ignorado (tarifa já refletida em outro lugar, estorno, etc.), com motivo.
     *
     * @return array<string,mixed>
     * @throws ExtratoInvalidoException     Motivo vazio ou longo demais.
     * @throws ConciliacaoConflitoException Movimento não está pendente.
     */
    public function ignorar(int $movimentoId, string $motivo, ?int $userId = null): array
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new ExtratoInvalidoException('Informe o motivo para ignorar o movimento.');
        }
        if (mb_strlen($motivo) > 255) {
            throw new ExtratoInvalidoException('O motivo deve ter no máximo 255 caracteres.');
        }

        $mov = $this->obterMovimento($movimentoId);
        $this->exigirPendente($mov);

        if (!$this->movimentos->atualizarVinculo($movimentoId, [
            'status'          => 'ignorado',
            'motivo_ignorado' => $motivo,
            'conciliado_por'  => $userId,
            'conciliado_em'   => date('Y-m-d H:i:s'),
        ])) {
            throw new \RuntimeException('Não foi possível ignorar o movimento.');
        }

        log_message('info', sprintf('[Conciliacao] movimento=%d ignorado por usuario=%s', $movimentoId, $userId ?? '-'));

        return $this->movimentos->obter($movimentoId) ?? $mov;
    }

    /**
     * Concilia em lote itens já mostrados ao usuário, que só passam se ainda forem sugestões de confiança alta.
     * Exige `$confirmado = true`. Cada item é tratado à parte: um erro não impede os demais.
     *
     * @param list<array{movimento_id:int,pagamento_tipo:string,pagamento_id:int}> $itens
     * @return array{conciliados:list<int>,rejeitados:list<array{movimento_id:int,motivo:string}>}
     * @throws ExtratoInvalidoException Sem confirmação, lista vazia ou grande demais.
     */
    public function conciliarLote(array $itens, bool $confirmado, ?int $userId = null, int $janelaDias = self::JANELA_PADRAO_DIAS): array
    {
        if (!$confirmado) {
            throw new ExtratoInvalidoException('A conciliação em lote exige confirmação do usuário.');
        }
        if (!$itens) {
            throw new ExtratoInvalidoException('Nenhum item informado.');
        }
        $limite = $this->config->limiteLoteConciliacao();
        if (count($itens) > $limite) {
            throw new ExtratoInvalidoException('Máximo de ' . $limite . ' itens por lote.');
        }

        $altas = [];
        $r     = ['conciliados' => [], 'rejeitados' => []];

        foreach ($itens as $item) {
            $id   = (int) ($item['movimento_id'] ?? 0);
            $tipo = (string) ($item['pagamento_tipo'] ?? '');
            $pag  = (int) ($item['pagamento_id'] ?? 0);

            try {
                $mov = $this->obterMovimento($id);
                if (!isset($altas[$mov['conta_id']])) {
                    $altas[$mov['conta_id']] = $this->sugestoesLote($mov['conta_id'], $janelaDias);
                }
                $ok = array_filter(
                    $altas[$mov['conta_id']],
                    static fn (array $a): bool => $a['movimento']['id_movimento'] === $id && $a['pagamento_tipo'] === $tipo && $a['pagamento_id'] === $pag
                );
                if (!$ok) {
                    throw new ExtratoInvalidoException('Não é mais uma sugestão de confiança alta; concilie individualmente.');
                }
                $this->conciliar($id, $tipo, $pag, $userId);
                $r['conciliados'][] = $id;
            } catch (ExtratoInvalidoException | ExtratoNaoEncontradoException | ConciliacaoConflitoException $e) {
                $r['rejeitados'][] = ['movimento_id' => $id, 'motivo' => $e->getMessage()];
            }
        }

        return $r;
    }

    // ------------------------------------------------------------------
    // Cálculo
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $mov
     * @return array{pagamentos:list<array>,em_aberto:list<array>,pares:list<array>,confianca:?string}
     */
    private function calcular(array $mov, int $janela): array
    {
        $centavos = abs((int) $mov['valor_centavos']);
        $saida    = (int) $mov['valor_centavos'] < 0;
        $de       = $this->deslocar($mov['data'], -$janela);
        $ate      = $this->deslocar($mov['data'], $janela);
        $conta    = (int) $mov['conta_id'];

        $pagamentos = [];
        $tipo       = $saida ? 'despesa' : 'receita';
        $livres     = $saida
            ? $this->candidatos->pagamentosDespesaLivres($conta, $centavos, $de, $ate)
            : $this->candidatos->pagamentosReceitaLivres($conta, $centavos, $de, $ate);
        foreach ($livres as $c) {
            $pagamentos[] = $this->pontuar($mov, $c, $tipo);
        }

        $pares = [];
        foreach ($this->candidatos->movimentosParaTransferencia($conta, -(int) $mov['valor_centavos'], $de, $ate) as $outro) {
            $pares[] = [
                'id_movimento'   => (int) $outro['id_movimento'],
                'conta_id'       => (int) $outro['conta_id'],
                'data'           => $outro['data'],
                'valor_centavos' => (int) $outro['valor_centavos'],
                'dias'           => $this->dias($mov['data'], $outro['data']),
            ];

            // Transferência já lançada entre as duas contas: pode ser vinculada.
            [$origem, $destino] = $saida ? [$conta, (int) $outro['conta_id']] : [(int) $outro['conta_id'], $conta];
            foreach ($this->candidatos->transferenciasLivres($origem, $destino, $centavos, $de, $ate, $conta) as $t) {
                $achado = $this->pontuar($mov, $t, 'transferencia');
                $achado['movimento_par_id'] = (int) $outro['id_movimento'];
                $pagamentos[] = $achado;
            }
        }

        $emAberto = [];
        $abertos  = $saida
            ? $this->candidatos->despesasEmAberto($centavos, $de, $ate)
            : $this->candidatos->receitasEmAberto($centavos, $de, $ate);
        foreach ($abertos as $c) {
            $emAberto[] = $this->pontuar($mov, $c, $saida ? 'despesa_em_aberto' : 'receita_em_aberto');
        }

        $ordem = static fn (array $a, array $b): int => [$b['pontos'], $a['dias']] <=> [$a['pontos'], $b['dias']];
        usort($pagamentos, $ordem);
        usort($emAberto, $ordem);

        // Transferência aparece uma vez por par; remove repetição do mesmo id.
        $vistos     = [];
        $pagamentos = array_values(array_filter($pagamentos, static function (array $p) use (&$vistos): bool {
            $k = $p['tipo'] . ':' . $p['id'];
            if (isset($vistos[$k])) {
                return false;
            }
            $vistos[$k] = true;

            return true;
        }));

        // Confiança alta (única que vale para o lote): valor e data exatos, um só candidato e nenhuma outra
        // hipótese (em aberto, par de transferência). Transferência sempre é conferida pelo usuário, uma a uma.
        $alta = count($pagamentos) === 1 && !$emAberto && !$pares
            && $pagamentos[0]['dias'] === 0 && $pagamentos[0]['tipo'] !== 'transferencia';

        return [
            'pagamentos' => $pagamentos,
            'em_aberto'  => $emAberto,
            'pares'      => $pares,
            'confianca'  => $alta ? self::CONFIANCA_ALTA : (($pagamentos || $emAberto || $pares) ? self::CONFIANCA_MEDIA : null),
        ];
    }

    /**
     * @param array<string,mixed> $mov
     * @param array<string,mixed> $c
     * @return array<string,mixed>
     */
    private function pontuar(array $mov, array $c, string $tipo): array
    {
        $dias   = $this->dias($mov['data'], $c['data']);
        $pontos = self::PONTOS_VALOR + max(0, self::PONTOS_DATA_MAX - self::PONTOS_POR_DIA * $dias);
        $nome   = $this->nomeConfere((string) ($mov['memo'] ?? '') . ' ' . (string) ($mov['nome_contraparte'] ?? ''), (string) ($c['descricao'] ?? '') . ' ' . (string) ($c['contraparte'] ?? '') . ' ' . (string) ($c['comentario'] ?? ''));
        if ($nome) {
            $pontos += self::PONTOS_NOME;
        }

        return [
            'tipo'           => $tipo,
            'id'             => (int) $c['id'],
            'data'           => $c['data'],
            'valor_centavos' => (int) $c['valor_centavos'],
            'descricao'      => $c['descricao'] ?? null,
            'contraparte'    => $c['contraparte'] ?? null,
            'dias'           => $dias,
            'nome_confere'   => $nome,
            'pontos'         => $pontos,
        ];
    }

    private function nomeConfere(string $extrato, string $lancamento): bool
    {
        $a = $this->palavras($extrato);
        $b = $this->palavras($lancamento);

        return $a && $b && array_intersect($a, $b) !== [];
    }

    /** @return list<string> */
    private function palavras(string $texto): array
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);
        $partes = preg_split('/[^a-z0-9]+/', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $partes,
            static fn (string $p): bool => strlen($p) >= 4 && !ctype_digit($p) && !in_array($p, self::PALAVRAS_IGNORADAS, true)
        )));
    }

    // ------------------------------------------------------------------
    // Validação e gravação
    // ------------------------------------------------------------------

    /**
     * Valida o alvo do vínculo e devolve a data dele (para a checagem de mês fechado).
     *
     * @param array<string,mixed>      $mov
     * @param array<string,mixed>|null $par
     */
    private function validarAlvo(array $mov, string $tipo, int $id, ?array $par): string
    {
        if (!in_array($tipo, ['despesa', 'receita', 'transferencia'], true) || $id <= 0) {
            throw new ExtratoInvalidoException('Informe o tipo (despesa, receita ou transferencia) e o id do pagamento.');
        }

        $centavos = abs((int) $mov['valor_centavos']);
        $saida    = (int) $mov['valor_centavos'] < 0;

        if ($tipo === 'transferencia') {
            $t = $this->candidatos->obterTransferencia($id);
            if (!$t) {
                throw new ExtratoNaoEncontradoException('Transferência não encontrada.');
            }
            $contaDoLado = $saida ? $t['conta_origem'] : $t['conta_destino'];
            if ($contaDoLado !== (int) $mov['conta_id']) {
                throw new ExtratoInvalidoException('A conta e o sentido do movimento não batem com a transferência.');
            }
            if ($t['valor_centavos'] !== $centavos) {
                throw new ExtratoInvalidoException('O valor da transferência é diferente do valor do movimento.');
            }
            if ($par) {
                $contaPar = $saida ? $t['conta_destino'] : $t['conta_origem'];
                if ((int) $par['conta_id'] !== $contaPar || (int) $par['valor_centavos'] !== -(int) $mov['valor_centavos']) {
                    throw new ExtratoInvalidoException('O movimento par não corresponde ao outro lado da transferência.');
                }
            }
            $this->exigirLivre((int) $mov['conta_id'], $tipo, $id);
            if ($par) {
                $this->exigirLivre((int) $par['conta_id'], $tipo, $id);
            }

            return $t['data'];
        }

        if (($tipo === 'despesa') !== $saida) {
            throw new ExtratoInvalidoException($saida ? 'Saída só pode ser vinculada a pagamento de despesa.' : 'Entrada só pode ser vinculada a pagamento de receita.');
        }

        $p = $this->candidatos->obterPagamento($tipo, $id);
        if (!$p) {
            throw new ExtratoNaoEncontradoException('Pagamento não encontrado.');
        }
        if ((int) $p['conta_id'] !== (int) $mov['conta_id']) {
            throw new ExtratoInvalidoException('O pagamento é de outra conta.');
        }
        if ($p['valor_centavos'] !== $centavos) {
            throw new ExtratoInvalidoException('O valor do pagamento é diferente do valor do movimento.');
        }
        $this->exigirLivre((int) $mov['conta_id'], $tipo, $id);

        return $p['data'];
    }

    private function exigirLivre(int $contaId, string $tipo, int $id): void
    {
        if ($this->movimentos->buscarPorPagamento($contaId, $tipo, $id)) {
            throw new ConciliacaoConflitoException('Este pagamento já está vinculado a outro movimento do extrato.');
        }
    }

    /** @param array<string,mixed> $mov */
    private function gravarVinculo(array $mov, string $tipo, int $id, ?int $userId): void
    {
        $ok = $this->movimentos->atualizarVinculo((int) $mov['id_movimento'], [
            'status'          => 'conciliado',
            'pagamento_tipo'  => $tipo,
            'pagamento_id'    => $id,
            'motivo_ignorado' => null,
            'conciliado_por'  => $userId,
            'conciliado_em'   => date('Y-m-d H:i:s'),
        ]);

        if (!$ok) {
            // O índice único barrou: outra requisição vinculou o mesmo pagamento primeiro.
            throw new ConciliacaoConflitoException('Este pagamento já está vinculado a outro movimento do extrato.');
        }
    }

    /** @param array<string,mixed> $mov */
    private function exigirPendente(array $mov): void
    {
        if ($mov['status'] !== 'pendente') {
            throw new ConciliacaoConflitoException('O movimento não está pendente (situação atual: ' . $mov['status'] . ').');
        }
    }

    private function bloqueioMensal(int $contaId, string $data): void
    {
        try {
            $this->saldoMensal->verificarBloqueioMensal('CONTA', $contaId, $data);
        } catch (\Exception $e) {
            throw new ExtratoInvalidoException('Mês fechado: não é possível vincular nem desvincular movimentos em ' . substr($data, 0, 7) . '.');
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

    private function janela(int $dias): int
    {
        return min(self::JANELA_MAXIMA_DIAS, max(0, $dias));
    }

    private function deslocar(string $data, int $dias): string
    {
        return (new DateTimeImmutable($data))->modify(($dias >= 0 ? '+' : '') . $dias . ' days')->format('Y-m-d');
    }

    private function dias(string $a, string $b): int
    {
        return abs((int) (new DateTimeImmutable($a))->diff(new DateTimeImmutable($b))->format('%a'));
    }
}
