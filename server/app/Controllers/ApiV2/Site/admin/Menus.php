<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2\Site\admin;

use App\Controllers\BaseController;
use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Services\Site\SiteMenuService;
use CodeIgniter\API\ResponseTrait;

/**
 * Menus do site (issue #648): `GET /menus` e `PUT /menus/{local}` com a árvore inteira.
 */
class Menus extends BaseController
{
    use ResponseTrait;
    use RespondeErrosDoSiteTrait;

    public function __construct(private ?SiteMenuService $service = null)
    {
        $this->service ??= new SiteMenuService();
    }

    public function index()
    {
        return $this->executar(fn () => $this->respond(['data' => $this->service->listar()]));
    }

    /** Corpo: `{"itens": [...]}`. */
    public function update($local)
    {
        return $this->executar(function () use ($local) {
            $itens = $this->entrada()['itens'] ?? null;
            if (!is_array($itens)) {
                throw ErroDeValidacao::campo('itens', 'Informe a lista de itens do menu.');
            }
            $r = $this->service->substituir((string) $local, $itens);

            return $this->respond(['data' => $r['itens'], 'avisos' => $r['avisos']]);
        });
    }
}
