<?php

declare(strict_types=1);

namespace App\Repositories\Financeiro;

use App\Models\Financeiro\FinanceiroContasModel;
use App\Repositories\BaseRepository;

/**
 * ContasRepository
 *
 * @version 1.0.0 Beta
 * @since 2026-01-03
 */
class ContasRepository extends BaseRepository
{
    public function __construct(?FinanceiroContasModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('Financeiro/FinanceiroContasModel');
        $this->cacheKey = 'financeiro_contas';
    }

    /**
     * Retorna todas as contas ordenadas por nome com cache.
     *
     * @return array
     */
    public function listarContasOrdenadas(): array
    {
        return $this->remember('all_ordered', function () {
            return $this->model->orderBy('conta')->findAll();
        });
    }
}
