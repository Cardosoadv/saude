<?php

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\Checklist\ProcedimentoService;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;

class Procedimentos extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;

    protected ProcedimentoService $procedimentoService;
    protected string $permissaoNecessaria = 'checklist';

    public function __construct(?ProcedimentoService $procedimentoService = null)
    {
        $this->procedimentoService = $procedimentoService ?? new ProcedimentoService();
    }

    public function listar()
    {
        try {
            if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
                return $this->failForbidden('Sem permissão para acessar os procedimentos.');
            }

            $page = (int) ($this->request->getGet('page') ?? 1);
            $perPage = (int) ($this->request->getGet('limit') ?? 50); // Default to 50 for simple grids
            $search = $this->request->getGet('search');

            $procedimentos = $this->procedimentoService->listarProcedimentos($page, $perPage, $search);

            $data = [
                'data' => $procedimentos['data'],
                'pager' => $procedimentos['pager'] ? [
                    'currentPage' => $procedimentos['pager']->getCurrentPage(),
                    'pageCount' => $procedimentos['pager']->getPageCount(),
                    'total' => $procedimentos['pager']->getTotal(),
                ] : null
            ];

            return $this->respond(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            log_message('error', '[ApiV2\Procedimentos::listar] ' . $e->getMessage());
            return $this->fail('Erro ao carregar procedimentos: ' . $e->getMessage());
        }
    }

    public function detalhes(int $id)
    {
        try {
            if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
                return $this->failForbidden('Sem permissão para acessar os procedimentos.');
            }

            $procedimento = $this->procedimentoService->buscarProcedimento($id);
            return $this->respond(['success' => true, 'data' => $procedimento]);
        } catch (\Exception $e) {
            log_message('error', '[ApiV2\Procedimentos::detalhes] ' . $e->getMessage());
            return $this->fail('Erro ao buscar procedimento: ' . $e->getMessage());
        }
    }

    public function salvar()
    {
        try {
            if (!$this->verificaPermissao($this->permissaoNecessaria, 'edit')) {
                return $this->failForbidden('Sem permissão para salvar procedimentos.');
            }

            $json = $this->getRequestData();
            $id = $json['id'] ?? null;

            $dados = [
                'nome' => $json['nome'] ?? '',
                'descricao' => $json['descricao'] ?? null
            ];

            if ($id) {
                $this->procedimentoService->atualizarProcedimento((int)$id, $dados, (int)auth()->id());
                $message = 'Procedimento atualizado com sucesso';
            } else {
                $id = $this->procedimentoService->criarProcedimento($dados, (int)auth()->id());
                $message = 'Procedimento criado com sucesso';
            }

            return $this->respond(['success' => true, 'id' => $id, 'message' => $message]);
        } catch (\Exception $e) {
            log_message('error', '[ApiV2\Procedimentos::salvar] ' . $e->getMessage());
            return $this->fail('Erro ao salvar procedimento: ' . $e->getMessage());
        }
    }

    public function excluir(int $id)
    {
        try {
            if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) {
                return $this->failForbidden('Sem permissão para excluir procedimentos.');
            }

            $this->procedimentoService->excluirProcedimento($id, (int)auth()->id());
            return $this->respond(['success' => true, 'message' => 'Procedimento excluído com sucesso']);
        } catch (\Exception $e) {
            log_message('error', '[ApiV2\Procedimentos::excluir] ' . $e->getMessage());
            return $this->fail($e->getMessage());
        }
    }
}
