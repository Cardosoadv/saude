<?php

namespace App\Controllers\ApiV2\Financeiro;

use App\Controllers\BaseController;
use App\Services\Financeiro\CategoriasService;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

class Categorias extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;

    protected CategoriasService $service;
    private string $permissaoNecessaria = 'financeiro';

    public function __construct(?CategoriasService $service = null)
    {
        $this->service = $service ?? new CategoriasService();
    }

    public function listar(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        try {
            $s = $this->request->getGet('s') ?? '';
            $categorias = !empty($s) ? $this->service->listarArvore($s) : $this->service->listarArvore();
            return $this->respond([
                'success' => true,
                'data'    => $categorias
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Categorias::listar] ' . $e->getMessage());
            return $this->respond(['success' => false, 'message' => 'Erro ao listar categorias.'], 500);
        }
    }

    public function salvar(): ResponseInterface
    {
        $data = $this->request->getPost();
        $id = isset($data['id_categoria']) && is_numeric($data['id_categoria']) ? (int)$data['id_categoria'] : null;
        $action = $id ? 'edit' : 'create';

        if (!$this->verificaPermissao($this->permissaoNecessaria, $action)) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        $rules = [
            'categoria' => 'required|max_length[255]',
        ];

        if (empty($data['categoria_pai_id'])) {
            $rules['elemento_id'] = 'required|integer';
        }

        if (!$this->validateData($data, $rules)) {
            return $this->respond(['success' => false, 'message' => implode(' ', $this->validator->getErrors())], 400);
        }

        try {
            if ($id) {
                $this->service->atualizar($id, $data);
                $msg = 'Categoria atualizada com sucesso!';
            } else {
                $newId = $this->service->criar($data);
                $id = $newId;
                $msg = 'Categoria criada com sucesso!';
            }
            return $this->respond(['success' => true, 'message' => $msg, 'id' => $id]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Categorias::salvar] ' . $e->getMessage());
            return $this->respond(['success' => false, 'message' => 'Erro ao salvar categoria.'], 500);
        }
    }

    public function excluir($id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        try {
            $this->service->excluir((int)$id);
            return $this->respond(['success' => true, 'message' => 'Categoria excluída com sucesso!']);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Categorias::excluir] ' . $e->getMessage());
            return $this->respond(['success' => false, 'message' => 'Erro ao excluir categoria.'], 500);
        }
    }
}
