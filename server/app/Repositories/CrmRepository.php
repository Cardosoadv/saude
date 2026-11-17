<?php

namespace App\Repositories;

use App\Models\CrmOportunidadesModel;
use App\Models\CrmEtapasModel;
use App\Models\CrmInteracoesModel;
use App\Models\CrmOrigensModel;
use App\Repositories\Traits\EscopoPrivacidadeTrait;

/**
 * Repositório para gerenciamento de oportunidades e interações do CRM.
 *
 * @version 1.1.0
 */
class CrmRepository extends BaseRepository
{
    use EscopoPrivacidadeTrait;

    /**
     * @var CrmEtapasModel Model de etapas do CRM.
     */
    protected $etapasModel;

    /**
     * @var CrmInteracoesModel Model de interações do CRM.
     */
    protected $interacoesModel;

    /**
     * @var CrmOrigensModel Model de origens de oportunidades do CRM.
     */
    protected $origensModel;

    /**
     * Construtor do repositório CRM.
     *
     * @param CrmOportunidadesModel|null $model Instância do model de oportunidades.
     * @param CrmEtapasModel|null $etapasModel Instância do model de etapas.
     * @param CrmInteracoesModel|null $interacoesModel Instância do model de interações.
     * @param CrmOrigensModel|null $origensModel Instância do model de origens.
     */
    public function __construct(
        ?CrmOportunidadesModel $model = null,
        ?CrmEtapasModel $etapasModel = null,
        ?CrmInteracoesModel $interacoesModel = null,
        ?CrmOrigensModel $origensModel = null
    ) {
        parent::__construct();
        $this->model = $model ?? model('CrmOportunidadesModel');
        $this->etapasModel = $etapasModel ?? model('CrmEtapasModel');
        $this->interacoesModel = $interacoesModel ?? model('CrmInteracoesModel');
        $this->origensModel = $origensModel ?? model('CrmOrigensModel');
        $this->cacheKey = 'crm';
    }

    /**
     * Retorna todas as etapas do CRM ordenadas por ordem.
     *
     * @return array
     */
    public function getEtapas(): array
    {
        return $this->remember('etapas_funil', function () {
            return $this->etapasModel->orderBy('ordem', 'ASC')->findAll();
        });
    }

    /**
     * Retorna oportunidades filtradas por etapa.
     *
     * @param int $etapaId ID da etapa.
     * @return array
     */
    public function getOportunidadesPorEtapa(int $etapaId): array
    {
        return $this->remember("oportunidades_etapa_{$etapaId}" . $this->sufixoEscopo('cliente'), function () use ($etapaId) {
            // A oportunidade segue a privacidade do cliente.
            return $this->escopo($this->model, 'cliente', 'clientes')
                ->select('crm_oportunidades.*, clientes.nome as cliente_nome, clientes.documento')
                ->join('clientes', 'clientes.id_cliente = crm_oportunidades.cliente_id')
                ->where('crm_oportunidades.etapa_id', $etapaId)
                ->where('crm_oportunidades.status', 'aberto')
                ->findAll();
        });
    }

    /**
     * Busca todas as oportunidades em aberto com dados dos clientes.
     * Otimizado para o funil de vendas (evita N+1).
     *
     * @return array
     */
    public function getOportunidadesAbertas(): array
    {
        return $this->remember('oportunidades_abertas' . $this->sufixoEscopo('cliente'), function () {
            // A oportunidade segue a privacidade do cliente.
            return $this->escopo($this->model, 'cliente', 'clientes')
                ->select('crm_oportunidades.*, clientes.nome as cliente_nome, clientes.documento')
                ->join('clientes', 'clientes.id_cliente = crm_oportunidades.cliente_id')
                ->where('crm_oportunidades.status', 'aberto')
                ->findAll();
        });
    }

    /**
     * Retorna interações de um cliente.
     *
     * @param int $clienteId ID do cliente.
     * @param int $limit Limite de registros.
     * @return array
     */
    public function getInteracoesCliente(int $clienteId, int $limit = 10): array
    {
        return $this->remember("interacoes_cliente_{$clienteId}_lim_{$limit}", function () use ($clienteId, $limit) {
            return $this->interacoesModel
                ->where('cliente_id', $clienteId)
                ->orderBy('data_interacao', 'DESC')
                ->limit($limit)
                ->findAll();
        });
    }

    /**
     * Retorna todas as origens do CRM ordenadas por nome.
     *
     * @return array
     */
    public function getOrigens(): array
    {
        return $this->remember('origens_crm', function () {
            return $this->origensModel->orderBy('nome', 'ASC')->findAll();
        });
    }

    /**
     * Salva ou atualiza uma interação.
     *
     * @param array $data Dados da interação.
     * @return bool|int|string
     */
    public function salvarInteracao(array $data)
    {
        if (isset($data['id']) && is_numeric($data['id'])) {
            $id = (int)$data['id'];
            unset($data['id']);
            $result = $this->interacoesModel->update($id, $data);
        } else {
            $result = $this->interacoesModel->insert($data);
        }

        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    /**
     * Exclui uma interação.
     *
     * @param int $id ID da interação.
     * @return bool
     */
    public function excluirInteracao(int $id): bool
    {
        $result = (bool) $this->interacoesModel->delete($id);
        if ($result) {
            $this->clearCache();
        }
        return $result;
    }

    /**
     * Atualiza a etapa de uma oportunidade.
     *
     * @param int $oportunidadeId ID da oportunidade.
     * @param int $etapaId ID da etapa.
     * @return bool
     */
    public function atualizarEtapaOportunidade(int $oportunidadeId, int $etapaId): bool
    {
        return $this->update($oportunidadeId, ['etapa_id' => $etapaId]);
    }

    /**
     * Retorna métricas para o dashboard do CRM.
     *
     * @return array
     */
    public function getDashboardMetrics(): array
    {
        return $this->remember('dashboard_metrics', function () {
            // 1. Pipeline Total
            $pipelineQuery = $this->model
                ->selectSum('valor_estimado', 'total')
                ->selectCount('id', 'qtd')
                ->where('status', 'aberto')
                ->first();
            $totalPipelineValue = (float)($pipelineQuery['total'] ?? 0.0);
            $totalOpportunities = (int)($pipelineQuery['qtd'] ?? 0);

            // 2. Taxa de Conversão
            $dateLimit = date('Y-m-d H:i:s', strtotime('-30 days'));
            
            $ganhas30 = $this->model
                ->where('status', 'ganho')
                ->where('updated_at >=', $dateLimit)
                ->countAllResults();
                
            $perdidas30 = $this->model
                ->where('status', 'perdido')
                ->where('updated_at >=', $dateLimit)
                ->countAllResults();
                
            $totalClosed30 = $ganhas30 + $perdidas30;
            $conversionRate = 0.0;
            if ($totalClosed30 > 0) {
                $conversionRate = ($ganhas30 / $totalClosed30) * 100;
            } else {
                $ganhasGeral = $this->model->where('status', 'ganho')->countAllResults();
                $perdidasGeral = $this->model->where('status', 'perdido')->countAllResults();
                $totalClosedGeral = $ganhasGeral + $perdidasGeral;
                if ($totalClosedGeral > 0) {
                    $conversionRate = ($ganhasGeral / $totalClosedGeral) * 100;
                }
            }

            // 3. Novos Leads (mês atual)
            $firstDayMonth = date('Y-m-01 00:00:00');
            $newLeadsCount = $this->model
                ->where('created_at >=', $firstDayMonth)
                ->countAllResults();

            // 4. Ticket Médio (de oportunidades 'ganho')
            $avgQuery = $this->model
                ->selectAvg('valor_estimado', 'media')
                ->where('status', 'ganho')
                ->first();
            $averageTicket = (float)($avgQuery['media'] ?? 0.0);

            // 5. Origem dos Leads (distribuição)
            $origemData = $this->model
                ->select('COALESCE(crm_origens.nome, "Outro") as nome, COUNT(crm_oportunidades.id) as total')
                ->join('clientes', 'clientes.id_cliente = crm_oportunidades.cliente_id')
                ->join('crm_origens', 'crm_origens.id = clientes.origem_id', 'left')
                ->groupBy('nome')
                ->findAll();

            // 6. Atividades Recentes
            $recentActivities = $this->interacoesModel
                ->select('crm_interacoes.*, clientes.nome as cliente_nome')
                ->join('clientes', 'clientes.id_cliente = crm_interacoes.cliente_id')
                ->orderBy('crm_interacoes.data_interacao', 'DESC')
                ->limit(5)
                ->findAll();

            $recentActivitiesFormatted = array_map(function ($item) {
                $item['data_formatada'] = date('d/m/Y H:i', strtotime($item['data_interacao']));
                return $item;
            }, $recentActivities);

            return [
                'totalPipelineValue' => $totalPipelineValue,
                'totalOpportunities' => $totalOpportunities,
                'conversionRate'     => round($conversionRate, 1),
                'newLeadsCount'      => $newLeadsCount,
                'averageTicket'      => $averageTicket,
                'leadsSource'        => $origemData,
                'recentActivities'   => $recentActivitiesFormatted
            ];
        });
    }
}
