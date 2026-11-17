<?php

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\CrmContactService;
use CodeIgniter\API\ResponseTrait;

class CrmLeads extends BaseController
{
    use ResponseTrait;

    protected $service;

    public function __construct(?CrmContactService $service = null)
    {
        $this->service = $service ?? new CrmContactService();
    }

    public function listar()
    {
        $search = $this->request->getGet('search') ?? '';
        $page = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('limit') ?? 20);
        $status = $this->request->getGet('status') ?? null;
        $areaId = $this->request->getGet('area_id') ? (int) $this->request->getGet('area_id') : null;

        try {
            $data = $this->service->buscarContatos($search, $perPage, $page, $status, $areaId);
            return $this->respond($data);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function ramos()
    {
        try {
            $ramos = $this->service->getRamosDireito();
            return $this->respond($ramos);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function converter(int $id)
    {
        $etapaId = (int) $this->request->getPost('etapa_id');
        $titulo = $this->request->getPost('titulo');

        if (!$etapaId || empty($titulo)) {
            return $this->failValidationErrors('Título da oportunidade e etapa são obrigatórios.');
        }

        try {
            // A lógica de conversão real vai ficar no CrmContactService
            $oportunidadeId = $this->service->converterEmOportunidade($id, $titulo, $etapaId);
            
            return $this->respond([
                'success' => true,
                'oportunidade_id' => $oportunidadeId,
                'message' => 'Lead convertido com sucesso!'
            ]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function atualizarStatus(int $id)
    {
        $status = $this->request->getJsonVar('status') ?? $this->request->getPost('status');

        if (empty($status)) {
            return $this->failValidationErrors('Status é obrigatório.');
        }

        try {
            $this->service->update($id, ['status' => $status]);
            return $this->respond([
                'success' => true,
                'message' => 'Status atualizado com sucesso!'
            ]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }
}
