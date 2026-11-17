<?php

namespace App\Repositories;

use App\Models\PrazoEstrategicoModel;

/**
 * Repository para gerenciar o acesso aos dados de Prazos Estratégicos.
 * Responsável por abstrair a lógica de acesso ao banco de dados e gerenciar cache.
 */
class PrazoEstrategicoRepository extends BaseRepository
{
    public function __construct(?PrazoEstrategicoModel $model = null)
    {
        parent::__construct();
        $this->model    = $model ?? model('PrazoEstrategicoModel');
        $this->cacheKey = 'prazos_estrategicos';
    }

    /**
     * Lista prazos estratégicos ativos.
     *
     * @return array
     */
    public function findAtivos(): array
    {
        return $this->remember('ativos', function () {
            return $this->model->where('status', 'Ativo')->findAll();
        });
    }
}
