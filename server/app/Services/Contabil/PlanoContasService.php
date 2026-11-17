<?php

namespace App\Services\Contabil;

use App\Services\BaseService;
use App\Repositories\Contabil\PlanoContasRepository;

/**
 * Serviço para o Plano de Contas.
 */
class PlanoContasService extends BaseService
{
    public function __construct(?PlanoContasRepository $repository = null)
    {
        $this->repository = $repository ?? new PlanoContasRepository();
    }
}
