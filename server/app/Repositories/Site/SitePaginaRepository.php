<?php

declare(strict_types=1);

namespace App\Repositories\Site;

use App\Models\Site\SitePaginaModel;
use App\Repositories\BaseRepository;

class SitePaginaRepository extends BaseRepository
{
    public function __construct(?SitePaginaModel $model = null)
    {
        parent::__construct();
        $this->model    = $model ?? new SitePaginaModel();
        $this->cacheKey = 'site_paginas';
    }

    public function getBySlug(string $slug): ?array
    {
        return $this->remember("slug_{$slug}", fn () => $this->model->where('slug', $slug)->first());
    }

    public function getHome(): ?array
    {
        return $this->remember('home', fn () => $this->model->where('tipo', 'home')->first());
    }

    /** Páginas publicadas, na ordem de exibição. */
    public function publicadas(): array
    {
        return $this->remember('publicadas', fn () => $this->model
            ->where('status', 'publicado')
            ->orderBy('ordem', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll());
    }
}
