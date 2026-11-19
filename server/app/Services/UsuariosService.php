<?php

namespace App\Services;

use App\Repositories\UsuariosRepository;
use CodeIgniter\HTTP\Files\UploadedFile;
use Exception;

/**
 * Service para gerenciar Usuários.
 * @version 0.9.1
 * @since 2025-12-20
 *
 * @property UsuariosRepository $repository
 */
class UsuariosService extends BaseService
{
    public function __construct(?UsuariosRepository $repository = null)
    {
        $this->repository = $repository ?? new UsuariosRepository();
    }

    /**
     * Get all users with optional search and pagination.
     *
     * @param int $perPage
     * @param string $search
     * @return array
     */
    public function getAll(int $perPage = 20, string $search = ''): array
    {
        $page = (int) (service('request')->getVar('page') ?? 1);
        return $this->repository->listarPaginado($perPage, $search, $page);
    }


    public function getAllUserNames(): array
    {
        return $this->repository->getAllUserNames();
    }

    /**
     * Retorna lista simples de usuários (id, username) para dropdowns.
     *
     * @return array
     */
    public function getUsers(): array
    {
        return $this->repository->getUsers();
    }

    /**
     * Get user by ID.
     * @param int $id
     * @return array|null
     * @deprecated Use parent::getById() instead.
     */
    public function getById(int $id): ?array
    {
        return parent::getById($id);
    }



    /**
     * Create a new user.
     *
     * @param array $data
     * @return int
     * @throws Exception
     */
    public function create(array $data): int
    {
        $photo = $data['photo'] ?? null;
        unset($data['photo']);

        // Business Logic Validation
        if (isset($data['is_profissional']) && $data['is_profissional'] == 1) {
            if (empty($data['registro_numero']) || empty($data['registro_uf'])) {
                throw new Exception('Para profissionais de saúde, o número e a UF do registro no conselho são obrigatórios.');
            }
        }

        $userId = $this->repository->create($data);

        if ($userId && $photo && $photo->isValid() && !$photo->hasMoved()) {
            $this->uploadPhoto($userId, $photo);
        }

        $this->limparCacheSite();

        return $userId;
    }

    /**
     * Update a user.
     *
     * @param int $id
     * @param array $data
     * @return bool
     * @throws Exception
     */
    public function update(int $id, array $data): bool
    {
        $photo = $data['photo'] ?? null;
        unset($data['photo']);

        if (isset($data['is_profissional']) && $data['is_profissional'] == 1) {
            if (empty($data['registro_numero']) || empty($data['registro_uf'])) {
                throw new Exception('Para profissionais de saúde, o número e a UF do registro no conselho são obrigatórios.');
            }
        }

        $success = $this->repository->update($id, $data);

        if ($success && $photo && $photo->isValid() && !$photo->hasMoved()) {
            $this->uploadPhoto($id, $photo);
        }

        $this->limparCacheSite();

        return $success;
    }

    /**
     * Dados do perfil do próprio usuário (nunca de outro).
     *
     * @return array<string, mixed>|null
     */
    public function getPerfil(int $id): ?array
    {
        $u = $this->repository->findById($id);
        if (!$u) {
            return null;
        }

        $campos = ['id', 'username', 'email', 'is_profissional', 'registro_numero', 'registro_uf', 'exibir_site', 'responsavel_tarefas',
            'cargo', 'formacao', 'especializacoes', 'areas_atuacao', 'curriculo', 'lattes_url', 'linkedin_url', 'auth_image'];

        return array_intersect_key($u, array_flip($campos));
    }

    /**
     * Atualiza o próprio perfil: nome, e-mail, dados profissionais e foto. Permissões, grupos,
     * situação e as marcações de visibilidade só mudam pela administração de usuários.
     *
     * @param array<string, mixed> $data
     */
    public function atualizarPerfil(int $id, array $data): bool
    {
        $photo = $data['photo'] ?? null;
        $ok = $this->repository->atualizarPerfil($id, $data);

        if ($ok && $photo && $photo->isValid() && !$photo->hasMoved()) {
            $this->uploadPhoto($id, $photo);
        }

        $this->limparCacheSite();

        return $ok;
    }

    /**
     * Troca a senha do próprio usuário, exigindo a senha atual.
     *
     * @throws Exception Quando a senha atual não confere.
     */
    public function alterarSenha(int $id, string $senhaAtual, string $novaSenha): void
    {
        $user = auth()->getProvider()->findById($id);
        if (!$user || !service('passwords')->verify($senhaAtual, (string) $user->password_hash)) {
            throw new Exception('A senha atual não confere.');
        }

        $this->repository->definirSenha($id, $novaSenha);
    }

    /**
     * Gera o link de redefinição e envia por e-mail. Resposta sempre genérica para quem chama:
     * não revela se o e-mail existe.
     */
    public function solicitarRedefinicaoSenha(string $email): void
    {
        $dados = $this->repository->criarTokenRedefinicao($email);
        if ($dados === null) {
            log_message('info', '[Senha] Redefinição solicitada para e-mail inexistente ou usuário inativo.');
            return;
        }

        $link = rtrim(config('App')->frontURL, '/') . '/redefinir-senha?token=' . $dados['token'] . '&email=' . rawurlencode($email);

        if (ENVIRONMENT === 'development') {
            log_message('info', "\n\n=====================\nLINK DE REDEFINIÇÃO DE SENHA PARA {$email}:\n{$link}\n=====================\n");
            return;
        }

        $mail = \Config\Services::email();
        \App\Libraries\Email\EmailMarca::montar($mail, 'emails/redefinir_senha', [
            'nome'       => $dados['username'],
            'link'       => $link,
            'expiracaoH' => UsuariosRepository::VALIDADE_REDEFINICAO_HORAS,
        ], [
            'titulo'    => 'Redefinição de senha',
            'etiqueta'  => 'Acesso seguro',
            'tom'       => 'primary',
            'preheader' => 'Use o link para criar uma nova senha.',
        ]);
        $mail->setTo($email, $dados['username']);
        $mail->setSubject('Redefinição de senha');

        if (!$mail->send(false)) {
            log_message('error', '[Senha] Falha ao enviar e-mail de redefinição: ' . $mail->printDebugger(['headers']));
        }
    }

    /** Define a nova senha a partir do link recebido por e-mail. */
    public function redefinirSenha(string $email, string $token, string $novaSenha): bool
    {
        return $this->repository->redefinirSenhaComToken($email, $token, $novaSenha);
    }

    private function limparCacheSite(): void
    {
        try {
            \App\Services\Site\SiteCache::limpar();
        } catch (\Throwable $e) {
            log_message('warning', 'Não foi possível limpar o cache do site: ' . $e->getMessage());
        }
    }

    /** Caminho da foto de um membro da equipe exibido no site; null se ele não for público. */
    public function getFotoEquipeSite(int $id): ?string
    {
        foreach ($this->repository->getEquipeSite() as $membro) {
            if ($membro['id'] === $id && $membro['tem_foto']) {
                return $this->getUserImagePath($id);
            }
        }

        return null;
    }


    /**
     * Upload user photo.
     *
     * @param int $userId
     * @param UploadedFile $file
     * @throws Exception
     */
    private function uploadPhoto(int $userId, UploadedFile $file)
    {
        $tipoArquivo = $file->getClientMimeType();
        $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($tipoArquivo, $tiposPermitidos)) {
            throw new Exception('O arquivo enviado não é uma imagem válida');
        }

        if ($file->getSize() > 2097152) { // 2MB
            throw new Exception('A imagem deve ter menos de 2MB');
        }

        $newName = $file->getRandomName();
        $uploadDir = WRITEPATH . 'uploads/userImgs/' . $userId;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file->move($uploadDir, $newName);

        // Update UserImage through Repository
        $this->repository->saveUserImage($userId, $newName);
    }

    /**
     * Retorna usuários que participão do Rateio
     *
     * @return array
     */
    public function getParticipaRateio(): array
    {
        return $this->repository->getParticipaRateio();
    }


    public function getProfissionais(): array
    {
        return $this->repository->getProfissionais();
    }

    /**
     * Search users by username or email.
     *
     * @param string $term
     * @return array
     */
    public function search(string $term, bool $apenasResponsaveisTarefas = false): array
    {
        return $this->repository->search($term, 20, $apenasResponsaveisTarefas);
    }

    /** Usuários ativos habilitados como responsáveis por tarefas (id, username). */
    public function getResponsaveisTarefas(): array
    {
        return $this->repository->getResponsaveisTarefas();
    }

    public function podeSerResponsavelTarefas(int $id): bool
    {
        return $this->repository->podeSerResponsavelTarefas($id);
    }

    /** Membros marcados para aparecer em Quem Somos (formato da seção `equipe`). */
    public function getEquipeSite(): array
    {
        return $this->repository->getEquipeSite();
    }

    /**
     * Retorna o caminho completo da imagem de perfil do usuário.
     *
     * @param int $id ID do usuário.
     * @return string|null Caminho completo do arquivo ou null se não encontrada.
     */
    public function getUserImagePath(int $id): ?string
    {
        $userImage = $this->repository->getUserImage($id);

        if ($userImage === null) {
            return null;
        }

        $baseDir = WRITEPATH . 'uploads/userImgs/' . $id . '/';
        $caminhoImagem = $baseDir . $userImage['image_path'];

        // Proteção contra Path Traversal: canonicaliza o caminho
        $realPath = realpath($caminhoImagem);
        $realBaseDir = realpath($baseDir);

        // Verifica se o arquivo existe e se está dentro do diretório base permitido para o usuário
        // Adiciona DIRECTORY_SEPARATOR para evitar Partial Path Match (ex: /uploads/1 validando /uploads/11)
        if (!$realPath || !$realBaseDir || !str_starts_with($realPath, $realBaseDir . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realPath;
    }
}
