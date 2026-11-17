<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2\Site;

use App\Controllers\BaseController;
use App\Libraries\Site\RobotsTxt;
use App\Services\Site\SiteConfiguracaoService;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * GET /robots.txt na raiz do site. Dinâmico para usar a URL pública correta
 * no `Sitemap:` e aceitar regras extras do CMS (`seo.robots_extra`).
 */
class Robots extends BaseController
{
    public function __construct(private ?SiteConfiguracaoService $configuracoes = null)
    {
    }

    public function index()
    {
        // Sem o módulo "Site", o site público responde 404 (igual ao Site::responder).
        if (!service('modulos')->isActive('site')) {
            throw PageNotFoundException::forPageNotFound();
        }

        helper('site');
        $extra = (string) ($this->configuracoes ??= new SiteConfiguracaoService())->obter('seo.robots_extra', '');

        return $this->response
            ->setContentType('text/plain', 'utf-8')
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setBody(RobotsTxt::gerar(site_url_publica('sitemap.xml'), $extra));
    }
}
