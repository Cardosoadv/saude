<?php

declare(strict_types=1);

namespace App\Repositories\Financeiro;

use App\Models\Financeiro\FinanceiroPagtoReceitasRateioModel;
use App\Repositories\BaseRepository;

/**
 * Repositório para gerenciar o rateio de pagamentos de receitas.
 * v1.1.1
 */
class PagamentoReceitasRateioRepository extends BaseRepository
{
    protected $cacheKey = 'fin_pgto_receitas_rateio';

    public function __construct(?FinanceiroPagtoReceitasRateioModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('Financeiro/FinanceiroPagtoReceitasRateioModel');
    }

    /**
     * Busca o rateio para múltiplos IDs de pagamento de receita (Batch Fetching).
     *
     * @param array $ids Array de IDs de pagamento.
     * @return array
     */
    public function buscarPorPagamentosBatch(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->model->whereIn('pagto_receita_id', $ids)->findAll();
    }

    /**
     * Busca o rateio por ID de pagamento de receita.
     *
     * @param int $pagtoReceitaId
     * @return array
     */
    public function buscarPorPagamento(int $pagtoReceitaId): array
    {
        return $this->remember("pagto_{$pagtoReceitaId}", function () use ($pagtoReceitaId) {
            $data = $this->model->where('pagto_receita_id', $pagtoReceitaId)->findAll();
            if (!empty($data)) {
                foreach ($data as &$row) {
                    $row['id'] = $row['user_id'];
                }
            }
            return $data;
        });
    }

    /**
     * Deleta o rateio de um pagamento.
     *
     * @param int $pagtoReceitaId
     * @return bool
     */
    public function deletarPorPagamento(int $pagtoReceitaId): bool
    {
        $result = $this->model->where('pagto_receita_id', $pagtoReceitaId)->delete();
        if ($result) {
            $this->clearCache();
        }
        return (bool) $result;
    }
    /**
     * Retorna o rateio agrupado por usuário para um período e conta específicos.
     *
     * @param int $contaId
     * @param string $dataInicial
     * @param string $dataFinal
     * @return array
     */
    public function getSaldoPeriodoPorConta(int $contaId, string $dataInicial, string $dataFinal): array
    {
        return $this->model->builder()
            ->select('fin_pgto_receitas_rateio.user_id')
            ->selectSum('fin_pgto_receitas_rateio.valor')
            ->join('fin_pgto_receitas', 'fin_pgto_receitas.id_pgto_receita = fin_pgto_receitas_rateio.pagto_receita_id')
            ->where('conta_id', $contaId)
            ->where('pagamento_receita_dt >=', $dataInicial)
            ->where('pagamento_receita_dt <=', $dataFinal)
            ->where('fin_pgto_receitas.deleted_at IS NULL')
            ->groupBy('fin_pgto_receitas_rateio.user_id')
            ->get()
            ->getResultArray();
    }
}
