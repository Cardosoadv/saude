<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\NewsModel;

/**
 * Repositório para gerenciamento de notícias.
 *
 * @version 1.1.1
 */
class NewsRepository extends BaseRepository
{
    /**
     * @var NewsCategoryRepository Repositório de categorias de notícias.
     */
    protected NewsCategoryRepository $newsCategoryRepository;

    /**
     * Construtor do repositório de notícias.
     *
     * @param NewsModel|null $model Instância do model de notícias.
     * @param NewsCategoryRepository|null $newsCategoryRepository Repositório de categorias de notícias.
     */
    public function __construct(
        ?NewsModel $model = null,
        ?NewsCategoryRepository $newsCategoryRepository = null
    ) {
        parent::__construct();
        $this->model = $model ?? new NewsModel();
        $this->cacheKey = 'news';
        $this->newsCategoryRepository = $newsCategoryRepository ?? new NewsCategoryRepository();
    }

    /**
     * Retorna notícias filtradas por categoria.
     *
     * @param int $categoryId ID da categoria.
     * @param int $limit Limite de registros.
     * @return array
     */
    public function getNewsByCategory(int $categoryId, int $limit = 10): array
    {
        return $this->remember("by_category_{$categoryId}_{$limit}", function () use ($categoryId, $limit) {
            return $this->model
                ->where('category_id', $categoryId)
                ->orderBy('published_at', 'desc')
                ->findAll($limit);
        });
    }

    /**
     * Retorna notícias filtradas por autor.
     *
     * @param int $authorId ID do autor.
     * @param int $limit Limite de registros.
     * @return array
     */
    public function getNewsByAuthor(int $authorId, int $limit = 10): array
    {
        return $this->remember("by_author_{$authorId}_{$limit}", function () use ($authorId, $limit) {
            return $this->model
                ->where('author_id', $authorId)
                ->orderBy('published_at', 'desc')
                ->findAll($limit);
        });
    }

    /**
     * Retorna notícias filtradas por status.
     *
     * @param string $status Status da notícia.
     * @param int $limit Limite de registros.
     * @return array
     */
    public function getNewsByStatus(string $status, int $limit = 10): array
    {
        return $this->remember("by_status_{$status}_{$limit}", function () use ($status, $limit) {
            return $this->model
                ->where('status', $status)
                ->orderBy('published_at', 'desc')
                ->findAll($limit);
        });
    }

    /**
     * Retorna notícias filtradas por status e categoria.
     *
     * @param string $status Status da notícia.
     * @param int $categoryId ID da categoria.
     * @param int $limit Limite de registros.
     * @return array
     */
    public function getNewsByStatusAndCategory(string $status, int $categoryId, int $limit = 10): array
    {
        return $this->remember("by_status_{$status}_cat_{$categoryId}_{$limit}", function () use ($status, $categoryId, $limit) {
            return $this->model
                ->where('status', $status)
                ->where('category_id', $categoryId)
                ->orderBy('published_at', 'desc')
                ->findAll($limit);
        });
    }

    /**
     * Retorna todas as categorias de notícias.
     *
     * @return array
     */
    public function getCategories(): array
    {
        return $this->newsCategoryRepository->getModel()->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Retorna uma categoria pelo ID.
     *
     * @param int $id ID da categoria.
     * @return array|null
     */
    public function getCategoryById(int $id): ?array
    {
        return $this->newsCategoryRepository->findById($id);
    }

    /**
     * Retorna uma categoria pelo nome.
     *
     * @param string $name Nome da categoria.
     * @return array|null
     */
    public function getCategoryByName(string $name): ?array
    {
        return $this->newsCategoryRepository->getByName($name);
    }

    /**
     * Retorna uma categoria pelo slug.
     *
     * @param string $slug Slug da categoria.
     * @return array|null
     */
    public function getCategoryBySlug(string $slug): ?array
    {
        return $this->newsCategoryRepository->getBySlug($slug);
    }

    /**
     * Busca uma notícia pelo slug.
     *
     * @param string $slug Slug da notícia.
     * @param string|array $select Campos a selecionar.
     * @param array $joins Joins adicionais.
     * @return array|null
     */
    public function findBySlug(string $slug, string|array $select = '*', array $joins = []): ?array
    {
        return $this->remember("slug_{$slug}", function () use ($slug, $select, $joins) {
            $builder = $this->model->select($select);

            foreach ($joins as $join) {
                $builder->join($join[0], $join[1], $join[2] ?? 'inner');
            }

            return $builder->where('site_news.slug', $slug)->first();
        });
    }

    /**
     * Retorna as últimas notícias publicadas excluindo um slug específico.
     *
     * @param string $slug Slug a excluir.
     * @param int $limit Limite de registros.
     * @return array
     */
    public function getLatestPublishedExcept(string $slug, int $limit = 3): array
    {
        return $this->remember("latest_except_{$slug}_{$limit}", function () use ($slug, $limit) {
            return $this->model
                ->select('id, title, slug, published_at')
                ->where('status', 'published')
                ->where('site_news.slug !=', $slug)
                ->orderBy('published_at', 'desc')
                ->findAll($limit);
        });
    }

    /**
     * Retorna registros com filtros genéricos e cache.
     *
     * @param string|array $select Campos a selecionar.
     * @param array $where Condições WHERE.
     * @param string|null $orderBy Campo de ordenação.
     * @param string|null $direction Direção da ordenação.
     * @param array $joins Joins adicionais.
     * @param int|null $limit Limite de registros.
     * @return array
     */
    public function getAll(
        string|array $select = '*',
        array $where = [],
        ?string $orderBy = null,
        ?string $direction = null,
        array $joins = [],
        ?int $limit = null
    ): array {
        $suffix = 'getAll_' . md5(serialize([$select, $where, $orderBy, $direction, $joins, $limit]));

        return $this->remember($suffix, function () use ($select, $where, $orderBy, $direction, $joins, $limit) {
            $builder = $this->model->select($select);

            foreach ($joins as $join) {
                $builder->join($join[0], $join[1], $join[2] ?? 'inner');
            }

            if (!empty($where)) {
                $builder->where($where);
            }

            if ($orderBy !== null) {
                $builder->orderBy($orderBy, $direction ?? 'ASC');
            }

            if ($limit !== null) {
                $builder->limit($limit);
            }

            return $builder->findAll();
        });
    }

    /**
     * Busca notícias publicadas com paginação e filtro por texto.
     *
     * @param string $search Termo de busca.
     * @param int $page Página atual.
     * @param int $limit Registros por página.
     * @return array Dados paginados com metadados.
     */
    public function buscarPublicadas(string $search = '', int $page = 1, int $limit = 12): array
    {
        $cacheKey = "publicadas_search_" . md5($search) . "_page_{$page}_limit_{$limit}";

        return $this->remember($cacheKey, function () use ($search, $page, $limit) {
            $builder = $this->model->where('status', 'published');

            if ($search !== '') {
                $builder = $builder
                    ->groupStart()
                        ->like('title', $search)
                        ->orLike('summary', $search)
                        ->orLike('content', $search)
                    ->groupEnd();
            }

            // Conta o total antes da paginação
            $total = $builder->countAllResults(false);

            $lastPage = (int) ceil($total / $limit);
            $offset = ($page - 1) * $limit;

            $data = $builder
                ->orderBy('published_at', 'desc')
                ->findAll($limit, $offset);

            return [
                'data'         => $data,
                'total'        => $total,
                'per_page'     => $limit,
                'current_page' => $page,
                'last_page'    => $lastPage,
            ];
        });
    }

    /**
     * Busca notícias para o painel de administração (incluindo rascunhos) com paginação e filtro por texto.
     *
     * @param string $search Termo de busca.
     * @param int $page Página atual.
     * @param int $limit Registros por página.
     * @return array Dados paginados com metadados.
     */
    public function buscarPainelAdmin(string $search = '', int $page = 1, int $limit = 20): array
    {
        $cacheKey = "admin_search_" . md5($search) . "_page_{$page}_limit_{$limit}";

        return $this->remember($cacheKey, function () use ($search, $page, $limit) {
            $builder = $this->model->select('site_news.id, site_news.title, site_news.slug, site_news.status, site_news.published_at, site_news.category_id, site_news_categories.name as category_name');
            $builder->join('site_news_categories', 'site_news.category_id = site_news_categories.id', 'left');

            if ($search !== '') {
                $builder = $builder
                    ->groupStart()
                        ->like('site_news.title', $search)
                        ->orLike('site_news.summary', $search)
                    ->groupEnd();
            }

            // Conta o total antes da paginação
            $total = $builder->countAllResults(false);

            $lastPage = (int) ceil($total / $limit);
            $offset = ($page - 1) * $limit;

            $data = $builder
                ->orderBy('site_news.id', 'desc')
                ->findAll($limit, $offset);

            return [
                'data'         => $data,
                'total'        => $total,
                'per_page'     => $limit,
                'current_page' => $page,
                'last_page'    => $lastPage,
            ];
        });
    }
}
