<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2\Site;

use App\Controllers\BaseController;
use App\Services\Site\LlmsTxtService;
use App\Services\Site\SiteCache;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\SiteTemplate;

/**
 * GET /llms.txt na raiz do site: resumo do escritório em markdown para assistentes de IA.
 * O texto fica no cache das páginas do site e é descartado junto com o sitemap, sempre que o conteúdo muda.
 */
class LlmsTxt extends BaseController
{
    public function __construct(private ?LlmsTxtService $servico = null)
    {
    }

    public function index()
    {
        return $this->servir('arquivo:llms.txt', fn () => ($this->servico ??= new LlmsTxtService())->gerar());
    }

    /**
     * Resposta markdown vinda do cache (ou gerada e guardada).
     *
     * @param callable():string $gerar
     */
    protected function servir(string $chaveDeCache, callable $gerar)
    {
        // Sem o módulo "Site", o site público responde 404 (igual ao Site::responder).
        if (!service('modulos')->isActive('site')) {
            throw PageNotFoundException::forPageNotFound();
        }

        $texto = SiteCache::pagina($chaveDeCache);
        if ($texto === null) {
            $texto = $gerar();
            SiteCache::guardarPagina($chaveDeCache, $texto, config(SiteTemplate::class)->ttlPagina);
        }

        return $this->response
            ->setContentType('text/markdown', 'utf-8')
            ->setHeader('Cache-Control', 'public, max-age=3600')
            ->setBody($texto);
    }
}
