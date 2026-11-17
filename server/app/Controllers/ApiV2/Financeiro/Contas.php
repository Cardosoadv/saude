<?php

namespace App\Controllers\ApiV2\Financeiro;

use App\Controllers\BaseController;
use App\Services\Financeiro\ContasService;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

class Contas extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;

    protected ContasService $service;
    private string $permissaoNecessaria = 'financeiro';

    public function __construct(?ContasService $service = null)
    {
        $this->service = $service ?? new ContasService();
    }

    public function listar(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        try {
            $contas = $this->service->listarTodas();
            return $this->respond([
                'success' => true,
                'data'    => $contas
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Contas::listar] ' . $e->getMessage());
            return $this->respond(['success' => false, 'message' => 'Erro ao listar contas.'], 500);
        }
    }

    public function salvar(): ResponseInterface
    {
        $data = $this->getRequestData();
        $id = isset($data['id_conta']) && is_numeric($data['id_conta']) ? (int)$data['id_conta'] : null;
        $action = $id ? 'edit' : 'create';

        if (!$this->verificaPermissao($this->permissaoNecessaria, $action)) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        $rules = [
            'conta'        => 'required|string|max_length[50]',
            'banco'        => 'permit_empty|string|max_length[50]',
            'agencia'      => 'permit_empty|string|max_length[50]',
            'numero_conta' => 'permit_empty|string|max_length[50]',
            'pix'          => 'permit_empty|string|max_length[50]',
            'comentarios'  => 'permit_empty|string',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->respond(['success' => false, 'message' => implode(' ', $this->validator->getErrors())], 400);
        }

        try {
            if ($id) {
                $this->service->update($id, $data);
                $msg = 'Conta atualizada com sucesso!';
            } else {
                $newId = $this->service->create($data);
                $id = $newId;
                $msg = 'Conta criada com sucesso!';
            }
            return $this->respond(['success' => true, 'message' => $msg, 'id' => $id]);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Contas::salvar] ' . $e->getMessage());
            return $this->respond(['success' => false, 'message' => 'Erro ao salvar conta.'], 500);
        }
    }

    public function excluir($id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        try {
            $vinculos = $this->service->vinculos((int)$id);
            if ($vinculos !== []) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Não é possível excluir: a conta possui ' . implode(', ', $vinculos) . '.',
                ], 409);
            }

            $this->service->delete((int)$id);
            return $this->respond(['success' => true, 'message' => 'Conta excluída com sucesso!']);
        } catch (\Exception $e) {
            log_message('error', '[Financeiro\Contas::excluir] ' . $e->getMessage());
            return $this->respond(['success' => false, 'message' => 'Erro ao excluir conta.'], 500);
        }
    }
}
