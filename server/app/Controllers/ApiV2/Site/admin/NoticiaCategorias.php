<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2\Site\admin;

use App\Controllers\BaseController;
use App\Services\Site\NoticiaCategoriaService;
use CodeIgniter\API\ResponseTrait;

/**
 * Categorias das notícias do site: `GET|POST /noticias/categorias`,
 * `GET|PUT|PATCH|DELETE /noticias/categorias/{id}`.
 */
class NoticiaCategorias extends BaseController
{
    use ResponseTrait;
    use RespondeErrosDoSiteTrait;

    public function __construct(private ?NoticiaCategoriaService $service = null)
    {
        $this->service ??= new NoticiaCategoriaService();
    }

    /** `?busca=` filtra por nome ou slug. */
    public function index()
    {
        return $this->executar(fn () => $this->respond([
            'data' => $this->service->listar($this->request->getGet('busca')),
        ]));
    }

    public function show($id)
    {
        return $this->executar(fn () => $this->respond(['data' => $this->service->obter((int) $id)]));
    }

    /** Corpo: `{name, slug?, meta_description?}`. */
    public function store()
    {
        return $this->executar(fn () => $this->respondCreated(['data' => $this->service->criar($this->entrada())]));
    }

    public function update($id)
    {
        return $this->executar(fn () => $this->respond(['data' => $this->service->atualizar((int) $id, $this->entrada())]));
    }

    public function destroy($id)
    {
        return $this->executar(function () use ($id) {
            $this->service->excluir((int) $id);

            return $this->respondDeleted(['id' => (int) $id, 'message' => 'Categoria excluída.']);
        });
    }
}
