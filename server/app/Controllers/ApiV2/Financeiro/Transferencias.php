<?php

namespace App\Controllers\ApiV2\Financeiro;

use App\Controllers\BaseController;
use App\Services\Financeiro\TransferenciasService;
use App\Traits\FormataValorTrait;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

class Transferencias extends BaseController
{
    use ResponseTrait;
    use FormataValorTrait;
    use VerificaPermissoesTrait;

    protected TransferenciasService $service;
    private string $permissaoNecessaria = 'financeiro';

    public function __construct(?TransferenciasService $service = null)
    {
        $this->service = $service ?? new TransferenciasService();
    }

    public function listar(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        try {
            $transferencias = $this->service->listar(25);
            return $this->respond([
                'success' => true,
                'data'    => $transferencias
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Transferencias::listar] ' . $e->getMessage());
            return $this->respond(['success' => false, 'message' => 'Erro ao listar transferencias.'], 500);
        }
    }

    public function detalhes($id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        try {
            $transferencia = $this->service->buscarPorId((int)$id);
            if (!$transferencia) {
                return $this->respond(['success' => false, 'message' => 'Transferência não encontrada.'], 404);
            }
            return $this->respond(['success' => true, 'data' => $transferencia]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Transferencias::detalhes] ' . $e->getMessage());
            return $this->respond(['success' => false, 'message' => 'Erro ao buscar transferencia.'], 500);
        }
    }

    public function salvar(): ResponseInterface
    {
        $data = $this->getRequestData();
        $id = isset($data['id_transferencia']) && is_numeric($data['id_transferencia']) ? (int)$data['id_transferencia'] : null;
        $action = $id ? 'edit' : 'create';

        if (!$this->verificaPermissao($this->permissaoNecessaria, $action)) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        if (isset($data['valor'])) {
            $data['valor'] = $this->formatarValorParaBanco($data['valor']);
        }

        if (isset($data['rateio']) && is_array($data['rateio'])) {
            foreach ($data['rateio'] as &$item) {
                if (isset($item['valor'])) {
                    $item['valor'] = $this->formatarValorParaBanco($item['valor']);
                }
            }
        }
 
        $rules = [
            'transferencia'      => 'required|string|max_length[255]',
            'data_transferencia' => 'required|valid_date',
            'id_conta_origem'    => 'required|is_natural_no_zero',
            'id_conta_destino'   => 'required|is_natural_no_zero|differs[id_conta_origem]',
            'valor'              => 'required|numeric',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->respond(['success' => false, 'message' => implode(' ', $this->validator->getErrors())], 400);
        }

        try {
            if ($id) {
                $this->service->atualizar($id, $data);
                $msg = 'Transferencia salva com sucesso!';
            } else {
                $newId = $this->service->criar($data);
                $id = $newId;
                $msg = 'Transferencia salva com sucesso!';
            }
            return $this->respond(['success' => true, 'message' => $msg, 'id' => $id]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Transferencias::salvar] ' . $e->getMessage());
            $msg = strpos($e->getMessage(), 'já está fechado') !== false ? $e->getMessage() : 'Erro ao salvar transferencia.';
            return $this->respond(['success' => false, 'message' => $msg], 500);
        }
    }

    public function excluir($id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        try {
            $this->service->excluir((int)$id);
            return $this->respond(['success' => true, 'message' => 'Transferencia excluída com sucesso!']);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Transferencias::excluir] ' . $e->getMessage());
            $msg = strpos($e->getMessage(), 'já está fechado') !== false ? $e->getMessage() : 'Erro ao excluir transferencia.';
            return $this->respond(['success' => false, 'message' => $msg], 500);
        }
    }
}
