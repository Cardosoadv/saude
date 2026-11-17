<?php

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\EtiquetasService;
use CodeIgniter\API\ResponseTrait;

class Etiquetas extends BaseController
{
    use ResponseTrait;

    protected EtiquetasService $service;

    public function __construct()
    {
        $this->service = new EtiquetasService();
    }

    public function index()
    {
        return $this->listar();
    }

    public function listar()
    {
        $etiquetas = $this->service->listarEtiquetas('nome', 'asc');
        return $this->respond([
            'success' => true,
            'data' => $etiquetas
        ]);
    }

    public function store()
    {
        $data = $this->request->getJSON(true);

        if (empty($data['nome'])) {
            return $this->failValidationErrors('O nome é obrigatório.');
        }

        $cor = isset($data['cor']) ? ltrim((string)$data['cor'], '#') : '6c757d';

        try {
            $id = $this->service->salvarEtiqueta([
                'nome' => $data['nome'],
                'cor' => $cor
            ]);
            return $this->respondCreated(['success' => true, 'message' => 'Etiqueta criada com sucesso', 'id_etiqueta' => $id]);
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao salvar etiqueta.');
        }
    }

    public function update($id)
    {
        $data = $this->request->getJSON(true);

        if (empty($data['nome'])) {
            return $this->failValidationErrors('O nome é obrigatório.');
        }

        $cor = isset($data['cor']) ? ltrim((string)$data['cor'], '#') : '6c757d';

        try {
            $this->service->salvarEtiqueta([
                'id_etiqueta' => $id,
                'nome' => $data['nome'],
                'cor' => $cor
            ]);
            return $this->respond(['success' => true, 'message' => 'Etiqueta atualizada com sucesso']);
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao atualizar etiqueta.');
        }
    }

    public function delete($id)
    {
        try {
            $this->service->deletarEtiqueta((int) $id);
            return $this->respondDeleted(['success' => true, 'id_etiqueta' => $id, 'message' => 'Etiqueta excluída com sucesso']);
        } catch (\Exception $e) {
            return $this->failServerError('Erro ao excluir etiqueta. Ela pode estar vinculada a processos.');
        }
    }
}
