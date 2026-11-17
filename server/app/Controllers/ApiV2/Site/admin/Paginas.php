<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2\Site\admin;

use App\Controllers\BaseController;
use App\Services\Site\SitePaginaService;
use CodeIgniter\API\ResponseTrait;

/**
 * Páginas do site (issue #648). Criada como rascunho; publicar/despublicar são ações à parte.
 */
class Paginas extends BaseController
{
    use ResponseTrait;
    use RespondeErrosDoSiteTrait;

    public function __construct(private ?SitePaginaService $service = null)
    {
        $this->service ??= new SitePaginaService();
    }

    public function index()
    {
        return $this->executar(fn () => $this->respond(['data' => $this->service->listar([
            'status' => $this->request->getGet('status'),
            'tipo'   => $this->request->getGet('tipo'),
            'busca'  => $this->request->getGet('busca'),
        ])]));
    }

    public function show($id)
    {
        return $this->executar(fn () => $this->respond(['data' => $this->service->obter((int) $id)]));
    }

    public function store()
    {
        return $this->executar(fn () => $this->respondCreated(['data' => $this->service->criar($this->entrada())]));
    }

    public function update($id)
    {
        return $this->executar(function () use ($id) {
            $r = $this->service->atualizar((int) $id, $this->entrada());

            return $this->respond(['data' => $r['pagina'], 'avisos' => $r['avisos']]);
        });
    }

    public function destroy($id)
    {
        return $this->executar(function () use ($id) {
            $this->service->excluir((int) $id);

            return $this->respondDeleted(['id' => (int) $id, 'message' => 'Página excluída.']);
        });
    }

    public function publicar($id)
    {
        return $this->executar(function () use ($id) {
            $r = $this->service->publicar((int) $id);

            return $this->respond(['data' => $r['pagina'], 'avisos' => $r['avisos']]);
        });
    }

    public function despublicar($id)
    {
        return $this->executar(fn () => $this->respond(['data' => $this->service->despublicar((int) $id)]));
    }

    public function duplicar($id)
    {
        return $this->executar(fn () => $this->respondCreated(['data' => $this->service->duplicar((int) $id)]));
    }

    public function previewToken($id)
    {
        return $this->executar(fn () => $this->respond(['data' => $this->service->previewToken((int) $id)]));
    }
}
