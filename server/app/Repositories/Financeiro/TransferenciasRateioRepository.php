<?php

declare(strict_types=1);

namespace App\Repositories\Financeiro;

use App\Models\Financeiro\FinanceiroTransferenciasRateioModel;
use App\Repositories\BaseRepository;

/**
 * Repositório para gerenciar o rateio de transferências financeiras.
 * v1.0.0
 */
class TransferenciasRateioRepository extends BaseRepository
{
    protected $cacheKey = 'fin_transferencias_rateio';

    public function __construct(?FinanceiroTransferenciasRateioModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('Financeiro/FinanceiroTransferenciasRateioModel');
    }

    /**
     * Busca o rateio por ID de transferência.
     *
     * @param int $transferenciaId
     * @return array
     */
    public function buscarPorTransferencia(int $transferenciaId): array
    {
        return $this->remember("transf_{$transferenciaId}", function () use ($transferenciaId) {
            $data = $this->model->where('transferencia_id', $transferenciaId)->findAll();
            if (!empty($data)) {
                foreach ($data as &$row) {
                    $row['id'] = $row['user_id'];
                }
            }
            return $data;
        });
    }

    /**
     * Retorna o rateio líquido (entradas - saídas) agrupado por usuário para um período e conta específicos.
     *
     * @param int $contaId
     * @param string $dataInicial
     * @param string $dataFinal
     * @return array
     */
    public function getSaldoPeriodoPorConta(int $contaId, string $dataInicial, string $dataFinal): array
    {
        return $this->model->builder()
            ->select('fin_transferencias_rateios.user_id')
            ->select("SUM(CASE WHEN t.id_conta_destino = {$contaId} THEN fin_transferencias_rateios.valor ELSE -fin_transferencias_rateios.valor END) as valor", false)
            ->join('fin_transferencias t', 't.id_transferencia = fin_transferencias_rateios.transferencia_id')
            ->where("(t.id_conta_origem = {$contaId} OR t.id_conta_destino = {$contaId})")
            ->where('t.data_transferencia >=', $dataInicial)
            ->where('t.data_transferencia <=', $dataFinal)
            ->where('t.deleted_at IS NULL')
            ->groupBy('fin_transferencias_rateios.user_id')
            ->get()
            ->getResultArray();
    }
}
