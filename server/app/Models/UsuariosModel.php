<?php

namespace App\Models;

use CodeIgniter\Shield\Models\UserModel;

/**
 * Modelo de usuários.
 * @version 0.9.0
 * @since 2025-12-20
 *
 * Estende o UserModel do CodeIgniter Shield para gerenciar usuários do sistema,
 * adicionando campos específicos como 'is_profissional', 'registro_numero' e métodos auxiliares.
 */
class UsuariosModel extends UserModel
{
    use \App\Traits\AuditoriaTrait;
    /**
     * Nome da tabela no banco de dados.
     *
     * @var string
     */
    protected $table            = 'users';

    /**
     * Chave primária da tabela.
     *
     * @var string
     */
    protected $primaryKey       = 'id';

    /**
     * Indica se a chave primária é auto-incrementável.
     *
     * @var bool
     */
    protected $useAutoIncrement = true;

    /**
     * Tipo de retorno dos resultados (array ou object).
     *
     * @var string
     */
    protected $returnType       = 'array';

    /**
     * Indica se deve usar soft deletes (exclusão lógica).
     *
     * @var bool
     */
    protected $useSoftDeletes   = false;

    /**
     * Indica se os campos devem ser protegidos contra inserção em massa.
     *
     * @var bool
     */
    protected $protectFields    = true;

    /**
     * Campos permitidos para inserção e atualização.
     *
     * @var array
     */
    protected $allowedFields    = [
        // Campos do Shield (sem eles o `active` informado no cadastro seria ignorado)
        'username',
        'status',
        'status_message',
        'active',
        'last_active',
        'deleted_at',
        'is_profissional',
        'rateio_ativo',
        'registro_numero',
        'registro_uf',
        'avatar',
        'responsavel_tarefas',
        'exibir_site',
        'site_ordem',
        'cargo',
        'formacao',
        'especializacoes',
        'areas_atuacao',
        'curriculo',
        'lattes_url',
        'linkedin_url',
    ];
    // Callbacks

    /**
     * Callbacks executados após a inserção.
     *
     * @var array
     */
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeDelete   = ['auditoriaDeletar'];
    protected $afterDelete    = [];

    /**
     * Retorna uma lista de profissionais.
     *
     * @return array Lista de usuários marcados como profissionais (is_profissional = 1).
     */
    public function getProfissionais()
    {
        return $this->where('is_profissional', 1)->findAll();
    }

    /**
     * Retorna uma lista de usuários simplificada, ordenada por nome de usuário.
     * Otimizado: Realiza seleção e ordenação diretamente no banco de dados.
     *
     * @return array Lista de arrays com 'id' e 'username'.
     */
    public function getUsers(): array
    {
        return $this->select('id, username')
            ->orderBy('username', 'ASC')
            ->findAll();
    }

    /**
     * Retorna o nome do usuário pelo ID.
     *
     * @param int $id ID do usuário.
     * @return string Nome do usuário ou string vazia se não encontrado.
     */
    public function getUserName(int $id): string
    {
        $users = auth()->getProvider();
        $user = $users->findById($id);

        return $user->username ?? '';
    }

    /**
     * Retorna o e-mail do usuário pelo ID.
     *
     * @param int $id ID do usuário.
     * @return string E-mail do usuário ou string vazia se não encontrado.
     */
    public function getEmailById(int $id): string
    {
        $users = auth()->getProvider();
        $user = $users->findById($id);

        return $user->email ?? '';
    }

    /**
     * Encontra um usuário pelo ID.
     *
     * @param int $id ID do usuário.
     * @return \CodeIgniter\Shield\Entities\User|null Entidade do usuário ou null.
     */
    public function findUserById($id)
    {
        $users = auth()->getProvider();
        return $users->findById($id);
    }
}
