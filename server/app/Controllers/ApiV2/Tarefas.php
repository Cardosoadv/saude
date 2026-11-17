<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\TarefasService;
use App\Services\UsuariosService;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use Exception;

/**
 * Controller API para o módulo de Tarefas.
 *
 * Fornece endpoints JSON para listagem, criação, atualização e exclusão de tarefas.
 * Utilizado pelo frontend via AJAX.
 *
 * @version 1.0.1
 * @since 2026-04-12
 */
class Tarefas extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;

    private TarefasService $service;
    private UsuariosService $usuariosService;

    protected $permissaoNecessaria = 'tarefas';

    public function __construct(
        ?TarefasService $service = null,
        ?UsuariosService $usuariosService = null
    ) {
        $this->service         = $service ?? new TarefasService();
        $this->usuariosService = $usuariosService ?? new UsuariosService();
    }

    /**
     * Retorna a listagem de tarefas em formato JSON.
     * Suporta filtros de busca, status, responsável e vínculos.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function listarJson()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão.'], 403);
        }

        $filtros = [
            'search'      => $this->request->getGet('s') ?? $this->request->getGet('search'),
            'status'      => $this->request->getGet('status'),
            'responsavel' => $this->request->getGet('responsavel'),
            'vinculo'     => $this->request->getGet('vinculo'),
            'prazo'       => $this->request->getGet('prazo'),
            'minhas'      => $this->request->getGet('minhas'),
            'emAndamento' => $this->request->getGet('emAndamento'),
            'sort_field'  => $this->request->getGet('sort') ?? 'prazo',
            'sort_order'  => $this->request->getGet('order') ?? 'ASC'
        ];

        try {
            $tarefas = $this->service->listarTarefas($filtros);
            $tarefasComVinculos = $this->service->popularVinculos($tarefas);

            return $this->respond([
                'success' => true,
                'data'    => $tarefasComVinculos,
                'meta'    => [
                    'total' => count($tarefasComVinculos)
                ]
            ]);
        } catch (Exception $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Salva ou atualiza uma tarefa via JSON.
     * Implementa proteção contra IDOR em atualizações.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function salvar()
    {
        $data = $this->getRequestData(); 
        $id = $data['id_tarefa'] ?? null;
        $acao = !empty($id) ? 'edit' : 'create';

        if ($acao === 'edit') {
            $allowed = $this->checkOwnership((int)$id, 'edit');
            if ($allowed !== true) {
                return $allowed;
            }
        } else {
            if (!$this->verificaPermissao($this->permissaoNecessaria, 'create')) {
                return $this->respond(['success' => false, 'message' => 'Sem permissão para criar tarefas.'], 403);
            }
        }

        if ($negado = $this->responsavelInvalido($data, $acao === 'edit' ? (int) $id : null)) {
            return $negado;
        }

        try {
            $resultado = $this->service->salvarTarefa($data);
            return $this->respond($resultado);
        } catch (Exception $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Atualiza o status de uma tarefa (ex: via Drag & Drop).
     * Implementa proteção contra IDOR.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function mudarStatus()
    {
        $data = $this->getRequestData();
        $id     = $data['id'] ?? null;
        $status = $data['status'] ?? null;

        if (!$id || !$status) {
            return $this->respond(['success' => false, 'message' => 'Parâmetros inválidos.'], 400);
        }

        $allowed = $this->checkOwnership((int)$id, 'edit');
        if ($allowed !== true) {
            return $allowed;
        }

        try {
            $resultado = $this->service->atualizarStatus((int)$id, (int)$status);
            return $this->respond($resultado);
        } catch (Exception $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Atualiza o responsável de uma tarefa.
     * Implementa proteção contra IDOR.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function mudarResponsavel()
    {
        $data = $this->getRequestData();
        $id          = $data['id'] ?? null;
        $responsavel = $data['responsavel'] ?? null;

        if (!$id || !$responsavel) {
            return $this->respond(['success' => false, 'message' => 'Parâmetros inválidos.'], 400);
        }

        $allowed = $this->checkOwnership((int)$id, 'edit');
        if ($allowed !== true) {
            return $allowed;
        }

        if ($negado = $this->responsavelInvalido(['responsavel' => $responsavel], (int) $id)) {
            return $negado;
        }

        try {
            $resultado = $this->service->atualizarTarefa((int)$id, ['responsavel' => $responsavel]);
            return $this->respond($resultado);
        } catch (Exception $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Retorna tarefas urgentes.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function urgentes()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão.'], 403);
        }

        try {
            $tarefas = $this->service->obterTarefasUrgentes();
            return $this->respond(['success' => true, 'data' => $tarefas]);
        } catch (Exception $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Retorna estatísticas de tarefas do usuário.
     * Implementa proteção contra divulgação de informações de outros usuários.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function estatisticas()
    {
        $requestedUserId = (int) ($this->request->getGet('user_id') ?? user_id());
        $currentUserId   = (int) user_id();

        // Proteção: Apenas administradores podem ver estatísticas de outros usuários
        if ($requestedUserId !== $currentUserId) {
            if (!$this->verificaPermissao('admin', 'access') && !$this->verificaPermissao('users', 'manage-admins')) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar estatísticas de outros usuários.',
                ], 403);
            }
        }

        try {
            $stats = $this->service->obterEstatisticas($requestedUserId);
            return $this->respond(['success' => true, 'data' => $stats]);
        } catch (Exception $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Exclui uma tarefa.
     * Implementa proteção contra IDOR.
     *
     * @param int $id
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function excluir(int $id)
    {
        $allowed = $this->checkOwnership($id, 'delete');
        if ($allowed !== true) {
            return $allowed;
        }

        try {
            $resultado = $this->service->excluirTarefa($id);
            return $this->respond($resultado);
        } catch (Exception $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Só quem está marcado como "pode ser responsável por tarefas" recebe tarefa. Quem já é o
     * responsável de uma tarefa existente continua podendo salvá-la sem trocar de responsável.
     *
     * @param array<string, mixed> $data
     */
    private function responsavelInvalido(array $data, ?int $tarefaId)
    {
        $responsavel = (int) ($data['responsavel'] ?? 0);
        if ($responsavel === 0) {
            return null;
        }

        if ($tarefaId) {
            $atual = (int) ($this->service->buscarTarefa($tarefaId)['responsavel'] ?? 0);
            if ($atual === $responsavel) {
                return null;
            }
        }

        if (!$this->usuariosService->podeSerResponsavelTarefas($responsavel)) {
            return $this->respond([
                'success' => false,
                'message' => 'Este usuário não está habilitado para ser responsável por tarefas.',
            ], 422);
        }

        return null;
    }

    /**
     * Retorna a lista de responsáveis (usuários).
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function responsaveis()
    {
        // Proteção: Apenas usuários com acesso a tarefas podem listar responsáveis
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão.'], 403);
        }

        try {
            $usuarios = $this->usuariosService->getResponsaveisTarefas();
            return $this->respond(['success' => true, 'data' => $usuarios]);
        } catch (Exception $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Verifica se o usuário tem permissão para realizar uma ação em uma tarefa específica.
     * Implementa proteção contra IDOR (Broken Object Level Authorization).
     *
     * @param int    $id     ID da tarefa.
     * @param string $action Ação a ser verificada ('edit', 'delete').
     * @return bool|\CodeIgniter\HTTP\ResponseInterface Retorna true se permitido, ou ResponseInterface se negado.
     */
    private function checkOwnership(int $id, string $action)
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, $action)) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão para esta ação.'], 403);
        }

        // Busca a tarefa para verificar propriedade
        $tarefa = $this->service->buscarTarefa($id);

        if (!$tarefa) {
            return $this->respond(['success' => false, 'message' => 'Tarefa não encontrada.'], 404);
        }

        // Se o usuário tem permissão de admin, pode tudo
        if ($this->verificaPermissao('admin', 'access')) {
            return true;
        }

        // Se o usuário for o responsável pela tarefa, ele pode editar/excluir (dependendo do permissionamento global)
        $currentUserId = (int) user_id();
        if ((int)$tarefa['responsavel'] === $currentUserId) {
            return true;
        }

        // Profissionais geralmente podem gerenciar todas as tarefas da equipe, a menos que tenham permissão 'exclusive'.
        if ($this->verificaPermissao('tarefas', 'exclusive')) {
            return $this->respond(['success' => false, 'message' => 'Você só pode gerenciar suas próprias tarefas.'], 403);
        }

        return true;
    }

    /**
     * Retorna dados formatados para o calendário.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function calendario()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão.'], 403);
        }

        $filtros = [
            'responsavel' => $this->request->getGet('responsavel'),
            'minhas'      => $this->request->getGet('minhas')
        ];

        try {
            $dados = $this->service->obterDadosCalendario($filtros);
            return $this->respond(['success' => true, 'data' => $dados]);
        } catch (Exception $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
