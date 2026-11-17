<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\NewsRepository;

/**
 * Serviço para gerenciamento de notícias do site.
 *
 * @property NewsRepository $repository
 * @version 1.1.0
 */
class NewsService extends BaseService
{
    /**
     * Construtor com injeção de dependência.
     *
     * @param NewsRepository|null $repository
     */
    public function __construct(?NewsRepository $repository = null)
    {
        $this->repository = $repository ?? new NewsRepository();
        helper(['url', 'security']);
    }

    /**
     * Busca uma notícia pelo slug.
     *
     * @param string $slug
     * @return mixed
     */
    public function getBySlug(string $slug): mixed
    {
        return $this->repository->findBySlug(
            $slug,
            'site_news.*, site_news_categories.name as category_name',
            [
                ['site_news_categories', 'site_news.category_id = site_news_categories.id', 'left']
            ]
        );
    }

    /**
     * Retorna todas as categorias de notícias.
     *
     * @return array
     */
    public function getCategories(): array
    {
        return $this->repository->getCategories();
    }

    /**
     * Cria uma nova notícia com sanitização e geração automática de slug.
     *
     * @param array $data
     * @return int|string
     * @throws \Exception
     */
    public function createNews(array $data): int|string
    {
        helper(['url', 'security']);

        // Conteúdo HTML é permitido vindo do painel admin (WYSIWYG)

        // Gera slug automaticamente a partir do título se não fornecido
        if (empty($data['slug']) && !empty($data['title'])) {
            $data['slug'] = $this->makeSlug($data['title']);
        }

        // Atribui autor automaticamente se usando Shield
        if (empty($data['author_id']) && auth()->loggedIn()) {
            $data['author_id'] = auth()->id();
        }

        // Define published_at se publicando agora e sem data fornecida
        if (isset($data['status']) && $data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        } elseif (!empty($data['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s', strtotime($data['published_at']));
        }

        $id = $this->repository->create($data);

        if (!$id) {
            throw new \Exception('Erro ao criar a notícia. Verifique os dados e tente novamente.');
        }

        return $id;
    }

    /**
     * Atualiza uma notícia existente.
     *
     * @param int $id
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function updateNews(int $id, array $data): bool
    {
        helper(['url', 'security']);

        // Conteúdo HTML é permitido vindo do painel admin (WYSIWYG)

        // Atualiza slug se o título mudou e o slug não foi alterado manualmente
        if (empty($data['slug']) && !empty($data['title'])) {
            $data['slug'] = $this->makeSlug($data['title']);
        }

        // Formata ou define published_at
        if (isset($data['status']) && $data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        } elseif (!empty($data['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s', strtotime($data['published_at']));
        }

        $updated = $this->repository->update($id, $data);

        if (!$updated) {
            throw new \Exception('Erro ao atualizar a notícia.');
        }

        return $updated;
    }

    /**
     * Exclui uma notícia pelo ID.
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deleteNews(int $id): bool
    {
        $deleted = $this->repository->delete($id);

        if (!$deleted) {
            throw new \Exception('Erro ao excluir a notícia.');
        }

        return $deleted;
    }

    /**
     * Retorna as últimas notícias publicadas, exceto a do slug informado.
     *
     * @param string $slug
     * @param int $limit
     * @return array
     */
    public function getLatestPublishedExcept(string $slug, int $limit = 3): array
    {
        return $this->repository->getLatestPublishedExcept($slug, $limit);
    }

    /**
     * Retorna registros com filtros avançados.
     *
     * @param string|array $select
     * @param array $where
     * @param string|null $orderBy
     * @param string|null $direction
     * @param array $joins
     * @param int|null $limit
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
        return $this->repository->getAll($select, $where, $orderBy, $direction, $joins, $limit);
    }

    /**
     * Busca notícias publicadas com paginação.
     *
     * @param string $search
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function buscarPublicadas(string $search = '', int $page = 1, int $limit = 12): array
    {
        return $this->repository->buscarPublicadas($search, $page, $limit);
    }

    /**
     * Busca notícias para o painel de administração (inclui rascunhos) com paginação.
     *
     * @param string $search
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function buscarPainelAdmin(string $search = '', int $page = 1, int $limit = 20): array
    {
        return $this->repository->buscarPainelAdmin($search, $page, $limit);
    }

    /**
     * Busca uma notícia pelo ID.
     *
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Gera slug URL-seguro a partir de um título,
     * transliterando acentos e caracteres especiais para ASCII.
     *
     * @param string $title
     * @return string
     */
    private function makeSlug(string $title): string
    {
        // Transliterar caracteres acentuados para ASCII (ex: "ã" -> "a", "ê" -> "e")
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);

        // Fallback: remove caracteres não-ASCII se iconv falhar
        if ($ascii === false || $ascii === '') {
            $ascii = preg_replace('/[^\x00-\x7F]/u', '', $title);
        }

        return url_title($ascii, '-', true);
    }
}
