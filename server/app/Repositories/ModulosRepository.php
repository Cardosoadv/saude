<?php

namespace App\Repositories;

use App\Models\ModulosModel;

class ModulosRepository extends BaseRepository
{
    public function __construct(?ModulosModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('ModulosModel');
        $this->cacheKey = 'modulos';
    }

    public function getBySlug(string $slug): ?array
    {
        return $this->remember("slug_{$slug}", function () use ($slug) {
            return $this->model->where('slug', $slug)->first();
        });
    }

    public function listarOrdenados(): array
    {
        return $this->remember('ordenados', function () {
            return $this->model->orderBy('nome', 'asc')->findAll();
        });
    }

    public function contarModulos(): int
    {
        return (int) $this->remember('total', function () {
            return $this->model->countAllResults();
        });
    }

    public function getActiveModules(): array
    {
        return $this->remember('ativos', function () {
            return $this->model->where('ativo', 1)->findAll();
        });
    }
}
