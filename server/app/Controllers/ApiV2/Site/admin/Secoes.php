<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2\Site\admin;

use App\Controllers\BaseController;
use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Services\Site\SiteSecaoService;
use CodeIgniter\API\ResponseTrait;

/**
 * Seções de uma página (issue #648). `dados` é validado pelo schema do tipo (422 por campo).
 */
class Secoes extends BaseController
{
    use ResponseTrait;
    use RespondeErrosDoSiteTrait;

    public function __construct(private ?SiteSecaoService $service = null)
    {
        $this->service ??= new SiteSecaoService();
    }

    public function store($paginaId)
    {
        return $this->executar(function () use ($paginaId) {
            $r = $this->service->criar((int) $paginaId, $this->entrada());

            return $this->respondCreated(['data' => $r['secao'], 'avisos' => $r['avisos']]);
        });
    }

    public function update($id)
    {
        return $this->executar(function () use ($id) {
            $r = $this->service->atualizar((int) $id, $this->entrada());

            return $this->respond(['data' => $r['secao'], 'avisos' => $r['avisos']]);
        });
    }

    public function destroy($id)
    {
        return $this->executar(function () use ($id) {
            $this->service->excluir((int) $id);

            return $this->respondDeleted(['id' => (int) $id, 'message' => 'Seção excluída.']);
        });
    }

    /** Corpo: `{"ids": [3, 1, 2]}`. */
    public function ordem($paginaId)
    {
        return $this->executar(function () use ($paginaId) {
            $ids = $this->entrada()['ids'] ?? null;
            if (!is_array($ids) || !array_is_list($ids)) {
                throw ErroDeValidacao::campo('ids', 'Informe a lista de ids das seções, na nova ordem.');
            }

            return $this->respond(['data' => $this->service->reordenar((int) $paginaId, $ids)]);
        });
    }
}
