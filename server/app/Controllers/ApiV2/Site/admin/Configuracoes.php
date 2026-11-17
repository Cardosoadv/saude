<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2\Site\admin;

use App\Controllers\BaseController;
use App\Services\Site\SiteConfiguracaoService;
use CodeIgniter\API\ResponseTrait;

/**
 * Configurações do site (issue #648): identidade, contato, SEO, integrações, textos…
 */
class Configuracoes extends BaseController
{
    use ResponseTrait;
    use RespondeErrosDoSiteTrait;

    public function __construct(private ?SiteConfiguracaoService $service = null)
    {
        $this->service ??= new SiteConfiguracaoService();
    }

    public function index()
    {
        return $this->executar(fn () => $this->respond(['data' => $this->service->agrupadas()]));
    }

    /** Corpo: `{"contato.telefone": "…", "seo.sufixo_titulo": "…"}`; só as chaves enviadas mudam. */
    public function update()
    {
        return $this->executar(fn () => $this->respond(['data' => $this->service->atualizar($this->entrada())]));
    }
}
