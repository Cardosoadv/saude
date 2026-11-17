<?php

namespace App\Repositories\Contabil;

use App\Repositories\BaseRepository;
use App\Models\Contabil\PlanoContasModel;

/**
 * Repositório para o Plano de Contas.
 */
class PlanoContasRepository extends BaseRepository
{
    public function __construct(?PlanoContasModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('App\Models\Contabil\PlanoContasModel');
        $this->cacheKey = 'plano_contas';
    }
}
