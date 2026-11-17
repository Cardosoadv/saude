<?php

declare(strict_types=1);

namespace App\Repositories\Financeiro;

use App\Models\Financeiro\FinanceiroPagtoDespesasModel;
use App\Repositories\BaseRepository;
use App\Repositories\Financeiro\PagamentoDespesasRateioRepository;

/**
 * Repository for PagamentoDespesas
 * v1.1.2
 */
class PagamentoDespesasRepository extends BaseRepository
{
    /**
     * @var string Chave de cache para o repositório.
     */
    protected $cacheKey = 'financeiro_pagto_despesas';

    protected $beforeInsert = ['beforeInsert'];
    protected $beforeUpdate = ['beforeUpdate'];

    protected PagamentoDespesasRateioRepository $rateioRepository;

    public function __construct(?FinanceiroPagtoDespesasModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('Financeiro/FinanceiroPagtoDespesasModel');
        $this->rateioRepository = new PagamentoDespesasRateioRepository();
    }

    /**
     * Sobrescreve clearCache para limpar também o cache de despesas.
     */
    public function clearCache(): void
    {
        parent::clearCache();
        (new DespesasRepository())->clearCache();
    }

    /**
     * Busca pagamentos com filtros
     *
     * @param array $filters
     * @param int $perPage
     * @param int $page
     * @return array
     */
    public function findByFilters(array $filters, int $perPage = 25, int $page = 1): array
    {
        $filtrosKey = md5(serialize($filters));

        $consulta = function () use ($filters, $perPage, $page) {
            $builder = $this->model
            ->join('fin_despesas', 'fin_despesas.id_despesa = fin_pgto_despesas.despesa_id', 'left')
            ->join('fin_contas', 'fin_contas.id_conta = fin_pgto_despesas.conta_id', 'left')
            ->join('fin_fornecedores', 'fin_fornecedores.id_fornecedor = fin_despesas.fornecedor', 'left')
            ->select('fin_pgto_despesas.*, fin_despesas.despesa, fin_contas.conta as conta_nome, fin_fornecedores.nome as fornecedor_nome');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $builder->groupStart()
                ->like('fin_despesas.despesa', $search)
                ->orLike('fin_fornecedores.nome', $search)
                ->orLike('fin_pgto_despesas.valor', $search)
                ->orLike('fin_pgto_despesas.observacoes', $search)
                ->groupEnd();
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $builder->where('fin_pgto_despesas.pagamento_despesa_dt >=', $filters['start_date'])
                ->where('fin_pgto_despesas.pagamento_despesa_dt <=', $filters['end_date']);
        }

        if (!empty($filters['conta_id'])) {
            $builder->where('fin_pgto_despesas.conta_id', $filters['conta_id']);
        }

        if (!empty($filters['sem_comprovante'])) {
            $builder->where("NOT EXISTS (SELECT 1 FROM fin_pgto_comprovantes c WHERE c.tipo = 'despesa' AND c.pagamento_id = fin_pgto_despesas.id_pgto_despesa AND c.deleted_at IS NULL)", null, false);
        }

        if (!empty($filters['categoria'])) {
            $subCategoria = $this->db->table('fin_despesas_categorias')
                ->select('despesa_id')
                ->where('categoria_id', (int) $filters['categoria'])
                ->getCompiledSelect();
            $builder->where("fin_despesas.id_despesa IN ($subCategoria)", null, false);
        }

        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'data_asc':
                    $builder->orderBy('fin_pgto_despesas.pagamento_despesa_dt', 'ASC');
                    break;
                case 'valor_desc':
                    $builder->orderBy('fin_pgto_despesas.valor', 'DESC');
                    break;
                case 'valor_asc':
                    $builder->orderBy('fin_pgto_despesas.valor', 'ASC');
                    break;
                default:
                    $builder->orderBy('fin_pgto_despesas.pagamento_despesa_dt', 'DESC');
                    break;
            }
        } else {
            $builder->orderBy('fin_pgto_despesas.pagamento_despesa_dt', 'DESC');
        }

            $builder->where('fin_pgto_despesas.deleted_at IS NULL');

            $results = $builder->paginate($perPage, 'default', $page);
            $pager = $this->model->pager;

            $data = [
                'data'  => $results,
                'pager' => [
                    'current_page' => $pager ? (int)$pager->getCurrentPage() : (int)$page,
                    'last_page'    => $pager ? (int)$pager->getPageCount() : 1,
                    'total'        => $pager ? (int)$pager->getTotal() : count($results),
                    'per_page'     => $pager ? (int)$pager->getPerPage() : (int)$perPage,
                ],
            ];

            $this->hidratarRateio($data['data']);
            $this->hidratarNomeCategoria($data['data']);

            return $data;
        };

        // Pendências mudam a cada upload; ficam fora do cache da listagem.
        return !empty($filters['sem_comprovante'])
            ? $consulta()
            : $this->remember("filtros_{$filtrosKey}_{$perPage}_{$page}", $consulta);
    }

    /**
     * Hidrata os dados de rateio para uma lista de pagamentos (Batch Fetching).
     *
     * @param array $data Referência para o array de pagamentos.
     * @return void
     */
    protected function hidratarRateio(array &$data): void
    {
        if (empty($data)) {
            return;
        }

        $ids = array_column($data, 'id_pgto_despesa');
        $allRateios = $this->rateioRepository->buscarPorPagamentosBatch($ids);

        $rateioMap = [];
        foreach ($allRateios as $rateio) {
            $rateioMap[$rateio['pagto_despesa_id']][] = $rateio;
        }

        foreach ($data as &$row) {
            $row['rateio'] = $rateioMap[$row['id_pgto_despesa']] ?? [];
        }
    }

    /**
     * Hidrata 'nome_categoria' (nomes das categorias do rateio da despesa, concatenados)
     * para uma lista de pagamentos, em lote.
     *
     * @param array $data Referência para o array de pagamentos.
     */
    protected function hidratarNomeCategoria(array &$data): void
    {
        if (empty($data)) {
            return;
        }

        $despesaIds = array_unique(array_filter(array_column($data, 'despesa_id')));

        if (empty($despesaIds)) {
            foreach ($data as &$row) {
                $row['nome_categoria'] = '';
            }
            return;
        }

        $rows = $this->db->table('fin_despesas_categorias as dc')
            ->select('dc.despesa_id, cat.categoria as categoria_nome')
            ->join('fin_categorias as cat', 'cat.id_categoria = dc.categoria_id', 'left')
            ->whereIn('dc.despesa_id', $despesaIds)
            ->get()
            ->getResultArray();

        $mapa = [];
        foreach ($rows as $row) {
            $mapa[$row['despesa_id']][] = $row['categoria_nome'];
        }

        foreach ($data as &$row) {
            $row['nome_categoria'] = implode(', ', $mapa[$row['despesa_id']] ?? []);
        }
    }

    /**
     * Sobrescreve para incluir rateio
     */
    public function findById(int $id): ?array
    {
        $builder = $this->model->builder()
            ->select('fin_pgto_despesas.*, fin_despesas.despesa, fin_fornecedores.nome as fornecedor_nome')
            ->join('fin_despesas', 'fin_despesas.id_despesa = fin_pgto_despesas.despesa_id', 'left')
            ->join('fin_fornecedores', 'fin_fornecedores.id_fornecedor = fin_despesas.fornecedor', 'left')
            ->where('fin_pgto_despesas.id_pgto_despesa', $id)
            ->where('fin_pgto_despesas.deleted_at IS NULL');

        $data = (array)$builder->get()->getRowArray();

        if (!empty($data)) {
            $data['rateio'] = $this->rateioRepository->buscarPorPagamento($id);
            $linha = [$data];
            $this->hidratarNomeCategoria($linha);
            return $linha[0];
        }
        return null;
    }

    /**
     * Busca todos os pagamentos para exportação (sem paginação)
     *
     * @param array $filters
     * @return array
     */
    public function findAllForExport(array $filters): array
    {
        $filtrosKey = md5(serialize($filters));

        return $this->remember("export_{$filtrosKey}", function () use ($filters) {
            $builder = $this->model
            ->join('fin_despesas', 'fin_despesas.id_despesa = fin_pgto_despesas.despesa_id', 'left')
            ->join('fin_contas', 'fin_contas.id_conta = fin_pgto_despesas.conta_id', 'left')
            ->join('fin_fornecedores', 'fin_fornecedores.id_fornecedor = fin_despesas.fornecedor', 'left')
            ->select('fin_pgto_despesas.*, fin_despesas.despesa, fin_contas.conta as conta_nome, fin_fornecedores.nome as fornecedor_nome');

        // Reutilizar lógica de filtros (idealmente refatorar para trait ou método protected se ficar complexo)
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $builder->groupStart()
                ->like('fin_despesas.despesa', $search)
                ->orLike('fin_fornecedores.nome', $search)
                ->groupEnd();
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $builder->where('fin_pgto_despesas.pagamento_despesa_dt >=', $filters['start_date'])
                ->where('fin_pgto_despesas.pagamento_despesa_dt <=', $filters['end_date']);
        }

        if (!empty($filters['conta_id'])) {
            $builder->where('fin_pgto_despesas.conta_id', $filters['conta_id']);
        }

            $builder->where('fin_pgto_despesas.deleted_at IS NULL')
                ->orderBy('fin_pgto_despesas.pagamento_despesa_dt', 'DESC');

            return $builder->findAll();
        });
    }

    /**
     * Obtém estatísticas de pagamentos de despesas para um mês específico.
     * Otimizado para usar agregados SQL (SUM, COUNT, AVG) em vez de carregar todos os registros.
     *
     * @param string $mesYYYYMM Formato 'YYYY-MM'
     * @return array
     */
    public function getEstatisticasMes(string $mesYYYYMM): array
    {
        return $this->remember("stats_{$mesYYYYMM}", function () use ($mesYYYYMM) {
            $primeiroDia = date($mesYYYYMM . '-01');
        $proximoMes = date('Y-m-d', strtotime('+1 month', strtotime($primeiroDia)));

        $result = $this->model->builder()
            ->select('SUM(valor) as total_pago, COUNT(*) as count, AVG(valor) as media')
            ->where('pagamento_despesa_dt >=', $primeiroDia)
            ->where('pagamento_despesa_dt <', $proximoMes)
            ->where('deleted_at IS NULL')
            ->get()
                ->getRow();

            return [
                'total_pago' => (float) ($result->total_pago ?? 0.0),
                'count'      => (int) ($result->count ?? 0),
                'media'      => (float) ($result->media ?? 0.0),
            ];
        });
    }

    /**
     * Retorna o resumo de pagamentos de despesas agrupado por conta para um período.
     * Otimizado para evitar N+1 queries.
     *
     * @param string $primeiroDia Data inicial YYYY-MM-DD
     * @param string $ultimoDia Data final YYYY-MM-DD
     * @return array
     */
    public function getResumoPorConta(string $primeiroDia, string $proximoMes): array
    {
        return $this->remember("resumo_conta_{$primeiroDia}_{$proximoMes}", function () use ($primeiroDia, $proximoMes) {
            return $this->model->builder()
            ->select('fin_contas.conta as nome_conta, fin_contas.id_conta, SUM(valor) as total, COUNT(id_pgto_despesa) as quantidade')
            ->join('fin_contas', 'fin_contas.id_conta = fin_pgto_despesas.conta_id')
            ->where('fin_pgto_despesas.pagamento_despesa_dt >=', $primeiroDia)
            ->where('fin_pgto_despesas.pagamento_despesa_dt <', $proximoMes)
            ->where('fin_pgto_despesas.deleted_at IS NULL')
                ->groupBy('fin_contas.id_conta, fin_contas.conta')
                ->get()
                ->getResultArray();
        });
    }

    public function getSaldoPeriodoPorConta(int $contaId, string $dataInicial, string $dataFinal)
    {
        return (float) $this->model->builder()
            ->select('SUM(valor) as total')
            ->where('conta_id', $contaId)
            ->where('pagamento_despesa_dt >=', $dataInicial)
            ->where('pagamento_despesa_dt <=', $dataFinal)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRow()
            ->total ?? 0.0;
    }

    public function findByDespesaId(int $despesaId): ?array
    {
        return $this->model->builder()
            ->select('*')
            ->where('despesa_id', $despesaId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();
    }

    /**
     * Deleta múltiplos registros em uma única query e limpa o cache.
     *
     * @param array $ids Array de IDs de pagamento.
     * @return int Número de registros deletados.
     */
    public function deleteBatch(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $result = $this->model->whereIn($this->model->primaryKey, $ids)->delete();

        if ($result) {
            $affectedRows = $this->db->affectedRows();
            $this->clearCache();
            return $affectedRows;
        }

        return 0;
    }
}
