<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Site\SeoNoticias;
use App\Libraries\Site\SiteContexto;
use App\Libraries\Site\SiteRenderService;
use App\Services\NewsService;
use App\Services\Site\SiteCache;
use Config\SiteTemplate;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Site público. O conteúdo (páginas, seções, menus, textos) vem do banco e é
 * montado pelo template ativo (SiteRenderService); este controller só reúne
 * os dados de cada rota e devolve o HTML.
 */
class Site extends BaseController
{
    private NewsService $newsService;
    private ?SiteRenderService $renderService;

    public function __construct(
        NewsService $newsService = null,
        ?SiteRenderService $renderService = null
    ) {
        $this->renderService = $renderService;
        $this->newsService = $newsService ?? new NewsService();
    }

    private function render(): SiteRenderService
    {
        return $this->renderService ??= new SiteRenderService();
    }

    /**
     * Responde com o HTML, ou 404 se a página não existe, não está publicada
     * ou o site não tem template ativo. Prévia de rascunho e usuário logado
     * nunca recebem cache de navegador/proxy; `$noindex` (área e painel do cliente) acrescenta `X-Robots-Tag: noindex`; as demais respostas levam ETag e
     * `Cache-Control: public, max-age=0, must-revalidate` (304 se não mudou).
     */
    private function responder(?string $html, ?string $preview = null, bool $noindex = false)
    {
        // Instalação sem site público: o módulo "Site" desligado responde 404 em todo o site.
        if ($html === null || !service('modulos')->isActive('site')) {
            throw PageNotFoundException::forPageNotFound();
        }
        // A resposta já nasce com um Cache-Control restritivo do framework; ele é substituído, não somado.
        $this->response->removeHeader('Cache-Control');
        if ($noindex) {
            $this->response->setHeader('X-Robots-Tag', 'noindex');
        }
        if ($preview !== null) {
            return $this->response->setHeader('Cache-Control', 'no-store')->setHeader('X-Robots-Tag', 'noindex')->setBody($html);
        }
        if (auth()->loggedIn()) {
            return $this->response->setHeader('Cache-Control', 'private, no-cache')->setBody($html);
        }

        $etag = '"' . md5($html) . '"';
        $this->response->setHeader('ETag', $etag)->setHeader('Cache-Control', 'public, max-age=0, must-revalidate');
        if (trim((string) $this->request->getHeaderLine('If-None-Match')) === $etag) {
            return $this->response->setStatusCode(304)->setBody('');
        }

        return $this->response->setBody($html);
    }

    /**
     * HTML renderizado vindo do cache quando possível. Prévia e usuário logado
     * sempre renderizam do banco e não gravam no cache.
     */
    private function emCache(string $chave, ?string $preview, callable $renderizar): ?string
    {
        if ($preview !== null || auth()->loggedIn()) {
            return $renderizar();
        }
        if (($html = SiteCache::pagina($chave)) !== null) {
            return $html;
        }

        $html = $renderizar();
        if ($html !== null) {
            SiteCache::guardarPagina($chave, $html, config(SiteTemplate::class)->ttlPagina);
        }

        return $html;
    }

    public function index()
    {
        $preview = $this->request->getGet('preview');

        return $this->responder($this->emCache('home', $preview, fn () => $this->render()->renderHome($preview)), $preview);
    }

    public function pagina(string $slug)
    {
        $preview = $this->request->getGet('preview');

        return $this->responder($this->emCache($slug, $preview, fn () => $this->render()->renderPagina($slug, $preview)), $preview);
    }

    public function noticias()
    {
        $search = $this->request->getGet('search') ?? '';
        $page = (int) ($this->request->getGet('page') ?? 1);
        $limit = 9;

        $result = $this->newsService->buscarPublicadas($search, $page, $limit);

        return $this->responder($this->render()->renderTela('noticias_lista', 'noticias', [
            'searchQuery' => $search,
            'currentPage' => $page,
            'articles' => $result['data'] ?? [],
            'lastPage' => $result['last_page'] ?? 1,
            'total' => $result['total'] ?? 0,
        ], 'Artigos e Notícias'));
    }

    public function noticiaDetail(string $slug)
    {
        $article = $this->newsService->getBySlug($slug);

        if (!$article) {
            throw PageNotFoundException::forPageNotFound();
        }

        $related = $this->newsService->getLatestPublishedExcept($slug, 3);

        return $this->responder($this->render()->renderTela('noticias_artigo', 'noticias', [
            'article' => $article,
            'related' => $related,
        ], 'Artigos e Notícias', (new SeoNoticias(SiteContexto::atual()))->artigo($article)));
    }

    /** Foto de um membro da equipe marcado para aparecer em Quem Somos (as demais fotos continuam privadas). */
    public function fotoEquipe(int $id)
    {
        if (!service('modulos')->isActive('site')) {
            throw PageNotFoundException::forPageNotFound();
        }

        $caminho = (new \App\Services\UsuariosService())->getFotoEquipeSite($id);
        if ($caminho === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->response->removeHeader('Cache-Control');

        return $this->response
            ->setHeader('Cache-Control', 'public, max-age=3600')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setContentType((string) (new \CodeIgniter\Files\File($caminho))->getMimeType())
            ->setBody((string) file_get_contents($caminho));
    }

    public function areaCliente()
    {
        return $this->responder($this->emCache('tela:area-cliente', null, fn () => $this->render()->renderTela('area_cliente', 'area-cliente', [], 'Área do Cliente')), null, true);
    }

    public function painelCliente()
    {
        return $this->responder($this->emCache('tela:painel-cliente', null, fn () => $this->render()->renderTela('painel_cliente', 'painel-cliente', [], 'Painel do Cliente')), null, true);
    }
}
