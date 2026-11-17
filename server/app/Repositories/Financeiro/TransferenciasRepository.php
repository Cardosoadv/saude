<?php

declare(strict_types=1);

namespace App\Repositories\Financeiro;

use App\Models\Financeiro\FinanceiroTransferenciasModel;
use App\Repositories\BaseRepository;

/**
 * v1.1.0
 */
class TransferenciasRepository extends BaseRepository
{
    public function __construct(?FinanceiroTransferenciasModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('Financeiro/FinanceiroTransferenciasModel');
        $this->cacheKey = 'financeiro_transferencias';
    }

    /**
     * Sobrescreve clearCache para limpar também o cache de extratos.
     */
    public function clearCache(): void
    {
        parent::clearCache();
        (new ExtratoRepository())->clearCache();
    }

    public function findAll(int $limit = 0, int $offset = 0): array
    {
        return $this->model->orderBy('data_transferencia', 'DESC')->findAll($limit, $offset);
    }

    public function paginate(int $perPage = 20, string $group = 'default', ?int $page = null): array
    {
        $this->model->orderBy('data_transferencia', 'DESC');
        return parent::paginate($perPage, $group, $page);
    }
    /**
     * Retorna o saldo de entradas e saídas por transferência para um período e conta específicos.
     *
     * @param int $contaId
     * @param string $dataInicial
     * @param string $dataFinal
     * @return array ['in' => float, 'out' => float]
     */
    public function getSaldoPeriodoPorConta(int $contaId, string $dataInicial, string $dataFinal): array
    {
        $in = (float) $this->model->builder()
            ->selectSum('valor')
            ->where('id_conta_destino', $contaId)
            ->where('data_transferencia >=', $dataInicial)
            ->where('data_transferencia <=', $dataFinal)
            ->where('deleted_at IS NULL')
            ->get()->getRow()->valor ?? 0.0;

        $out = (float) $this->model->builder()
            ->selectSum('valor')
            ->where('id_conta_origem', $contaId)
            ->where('data_transferencia >=', $dataInicial)
            ->where('data_transferencia <=', $dataFinal)
            ->where('deleted_at IS NULL')
            ->get()->getRow()->valor ?? 0.0;

        return ['in' => $in, 'out' => $out];
    }
}
