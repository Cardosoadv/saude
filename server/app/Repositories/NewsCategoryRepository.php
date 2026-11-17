<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\NewsCategoryModel;

/**
 * Repositório para gerenciamento de categorias de notícias.
 *
 * @version 1.0.0
 */
class NewsCategoryRepository extends BaseRepository
{
    /**
     * Construtor do repositório de categorias de notícias.
     *
     * @param NewsCategoryModel|null $model Instância do model de categorias.
     */
    public function __construct(?NewsCategoryModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? new NewsCategoryModel();
        $this->cacheKey = 'news_categories';
    }

    /**
     * Retorna uma categoria pelo nome com cache.
     *
     * @param string $name Nome da categoria.
     * @return array|null
     */
    public function getByName(string $name): ?array
    {
        return $this->remember("name_{$name}", function () use ($name) {
            return $this->model->where('name', $name)->first();
        });
    }

    /**
     * Retorna uma categoria pelo slug com cache.
     *
     * @param string $slug Slug da categoria.
     * @return array|null
     */
    public function getBySlug(string $slug): ?array
    {
        return $this->remember("slug_{$slug}", function () use ($slug) {
            return $this->model->where('slug', $slug)->first();
        });
    }
}
