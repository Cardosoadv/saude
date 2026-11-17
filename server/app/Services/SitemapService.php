<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Site\AreasRestritas;
use App\Libraries\Site\UrlPagina;
use App\Repositories\SitemapRepository;
use App\Repositories\Site\SitePaginaRepository;
use App\Repositories\Site\SiteSecaoRepository;
use App\Services\NewsService;
use CodeIgniter\Cache\CacheInterface;

/**
 * Serviço para geração de sitemap do site.
 *
 * @version 1.1.0
 */
class SitemapService extends BaseService
{
    /**
     * Serviço de notícias para buscar URLs publicadas.
     */
    protected NewsService $newsService;

    /**
     * Cache para registrar tempo de geração do sitemap.
     */
    protected CacheInterface $cron;

    protected SitePaginaRepository $paginas;
    protected SiteSecaoRepository $secoes;

    /**
     * Construtor com injeção de dependência.
     *
     * @param SitemapRepository|null $repository
     * @param NewsService|null $newsService
     * @param CacheInterface|null $cache
     * @param SitePaginaRepository|null $paginas
     * @param SiteSecaoRepository|null $secoes
     */
    public function __construct(
        ?SitemapRepository $repository = null,
        ?NewsService $newsService = null,
        ?CacheInterface $cache = null,
        ?SitePaginaRepository $paginas = null,
        ?SiteSecaoRepository $secoes = null
    ) {
        $this->paginas = $paginas ?? new SitePaginaRepository();
        $this->secoes  = $secoes ?? new SiteSecaoRepository();
        $this->repository = $repository ?? new SitemapRepository();
        $this->newsService = $newsService ?? new NewsService();
        $this->cron = $cache ?? service('cache');
    }

    /**
     * Retorna os links do sitemap, utilizando cache.
     *
     * @return array
     */
    public function getSitemapLinks(): array
    {
        $sitemap = $this->cron->get('sitemap');
        if ($sitemap !== null) {
            return $sitemap;
        }
        $this->generateSitemap();
        $sitemap = $this->repository->getModel()
            ->select('url, last_modified, priority, changefreq')
            ->findAll();
        $this->cron->save('sitemap', $sitemap, 24 * 60 * 60);
        return $sitemap;
    }

    /**
     * Limpa todos os registros do sitemap.
     *
     * @return mixed
     */
    public function truncateSitemap(): mixed
    {
        return $this->repository->truncate();
    }

    /**
     * Adiciona um link ao sitemap.
     *
     * @param string $url
     * @param string $last_modified
     * @param string $priority
     * @param string $changefreq
     * @return int
     */
    public function addLink(string $url, string $last_modified, string $priority, string $changefreq): int
    {
        $data = [
            'url'           => $url,
            'last_modified' => $last_modified,
            'priority'      => $priority,
            'changefreq'    => $changefreq,
        ];
        return (int) $this->repository->create($data);
    }

    /**
     * Gera o sitemap com as páginas publicadas do site (`exibir_sitemap = 1`,
     * prioridade e frequência de cada página) e as notícias publicadas.
     * Âncoras (`#contato`) não entram: não são URLs.
     *
     * @return void
     */
    public function generateSitemap(): void
    {
        $this->truncateSitemap();

        $links = array_merge($this->linksDasPaginas(), $this->linksDasNoticias());

        if (!empty($links)) {
            $this->repository->insertBatch($links);
        }
    }

    /** @return list<array{url:string, last_modified:string, priority:string, changefreq:string}> */
    private function linksDasPaginas(): array
    {
        $atualizacoes = $this->secoes->ultimaAtualizacaoPorPagina();
        $links = [];

        foreach ($this->paginas->publicadas() as $pagina) {
            if (!(int) $pagina['exibir_sitemap'] || AreasRestritas::ehRestrita($pagina['slug'])) {
                continue;
            }

            $datas = array_filter([$pagina['updated_at'] ?? null, $atualizacoes[$pagina['id']] ?? null]);
            $links[] = [
                'url'           => UrlPagina::de($pagina),
                'last_modified' => $datas ? max($datas) : date('Y-m-d H:i:s'),
                'priority'      => number_format((float) $pagina['prioridade_sitemap'], 1, '.', ''),
                'changefreq'    => $pagina['changefreq'],
            ];
        }
        return $links;
    }

    /** @return list<array{url:string, last_modified:string, priority:string, changefreq:string}> */
    private function linksDasNoticias(): array
    {
        helper('site');
        $links = [];
        $noticias = $this->newsService->getAll(['slug', 'updated_at'], ['status' => 'published'], 'updated_at', 'desc');
        foreach ($noticias as $noticia) {
            $links[] = [
                'url'           => site_url_publica('noticias/' . $noticia['slug']),
                'last_modified' => $noticia['updated_at'],
                'priority'      => '0.8',
                'changefreq'    => 'monthly',
            ];
        }
        return $links;
    }
}
