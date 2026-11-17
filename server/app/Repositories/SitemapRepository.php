<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SitemapModel;

/**
 * Repositório para gerenciamento do sitemap.
 *
 * @version 1.0.0
 */
class SitemapRepository extends BaseRepository
{
    /**
     * Construtor do repositório de sitemap.
     *
     * @param SitemapModel|null $model Instância do model de sitemap.
     */
    public function __construct(?SitemapModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? new SitemapModel();
        $this->cacheKey = 'sitemap';
    }

    /**
     * Limpa todos os registros da tabela de sitemap.
     *
     * @return mixed
     */
    public function truncate(): mixed
    {
        return $this->model->truncate();
    }
}
