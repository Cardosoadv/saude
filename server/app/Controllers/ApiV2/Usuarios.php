<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\UsuariosService;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;

/**
 * Controller API para fornecer dados de Usuários via JSON.
 *
 * @version 1.1.0
 * @since 2026-05-25
 */
class Usuarios extends BaseController
{
    use ResponseTrait;

    private UsuariosService $service;

    public function __construct(?UsuariosService $service = null)
    {
        $this->service = $service ?? new UsuariosService();
    }

    /**
     * Retorna a listagem de usuários com paginação e filtros.
     *
     * @return ResponseInterface
     */
    public function listar()
    {
        if (!auth()->user()->can('admin.access') && !auth()->user()->can('admin.settings')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para listar usuários.',
            ], 403);
        }

        try {
            $search = $this->request->getGet('s') ?? '';
            $perPage = (int) ($this->request->getGet('perPage') ?? 20);

            $resultado = $this->service->getAll($perPage, $search);
            $users = $resultado['users'] ?? [];
            $pager = $resultado['pager'] ?? null;

            return $this->respond([
                'success' => true,
                'data'    => $users,
                'pagination' => [
                    'current_page' => $pager ? $pager->getCurrentPage() : 1,
                    'total_pages'  => $pager ? $pager->getPageCount() : 1,
                    'total_items'  => $pager ? $pager->getTotal() : count($users),
                    'per_page'     => $pager ? $pager->getPerPage() : $perPage,
                ],
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao listar usuários API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro interno ao listar usuários.',
            ], 500);
        }
    }

    /**
     * Retorna os detalhes de um único usuário.
     *
     * @param int|string|null $id
     * @return ResponseInterface
     */
    public function detalhes($id = null)
    {
        if (!auth()->user()->can('admin.access') && !auth()->user()->can('admin.settings')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para ver detalhes de usuários.',
            ], 403);
        }

        if ($id === null) {
            return $this->respond([
                'success' => false,
                'message' => 'ID do usuário não fornecido.',
            ], 400);
        }

        try {
            $user = $this->service->getById((int) $id);

            if (!$user) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Usuário não encontrado.',
                ], 404);
            }

            return $this->respond([
                'success' => true,
                'data'    => $user,
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao obter detalhes de usuário API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro interno ao obter detalhes do usuário.',
            ], 500);
        }
    }

    /**
     * Cria um novo usuário.
     *
     * @return ResponseInterface
     */
    public function salvar()
    {
        if (!auth()->user()->can('admin.access') && !auth()->user()->can('admin.settings')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para cadastrar usuários.',
            ], 403);
        }

        $rules = [
            'username' => 'required|alpha_numeric_space|min_length[3]|is_unique[users.username]',
            'email'    => 'required|valid_email|is_unique[auth_identities.secret]',
            'password' => 'required|min_length[8]',
            'pass_confirm' => 'required|matches[password]',
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'success' => false,
                'message' => 'Dados de validação inválidos.',
                'errors'  => $this->validator->getErrors(),
            ], 400);
        }

        try {
            $data = $this->request->getPost();
            $data['photo'] = $this->request->getFile('foto-perfil');

            // Trata booleanos enviados por formulário
            $data['is_profissional'] = (isset($data['is_profissional']) && ($data['is_profissional'] === 'true' || $data['is_profissional'] == 1)) ? 1 : 0;
            $data['rateio_ativo'] = (isset($data['rateio_ativo']) && ($data['rateio_ativo'] === 'true' || $data['rateio_ativo'] == 1)) ? 1 : 0;
            $data['active'] = (isset($data['active']) && ($data['active'] === 'true' || $data['active'] == 1)) ? 1 : 0;
            foreach (['responsavel_tarefas', 'exibir_site'] as $flag) {
                if (array_key_exists($flag, $data)) {
                    $data[$flag] = ($data[$flag] === 'true' || $data[$flag] == 1) ? 1 : 0;
                }
            }

            if (isset($data['groups']) && is_string($data['groups'])) {
                $data['groups'] = json_decode($data['groups'], true);
            }
            if (isset($data['permissions']) && is_string($data['permissions'])) {
                $data['permissions'] = json_decode($data['permissions'], true);
            }

            $userId = $this->service->create($data);

            return $this->respond([
                'success' => true,
                'message' => 'Usuário criado com sucesso.',
                'id'      => $userId,
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao criar usuário API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao criar usuário: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Atualiza um usuário existente.
     *
     * @param int|string|null $id
     * @return ResponseInterface
     */
    public function atualizar($id = null)
    {
        if (!auth()->user()->can('admin.access') && !auth()->user()->can('admin.settings')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para atualizar usuários.',
            ], 403);
        }

        if ($id === null) {
            return $this->respond([
                'success' => false,
                'message' => 'ID do usuário não fornecido.',
            ], 400);
        }

        $rules = [
            'username' => "required|alpha_numeric_space|min_length[3]|is_unique[users.username,id,{$id}]",
            'email'    => "required|valid_email",
        ];

        if ($this->request->getPost('password')) {
            $rules['password'] = 'required|min_length[8]';
            $rules['pass_confirm'] = 'required|matches[password]';
        }

        if (!$this->validate($rules)) {
            return $this->respond([
                'success' => false,
                'message' => 'Dados de validação inválidos.',
                'errors'  => $this->validator->getErrors(),
            ], 400);
        }

        try {
            $data = $this->request->getPost();
            $data['photo'] = $this->request->getFile('foto-perfil');

            $data['is_profissional'] = (isset($data['is_profissional']) && ($data['is_profissional'] === 'true' || $data['is_profissional'] == 1)) ? 1 : 0;
            $data['rateio_ativo'] = (isset($data['rateio_ativo']) && ($data['rateio_ativo'] === 'true' || $data['rateio_ativo'] == 1)) ? 1 : 0;
            $data['active'] = (isset($data['active']) && ($data['active'] === 'true' || $data['active'] == 1)) ? 1 : 0;
            foreach (['responsavel_tarefas', 'exibir_site'] as $flag) {
                if (array_key_exists($flag, $data)) {
                    $data[$flag] = ($data[$flag] === 'true' || $data[$flag] == 1) ? 1 : 0;
                }
            }

            if (isset($data['groups']) && is_string($data['groups'])) {
                $data['groups'] = json_decode($data['groups'], true);
            }
            if (isset($data['permissions']) && is_string($data['permissions'])) {
                $data['permissions'] = json_decode($data['permissions'], true);
            }

            $this->service->update((int) $id, $data);

            return $this->respond([
                'success' => true,
                'message' => 'Usuário atualizado com sucesso.',
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao atualizar usuário API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao atualizar usuário: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exclui um usuário do sistema.
     *
     * @param int|string|null $id
     * @return ResponseInterface
     */
    public function excluir($id = null)
    {
        if (!auth()->user()->can('admin.access') && !auth()->user()->can('admin.settings')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para excluir usuários.',
            ], 403);
        }

        if ($id === null) {
            return $this->respond([
                'success' => false,
                'message' => 'ID do usuário não fornecido.',
            ], 400);
        }

        try {
            $this->service->delete((int) $id);
            return $this->respond([
                'success' => true,
                'message' => 'Usuário excluído com sucesso.',
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao excluir usuário API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao excluir usuário: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna a matriz de grupos e permissões do Shield.
     *
     * @return ResponseInterface
     */
    public function grupos()
    {
        if (!auth()->user()->can('admin.access') && !auth()->user()->can('admin.settings')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para acessar grupos.',
            ], 403);
        }

        try {
            $config = config('AuthGroups');
            return $this->respond([
                'success'     => true,
                'groups'      => $config->groups,
                'permissions' => $config->permissions,
                'matrix'      => $config->matrix,
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao obter grupos/permissões API V2: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro interno ao obter grupos/permissões.',
            ], 500);
        }
    }

    /**
     * Busca usuários para componentes de autocomplete.
     * Exige acesso a clientes, tarefas ou administração.
     *
     * @return ResponseInterface
     */
    public function buscar()
    {
        // Quem só tem acesso restrito (sem acesso a clientes/tarefas) não lista usuários do sistema.
        $usuario = auth()->user();
        if (!$usuario->can('clientes.access') && !$usuario->can('tarefas.access') && !$usuario->can('admin.access')) {
            return $this->respond(['results' => []], 403);
        }

        $term = $this->request->getGet('term');

        if (empty($term) || strlen($term) < 2) {
            return $this->respond(['results' => []]);
        }

        try {
            $apenasTarefas = $this->request->getGet('contexto') === 'tarefas';
            $usuarios = $this->service->search($term, $apenasTarefas);

            $results = [];
            foreach ($usuarios as $usuario) {
                $results[] = [
                    'id'   => $usuario['id'],
                    'text' => $usuario['username'] . ($usuario['email'] ? ' (' . $usuario['email'] . ')' : ''),
                ];
            }

            return $this->respond(['results' => $results]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao buscar usuários API: ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro interno ao buscar usuários.',
            ], 500);
        }
    }

    /**
     * Exibe a foto do perfil do usuário.
     *
     * @param int|null $id ID do usuário.
     * @return ResponseInterface Resposta com a imagem ou erro 404.
     */
    public function exibirFoto($id = null)
    {
        // Verifica se o ID foi fornecido
        if ($id === null) {
            return $this->response->setStatusCode(404, 'ID do usuário não fornecido');
        }

        $caminhoImagem = $this->service->getUserImagePath((int) $id);

        // Verifica se o usuário tem uma imagem (getUserImagePath já faz a validação de segurança)
        if ($caminhoImagem === null) {
            // Retorna um placeholder ou 404
            return redirect()->to('assets/img/user-placeholder.png');
        }

        // Retorna a imagem usando o método seguro do CodeIgniter
        return $this->response->download($caminhoImagem, null, true)->inline();
    }

    public function participaRateio()
    {
        try {
            $socios = $this->service->getParticipaRateio();
            return $this->respond(['results' => $socios]);
        } catch (Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => 'Erro interno ao buscar usuários para rateio.',
            ], 500);
        }
    }

    public function getProfissionais(){
        try {
            $profissionais = $this->service->getProfissionais();
            return $this->respond(['results' => $profissionais]);
        } catch (Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => 'Erro interno ao buscar profissionais.',
            ], 500);
        }
    }
}
