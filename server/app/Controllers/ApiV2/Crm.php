<?php

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\CrmService;
use App\Traits\AcessoProcessoTrait;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;

class Crm extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;
    use AcessoProcessoTrait;

    protected $service;

    public function __construct(?CrmService $service = null)
    {
        $this->service = $service ?? new CrmService();
    }

    public function pipeline()
    {
        try {
            $data = $this->service->getPipeline();
            return $this->respond($data);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function mover()
    {
        if (!$this->verificaPermissao('clientes', 'edit')) {
            return $this->failForbidden('Você não tem permissão para mover oportunidades.');
        }

        $rules = [
            'oportunidade_id' => 'required|is_natural_no_zero',
            'etapa_id'       => 'required|is_natural_no_zero',
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $id = $this->request->getPost('oportunidade_id');
        $etapaId = $this->request->getPost('etapa_id');

        if ($negado = $this->acessoAoRegistroNegado('cliente', $this->clienteDaOportunidade((int) $id))) {
            return $negado;
        }

        try {
            $this->service->moverOportunidade((int)$id, (int)$etapaId);
            return $this->respond(['success' => true]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function interacoes(int $clienteId)
    {
        if ($negado = $this->acessoAoRegistroNegado('cliente', $clienteId)) {
            return $negado;
        }

        try {
            $data = $this->service->obterTimelineCliente($clienteId);
            return $this->respond($data);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function salvarInteracao()
    {
        if (!$this->verificaPermissao('clientes', 'edit')) {
            return $this->failForbidden('Você não tem permissão para registrar interações.');
        }

        $data = $this->getRequestData();
        if (isset($data['id']) && empty($data['id'])) {
            unset($data['id']);
        }

        $rules = [
            'cliente_id'     => 'required|is_natural_no_zero',
            'tipo'           => 'required|max_length[50]',
            'descricao'      => 'required',
            'data_interacao' => 'permit_empty|valid_date[Y-m-d H:i:s]',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        if ($negado = $this->acessoAoRegistroNegado('cliente', (int) $data['cliente_id'])) {
            return $negado;
        }

        try {
            $this->service->registrarInteracao($data);
            return $this->respond(['success' => true]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function excluirInteracao(int $id)
    {
        try {
            $this->service->excluirInteracao($id);
            return $this->respond(['success' => true]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function salvar()
    {
        $data = $this->getRequestData();
        $id = (isset($data['id']) && (int)$data['id'] > 0) ? (int)$data['id'] : null;

        if (!$id && isset($data['id'])) {
            unset($data['id']);
        }

        if ($id) {
            if (!$this->verificaPermissao('clientes', 'edit')) {
                return $this->failForbidden('Você não tem permissão para editar oportunidades.');
            }
        } else {
            if (!$this->verificaPermissao('clientes', 'create')) {
                return $this->failForbidden('Você não tem permissão para criar oportunidades.');
            }
        }

        $rules = [
            'cliente_id'     => 'required|is_natural_no_zero',
            'titulo'         => 'required|min_length[3]|max_length[255]',
            'etapa_id'       => 'required|is_natural_no_zero',
            'valor_estimado' => 'permit_empty|numeric',
            'status'         => 'permit_empty|in_list[aberto,ganho,perdido]',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        if ($negado = $this->acessoAoRegistroNegado('cliente', (int) $data['cliente_id'])) {
            return $negado;
        }
        if ($id && ($negado = $this->acessoAoRegistroNegado('cliente', $this->clienteDaOportunidade($id)))) {
            return $negado;
        }

        try {
            $oportunidadeId = $this->service->salvarOportunidade($data, $id);
            return $this->respond([
                'success' => true,
                'id' => $oportunidadeId
            ]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    /**
     * A oportunidade segue a privacidade do cliente a que pertence.
     */
    private function clienteDaOportunidade(int $oportunidadeId): ?int
    {
        $row = db_connect()->table('crm_oportunidades')->select('cliente_id')->where('id', $oportunidadeId)->get()->getRowArray();

        return empty($row['cliente_id']) ? null : (int) $row['cliente_id'];
    }

    public function dashboard()
    {
        try {
            $data = $this->service->obterDadosDashboard();
            return $this->respond($data);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }
}
