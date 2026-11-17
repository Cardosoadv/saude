<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para a tabela de advogados em intimações.
 *
 * Este modelo gerencia as operações de banco de dados para a tabela 'intimacoes_advogados',
 * relacionando advogados às intimações recebidas. Implementa auditoria de alterações.
 */
class IntimacoesAdvogadosModel extends Model
{
    use \App\Traits\AuditoriaTrait;
    /**
     * Nome da tabela no banco de dados.
     *
     * @var string
     */
    protected $table            = 'intimacoes_advogados';

    /**
     * Chave primária da tabela.
     *
     * @var string
     */
    protected $primaryKey       = 'id_pk';

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
        'id_pk',
        'id',
        'comunicacao_id',
        'advogado_id',
        'advogado_nome',
        'advogado_oab',
        'advogado_oab_uf',
        'created_at',
        'updated_at',
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
     * @var array
     */
    protected array $casts = [];

    /**
     * Handlers personalizados para casting.
     *
     * @var array
     */
    protected array $castHandlers = [];

    // Dates

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
    protected $dateFormat    = 'datetime';

    /**
     * Nome do campo de data de criação.
     *
     * @var string
     */
    protected $createdField  = 'created_at';

    /**
     * Nome do campo de data de atualização.
     *
     * @var string
     */
    protected $updatedField  = 'updated_at';

    /**
     * Nome do campo de data de exclusão.
     *
     * @var string
     */
    protected $deletedField  = 'deleted_at';

    // Validation

    /**
     * Regras de validação.
     *
     * @var array
     */
    protected $validationRules      = [];

    /**
     * Mensagens de validação personalizadas.
     *
     * @var array
     */
    protected $validationMessages   = [];

    /**
     * Indica se deve pular a validação.
     *
     * @var bool
     */
    protected $skipValidation       = false;

    /**
     * Indica se as regras de validação devem ser limpas após a execução.
     *
     * @var bool
     */
    protected $cleanValidationRules = true;

    // Callbacks

    /**
     * Permite o uso de callbacks de eventos do modelo.
     *
     * @var bool
     */
    protected $allowCallbacks = true;

    /**
     * Callbacks executados antes da inserção.
     *
     * @var array
     */
    protected $beforeInsert   = [];

    /**
     * Callbacks executados após a inserção.
     *
     * @var array
     */
    protected $afterInsert    = ['auditoriaNovo'];

    /**
     * Callbacks executados antes da atualização.
     *
     * @var array
     */
    protected $beforeUpdate   = ['auditoriaAtualizar'];

    /**
     * Callbacks executados após a atualização.
     *
     * @var array
     */
    protected $afterUpdate    = [];

    /**
     * Callbacks executados antes de buscar registros.
     *
     * @var array
     */
    protected $beforeFind     = [];

    /**
     * Callbacks executados após buscar registros.
     *
     * @var array
     */
    protected $afterFind      = [];

    /**
     * Callbacks executados antes da exclusão.
     *
     * @var array
     */
    protected $beforeDelete   = ['auditoriaDeletar'];

    /**
     * Callbacks executados após a exclusão.
     *
     * @var array
     */
    protected $afterDelete    = [];


    /**
     * Busca advogados associados a um número de processo através das intimações.
     *
     * @param string $numero_processo Número do processo.
     * @return array Lista de advogados (nome, OAB, UF) encontrados nas intimações do processo.
     */
    public function getAdvogadosPorNumeroProcesso($numero_processo)
    {

        $data = $this->db
            ->table($this->table . ' as a')
            ->select('a.advogado_nome, a.advogado_oab, a.advogado_oab_uf')
            ->distinct()
            ->join('intimacoes as i', 'a.comunicacao_id = i.id_intimacao', 'left')
            ->where('i.numero_processo', $numero_processo)
            ->get()->getResultArray();
        return $data;
    }
}
