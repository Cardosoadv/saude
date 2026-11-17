<?php

namespace App\Repositories;

use App\Libraries\Liquidacao\ResumoPagamentos;
use App\Models\ItensLiquidacaoModel;
use App\Models\LiquidacoesDocumentosModel;
use App\Models\LiquidacoesHistoricoCalculosModel;
use App\Models\LiquidacoesModel;
use App\Models\LiquidacoesPagamentosModel;
use App\Models\ProcessosModel;
use App\Services\AcessoEntidadeService;

class LiquidacoesRepository extends BaseRepository
{
    protected $itensModel;
    protected $historicoModel;
    protected $pagamentosModel;
    protected $documentosModel;
    protected ProcessosModel $processosModel;
    protected ?AcessoEntidadeService $acesso;


    public function __construct(
        ?LiquidacoesModel $model = null,
        ?ItensLiquidacaoModel $itensModel = null,
        ?LiquidacoesHistoricoCalculosModel $historicoModel = null,
        ?LiquidacoesPagamentosModel $pagamentosModel = null,
        ?LiquidacoesDocumentosModel $documentosModel = null,
        ?ProcessosModel $processosModel = null,
        ?AcessoEntidadeService $acesso = null
    ) {
        parent::__construct();
        $this->model            = $model ?? model('LiquidacoesModel');
        $this->cacheKey         = 'liquidacoes';
        $this->itensModel       = $itensModel ?? new ItensLiquidacaoModel();
        $this->historicoModel   = $historicoModel ?? new LiquidacoesHistoricoCalculosModel();
        $this->pagamentosModel  = $pagamentosModel ?? new LiquidacoesPagamentosModel();
        $this->documentosModel  = $documentosModel ?? new LiquidacoesDocumentosModel();
        $this->processosModel   = $processosModel ?? new ProcessosModel();
        $this->acesso           = $acesso;
    }

    /** Além do cache próprio, invalida os indicadores de Legal Ops, que usam o valor atualizado da liquidação. */
    public function clearCache(): void
    {
        parent::clearCache();
        (new \App\Repositories\Relatorios\LegalOpsRepository())->clearCache();
    }

    public function getItensModel(): ItensLiquidacaoModel
    {
        return $this->itensModel;
    }

    public function getPagamentosModel(): LiquidacoesPagamentosModel
    {
        return $this->pagamentosModel;
    }

    public function getDocumentosModel(): LiquidacoesDocumentosModel
    {
        return $this->documentosModel;
    }

    public function getLiquidacoesComProcesso($params = [])
    {
        $paramsKey = md5(json_encode($params));
        $page = (int) ($params['page'] ?? service('request')->getVar('page') ?? 1);

        return $this->remember("list_{$paramsKey}_p{$page}", function () use ($params, $page) {
            $pagos   = $this->db->prefixTable('liquidacoes_pagamentos');
            $liq     = $this->db->prefixTable('liquidacoes');
            $proxima = "(SELECT MIN(pg.data_vencimento) FROM {$pagos} pg WHERE pg.liquidacao_id = {$liq}.liquidacao_id AND pg.status_pagamento IN ('pendente','vencida'))";
            $pago    = "(SELECT COALESCE(SUM(pg.valor_parcela), 0) FROM {$pagos} pg WHERE pg.liquidacao_id = {$liq}.liquidacao_id AND pg.status_pagamento = 'paga')";

            $query = $this->model
                ->select('liquidacoes.*, processos.numero_processo as processo_numero')
                ->select("{$pago} AS total_pago, {$proxima} AS proxima_parcela_vencimento", false)
                ->join('processos', 'processos.id_processo = liquidacoes.processo_id', 'left');

            if (!empty($params['search'])) {
                $term = $params['search'];
                $query->groupStart()
                    ->like('processos.numero_processo', $term)
                    ->orLike('liquidacoes.observacoes', $term)
                    ->groupEnd();
            }

            if (!empty($params['status'])) {
                $query->where('liquidacoes.status', $params['status']);
            }

            if (!empty($params['processo_id'])) {
                $query->where('liquidacoes.processo_id', $params['processo_id']);
            }

            if (!empty($params['cliente_id'])) {
                $query->where('processos.cliente_id', $params['cliente_id']);
            }

            // Escopo de quem só tem `liquidacoes.exclusive`: processos do próprio cliente.
            if (isset($params['escopo_cliente_id'])) {
                $query->where('processos.cliente_id', (int) $params['escopo_cliente_id']);
            }

            // Privacidade granular dos processos (sem efeito quando desligada).
            ($this->acesso ??= new AcessoEntidadeService())->aplicarEscopo($query, 'processo', 'processos');

            if (!empty($params['tipo_liquidacao'])) {
                $query->where('liquidacoes.tipo_liquidacao', $params['tipo_liquidacao']);
            }

            $query->orderBy('liquidacoes.liquidacao_id', 'DESC');

            return [
                'liquidacoes' => $query->paginate(20, 'default', $page),
                'pager'       => $this->model->pager,
            ];
        });
    }

    public function getDetalhes($id)
    {
        return $this->remember("detalhes_{$id}", function () use ($id) {
            $liquidacao = $this->findById((int) $id);
            if (!$liquidacao) {
                return null;
            }

            $liquidacao['processo']     = $this->processosModel->find($liquidacao['processo_id']);
            $liquidacao['itens']        = $this->itensModel->where('liquidacao_id', $id)->findAll();
            $liquidacao['pagamentos']   = $this->pagamentosModel->where('liquidacao_id', $id)->findAll();
            $liquidacao['documentos']   = $this->documentosModel->where('liquidacao_id', $id)->findAll();
            $liquidacao['historico']    = $this->getHistoricoPorLiquidacao((int) $id);

            return $liquidacao;
        });
    }

    /** Processo (linha crua) para checagem de acesso; null se não existir. */
    public function getProcesso(int $processoId): ?array
    {
        return $this->processosModel->find($processoId);
    }

    /** Histórico de cálculos de todos os itens da liquidação, do mais recente ao mais antigo. */
    public function getHistoricoPorLiquidacao(int $liquidacaoId): array
    {
        return $this->historicoModel
            ->select('liquidacoes_historico_calculos.*, itens_liquidacao.descricao as item_descricao')
            ->join('itens_liquidacao', 'itens_liquidacao.item_id = liquidacoes_historico_calculos.item_id')
            ->where('itens_liquidacao.liquidacao_id', $liquidacaoId)
            ->orderBy('liquidacoes_historico_calculos.data_calculo', 'DESC')
            ->orderBy('liquidacoes_historico_calculos.historico_id', 'DESC')
            ->findAll();
    }

    /**
     * Liquidações (fora dos status informados) cujos itens ou critério padrão citam algum dos índices.
     *
     * @param list<string> $codigos
     * @param list<string> $statusIgnorados
     *
     * @return list<int>
     */
    public function idsPorIndices(array $codigos, array $statusIgnorados = []): array
    {
        if ($codigos === []) {
            return [];
        }

        $consulta = $this->itensModel->distinct()->select('liquidacoes.liquidacao_id')
            ->join('liquidacoes', 'liquidacoes.liquidacao_id = itens_liquidacao.liquidacao_id')
            ->groupStart();
        foreach ($codigos as $codigo) {
            $consulta->orLike('itens_liquidacao.criterio', '"' . $codigo . '"', 'both', null, false)
                ->orLike('liquidacoes.criterio_padrao', '"' . $codigo . '"', 'both', null, false);
        }
        $consulta->groupEnd();
        if ($statusIgnorados !== []) {
            $consulta->whereNotIn('liquidacoes.status', $statusIgnorados);
        }

        return array_map('intval', array_column($consulta->findAll(), 'liquidacao_id'));
    }

    // Manage Items
    public function createItem(array $data)
    {
        $id = $this->itensModel->insert($data);
        if ($id) {
            $this->clearCache();
        }
        return $id;
    }

    public function updateItem($id, array $data)
    {
        $result = $this->itensModel->update($id, $data);
        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    public function deleteItem($id)
    {
        $result = $this->itensModel->delete($id);
        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    public function getItem($id)
    {
        return $this->itensModel->find($id);
    }

    public function getPagamento($id)
    {
        return $this->pagamentosModel->find($id);
    }

    public function getDocumento($id)
    {
        return $this->documentosModel->find($id);
    }

    public function getItensByLiquidacao(int $liquidacaoId): array
    {
        return $this->itensModel->where('liquidacao_id', $liquidacaoId)->findAll();
    }

    /** Soma o valor calculado dos itens (em centavos). */
    public function somarItens(int $liquidacaoId): int
    {
        $linha = $this->itensModel->selectSum('valor_calculado', 'total')
            ->where('liquidacao_id', $liquidacaoId)
            ->first();

        return (int) ($linha['total'] ?? 0);
    }

    /** Parcelas da liquidação, em ordem de número. */
    public function getPagamentosByLiquidacao(int $liquidacaoId): array
    {
        return $this->pagamentosModel->where('liquidacao_id', $liquidacaoId)->orderBy('numero_parcela')->findAll();
    }

    /** Remove as parcelas ainda não pagas (pendentes, vencidas e canceladas) para refazer o plano. */
    public function deletePagamentosNaoPagos(int $liquidacaoId): void
    {
        $this->pagamentosModel->where('liquidacao_id', $liquidacaoId)->where('status_pagamento !=', 'paga')->delete();
        $this->clearCache();
    }

    /** Parcelas pendentes com vencimento anterior a $hoje (ainda não marcadas como vencidas). */
    public function getPagamentosAtrasados(string $hoje): array
    {
        return $this->pagamentosModel->where('status_pagamento', 'pendente')->where('data_vencimento <', $hoje)->findAll();
    }

    /** Parcelas pendentes que vencem até $limite e ainda sem aviso de vencimento próximo. */
    public function getPagamentosAVencer(string $hoje, string $limite): array
    {
        return $this->pagamentosModel->where('status_pagamento', 'pendente')
            ->where('data_vencimento >=', $hoje)->where('data_vencimento <=', $limite)
            ->where('aviso_enviado_em IS NULL', null, false)->findAll();
    }

    /**
     * Resumo da liquidação mais recente do processo: valor atualizado e saldo a receber.
     * Sem checagem de acesso: quem chama já validou o acesso ao processo.
     *
     * @return array{liquidacao_id: int, status: string, tipo_liquidacao: ?string, data_base: ?string, calculado_em: ?string, valor_atualizado: int, total_pago: int, saldo_a_receber: int, proxima_parcela_vencimento: ?string}|null
     */
    public function resumoPorProcesso(int $processoId): ?array
    {
        $liquidacao = $this->model->where('processo_id', $processoId)->orderBy('liquidacao_id', 'DESC')->first();
        if (!$liquidacao) {
            return null;
        }

        $resumo = ResumoPagamentos::calcular(
            $this->pagamentosModel->where('liquidacao_id', $liquidacao['liquidacao_id'])->findAll(),
            (int) $liquidacao['valor_total'],
            date('Y-m-d')
        );

        return [
            'liquidacao_id'              => (int) $liquidacao['liquidacao_id'],
            'status'                     => (string) $liquidacao['status'],
            'tipo_liquidacao'            => $liquidacao['tipo_liquidacao'] ?? null,
            'data_base'                  => $liquidacao['data_base'] ?? null,
            'calculado_em'               => $liquidacao['calculado_em'] ?? null,
            'valor_atualizado'           => (int) $liquidacao['valor_total'],
            'total_pago'                 => $resumo['total_pago'],
            'saldo_a_receber'            => $resumo['saldo_a_receber'],
            'proxima_parcela_vencimento' => $resumo['proxima_parcela']['data_vencimento'] ?? null,
        ];
    }

    /**
     * Parcelas em aberto (pendentes ou vencidas) de liquidações em andamento, com o valor em centavos.
     * Alimentam a previsão de entradas do fluxo de caixa.
     *
     * @return list<array{data_vencimento: string, valor_parcela: int|string}>
     */
    public function getParcelasEmAberto(): array
    {
        return $this->pagamentosModel->select('data_vencimento, valor_parcela')
            ->whereIn('status_pagamento', ['pendente', 'vencida'])
            ->findAll();
    }

    /** Documentos vinculados a uma parcela (comprovantes). */
    public function getDocumentosPorPagamento(int $pagamentoId): array
    {
        return $this->documentosModel->where('pagamento_id', $pagamentoId)->findAll();
    }

    // Manage Payments
    public function createPagamento(array $data)
    {
        $id = $this->pagamentosModel->insert($data);
        if ($id) {
            $this->clearCache();
        }
        return $id;
    }

    public function updatePagamento($id, array $data)
    {
        $result = $this->pagamentosModel->update($id, $data);
        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    public function deletePagamento($id)
    {
        $result = $this->pagamentosModel->delete($id);
        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    // Manage Documents
    public function createDocumento(array $data)
    {
        $id = $this->documentosModel->insert($data);
        if ($id) {
            $this->clearCache();
        }
        return $id;
    }

    public function deleteDocumento($id)
    {
        $result = $this->documentosModel->delete($id);
        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    // History
    public function addHistorico(array $data)
    {
        $id = $this->historicoModel->insert($data);
        if ($id) {
            $this->clearCache();
        }
        return $id;
    }
}
