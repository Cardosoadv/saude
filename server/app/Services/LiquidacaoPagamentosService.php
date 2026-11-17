<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Liquidacao\LiquidacaoDominio;
use App\Libraries\Liquidacao\LiquidacaoException;
use App\Libraries\Liquidacao\PlanoParcelas;
use App\Repositories\LiquidacoesRepository;
use DateTimeImmutable;
use Throwable;

/**
 * Parcelas e pagamentos da liquidação: geração do parcelamento, baixa com comprovante,
 * cancelamento, vencimentos e avisos.
 *
 * Valores em centavos. Uma parcela paga é paga por inteiro (não há baixa parcial).
 */
class LiquidacaoPagamentosService
{
    public const DIAS_AVISO_PADRAO = 3;

    protected LiquidacoesService $liquidacoes;
    protected LiquidacoesRepository $repository;
    protected ?LiquidacoesArquivosService $arquivos;
    protected ?LiquidacaoFinanceiroService $financeiro;
    protected ?NotificacaoService $notificacao;
    protected ?TarefasService $tarefas;

    public function __construct(
        ?LiquidacoesService $liquidacoes = null,
        ?LiquidacoesRepository $repository = null,
        ?LiquidacoesArquivosService $arquivos = null,
        ?LiquidacaoFinanceiroService $financeiro = null,
        ?NotificacaoService $notificacao = null,
        ?TarefasService $tarefas = null
    ) {
        $this->repository  = $repository ?? new LiquidacoesRepository();
        $this->liquidacoes = $liquidacoes ?? new LiquidacoesService($this->repository);
        $this->arquivos    = $arquivos;
        $this->financeiro  = $financeiro;
        $this->notificacao = $notificacao;
        $this->tarefas     = $tarefas;
    }

    // ---- Parcelamento -----------------------------------------------

    /**
     * Gera as parcelas a partir do valor atualizado (ou de `valor_total`, se informado).
     *
     * Parâmetros: `numero_parcelas`, `primeiro_vencimento`, `periodicidade` (padrão mensal), `entrada`
     * (centavos), `data_entrada`, `valor_total`, `substituir` (refaz o plano se já houver parcelas
     * abertas; parcelas pagas impedem refazer).
     *
     * @return list<array> parcelas criadas
     */
    public function gerarParcelas(int $liquidacaoId, array $params): array
    {
        $liquidacao = $this->liquidacoes->liquidacaoComAcesso($liquidacaoId);

        $existentes = array_filter(
            $this->repository->getPagamentosByLiquidacao($liquidacaoId),
            static fn ($p) => $p['status_pagamento'] !== LiquidacaoDominio::PARCELA_CANCELADA
        );
        foreach ($existentes as $p) {
            if ($p['status_pagamento'] === LiquidacaoDominio::PARCELA_PAGA) {
                throw LiquidacaoException::conflito('Há parcelas já pagas; não é possível refazer o parcelamento.');
            }
        }
        if ($existentes !== [] && empty($params['substituir'])) {
            throw LiquidacaoException::conflito('A liquidação já tem parcelas em aberto. Envie "substituir": true para refazer o plano.');
        }

        $plano = PlanoParcelas::gerar(
            (int) ($params['valor_total'] ?? $liquidacao['valor_total']),
            (int) ($params['entrada'] ?? 0),
            (int) ($params['numero_parcelas'] ?? 0),
            (string) ($params['primeiro_vencimento'] ?? ''),
            (string) ($params['periodicidade'] ?? 'mensal'),
            !empty($params['data_entrada']) ? (string) $params['data_entrada'] : null
        );

        return $this->emTransacao(function () use ($liquidacao, $liquidacaoId, $plano) {
            $this->repository->deletePagamentosNaoPagos($liquidacaoId);

            $criadas = [];
            foreach ($plano as $parcela) {
                $parcela += ['liquidacao_id' => $liquidacaoId, 'status_pagamento' => LiquidacaoDominio::PARCELA_PENDENTE];
                $parcela['pagamento_id'] = (int) $this->repository->createPagamento($parcela);
                if ($parcela['pagamento_id'] === 0) {
                    throw LiquidacaoException::invalida('Parcela inválida.', $this->repository->getPagamentosModel()->errors());
                }
                $criadas[] = $parcela;
            }

            if ($liquidacao['status'] === LiquidacaoDominio::STATUS_HOMOLOGADA) {
                $this->repository->getModel()->update($liquidacaoId, ['status' => LiquidacaoDominio::STATUS_EM_PAGAMENTO]);
            }
            $this->repository->clearCache();

            return $criadas;
        });
    }

    // ---- Baixa e cancelamento -----------------------------------------

    /**
     * Baixa uma parcela: data, forma e comprovante(s) opcionais (upload no Drive). Quando todas as
     * parcelas ficam pagas a liquidação passa a "quitada". Se a liquidação lança honorários no
     * Financeiro (`lancar_honorarios`), as receitas saem na mesma operação (`conta_id` opcional).
     *
     * @param array<int,array{path:string,nome:string}> $comprovantes
     *
     * @return array{pagamento: array, quitada: bool, lancamentos: list<int>, erros_upload: list<array>}
     */
    public function baixar(int $pagamentoId, array $dados, array $comprovantes = [], ?int $usuarioId = null): array
    {
        $pagamento = $this->pagamentoOuFalha($pagamentoId);
        $liquidacao = $this->liquidacoes->liquidacaoComAcesso((int) $pagamento['liquidacao_id']);

        if (!in_array($pagamento['status_pagamento'], [LiquidacaoDominio::PARCELA_PENDENTE, LiquidacaoDominio::PARCELA_VENCIDA], true)) {
            throw LiquidacaoException::conflito('Só é possível baixar parcelas pendentes ou vencidas.');
        }

        $hoje = date('Y-m-d');
        $data = (string) ($dados['data_pagamento'] ?? $hoje);
        $d    = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if ($d === false || $d->format('Y-m-d') !== $data) {
            throw LiquidacaoException::invalida('Data de pagamento inválida (use AAAA-MM-DD).', ['data_pagamento' => 'Data inválida.']);
        }
        if ($data > $hoje) {
            throw LiquidacaoException::invalida('A data do pagamento não pode ser futura.', ['data_pagamento' => 'Data futura.']);
        }
        $forma = isset($dados['forma_pagamento']) && $dados['forma_pagamento'] !== '' ? mb_substr((string) $dados['forma_pagamento'], 0, 50) : null;
        $contaId = !empty($dados['conta_id']) ? (int) $dados['conta_id'] : null;

        // Upload antes de mexer no banco: se o Drive falhar, nada foi baixado.
        $enviados = [];
        $errosUpload = [];
        if ($comprovantes !== []) {
            $envio = $this->arquivos()->enviar($liquidacao, $comprovantes, 'comprovante', $pagamentoId, $usuarioId);
            $enviados    = $envio['enviados'];
            $errosUpload = $envio['erros'];
            if ($enviados === []) {
                throw new LiquidacaoException('Falha ao enviar o comprovante ao Google Drive: ' . ($errosUpload[0]['mensagem'] ?? 'erro desconhecido'), 502);
            }
        }

        try {
            $resultado = $this->emTransacao(function () use ($pagamento, $liquidacao, $data, $forma, $contaId, $enviados, $dados) {
                $alteracoes = [
                    'status_pagamento' => LiquidacaoDominio::PARCELA_PAGA,
                    'data_pagamento'   => $data,
                    'forma_pagamento'  => $forma,
                ];
                if ($enviados !== []) {
                    $alteracoes['comprovante_url'] = $enviados[0]['url_arquivo'];
                } elseif (!empty($dados['comprovante_url'])) {
                    $alteracoes['comprovante_url'] = mb_substr((string) $dados['comprovante_url'], 0, 255);
                }
                if (!empty($dados['observacao'])) {
                    $alteracoes['observacao'] = (string) $dados['observacao'];
                }

                $lancamentos = [];
                if (!empty($liquidacao['lancar_honorarios'])) {
                    $detalhes    = $this->liquidacoes->getById((int) $liquidacao['liquidacao_id']);
                    $processo    = $this->repository->getProcesso((int) $liquidacao['processo_id']) ?? [];
                    $lancamentos = $this->financeiro()->lancarHonorarios($liquidacao, $detalhes['itens'] ?? [], $pagamento, $processo, $data, $contaId);
                    if ($lancamentos !== []) {
                        $alteracoes['lancamentos_financeiros'] = json_encode($lancamentos);
                    }
                }

                $this->repository->updatePagamento((int) $pagamento['pagamento_id'], $alteracoes);
                $quitada = $this->atualizarSituacaoDaLiquidacao($liquidacao);

                return ['quitada' => $quitada, 'lancamentos' => $lancamentos];
            });
        } catch (Throwable $e) {
            $this->arquivos()->descartar($enviados);
            throw $e;
        }

        return [
            'pagamento'    => $this->repository->getPagamento($pagamentoId),
            'quitada'      => $resultado['quitada'],
            'lancamentos'  => $resultado['lancamentos'],
            'erros_upload' => $errosUpload,
        ];
    }

    /** Cancela uma parcela ainda não paga. */
    public function cancelar(int $pagamentoId, ?string $motivo = null): array
    {
        $pagamento  = $this->pagamentoOuFalha($pagamentoId);
        $liquidacao = $this->liquidacoes->liquidacaoComAcesso((int) $pagamento['liquidacao_id']);

        if (!in_array($pagamento['status_pagamento'], [LiquidacaoDominio::PARCELA_PENDENTE, LiquidacaoDominio::PARCELA_VENCIDA], true)) {
            throw LiquidacaoException::conflito('Só é possível cancelar parcelas pendentes ou vencidas.');
        }

        $this->emTransacao(function () use ($pagamentoId, $liquidacao, $motivo) {
            $this->repository->updatePagamento($pagamentoId, [
                'status_pagamento' => LiquidacaoDominio::PARCELA_CANCELADA,
                'observacao'       => $motivo,
            ]);
            $this->atualizarSituacaoDaLiquidacao($liquidacao);
        });

        return $this->repository->getPagamento($pagamentoId);
    }

    // ---- Vencimentos (comando agendado) --------------------------------

    /**
     * Marca como vencidas as parcelas pendentes com vencimento anterior a hoje, avisa o responsável
     * do processo (uma vez, na virada) e abre uma tarefa de cobrança. Também avisa das parcelas que
     * vencem nos próximos $diasAviso dias (uma vez por parcela).
     *
     * @return array{vencidas: int, avisos_a_vencer: int, avisos_vencida: int, tarefas: int, sem_responsavel: int}
     */
    public function processarVencimentos(int $diasAviso = self::DIAS_AVISO_PADRAO, ?string $hoje = null): array
    {
        $hoje ??= date('Y-m-d');
        $limite = (new DateTimeImmutable($hoje))->modify("+{$diasAviso} days")->format('Y-m-d');
        $r = ['vencidas' => 0, 'avisos_a_vencer' => 0, 'avisos_vencida' => 0, 'tarefas' => 0, 'sem_responsavel' => 0];

        foreach ($this->repository->getPagamentosAVencer($hoje, $limite) as $p) {
            if ($this->avisar($p, 'a_vencer', false, $r)) {
                $this->repository->updatePagamento((int) $p['pagamento_id'], ['aviso_enviado_em' => date('Y-m-d H:i:s')]);
                $r['avisos_a_vencer']++;
            }
        }

        foreach ($this->repository->getPagamentosAtrasados($hoje) as $p) {
            $this->repository->updatePagamento((int) $p['pagamento_id'], ['status_pagamento' => LiquidacaoDominio::PARCELA_VENCIDA]);
            $r['vencidas']++;
            if ($this->avisar($p, 'vencida', true, $r)) {
                $r['avisos_vencida']++;
            }
        }

        return $r;
    }

    /**
     * Avisa o responsável pelo processo; no caso de parcela vencida abre também uma tarefa.
     */
    private function avisar(array $pagamento, string $tipo, bool $criarTarefa, array &$contadores): bool
    {
        $liquidacao = $this->repository->findById((int) $pagamento['liquidacao_id']);
        $processo   = $liquidacao ? $this->repository->getProcesso((int) $liquidacao['processo_id']) : null;
        $responsavel = (int) ($processo['responsavel_id'] ?? 0);
        if (!$liquidacao || !$processo || $responsavel === 0) {
            $contadores['sem_responsavel']++;

            return false;
        }

        $dados = [
            'tipo'            => $tipo,
            'liquidacao_id'   => (int) $liquidacao['liquidacao_id'],
            'processo_id'     => (int) $liquidacao['processo_id'],
            'numero_parcela'  => (int) $pagamento['numero_parcela'],
            'valor_parcela'   => (int) $pagamento['valor_parcela'],
            'data_vencimento' => $pagamento['data_vencimento'],
        ];

        try {
            $this->notificacao()->enviarNotificacao($responsavel, 'LAYOUT_LIQUIDACAO_PARCELA', $dados);
        } catch (Throwable $e) {
            log_message('error', '[LiquidacaoPagamentosService] Falha ao avisar sobre a parcela ' . $pagamento['pagamento_id'] . ': ' . $e->getMessage());
        }

        if ($criarTarefa) {
            $resultado = $this->tarefas()->salvarTarefa([
                'tarefa'      => sprintf('Cobrar parcela %s da liquidação #%d (venceu em %s)', $pagamento['numero_parcela'] == 0 ? 'de entrada' : $pagamento['numero_parcela'], $liquidacao['liquidacao_id'], date('d/m/Y', strtotime($pagamento['data_vencimento']))),
                'detalhes'    => 'Valor da parcela: R$ ' . number_format($pagamento['valor_parcela'] / 100, 2, ',', '.') . '.',
                'prazo'       => date('Y-m-d'),
                'prioridade'  => 1,
                'responsavel' => $responsavel,
                'processo_id' => (int) $liquidacao['processo_id'],
                'cliente_id'  => $processo['cliente_id'] ?? null,
                'status'      => 2, // pendente
                'notificar'   => 0,
            ]);
            if (!empty($resultado['success'])) {
                $contadores['tarefas']++;
            }
        }

        return true;
    }

    // ---- Auxiliares -----------------------------------------------------

    /**
     * Ajusta o status da liquidação conforme as parcelas: todas pagas => quitada; primeira baixa de
     * liquidação homologada => em pagamento. Devolve se ficou quitada.
     */
    private function atualizarSituacaoDaLiquidacao(array $liquidacao): bool
    {
        $id       = (int) $liquidacao['liquidacao_id'];
        $parcelas = array_filter(
            $this->repository->getPagamentosByLiquidacao($id),
            static fn ($p) => $p['status_pagamento'] !== LiquidacaoDominio::PARCELA_CANCELADA
        );
        $abertas = array_filter($parcelas, static fn ($p) => $p['status_pagamento'] !== LiquidacaoDominio::PARCELA_PAGA);

        if ($parcelas !== [] && $abertas === []) {
            if ($liquidacao['status'] !== LiquidacaoDominio::STATUS_QUITADA) {
                $this->repository->getModel()->update($id, ['status' => LiquidacaoDominio::STATUS_QUITADA]);
            }
            $this->repository->clearCache();

            return true;
        }

        if ($liquidacao['status'] === LiquidacaoDominio::STATUS_HOMOLOGADA) {
            $this->repository->getModel()->update($id, ['status' => LiquidacaoDominio::STATUS_EM_PAGAMENTO]);
        } elseif ($liquidacao['status'] === LiquidacaoDominio::STATUS_QUITADA && $abertas !== []) {
            // parcela reaberta ou nova: deixa de estar quitada
            $this->repository->getModel()->update($id, ['status' => LiquidacaoDominio::STATUS_EM_PAGAMENTO]);
        }
        $this->repository->clearCache();

        return false;
    }

    private function pagamentoOuFalha(int $pagamentoId): array
    {
        $pagamento = $this->repository->getPagamento($pagamentoId);
        if (!$pagamento) {
            throw LiquidacaoException::naoEncontrada('Pagamento');
        }

        return $pagamento;
    }

    protected function emTransacao(callable $acao)
    {
        $db = \Config\Database::connect();
        $db->transBegin();
        try {
            $resultado = $acao();
            $db->transCommit();

            return $resultado;
        } catch (Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    protected function arquivos(): LiquidacoesArquivosService
    {
        return $this->arquivos ??= new LiquidacoesArquivosService($this->repository);
    }

    protected function financeiro(): LiquidacaoFinanceiroService
    {
        return $this->financeiro ??= new LiquidacaoFinanceiroService();
    }

    protected function notificacao(): NotificacaoService
    {
        return $this->notificacao ??= new NotificacaoService();
    }

    protected function tarefas(): TarefasService
    {
        return $this->tarefas ??= new TarefasService();
    }
}
