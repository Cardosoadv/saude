<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2\Site\admin;

use App\Controllers\BaseController;
use App\Services\Site\SiteTemaService;
use App\Services\Site\SiteTemplateService;
use CodeIgniter\API\ResponseTrait;

/**
 * Schema do template ativo (issue #648): layouts, locais de menu, tipos de seção com
 * seus campos, textos de interface e tokens de tema. A SPA monta os formulários com isto.
 */
class TemplateAtivo extends BaseController
{
    use ResponseTrait;
    use RespondeErrosDoSiteTrait;

    public function __construct(private ?SiteTemplateService $service = null, private ?SiteTemaService $tema = null)
    {
        $this->service ??= new SiteTemplateService();
        $this->tema    ??= new SiteTemaService();
    }

    public function schema()
    {
        return $this->executar(function () {
            $schema = $this->service->schema();

            return $schema === null ? $this->failNotFound('Não há template ativo.') : $this->respond(['data' => $schema]);
        });
    }

    /** Cores e fontes do template ativo: padrão × atual de cada token e as fontes permitidas. */
    public function tema()
    {
        return $this->executar(fn () => $this->respond(['data' => $this->tema->obter()]));
    }

    /** Corpo: `{"cores": {"gold": "#B08E35"}, "fontes": {"serif": "Lora"}}`; vazio restaura o padrão. */
    public function salvarTema()
    {
        return $this->executar(fn () => $this->respond(['data' => $this->tema->salvar($this->entrada())]));
    }

    /** Remove todas as personalizações de cor e fonte. */
    public function restaurarTema()
    {
        return $this->executar(fn () => $this->respond(['data' => $this->tema->restaurar()]));
    }
}
