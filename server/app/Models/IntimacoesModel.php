<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para a tabela de intimações.
 *
 * Este modelo gerencia as operações de banco de dados para a tabela 'intimacoes',
 * incluindo verificação de duplicidade, sincronização e auditoria de alterações.
 */
class IntimacoesModel extends Model
{
    use \App\Traits\AuditoriaTrait;
    /**
     * Nome da tabela no banco de dados.
     *
     * @var string
     */
    protected $table            = 'intimacoes';

    /**
     * Chave primária da tabela.
     *
     * @var string
     */
    protected $primaryKey       = 'id_intimacao';

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
    protected $useSoftDeletes   = true;

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
        'id_intimacao',
        'data_disponibilizacao',
        'tipoComunicacao',
        'texto',
        'numero_processo',
        'meio',
        'link',
        'numeroComunicacao',
        'hash',
        'motivo_cancelamento',
        'data_cancelamento',
        'datadisponibilizacao',
        'dataenvio',
        'meiocompleto',
        'synchronize_at',
        'ai_categoria',
        'ai_resumo',
        'ai_data_critica',
        'ai_valor_condenacao',
        'ai_urgencia',
        'ai_score_confianca',
        'ai_partes_relevantes',
        'ai_detalhes_extracao',
        'ai_analisado_em',
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
    protected $useTimestamps = true;

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
     * Função para verificar se a intimação já consta do db.
     *
     * @param string $id ID da intimação.
     * @return bool True se a intimação já existe, false caso contrário.
     */
    public function intimacaoJaExiste(string $id): bool
    {
        $query = $this->db->table('intimacoes')
            ->select('id_intimacao')
            ->where('id_intimacao', $id)
            ->get();
        return $query->getRowArray() !== null;
    }

    /**
     * Busca intimações dentro de um período específico.
     *
     * @param string $dataInicial Data de início do período.
     * @param string $dataFinal Data de fim do período.
     * @return array Lista de intimações encontradas.
     */
    public function getIntimacoesdoPeriodo($dataInicial, $dataFinal)
    {
        $dtInicial = date('Y-m-d', strtotime($dataInicial));
        $dtFinal = date('Y-m-d', strtotime($dataFinal));
        $data = $this->where('data_disponibilizacao >=', $dtInicial)
            ->where('data_disponibilizacao <=', $dtFinal)
            ->orderBy('data_disponibilizacao', 'DESC')
            ->limit(10)
            ->get()->getResultArray();
        return $data;
    }

    /**
     * Realiza um join entre intimações, destinatários e processos.
     *
     * @return array Dados combinados de intimações, destinatários e processos.
     */
    public function joinTabelasProcesso()
    {
        $data = $this->db
            ->table('intimacoes_destinatario as d')
            ->join('intimacoes as i', 'd.comunicacao_id = i.id_intimacao', 'left')
            ->join('processos as p', 'p.numero_processo = i.numero_processo', 'left');
        return $data->get()->getResultArray();
    }


    /**
     * Registra a sincronização de uma intimação.
     *
     * @param int $id ID da intimação.
     * @return void
     */
    public function registrarSincronizacao(int $id): void
    {
        // Atualiza o campo de sincronização para a data atual
        $this->update($id, ['synchronize_at' => date('Y-m-d')]);
    }


}
