<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para gerenciar os tipos de tratamento de intimações.
 *
 * Este modelo interage com a tabela 'intimacoes_tipos_tratamento' e também
 * gerencia a associação entre intimações e seus tratamentos na tabela 'intimacoes_tratamento'.
 */
class IntimacoesTratamentoModel extends Model
{
    use \App\Traits\AuditoriaTrait;
    /**
     * Nome da tabela no banco de dados.
     *
     * @var string
     */
    protected $table            = 'intimacoes_tipos_tratamento';

    /**
     * Chave primária da tabela.
     *
     * @var string
     */
    protected $primaryKey       = 'id_tratamento';

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
        'tratamento',
        'id_tratamento',
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
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeDelete   = ['auditoriaDeletar'];
    protected $afterDelete    = [];

    /**
     * Retorna o tratamento de uma intimação.
     *
     * @param int $idIntimacao ID da intimação.
     * @return array Lista contendo as informações do tratamento associado à intimação.
     */
    public function getTratamentoIntimacao($idIntimacao)
    {
        return
            $this->db->table($this->table . 'as t')
            ->join('intimacoes_tratamento as it', 't.tratamento_id', '=', 'it.id_tratamento')
            ->where('t.intimacao_id =' . $idIntimacao)
            ->get()->getResultArray();
    }

    /**
     * Realiza o tratamento de uma intimação.
     *
     * Remove qualquer tratamento anterior e insere o novo tratamento para a intimação especificada.
     *
     * @param int $idIntimacao ID da intimação.
     * @param int $idTratamento ID do tipo de tratamento.
     * @return void
     */
    public function tratarIntimacao($idIntimacao, $idTratamento)
    {
        $this->db->table('intimacoes_tratamento')
            ->delete('intimacao_id = ' . $idIntimacao);

        $this->db
            ->table('intimacoes_tratamento')
            ->insert([
                'intimacao_id' => $idIntimacao,
                'tratamento_id' => $idTratamento,
            ]);
    }
}
