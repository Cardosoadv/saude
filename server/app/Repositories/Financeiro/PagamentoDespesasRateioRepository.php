<?php

declare(strict_types=1);

namespace App\Repositories\Financeiro;

use App\Models\Financeiro\FinanceiroPagtoDespesasRateioModel;
use App\Repositories\BaseRepository;

/**
 * Repositório para gerenciar o rateio de pagamentos de despesas.
 * v1.0.2
 */
class PagamentoDespesasRateioRepository extends BaseRepository
{
    protected $cacheKey = 'fin_pgto_despesas_rateio';

    public function __construct(?FinanceiroPagtoDespesasRateioModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('Financeiro/FinanceiroPagtoDespesasRateioModel');
    }

    /**
     * Busca o rateio para múltiplos IDs de pagamento de despesa (Batch Fetching).
     *
     * @param array $ids Array de IDs de pagamento.
     * @return array
     */
    public function buscarPorPagamentosBatch(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->model->whereIn('pagto_despesa_id', $ids)->findAll();
    }

    /**
     * Busca o rateio por ID de pagamento de despesa.
     *
     * @param int $pagtoDespesaId
     * @return array
     */
    public function buscarPorPagamento(int $pagtoDespesaId): array
    {
        return $this->remember("pagto_{$pagtoDespesaId}", function () use ($pagtoDespesaId) {
            $data = $this->model->where('pagto_despesa_id', $pagtoDespesaId)->findAll();
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
     * @param int $pagtoDespesaId
     * @return bool
     */
    public function deletarPorPagamento(int $pagtoDespesaId): bool
    {
        $result = $this->model->where('pagto_despesa_id', $pagtoDespesaId)->delete();
        if ($result) {
            $this->clearCache();
        }
        return (bool) $result;
    }

        public function getSaldoPeriodoPorConta(int $contaId, string $dataInicial, string $dataFinal)
    {
        $despesasPagas = $this->model->builder()
                    ->select('fin_pgto_despesas_rateio.user_id')
                    ->selectSum('fin_pgto_despesas_rateio.valor')
                    ->join('fin_pgto_despesas', 'fin_pgto_despesas.id_pgto_despesa = fin_pgto_despesas_rateio.pagto_despesa_id')
                    ->where('conta_id', $contaId)
                    ->where('pagamento_despesa_dt >=', $dataInicial)
                    ->where('pagamento_despesa_dt <=', $dataFinal)
                    ->where('fin_pgto_despesas.deleted_at IS NULL')
                    ->groupBy('fin_pgto_despesas_rateio.user_id')
                    ->get()
                    ->getResultArray();
        
        
        
        
        
        return $despesasPagas;
    }
}
