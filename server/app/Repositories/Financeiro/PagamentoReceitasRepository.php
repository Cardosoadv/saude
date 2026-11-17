<?php

declare(strict_types=1);

namespace App\Repositories\Financeiro;

use App\Models\Financeiro\FinanceiroContasModel; // For joins
use App\Models\Financeiro\FinanceiroPagtoReceitasModel;
use App\Models\Financeiro\FinanceiroReceitasModel; // For joins
use App\Repositories\BaseRepository;
use App\Repositories\Financeiro\PagamentoReceitasRateioRepository;

class PagamentoReceitasRepository extends BaseRepository
{
    /**
     * v1.2.0
     * @var string Chave de cache para o repositório.
     */
    protected $cacheKey = 'financeiro_pagto_receitas';

    protected PagamentoReceitasRateioRepository $rateioRepository;

    public function __construct(?FinanceiroPagtoReceitasModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('Financeiro/FinanceiroPagtoReceitasModel');
        $this->rateioRepository = new PagamentoReceitasRateioRepository();
    }

    /**
     * Sobrescreve clearCache para limpar também o cache de receitas.
     */
    public function clearCache(): void
    {
        parent::clearCache();
        (new ReceitasRepository())->clearCache();
    }

    /**
     * Recupera pagamentos recebidos com filtros.
     * Usado por: PagamentoReceitas::index
     */
    public function listarComFiltros(array $filtros = [], int $limit = 0, int $offset = 0): array
    {
        $filtrosKey = md5(serialize($filtros));

        return $this->remember("filtros_{$filtrosKey}_{$limit}_{$offset}", function () use ($filtros, $limit, $offset) {
            $builder = $this->model
            ->select('fin_pgto_receitas.*, fin_receitas.receita')
            ->join('fin_receitas', 'fin_receitas.id_receita = fin_pgto_receitas.receita_id');

        if (!empty($filtros['cliente_id'])) {
            $builder->where('fin_receitas.cliente_id', $filtros['cliente_id']);
        }

        if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
            $builder->where('fin_pgto_receitas.pagamento_receita_dt >=', $filtros['data_inicio'])
                ->where('fin_pgto_receitas.pagamento_receita_dt <=', $filtros['data_fim']);
        }

        if (!empty($filtros['conta_id'])) {
            $builder->where('fin_pgto_receitas.conta_id', $filtros['conta_id']);
        }

            $builder->orderBy('fin_pgto_receitas.pagamento_receita_dt', 'DESC');

            $data = $builder->findAll();

            $this->hidratarRateio($data);

            return $data;
        });
    }

    public function listarPaginado(array $filtros = [], int $perPage = 25, int $page = 1): array
    {
        $filtrosKey = md5(serialize($filtros));

        $consulta = function () use ($filtros, $perPage, $page) {
            $builder = $this->model
                ->select('fin_pgto_receitas.*, fin_receitas.receita, fin_contas.conta as conta_nome')
                ->join('fin_receitas', 'fin_receitas.id_receita = fin_pgto_receitas.receita_id', 'left')
                ->join('fin_contas', 'fin_contas.id_conta = fin_pgto_receitas.conta_id', 'left');

            if (!empty($filtros['search'])) {
                $search = $filtros['search'];
                $builder->groupStart()
                    ->like('fin_receitas.receita', $search)
                    ->orLike('fin_pgto_receitas.valor', $search)
                    ->orLike('fin_pgto_receitas.observacoes', $search)
                    ->groupEnd();
            }

            if (!empty($filtros['cliente_id'])) {
                $builder->where('fin_receitas.cliente_id', $filtros['cliente_id']);
            }

            if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
                $builder->where('fin_pgto_receitas.pagamento_receita_dt >=', $filtros['data_inicio'])
                    ->where('fin_pgto_receitas.pagamento_receita_dt <=', $filtros['data_fim']);
            }

            if (!empty($filtros['conta_id'])) {
                $builder->where('fin_pgto_receitas.conta_id', $filtros['conta_id']);
            }

            if (!empty($filtros['sem_comprovante'])) {
                $builder->where("NOT EXISTS (SELECT 1 FROM fin_pgto_comprovantes c WHERE c.tipo = 'receita' AND c.pagamento_id = fin_pgto_receitas.id_pgto_receita AND c.deleted_at IS NULL)", null, false);
            }

            if (!empty($filtros['categoria'])) {
                $subCategoria = $this->db->table('fin_receitas_categorias')
                    ->select('receita_id')
                    ->where('categoria_id', (int) $filtros['categoria'])
                    ->getCompiledSelect();
                $builder->where("fin_receitas.id_receita IN ($subCategoria)", null, false);
            }

            if (!empty($filtros['sort'])) {
                switch ($filtros['sort']) {
                    case 'data_asc':
                        $builder->orderBy('fin_pgto_receitas.pagamento_receita_dt', 'ASC');
                        break;
                    case 'valor_desc':
                        $builder->orderBy('fin_pgto_receitas.valor', 'DESC');
                        break;
                    case 'valor_asc':
                        $builder->orderBy('fin_pgto_receitas.valor', 'ASC');
                        break;
                    default:
                        $builder->orderBy('fin_pgto_receitas.pagamento_receita_dt', 'DESC');
                        break;
                }
            } else {
                $builder->orderBy('fin_pgto_receitas.pagamento_receita_dt', 'DESC');
            }

            $builder->where('fin_pgto_receitas.deleted_at IS NULL');

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
        return !empty($filtros['sem_comprovante'])
            ? $consulta()
            : $this->remember("filtros_paginado_v2_{$filtrosKey}_{$perPage}_{$page}", $consulta);
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

        $ids = array_column($data, 'id_pgto_receita');
        $allRateios = $this->rateioRepository->buscarPorPagamentosBatch($ids);

        $rateioMap = [];
        foreach ($allRateios as $rateio) {
            $rateioMap[$rateio['pagto_receita_id']][] = $rateio;
        }

        foreach ($data as &$row) {
            $row['rateio'] = $rateioMap[$row['id_pgto_receita']] ?? [];
        }
    }

    /**
     * Hidrata 'nome_categoria' (nomes das categorias do rateio da receita, concatenados)
     * para uma lista de pagamentos, em lote.
     *
     * @param array $data Referência para o array de pagamentos.
     */
    protected function hidratarNomeCategoria(array &$data): void
    {
        if (empty($data)) {
            return;
        }

        $receitaIds = array_unique(array_filter(array_column($data, 'receita_id')));

        if (empty($receitaIds)) {
            foreach ($data as &$row) {
                $row['nome_categoria'] = '';
            }
            return;
        }

        $rows = $this->db->table('fin_receitas_categorias as rc')
            ->select('rc.receita_id, cat.categoria as categoria_nome')
            ->join('fin_categorias as cat', 'cat.id_categoria = rc.categoria_id', 'left')
            ->whereIn('rc.receita_id', $receitaIds)
            ->get()
            ->getResultArray();

        $mapa = [];
        foreach ($rows as $row) {
            $mapa[$row['receita_id']][] = $row['categoria_nome'];
        }

        foreach ($data as &$row) {
            $row['nome_categoria'] = implode(', ', $mapa[$row['receita_id']] ?? []);
        }
    }

    /**
     * Sobrescreve para incluir rateio
     */
    public function findById(int $id): ?array
    {
        $builder = $this->model->builder()
            ->select('fin_pgto_receitas.*, fin_receitas.receita, clientes.nome as cliente_nome')
            ->join('fin_receitas', 'fin_receitas.id_receita = fin_pgto_receitas.receita_id', 'left')
            ->join('clientes', 'clientes.id_cliente = fin_receitas.cliente_id', 'left')
            ->where('fin_pgto_receitas.id_pgto_receita', $id)
            ->where('fin_pgto_receitas.deleted_at IS NULL');

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
     * Retorna pagamentos para exportação
     */
    public function findAllForExport(): array
    {
        return $this->remember('export', function () {
            return $this->model
            ->select('fin_pgto_receitas.*, fin_receitas.receita, fin_contas.conta')
            ->join('fin_receitas', 'fin_receitas.id_receita = fin_pgto_receitas.receita_id')
                ->join('fin_contas', 'fin_contas.id_conta = fin_pgto_receitas.conta_id', 'left')
                ->orderBy('pagamento_receita_dt', 'DESC')
                ->findAll();
        });
    }

    /**
     * Retorna pagamentos vencendo no periodo (recebidas)
     * (Moved/adapted from ReceitasRepository::getReceitasPorPeriodo logic which queried pgto_receitas)
     */
    public function getRecebimentosPorPeriodo(string $dataInicio, string $dataFim): array
    {
        return $this->remember("periodo_{$dataInicio}_{$dataFim}", function () use ($dataInicio, $dataFim) {
            $resultado = $this->model
            ->distinct()
            ->select('pr.id_pgto_receita as id, pr.pagamento_receita_dt as data, r.receita as descricao, c.nome as cliente_fornecedor, (pr.valor) as valor, r.id_receita as receita_id')
            ->from('fin_pgto_receitas as pr')
            ->where('pr.pagamento_receita_dt <=', $dataFim)
            ->where('pr.pagamento_receita_dt >=', $dataInicio)
            ->where('pr.deleted_at is null')
            ->join('fin_receitas as r', 'r.id_receita = pr.receita_id', 'left')
                ->join('clientes as c', 'c.id_cliente = r.cliente_id', 'left')
                ->findAll();

            $this->hidratarNomeCategoria($resultado);

            return $resultado;
        });
    }

    /**
     * Retorna ultimos recebimentos
     */
    public function getUltimosRecebimentos(int $limit = 5): array
    {
        return $this->remember("ultimos_{$limit}", function () use ($limit) {
            return $this->model
            ->select('fin_pgto_receitas.*, fin_receitas.receita')
            ->join('fin_receitas', 'fin_receitas.id_receita = fin_pgto_receitas.receita_id')
            ->orderBy('pagamento_receita_dt', 'DESC')
                ->limit($limit)
                ->findAll();
        });
    }

    /**
     * Obtém estatísticas de recebimentos para um mês específico.
     * Otimizado para usar agregados SQL (SUM, COUNT, AVG) em vez de carregar todos os registros.
     *
     * @param string $mesYYYYMM Formato 'YYYY-MM'
     * @return array
     */
    public function getEstatisticasMes(string $mesYYYYMM): array
    {
        return $this->remember("stats_{$mesYYYYMM}", function () use ($mesYYYYMM) {
            $primeiroDia = date($mesYYYYMM . '-01');
        $ultimoDia = date('Y-m-t', strtotime($primeiroDia));

        $result = $this->model
            ->select('SUM(valor) as total_recebido, COUNT(*) as count, AVG(valor) as media')
            ->where('pagamento_receita_dt >=', $primeiroDia)
            ->where('pagamento_receita_dt <=', $ultimoDia)
            ->where('deleted_at IS NULL')
            ->get()
                ->getRow();

            return [
                'total_recebido' => (float) ($result->total_recebido ?? 0.0),
                'count'          => (int) ($result->count ?? 0),
                'media'          => (float) ($result->media ?? 0.0),
            ];
        });
    }

    /**
     * Retorna o resumo de recebimentos agrupado por conta para um período.
     *
     * @param string $primeiroDia Data inicial YYYY-MM-DD
     * @param string $ultimoDia Data final YYYY-MM-DD
     * @return array
     */
    public function getResumoPorConta(string $primeiroDia, string $ultimoDia): array
    {
        return $this->remember("resumo_conta_{$primeiroDia}_{$ultimoDia}", function () use ($primeiroDia, $ultimoDia) {
            return $this->model
            ->select('fin_contas.conta as nome_conta, SUM(fin_pgto_receitas.valor) as total, COUNT(fin_pgto_receitas.id_pgto_receita) as quantidade')
            ->join('fin_contas', 'fin_contas.id_conta = fin_pgto_receitas.conta_id')
            ->where('fin_pgto_receitas.pagamento_receita_dt >=', $primeiroDia)
            ->where('fin_pgto_receitas.pagamento_receita_dt <=', $ultimoDia)
            ->where('fin_pgto_receitas.deleted_at IS NULL')
            ->groupBy('fin_pgto_receitas.conta_id')
            ->findAll();
        });
    }

    /**
     * Retorna o saldo total de receitas para um período e conta específicos.
     *
     * @param int $contaId
     * @param string $dataInicial
     * @param string $dataFinal
     * @return float
     */
    public function getSaldoPeriodoPorConta(int $contaId, string $dataInicial, string $dataFinal): float
    {
        return (float) $this->model->builder()
            ->selectSum('valor')
            ->where('conta_id', $contaId)
            ->where('pagamento_receita_dt >=', $dataInicial)
            ->where('pagamento_receita_dt <=', $dataFinal)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRow()->valor ?? 0.0;
    }

    public function findByReceitaId(int $receitaId): array {
        return $this->model->select('*')
        ->where('receita_id', $receitaId)
        ->where('deleted_at IS NULL')
        ->findAll();
    }
}
