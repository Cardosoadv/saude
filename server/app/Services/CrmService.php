<?php

namespace App\Services;

use App\Repositories\CrmRepository;

/**
 * Serviço para gerenciamento de oportunidades e interações do CRM.
 *
 * @version 1.1.0
 */
class CrmService extends BaseService
{
    /**
     * Construtor com injeção de dependência.
     *
     * @param CrmRepository|null $repository Instância do repositório CRM.
     */
    public function __construct(?CrmRepository $repository = null)
    {
        $this->repository = $repository ?? new CrmRepository();
    }

    /**
     * Retorna o pipeline do CRM.
     *
     * @return array
     */
    public function getPipeline(): array
    {
        $etapas = $this->repository->getEtapas();
        $oportunidadesAbertas = $this->repository->getOportunidadesAbertas();

        $pipeline = [];

        // Agrupa oportunidades por etapa em memória (O(1) database queries)
        $oportunidadesPorEtapa = [];
        foreach ($oportunidadesAbertas as $oportunidade) {
            $etapaId = (int)$oportunidade['etapa_id'];
            if (!isset($oportunidadesPorEtapa[$etapaId])) {
                $oportunidadesPorEtapa[$etapaId] = [];
            }
            $oportunidadesPorEtapa[$etapaId][] = $oportunidade;
        }

        foreach ($etapas as $etapa) {
            $oportunidades = $oportunidadesPorEtapa[(int)$etapa['id']] ?? [];
            $valorTotal = array_sum(array_column($oportunidades, 'valor_estimado'));

            $pipeline[] = [
                'etapa' => $etapa,
                'oportunidades' => $oportunidades,
                'total_valor' => $valorTotal,
                'count' => count($oportunidades)
            ];
        }

        return $pipeline;
    }

    /**
     * Move uma oportunidade para uma nova etapa.
     *
     * @param int $oportunidadeId ID da oportunidade.
     * @param int $etapaId ID da etapa.
     * @return bool
     */
    public function moverOportunidade(int $oportunidadeId, int $etapaId): bool
    {
        return $this->repository->atualizarEtapaOportunidade($oportunidadeId, $etapaId);
    }

    /**
     * Registra uma nova interação.
     *
     * @param array $data Dados da interação.
     * @return bool|int|string
     */
    public function registrarInteracao(array $data)
    {
        if (empty($data['data_interacao'])) {
            $data['data_interacao'] = date('Y-m-d H:i:s');
        }
        return $this->repository->salvarInteracao($data);
    }

    /**
     * Exclui uma interação pelo ID.
     *
     * @param int $id ID da interação.
     * @return bool
     */
    public function excluirInteracao(int $id): bool
    {
        return $this->repository->excluirInteracao($id);
    }

    /**
     * Obtém a timeline de interações de um cliente.
     *
     * @param int $clienteId ID do cliente.
     * @return array
     */
    public function obterTimelineCliente(int $clienteId): array
    {
        $interacoes = $this->repository->getInteracoesCliente($clienteId);

        // Formata para exibição
        return array_map(function ($item) {
            $item['data_formatada'] = date('d/m/Y H:i', strtotime($item['data_interacao']));
            return $item;
        }, $interacoes);
    }

    /**
     * Salva ou atualiza uma oportunidade.
     *
     * @param array $data Dados da oportunidade.
     * @param int|null $id ID da oportunidade (opcional).
     * @return bool|int|string
     */
    public function salvarOportunidade(array $data, ?int $id = null)
    {
        if ($id) {
            return $this->update($id, $data);
        }
        return $this->create($data);
    }

    /**
     * Retorna todas as origens do CRM.
     *
     * @return array
     */
    public function obterOrigens(): array
    {
        return $this->repository->getOrigens();
    }

    /**
     * Retorna todas as etapas do CRM.
     *
     * @return array
     */
    public function obterEtapas(): array
    {
        return $this->repository->getEtapas();
    }

    /**
     * Retorna os dados para o dashboard do CRM.
     *
     * @return array
     */
    public function obterDadosDashboard(): array
    {
        return $this->repository->getDashboardMetrics();
    }
}
