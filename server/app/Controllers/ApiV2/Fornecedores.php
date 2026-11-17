<?php

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\FornecedorService;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;

/**
 * Controlador API para Fornecedores.
 * 
 * Fornece endpoints JSON para o módulo de fornecedores.
 * 
 * @author Antigravity
 * @version 1.0.1
 * @since 2026-04-18
 */
class Fornecedores extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;

    protected $service;
    private $permissaoNecessaria = 'fornecedores';

    /**
     * Construtor do FornecedoresApi.
     */
    public function __construct(?FornecedorService $service = null)
    {
        $this->service = $service ?? new FornecedorService();
    }

    /**
     * Lista fornecedores com busca e paginação.
     * 
     * GET /api/fornecedores
     */
    public function index(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para acessar fornecedores.'
            ], 403);
        }

        try {
            $s = $this->request->getGet('s');
            $perPage = $this->request->getGet('per_page') ?? 25;

            $result = $this->service->listarFornecedores($s, (int)$perPage);

            return $this->respond([
                'success' => true,
                'data'    => $result['fornecedores'],
                'pager'   => [
                    'currentPage' => $result['pager']->getCurrentPage(),
                    'pageCount'   => $result['pager']->getPageCount(),
                    'total'       => $result['pager']->getTotal(),
                    'perPage'     => $result['pager']->getPerPage(),
                    'links'       => $result['pager']->links(),
                ]
            ]);
        } catch (Exception $e) {
            log_message('error', '[Api\Fornecedores::index] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar fornecedores.'
            ], 500);
        }
    }

    /**
     * Retorna detalhes de um fornecedor (para edição).
     * 
     * GET /api/fornecedores/detalhes/(:num)
     */
    public function detalhes($id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'edit')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para editar fornecedores.'
            ], 403);
        }

        try {
            $fornecedor = $this->service->getById((int)$id);
            if (!$fornecedor) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Fornecedor não encontrado.'
                ], 404);
            }

            return $this->respond([
                'success' => true,
                'data'    => $fornecedor
            ]);
        } catch (Exception $e) {
            log_message('error', '[Api\Fornecedores::detalhes] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao recuperar detalhes.'
            ], 500);
        }
    }

    /**
     * Salva ou atualiza um fornecedor.
     * 
     * POST /api/fornecedores/salvar
     */
    public function salvar(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?: $this->request->getRawInput();
        if (empty($data)) {
            $data = $this->request->getPost();
        }

        $id = $data['id_fornecedor'] ?? null;
        $acao = !empty($id) ? 'edit' : 'create';

        if (!$this->verificaPermissao($this->permissaoNecessaria, $acao)) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para ' . ($acao === 'create' ? 'criar' : 'editar') . ' fornecedor.'
            ], 403);
        }

        try {

            // Validação de Input (Hardening)
            $rules = [
                'nome'        => 'required|min_length[3]',
                'tipo_pessoa' => 'required|in_list[F,J]',
                'documento'   => 'required',
                'email'       => 'permit_empty|valid_email',
                'ativo'       => 'required|in_list[0,1]',
                'tipo_conta'  => 'permit_empty|in_list[,corrente,poupanca,pagamento]',
            ];

            if (!$this->validateData($data, $rules)) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Erro de validação: ' . implode(' ', $this->validator->getErrors())
                ], 400);
            }

            $newId = $this->service->salvarFornecedor($data, !empty($id) ? (int)$id : null);

            return $this->respond([
                'success' => true,
                'message' => 'Fornecedor salvo com sucesso!',
                'id'      => $newId
            ]);
        } catch (Exception $e) {
            log_message('error', '[Api\Fornecedores::salvar] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao salvar fornecedor.'
            ], 400);
        }
    }

    /**
     * Atualiza um fornecedor existente (incluindo dados bancários).
     * 
     * PUT /api/v2/fornecedores/atualizar/:id
     *
     * @param int $id ID do fornecedor.
     */
    public function atualizar($id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'edit')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para editar fornecedor.'
            ], 403);
        }

        try {
            // Suporta JSON body (PUT) e form-data
            $data = $this->request->getJSON(true) ?: $this->request->getRawInput();
            if (empty($data)) {
                $data = $this->request->getPost();
            }

            $rules = [
                'nome'        => 'permit_empty|min_length[3]',
                'tipo_pessoa' => 'permit_empty|in_list[F,J]',
                'email'       => 'permit_empty|valid_email',
                'ativo'       => 'permit_empty|in_list[0,1]',
                'tipo_conta'  => 'permit_empty|in_list[,corrente,poupanca,pagamento]',
            ];

            if (!$this->validateData($data, $rules)) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Erro de validação: ' . implode(' ', $this->validator->getErrors())
                ], 400);
            }

            $this->service->salvarFornecedor($data, (int)$id);

            return $this->respond([
                'success' => true,
                'message' => 'Fornecedor atualizado com sucesso!',
                'id'      => (int)$id
            ]);
        } catch (Exception $e) {
            log_message('error', '[Api\Fornecedores::atualizar] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao atualizar fornecedor: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Exclui um fornecedor.
     * 
     * DELETE /api/fornecedores/excluir/(:num)
     */
    public function excluir($id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para excluir fornecedores.'
            ], 403);
        }

        try {
            $this->service->delete((int)$id);
            return $this->respond([
                'success' => true,
                'message' => 'Fornecedor excluído com sucesso!'
            ]);
        } catch (Exception $e) {
            log_message('error', '[Api\Fornecedores::excluir] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao excluir fornecedor.'
            ], 500);
        }
    }

    /**
     * Retorna estatísticas de fornecedores.
     * 
     * GET /api/fornecedores/estatisticas
     */
    public function estatisticas(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false], 403);
        }

        try {
            $stats = $this->service->getEstatisticas();
            return $this->respond([
                'success' => true,
                'data'    => $stats
            ]);
        } catch (Exception $e) {
            log_message('error', '[Api\Fornecedores::estatisticas] ' . $e->getMessage());
            return $this->respond(['success' => false], 500);
        }
    }

    /**
     * Retorna os últimos fornecedores cadastrados.
     * 
     * GET /api/fornecedores/recentes
     */
    public function recentes(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false], 403);
        }

        try {
            $limit = $this->request->getGet('limit') ?? 5;
            $recentes = $this->service->getRecentes((int)$limit);
            return $this->respond([
                'success' => true,
                'data'    => $recentes
            ]);
        } catch (Exception $e) {
            log_message('error', '[Api\Fornecedores::recentes] ' . $e->getMessage());
            return $this->respond(['success' => false], 500);
        }
    }

    /**
     * Busca fornecedores por nome ou documento.
     * 
     * GET /api/v2/fornecedores/buscar?term=...
     */
    public function buscar(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para acessar fornecedores.'
            ], 403);
        }

        try {
            $term = $this->request->getGet('term');
            $results = $this->service->buscarAjax($term);

            return $this->respond([
                'success' => true,
                'data'    => $results
            ]);
        } catch (Exception $e) {
            log_message('error', '[ApiV2\Fornecedores::buscar] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao buscar fornecedores.'
            ], 500);
        }
    }

    /**
     * Transforma um fornecedor existente em cliente.
     * 
     * POST /api/v2/fornecedores/transformar-em-cliente/(:num)
     *
     * @param int|string $id ID do fornecedor
     */
    public function transformarEmCliente($id): ResponseInterface
    {
        if (!$this->verificaPermissao('clientes', 'create')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para criar clientes.'
            ], 403);
        }

        try {
            $data = $this->request->getJSON(true) ?: $this->request->getRawInput();
            if (empty($data)) {
                $data = $this->request->getPost();
            }

            $opcoes = [
                'inativar_origem' => !empty($data['inativar_origem']),
            ];

            $resultado = $this->service->transformarEmCliente((int) $id, $opcoes);

            return $this->respond($resultado);
        } catch (Exception $e) {
            log_message('error', '[Api\Fornecedores::transformarEmCliente] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
