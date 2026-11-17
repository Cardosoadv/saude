<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2\Site\admin;

use App\Controllers\BaseController;
use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Services\Site\SiteMidiaService;
use CodeIgniter\API\ResponseTrait;

/**
 * Biblioteca de mídias do site (issue #649). Envio em multipart, campo `arquivo`.
 */
class Midias extends BaseController
{
    use ResponseTrait;
    use RespondeErrosDoSiteTrait;

    public function __construct(private ?SiteMidiaService $service = null)
    {
        $this->service ??= new SiteMidiaService();
    }

    public function index()
    {
        return $this->executar(fn () => $this->respond($this->service->listar(
            (int) ($this->request->getGet('page') ?? 1),
            (int) ($this->request->getGet('per_page') ?? 24),
            $this->request->getGet('busca'),
        )));
    }

    public function show($id)
    {
        return $this->executar(fn () => $this->respond(['data' => $this->service->obter((int) $id)]));
    }

    /** Multipart: `arquivo` (obrigatório) e `alt` (opcional). */
    public function store()
    {
        return $this->executar(function () {
            $arquivo = $this->request->getFile('arquivo');
            if ($arquivo === null) {
                throw ErroDeValidacao::campo('arquivo', 'Envie a imagem no campo "arquivo" (multipart).');
            }
            if (!$arquivo->isValid()) {
                throw ErroDeValidacao::campo('arquivo', 'Falha no envio: ' . $arquivo->getErrorString() . '.');
            }

            return $this->respondCreated(['data' => $this->service->enviar(
                $arquivo->getTempName(),
                $arquivo->getClientName(),
                $this->request->getPost('alt'),
            )]);
        });
    }

    /** Só o texto alternativo muda: `{"alt": "..."}`. */
    public function update($id)
    {
        return $this->executar(fn () => $this->respond(['data' => $this->service->atualizarAlt((int) $id, $this->entrada()['alt'] ?? null)]));
    }

    public function usos($id)
    {
        return $this->executar(fn () => $this->respond(['data' => $this->service->usos((int) $id)]));
    }

    public function destroy($id)
    {
        return $this->executar(function () use ($id) {
            $this->service->excluir((int) $id);

            return $this->respondDeleted(['id' => (int) $id, 'message' => 'Mídia excluída.']);
        });
    }
}
