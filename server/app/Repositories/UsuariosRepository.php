<?php

namespace App\Repositories;

use App\Models\UsuariosModel;
use CodeIgniter\Shield\Entities\User;
use Exception;

/**
 * Repositório para gerenciar usuários.
 * Lida com operações de banco de dados e limpeza de cache.
 *
 * @version 1.0.3
 */
class UsuariosRepository extends BaseRepository
{

    public function __construct(?UsuariosModel $model = null)
    {
        parent::__construct();
        $this->model            = $model ?? model('UsuariosModel');
        $this->cacheKey         = 'usuarios';
    }

    /**
     * Retorna todos os usuários com suporte a paginação e filtro.
     *
     * @param int $perPage Itens por página
     * @param string $search Termo de busca
     * @param int $page Número da página
     * @return array
     */
    public function listarPaginado(int $perPage = 20, string $search = '', int $page = 1): array
    {
        if (!empty($search)) {
            return $this->executarListarPaginado($perPage, $search, $page);
        }

        return $this->remember("lista_paginada_{$page}_{$perPage}", function () use ($perPage, $page) {
            return $this->executarListarPaginado($perPage, '', $page);
        });
    }

    /**
     * Executa a consulta de listagem paginada.
     */
    private function executarListarPaginado(int $perPage, string $search, int $page): array
    {
        $query = $this->model->select('users.*, auth_identities.secret as email, users.avatar as auth_image')
            ->join('auth_identities', 'auth_identities.user_id = users.id AND auth_identities.type = "email_password"', 'left');

        if (!empty($search)) {
            $query->groupStart()
                ->like('users.username', $search)
                ->orLike('auth_identities.secret', $search)
                ->groupEnd();
        }

        $users = $query->paginate($perPage, 'default', $page);

        // Adiciona informações de grupos e permissões para exibição
        if (empty($users)) {
            return [
                'users' => [],
                'pager' => $this->model->pager,
            ];
        }

        $userIds = array_column($users, 'id');

        // Fetch groups in batch
        $allGroups = $this->db->table('auth_groups_users')
            ->whereIn('user_id', $userIds)
            ->get()
            ->getResultArray();

        $groupsMap = [];
        foreach ($allGroups as $group) {
            $groupsMap[$group['user_id']][] = $group['group'];
        }

        // Fetch permissions count in batch
        $allPermissionsCount = $this->db->table('auth_permissions_users')
            ->select('user_id, COUNT(*) as count')
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id')
            ->get()
            ->getResultArray();

        $permissionsCountMap = array_column($allPermissionsCount, 'count', 'user_id');

        // Map data back to users
        $usersWithRoles = [];
        foreach ($users as $userData) {
            $userArray = (array) $userData;
            $userId = $userArray['id'];

            $userArray['groups'] = $groupsMap[$userId] ?? [];
            $userArray['permissions_count'] = (int) ($permissionsCountMap[$userId] ?? 0);

            $usersWithRoles[] = $userArray;
        }

        return [
            'users' => $usersWithRoles,
            'pager' => $this->model->pager,
        ];
    }

    public function getAllUserNames(): array
    {
        return $this->remember('all_user_names', function () {
            return $this->model->select('username')->findAll();
        });
    }

    /**
     * Retorna lista simples de usuários (id, username) para dropdowns.
     *
     * @return array
     */
    public function getUsers(): array
    {
        return $this->remember('dropdown_list', function () {
            return $this->model->getUsers();
        });
    }

    /**
     * Encontra um usuário pelo ID, incluindo permissões e grupos.
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        return $this->remember("detail_{$id}", function () use ($id) {
            $user = $this->model->select('users.*, auth_identities.secret as email, users.avatar as auth_image')
            ->join('auth_identities', 'auth_identities.user_id = users.id AND auth_identities.type = "email_password"', 'left')
            ->where('users.id', $id)
            ->first();

            if (!$user) {
                return null;
            }

            $userArray = (array) $user;

            // Buscar grupos
            $groups = $this->db->table('auth_groups_users')->where('user_id', $id)->get()->getResultArray();
            $userArray['groups'] = array_column($groups, 'group');

            // Buscar permissões granulares explicitas
            $permissions = $this->db->table('auth_permissions_users')->where('user_id', $id)->get()->getResultArray();
            $userArray['permissions'] = array_column($permissions, 'permission');

            return $userArray;
        });
    }

    /**
     * Cria um novo usuário.
     *
     * @param array $data Dados do usuário (username, email, password, custom fields, groups, permissions)
     * @return int ID do usuário criado
     * @throws Exception
     */
    public function create(array $data): int
    {
        $this->db->transStart();

        try {
            // 1. Criar usuário no Shield (Identity) com campos básicos
            $users = auth()->getProvider();
            $user = new User([
                'username' => $data['username'],
                'email'    => $data['email'],
                'password' => $data['password'],
            ]);

            // Salva o usuário (User + Identity)
            if (!$users->save($user)) {
                $errors = implode(', ', $users->errors());
                throw new Exception("Erro ao salvar usuário: $errors");
            }

            $userId = (int) $users->getInsertID();
            $user->id = $userId;

            // 2. Atualizar campos customizados diretamente no Model
            $updateData = [
                'is_profissional' => !empty($data['is_profissional']) ? 1 : 0,
                'rateio_ativo' => !empty($data['rateio_ativo']) ? 1 : 0,
                'registro_numero' => $data['registro_numero'] ?? null,
                'registro_uf' => $data['registro_uf'] ?? null,
                'active' => isset($data['active']) ? (int) $data['active'] : 1,
            ] + $this->camposPerfil($data);

            $this->model->update($userId, $updateData);

            // 3. Associar Grupos
            if (!empty($data['groups'])) {
                $this->syncGroups($userId, $data['groups']);
            }

            // 4. Associar Permissões Granulares
            if (isset($data['permissions']) && is_array($data['permissions'])) {
                $this->syncPermissions($userId, $data['permissions']);
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new Exception('Erro ao processar transação de criação de usuário.');
            }

            $this->clearCache();
            return $userId;
        } catch (Exception $e) {
            $this->db->transRollback();
            log_message('error', 'Erro ao criar usuário: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Atualiza um usuário existente.
     *
     * @param int $id ID do usuário
     * @param array $data Dados a atualizar
     * @return bool
     * @throws Exception
     */
    public function update(int $id, array $data): bool
    {
        $this->db->transStart();

        try {
            $users = auth()->getProvider();
            $user = $users->findById($id);

            if (!$user) {
                throw new Exception("Usuário não encontrado.");
            }

            // 1. Atualizar campos básicos e Identity via Shield Entity
            $user->fill([
                'username' => $data['username'] ?? $user->username,
                'email'    => $data['email'] ?? $user->email,
            ]);

            if (!empty($data['password'])) {
                $user->password = $data['password'];
            }

            // Salva as alterações do Shield (User + Identity)
            if (!$users->save($user)) {
                $errors = implode(', ', $users->errors());
                throw new Exception("Erro ao atualizar usuário: $errors");
            }

            // 2. Atualizar campos customizados diretamente no Model
            $updateData = [
                'is_profissional' => !empty($data['is_profissional']) ? 1 : 0,
                'rateio_ativo' => !empty($data['rateio_ativo']) ? 1 : 0,
                'registro_numero' => $data['registro_numero'] ?? null,
                'registro_uf' => $data['registro_uf'] ?? null,
                'active' => isset($data['active']) ? (int) $data['active'] : 1,
            ] + $this->camposPerfil($data);

            $this->model->update($id, $updateData);

            // 3. Sincronizar Grupos
            if (isset($data['groups'])) {
                $this->syncGroups($id, $data['groups']);
            }

            // 4. Sincronizar Permissões
            if (isset($data['permissions'])) {
                $this->syncPermissions($id, $data['permissions']);
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new Exception('Erro ao atualizar usuário.');
            }

            $this->clearCache();
            return true;
        } catch (Exception $e) {
            $this->db->transRollback();
            log_message('error', 'Erro ao atualizar usuário: ' . $e->getMessage());
            throw $e;
        }
    }

    /** Validade do link de redefinição de senha. */
    public const VALIDADE_REDEFINICAO_HORAS = 2;

    private const TIPO_IDENTIDADE_REDEFINICAO = 'password_reset';

    /** Campos de texto do perfil profissional (exibidos na página Quem Somos). */
    public const CAMPOS_PERFIL_TEXTO = ['cargo', 'formacao', 'especializacoes', 'areas_atuacao', 'curriculo', 'lattes_url', 'linkedin_url'];

    /**
     * Extrai de `$data` os campos de perfil e de visibilidade enviados. O que não veio
     * fica de fora, para uma atualização parcial não apagar o que já estava gravado.
     *
     * @return array<string, mixed>
     */
    public function camposPerfil(array $data): array
    {
        $campos = [];
        foreach (['responsavel_tarefas', 'exibir_site'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $campos[$flag] = (int) (bool) $data[$flag];
            }
        }
        if (array_key_exists('site_ordem', $data)) {
            $campos['site_ordem'] = max(0, (int) $data['site_ordem']);
        }
        foreach (self::CAMPOS_PERFIL_TEXTO as $campo) {
            if (array_key_exists($campo, $data)) {
                $valor = trim((string) $data[$campo]);
                $campos[$campo] = $valor === '' ? null : $valor;
            }
        }

        return $campos;
    }

    /**
     * Atualiza o perfil pelo próprio usuário: nome, e-mail e dados profissionais.
     * Não toca em grupos, permissões, situação nem nas marcações de visibilidade.
     *
     * @param array<string, mixed> $data
     * @throws Exception
     */
    public function atualizarPerfil(int $id, array $data): bool
    {
        $users = auth()->getProvider();
        $user  = $users->findById($id);
        if (!$user) {
            throw new Exception('Usuário não encontrado.');
        }

        $this->db->transStart();
        try {
            $user->fill([
                'username' => $data['username'] ?? $user->username,
                'email'    => $data['email'] ?? $user->email,
            ]);
            if (!$users->save($user)) {
                throw new Exception('Erro ao atualizar perfil: ' . implode(', ', $users->errors()));
            }

            $campos = array_diff_key(
                $this->camposPerfil($data),
                array_flip(['responsavel_tarefas', 'exibir_site', 'site_ordem'])
            );
            if ($campos !== []) {
                $this->model->update($id, $campos);
            }

            $this->db->transComplete();
            if ($this->db->transStatus() === false) {
                throw new Exception('Erro ao atualizar perfil.');
            }
        } catch (Exception $e) {
            $this->db->transRollback();
            throw $e;
        }

        $this->clearCache();
        return true;
    }

    /** Grava uma nova senha (hash feito pelo Shield) e encerra os acessos "lembrar de mim". */
    public function definirSenha(int $id, string $novaSenha): void
    {
        $users = auth()->getProvider();
        $user  = $users->findById($id);
        if (!$user) {
            throw new Exception('Usuário não encontrado.');
        }

        $user->password = $novaSenha;
        if (!$users->save($user)) {
            throw new Exception('Erro ao gravar a nova senha: ' . implode(', ', $users->errors()));
        }

        if ($this->db->tableExists('auth_remember_tokens')) {
            $this->db->table('auth_remember_tokens')->where('user_id', $id)->delete();
        }
        $this->db->table('auth_identities')
            ->where(['user_id' => $id, 'type' => self::TIPO_IDENTIDADE_REDEFINICAO])
            ->delete();
    }

    /**
     * Cria o token de redefinição (só o hash fica no banco) para um usuário ativo.
     * Substitui qualquer token anterior do mesmo usuário.
     *
     * @return array{token: string, username: string}|null null se o e-mail não existe ou o usuário está inativo.
     */
    public function criarTokenRedefinicao(string $email): ?array
    {
        $identidade = $this->db->table('auth_identities')
            ->select('auth_identities.user_id, users.username, users.active')
            ->join('users', 'users.id = auth_identities.user_id')
            ->where('auth_identities.type', 'email_password')
            ->where('auth_identities.secret', $email)
            ->get()->getRowArray();

        if (!$identidade || (int) $identidade['active'] !== 1) {
            return null;
        }

        $this->db->table('auth_identities')
            ->where(['user_id' => $identidade['user_id'], 'type' => self::TIPO_IDENTIDADE_REDEFINICAO])
            ->delete();

        $token = bin2hex(random_bytes(32));
        $agora = date('Y-m-d H:i:s');
        $this->db->table('auth_identities')->insert([
            'user_id'    => $identidade['user_id'],
            'type'       => self::TIPO_IDENTIDADE_REDEFINICAO,
            'name'       => 'password_reset',
            'secret'     => hash('sha256', $token),
            'expires'    => date('Y-m-d H:i:s', time() + self::VALIDADE_REDEFINICAO_HORAS * 3600),
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        return ['token' => $token, 'username' => (string) $identidade['username']];
    }

    /** Valida o token (do e-mail informado, dentro da validade, de uso único) e troca a senha. */
    public function redefinirSenhaComToken(string $email, string $token, string $novaSenha): bool
    {
        $registro = $this->db->table('auth_identities')
            ->where('type', self::TIPO_IDENTIDADE_REDEFINICAO)
            ->where('secret', hash('sha256', $token))
            ->where('expires >', date('Y-m-d H:i:s'))
            ->get()->getRowArray();

        if (!$registro) {
            return false;
        }

        $dono = $this->db->table('auth_identities')
            ->where(['user_id' => $registro['user_id'], 'type' => 'email_password', 'secret' => $email])
            ->countAllResults();
        if ($dono === 0) {
            return false;
        }

        $this->definirSenha((int) $registro['user_id'], $novaSenha);
        $this->clearCache();

        return true;
    }

    /**
     * Sincroniza grupos do usuário (remove todos e adiciona os novos).
     *
     * @param int $userId
     * @param array $groups Array de strings de grupos
     */
    private function syncGroups(int $userId, array $groups)
    {
        // Remove grupos existentes
        $this->db->table('auth_groups_users')->where('user_id', $userId)->delete();

        // Adiciona novos
        if (!empty($groups)) {
            $batch = [];
            foreach ($groups as $group) {
                $batch[] = [
                    'user_id' => $userId,
                    'group' => $group,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }
            $this->db->table('auth_groups_users')->insertBatch($batch);
        }
    }

    /**
     * Sincroniza permissões do usuário (remove todas e adiciona as novas).
     *
     * @param int $userId
     * @param array $permissions Array de strings de permissões
     */
    private function syncPermissions(int $userId, array $permissions)
    {
        // Remove permissões existentes
        $this->db->table('auth_permissions_users')->where('user_id', $userId)->delete();

        // Adiciona novas
        if (!empty($permissions)) {
            $batch = [];
            foreach ($permissions as $permission) {
                $batch[] = [
                    'user_id' => $userId,
                    'permission' => $permission,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }
            $this->db->table('auth_permissions_users')->insertBatch($batch);
        }
    }

    /**
     * Retorna o email de um usuário.
     *
     * @param int $id ID do usuário.
     * @return string|null
     */
    public function getEmailById(int $id): ?string
    {
        return $this->model->getEmailById($id) ?: null;
    }

    /**
     * Deleta um usuário.
     *
     * @param int $id ID do usuário.
     * @return bool
     * @throws Exception
     */
    public function delete(int $id): bool
    {
        $this->db->transStart();

        try {
            $this->model->delete($id);
            // Shield deve lidar com cascade delete de identities e groups se configurado no BD,
            // mas manualmente garantimos a limpeza
            $this->db->table('auth_identities')->where('user_id', $id)->delete();
            $this->db->table('auth_groups_users')->where('user_id', $id)->delete();
            $this->db->table('auth_permissions_users')->where('user_id', $id)->delete();

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new Exception('Erro ao deletar usuário.');
            }

            $this->clearCache();
            return true;
        } catch (Exception $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Limpa o cache de responsáveis.
     * @deprecated Use $this->clearCache()
     */
    public function limparCache(): void
    {
        $this->clearCache();
    }

    // Mantido para compatibilidade
    /**
     * Retorna uma lista de profissionais com cache versionado.
     *
     * @return array
     */
    public function getProfissionais(): array
    {
        return $this->remember('profissionais', function () {
            return $this->model->getProfissionais();
        });
    }

    /**
     * Retorna todos os usuários que participam do rateio.
     *
     * @return array
     */
    public function getParticipaRateio()
    {
        return $this->model->where('rateio_ativo', 1)->findAll();
    }

    // Mantido para compatibilidade
    public function atualizarUsuario(int $id, array $data): bool
    {
        return $this->update($id, $data);
    }

    /**
     * Salva ou atualiza a imagem do usuário.
     *
     * @param int $userId
     * @param string $imagePath
     * @return bool
     */
    public function saveUserImage(int $userId, string $imagePath): bool
    {
        $user = $this->model->find($userId);

        if ($user && !empty($user['avatar'])) {
            $oldPath = WRITEPATH . 'uploads/userImgs/' . $userId . '/' . $user['avatar'];
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        return $this->model->update($userId, ['avatar' => $imagePath]);
    }

    /**
     * Retorna a imagem do usuário pelo ID.
     *
     * @param int $userId
     * @return array|null
     */
    public function getUserImage(int $userId): ?array
    {
        $user = $this->model->find($userId);
        if ($user && !empty($user['avatar'])) {
            return ['image_path' => $user['avatar']];
        }
        return null;
    }

    /**
     * Search users by username or email.
     * Otimizado: Adicionado limite de resultados e seleção de colunas específicas.
     *
     * @param string $term
     * @param int $limit Limite de resultados (padrão 20)
     * @return array
     */
    public function search(string $term, int $limit = 20, bool $apenasResponsaveisTarefas = false): array
    {
        $query = $this->model->select('users.id, users.username, auth_identities.secret as email')
            ->join('auth_identities', 'auth_identities.user_id = users.id AND auth_identities.type = "email_password"', 'left')
            ->groupStart()
                ->like('users.username', $term)
                ->orLike('auth_identities.secret', $term)
            ->groupEnd();

        if ($apenasResponsaveisTarefas) {
            $query->where('users.responsavel_tarefas', 1)->where('users.active', 1);
        }

        return $query->orderBy('users.username', 'ASC')->findAll($limit);
    }

    /**
     * Usuários ativos que podem ser responsáveis por tarefas (id, username).
     *
     * @return array<int, array{id: int|string, username: string}>
     */
    public function getResponsaveisTarefas(): array
    {
        return $this->remember('responsaveis_tarefas', function () {
            return $this->model->select('id, username')
                ->where('responsavel_tarefas', 1)
                ->where('active', 1)
                ->orderBy('username', 'ASC')
                ->findAll();
        });
    }

    /** Indica se o usuário está ativo e marcado como possível responsável por tarefas. */
    public function podeSerResponsavelTarefas(int $id): bool
    {
        return $this->model->where('id', $id)
            ->where('responsavel_tarefas', 1)
            ->where('active', 1)
            ->countAllResults() > 0;
    }

    /**
     * Membros da equipe marcados para aparecer em Quem Somos, já no formato da seção `equipe`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEquipeSite(): array
    {
        $usuarios = $this->model->select('id, username, cargo, formacao, especializacoes, areas_atuacao, curriculo, lattes_url, linkedin_url, registro_numero, registro_uf, avatar, is_profissional')
            ->where('exibir_site', 1)
            ->where('active', 1)
            ->orderBy('site_ordem', 'ASC')
            ->orderBy('username', 'ASC')
            ->findAll();

        return array_map(static function (array $u): array {
            $registro = ($u['is_profissional'] ?? 0) && !empty($u['registro_numero'])
                ? trim($u['registro_numero'] . (!empty($u['registro_uf']) ? '/' . strtoupper($u['registro_uf']) : ''))
                : '';

            return [
                'id'              => (int) $u['id'],
                'nome'            => (string) $u['username'],
                'cargo'           => (string) ($u['cargo'] ?? ''),
                'registro' => $registro,
                'formacao'        => (string) ($u['formacao'] ?? ''),
                'especializacoes' => (string) ($u['especializacoes'] ?? ''),
                'areas'           => (string) ($u['areas_atuacao'] ?? ''),
                'curriculo'       => (string) ($u['curriculo'] ?? ''),
                'lattes'          => !empty($u['lattes_url']) ? ['href' => $u['lattes_url']] : null,
                'linkedin'        => !empty($u['linkedin_url']) ? ['href' => $u['linkedin_url']] : null,
                'tem_foto'        => !empty($u['avatar']),
            ];
        }, $usuarios);
    }
}
