<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para a tabela de mensagens.
 *
 * Este modelo gerencia as operações de banco de dados para a tabela 'mensagens',
 * permitindo o envio, leitura e listagem de mensagens entre usuários, com auditoria.
 *
 * @package App\Models
 * @version 1.0.0 Beta
 * @since   2025-12-21
 */
class MensagensModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    /**
     * Nome da tabela no banco de dados.
     *
     * @var string
     */
    protected $table = 'mensagens';

    /**
     * Chave primária da tabela.
     *
     * @var string
     */
    protected $primaryKey = 'id';

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
    protected $returnType = 'array';

    /**
     * Indica se deve usar soft deletes (exclusão lógica).
     *
     * @var bool
     */
    protected $useSoftDeletes = false;

    /**
     * Indica se os campos devem ser protegidos contra inserção em massa.
     *
     * @var bool
     */
    protected $protectFields = true;

    /**
     * Campos permitidos para inserção e atualização.
     *
     * @var list<string>
     */
    protected $allowedFields = [
        'remetente_id',
        'destinatario_id',
        'processo_id',
        'conteudo',
        'data_envio',
        'data_leitura',
        'assunto',
    ];

    /**
     * Permite inserções vazias.
     *
     * @var bool
     */
    protected bool $allowEmptyInserts = false;

    /**
     * Atualiza apenas os campos alterados.
     *
     * @var bool
     */
    protected bool $updateOnlyChanged = true;

    /**
     * Definições de casting para os atributos do modelo.
     *
     * @var array<string, string>
     */
    protected array $casts = [
        'remetente_id'    => 'int',
        'destinatario_id' => 'int',
        'processo_id'     => '?int',
        'id'              => 'int',
    ];

    /**
     * Indica se deve usar timestamps (created_at, updated_at).
     *
     * @var bool
     */
    protected $useTimestamps = false;

    /**
     * Formato de data utilizado no banco de dados.
     *
     * @var string
     */
    protected $dateFormat = 'datetime';

    /**
     * Nome do campo de data de criação.
     *
     * @var string
     */
    protected $createdField = 'created_at';

    /**
     * Nome do campo de data de atualização.
     *
     * @var string
     */
    protected $updatedField = 'updated_at';

    /**
     * Nome do campo de data de exclusão.
     *
     * @var string
     */
    protected $deletedField = 'deleted_at';

    /**
     * Regras de validação.
     *
     * @var array<string, string|string[]>
     */
    protected $validationRules = [
        'remetente_id'    => 'required|integer',
        'destinatario_id' => 'required|integer',
        'assunto'         => 'required|max_length[255]',
        'conteudo'        => 'required',
    ];

    /**
     * Mensagens de validação personalizadas.
     *
     * @var array<string, array<string, string>>
     */
    protected $validationMessages = [];

    /**
     * Indica se deve pular a validação.
     *
     * @var bool
     */
    protected $skipValidation = false;

    /**
     * Permite o uso de callbacks de eventos do modelo.
     *
     * @var bool
     */
    protected $allowCallbacks = true;

    /**
     * Callbacks executados antes da inserção.
     *
     * @var list<string>
     */
    protected $beforeInsert = [];

    /**
     * Callbacks executados após da inserção.
     *
     * @var list<string>
     */
    protected $afterInsert = ['auditoriaNovo'];

    /**
     * Callbacks executados antes da atualização.
     *
     * @var list<string>
     */
    protected $beforeUpdate = ['auditoriaAtualizar'];

    /**
     * Callbacks executados antes da exclusão.
     *
     * @var list<string>
     */
    protected $beforeDelete = ['auditoriaDeletar'];
}
