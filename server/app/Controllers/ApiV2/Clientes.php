<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\ClienteService;
use App\Services\VisibilidadeModulosService;
use App\Traits\AcessoProcessoTrait;
use App\Traits\ValidacoesTrait;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use Exception;

/**
 * Controller API V2 para fornecer e gerenciar dados de Clientes via JSON no SPA.
 *
 * @author Cardoso
 * @version 1.4.2
 * @since 2026-05-19
 */
class Clientes extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;
    use ValidacoesTrait;
    use AcessoProcessoTrait;

    private ClienteService $service;
    protected $permissaoNecessaria = 'clientes';

    public function __construct(?ClienteService $service = null)
    {
        $this->service = $service ?? new ClienteService();
    }

    /**
     * Retorna a listagem de clientes em formato JSON com paginação e filtros. 
     * Rota GET /api/v2/clientes
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function listar()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para acessar o módulo de Clientes.',
            ], 403);
        }

        try {
            // Filtros da requisição
            $filters = [
                'search'  => $this->request->getGet('s'),
                'tipo'    => $this->request->getGet('tipo'),
                'status'  => $this->request->getGet('status'),
                'ordenar' => $this->request->getGet('ordenar'),
                'responsavel_id' => $this->request->getGet('responsavel_id'),
            ];

            $perPage = (int) ($this->request->getGet('perPage') ?? 25);
            $page    = (int) ($this->request->getGet('page') ?? 1);

            // Busca os dados via Service
            $resultado = $this->service->listarClientesComFiltros($filters, $perPage, $page);

            $clientes = $resultado['clientes'] ?? [];
            $pager    = $resultado['pager'] ?? null;

            return $this->respond([
                'success' => true,
                'data'    => $clientes,
                'pagination' => [
                    'current_page' => $pager ? $pager->getCurrentPage() : $page,
                    'total_pages'  => $pager ? $pager->getPageCount() : 1,
                    'total_items'  => $pager ? $pager->getTotal() : count($clientes),
                    'per_page'     => $pager ? $pager->getPerPage() : $perPage,
                ],
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao listar clientes API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro interno ao carregar clientes.',
            ], 500);
        }
    }

    /**
     * Busca clientes por termo (autocomplete).
     * Rota GET /api/v2/clientes/buscar?term=abc
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function buscar()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para buscar clientes.',
            ], 403);
        }

        $term = $this->request->getGet('term') ?? '';

        try {
            $clientes = $this->service->pesquisarClientes((string)$term);

            return $this->respond([
                'success' => true,
                'data'    => $clientes,
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao buscar clientes API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro interno ao buscar clientes.',
            ], 500);
        }
    }

    /**
     * Retorna os detalhes de um único cliente em formato JSON.
     * Rota GET /api/v2/clientes/detalhes/{id}
     *
     * @param int $id ID do cliente
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function detalhes(int $id)
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para acessar os dados deste cliente.',
            ], 403);
        }

        try {
            $origens = $this->service->obterOrigens();

            if ($id === 0) {
                return $this->respond([
                    'success' => true,
                    'data'    => null,
                    'origens' => $origens
                ]);
            }

            if ($negado = $this->acessoAoRegistroNegado('cliente', $id)) {
                return $negado;
            }

            $cliente = $this->service->encontrarCliente($id);

            if (!$cliente) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Cliente não encontrado.',
                ], 404);
            }

            // Busca relacionamentos associados para renderizar nas abas
            $processos = $this->service->obterProcessosCliente($id, 10);
            $casos     = $this->service->obterCasosCliente($id, 10);

            //@TODO: Incluir relacionamentos financeiros, tarefas, anotações, etc.

            return $this->respond((new VisibilidadeModulosService())->ocultarBlocos([
                'success'   => true,
                'data'      => $cliente,
                'processos' => $processos,
                'casos'     => $casos,
                'origens'   => $origens,
            ], ClienteService::BLOCOS_DETALHE));
        } catch (Exception $e) {
            log_message('error', 'Erro ao obter detalhes do cliente API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro interno ao carregar dados do cliente.',
            ], 500);
        }
    }

    /**
     * Retorna as estatísticas consolidadas dos clientes em formato JSON.
     * Rota GET /api/v2/clientes/estatisticas
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function estatisticas()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para acessar estatísticas.',
            ], 403);
        }

        try {
            $stats = $this->service->obterEstatisticas();
            return $this->respond([
                'success' => true,
                'data'    => $stats,
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao buscar estatísticas de clientes API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar estatísticas.',
            ], 500);
        }
    }

    /**
     * Retorna os clientes cadastrados recentemente.
     * Rota GET /api/v2/clientes/recentes
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function recentes()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para listar clientes recentes.',
            ], 403);
        }

        try {
            $clientes = $this->service->obterClientesRecentes(5);
            return $this->respond([
                'success' => true,
                'data'    => $clientes,
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao obter clientes recentes API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar clientes recentes.',
            ], 500);
        }
    }

    /**
     * Retorna os aniversariantes do mês atual.
     * Rota GET /api/v2/clientes/aniversariantes
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function aniversariantes()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para listar aniversariantes.',
            ], 403);
        }

        try {
            $aniversariantes = $this->service->obterAniversariantes();
            return $this->respond([
                'success' => true,
                'data'    => $aniversariantes,
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao obter aniversariantes API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar aniversariantes.',
            ], 500);
        }
    }

    /**
     * Retorna as estatísticas detalhadas de um cliente específico (processos, finanças, tarefas).
     * Rota GET /api/v2/estatisticasCliente/{id}
     *
     * @param int $id ID do cliente
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function estatisticasCliente(int $id)
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para obter estatísticas do cliente.',
            ], 403);
        }

        if ($negado = $this->acessoAoRegistroNegado('cliente', $id)) {
            return $negado;
        }

        try {
            $stats = $this->service->obterEstatisticasCliente($id);
            return $this->respond([
                'success' => true,
                'data'    => $stats,
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao carregar estatísticas do cliente API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao obter estatísticas do cliente.',
            ], 500);
        }
    }

    /**
     * Retorna as anotações do cliente.
     * Rota GET /api/v2/clientes/anotacoes/{id}
     *
     * @param int $id ID do cliente
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function anotacoes(int $id)
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para obter anotações.',
            ], 403);
        }

        if ($negado = $this->acessoAoRegistroNegado('cliente', $id)) {
            return $negado;
        }

        try {
            $anotacoes = $this->service->obterAnotacoesCliente($id, 10);
            return $this->respond([
                'success' => true,
                'data'    => $anotacoes,
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao obter anotações API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar anotações do cliente.',
            ], 500);
        }
    }

    /**
     * Retorna as movimentações financeiras associadas ao cliente.
     * Rota GET /api/v2/clientes/movimentacoes/{id}
     *
     * @param int $id ID do cliente
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function movimentacoes(int $id)
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para obter movimentações.',
            ], 403);
        }

        if ($negado = $this->acessoAoRegistroNegado('cliente', $id)) {
            return $negado;
        }

        try {
            $movimentacoes = $this->service->obterMovimentacoesCliente($id, 10);
            return $this->respond([
                'success' => true,
                'data'    => $movimentacoes,
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao obter movimentações financeiras API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar receitas financeiras.',
            ], 500);
        }
    }

    /**
     * Retorna as intimações ativas relacionadas ao cliente.
     *
     * @param int $id ID do cliente
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function intimacoes(int $id)
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para obter intimações.',
            ], 403);
        }

        if ($negado = $this->acessoAoRegistroNegado('cliente', $id)) {
            return $negado;
        }

        try {
            $intimacoes = $this->service->obterIntimacoesCliente($id, 10);
            return $this->respond([
                'success' => true,
                'data'    => $intimacoes,
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao obter intimações API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar publicações/intimações.',
            ], 500);
        }
    }

    /**
     * Cria ou atualiza os dados de um cliente.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function salvar()
{
    $data = $this->getRequestData();

    // Extrai e sanitiza o ID antes de sujar o array $data
    $rawId     = $data['id_cliente'] ?? null;
    $idCliente = ($rawId !== null && $rawId !== '' && is_numeric($rawId) && (int)$rawId > 0)
        ? (int)$rawId
        : null;

    // Remove do array — o service recebe o ID via parâmetro explícito,
    // não embutido nos dados, evitando coerção null→0 no model.
    unset($data['id_cliente']);

    $action = $idCliente ? 'edit' : 'create';

    if (!$this->verificaPermissao($this->permissaoNecessaria, $action)) {
        return $this->respond([
            'success' => false,
            'message' => "Você não tem permissão para " . ($action === 'create' ? 'criar' : 'editar') . " clientes!",
        ], 403);
    }

    if ($idCliente && ($negado = $this->acessoAoRegistroNegado('cliente', $idCliente))) {
        return $negado;
    }

    $data = $this->filtrarCamposDePrivacidade('cliente', $idCliente, $data);
    $responsavelAnterior = $idCliente ? $this->responsavelAtual('cliente', $idCliente) : null;

    $cpfCnpj = $data['documento'] ?? null;
    if ($cpfCnpj && !$this->validarCpfCnpj($cpfCnpj)) {
        return $this->respond([
            'success' => false,
            'message' => 'CPF ou CNPJ inválido.',
        ], 400);
    }

    if (!$this->validarDadosCliente($data)) {
        return $this->respond([
            'success' => false,
            'message' => 'Dados de validação inválidos.',
            'errors'  => $this->validator->getErrors(),
        ], 400);
    }

    try { 
        $clientId = $this->service->salvarCliente($data, $idCliente);

        if ($idCliente) {
            $this->preservarAcessoDoResponsavelAnterior('cliente', $idCliente, $responsavelAnterior);
        }

        return $this->respond([
            'success'    => true,
            'message'    => $idCliente ? 'Dados do cliente atualizados com sucesso.' : 'Cliente cadastrado com sucesso.',
            'id_cliente' => $clientId,
        ]);
    } catch (Exception $e) {
        log_message('error', 'Erro ao salvar cliente API V2: ' . $e->getMessage());
        return $this->respond([
            'success' => false,
            'message' => 'Erro ao salvar dados do cliente: ' . $e->getMessage(),
        ], 500);
    }
}



    /**
     * Exclui um cliente.
     *
     * @param int $id ID do cliente
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function excluir(int $id)
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'delete')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para excluir clientes!',
            ], 403);
        }

        if ($negado = $this->acessoAoRegistroNegado('cliente', $id)) {
            return $negado;
        }

        try {
            $this->service->deletarCliente($id);

            return $this->respond([
                'success' => true,
                'message' => 'Cliente excluído com sucesso.',
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao excluir cliente API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Não foi possível excluir o cliente: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Realiza a validação dos dados do cliente.
     *
     * @param array $data
     * @return bool
     */
    private function validarDadosCliente(array $data): bool
    {
        // Leads e oportunidades (status_crm !== 'client') exigem apenas o nome;
        // o CPF/CNPJ só se torna obrigatório quando o cadastro é salvo como cliente.
        $statusCrm = $data['status_crm'] ?? 'client';

        $documentoRules  = 'permit_empty|min_length[11]|max_length[18]';
        $documentoErrors = [
            'min_length' => 'O CPF/CNPJ deve ter pelo menos 11 caracteres.',
            'max_length' => 'O CPF/CNPJ não pode exceder 18 caracteres.',
        ];

        if ($statusCrm === 'client') {
            $documentoRules              = 'required|min_length[11]|max_length[18]';
            $documentoErrors['required'] = 'O CPF/CNPJ é obrigatório para o cadastro de cliente.';
        }

        $rules = [
            'nome' => [
                'rules' => 'required|min_length[3]|max_length[255]',
                'errors' => [
                    'required' => 'O nome do cliente é obrigatório.',
                    'min_length' => 'O nome do cliente deve ter pelo menos 3 caracteres.',
                    'max_length' => 'O nome do cliente não pode exceder 255 caracteres.',
                ],
            ],
            'documento' => [
                'rules' => $documentoRules,
                'errors' => $documentoErrors,
            ],
            'data_nascimento' => [
                'rules' => 'permit_empty|valid_date',
                'errors' => [
                    'valid_date' => 'A Data de Nascimento deve ser uma data válida.',
                ],
            ],
        ];

        return $this->validate($rules);
    }

    /**
     * Transforma um cliente existente em fornecedor.
     * 
     * POST /api/v2/clientes/transformar-em-fornecedor/(:num)
     *
     * @param int|string $id ID do cliente
     */
    public function transformarEmFornecedor($id): \CodeIgniter\HTTP\ResponseInterface
    {
        if (!$this->verificaPermissao('fornecedores', 'create')) {
            return $this->respond([
                'success' => false,
                'message' => 'Sem permissão para criar fornecedores.'
            ], 403);
        }

        try {
            $data = $this->request->getJSON(true) ?: $this->request->getRawInput();
            if (empty($data)) {
                $data = $this->request->getPost();
            }

            $opcoes = [
                'inativar_origem' => !empty($data['inativar_origem']),
                'banco'           => $data['banco'] ?? null,
                'agencia'         => $data['agencia'] ?? null,
                'conta'           => $data['conta'] ?? null,
                'tipo_conta'      => $data['tipo_conta'] ?? null,
                'pix'             => $data['pix'] ?? null,
            ];

            $resultado = $this->service->transformarEmFornecedor((int) $id, $opcoes);

            return $this->respond($resultado);
        } catch (Exception $e) {
            log_message('error', '[ApiV2\Clientes::transformarEmFornecedor] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
