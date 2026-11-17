<?php

namespace App\Controllers\ApiV2\Financeiro;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\Financeiro\FinanceiroFavoritosModel;

class Favoritos extends BaseController
{
    use ResponseTrait;

    protected $favoritosModel;

    public function __construct()
    {
        $this->favoritosModel = new FinanceiroFavoritosModel();
    }

    /**
     * Lista os favoritos, opcionalmente filtrados por tipo (receita ou despesa).
     */
    public function index()
    {
        $tipo = $this->request->getGet('tipo');

        if ($tipo && in_array($tipo, ['receita', 'despesa'])) {
            $this->favoritosModel->where('tipo', $tipo);
        }

        $favoritos = $this->favoritosModel->orderBy('titulo', 'ASC')->findAll();

        return $this->respond([
            'status' => 'success',
            'data'   => $favoritos,
        ]);
    }

    /**
     * Cria um novo favorito.
     */
    public function create()
    {
        $rules = [
            'titulo' => 'required|max_length[255]',
            'tipo'   => 'required|in_list[despesa,receita]',
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $data = $this->request->getJSON(true);

        // Sanitize and structure the data
        $favoritoData = [
            'titulo'        => $data['titulo'],
            'tipo'          => $data['tipo'],
            'valor'         => $data['valor'] ?? null,
            'categoria_id'  => $data['categoria_id'] ?? null,
            'fornecedor_id' => $data['fornecedor_id'] ?? null,
            'cliente_id'    => $data['cliente_id'] ?? null,
            'comentario'    => $data['comentario'] ?? null,
            'rateio'        => $data['rateio'] ?? null,
        ];

        if ($this->favoritosModel->insert($favoritoData)) {
            $favoritoData['id_favorito'] = $this->favoritosModel->getInsertID();
            return $this->respondCreated([
                'status'  => 'success',
                'message' => 'Favorito salvo com sucesso.',
                'data'    => $favoritoData,
            ]);
        }

        return $this->fail('Falha ao salvar o favorito.');
    }

    /**
     * Atualiza um favorito.
     */
    public function update($id = null)
    {
        if (!$id || !$this->favoritosModel->find($id)) {
            return $this->failNotFound('Favorito não encontrado.');
        }

        $data = $this->request->getJSON(true);

        if ($this->favoritosModel->update($id, $data)) {
            return $this->respond([
                'status'  => 'success',
                'message' => 'Favorito atualizado com sucesso.',
            ]);
        }

        return $this->fail('Falha ao atualizar o favorito.');
    }

    /**
     * Exclui um favorito.
     */
    public function delete($id = null)
    {
        if (!$id || !$this->favoritosModel->find($id)) {
            return $this->failNotFound('Favorito não encontrado.');
        }

        if ($this->favoritosModel->delete($id)) {
            return $this->respondDeleted([
                'status'  => 'success',
                'message' => 'Favorito removido com sucesso.',
            ]);
        }

        return $this->fail('Falha ao remover o favorito.');
    }
}
