<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para a tabela de clientes.
 *
 * Este modelo gerencia as operações de banco de dados para a tabela 'clientes',
 * incluindo validação, auditoria de alterações (inserção, atualização, exclusão)
 * e métodos auxiliares para busca.
 */
class ClientesModel extends Model
{
    use \App\Traits\AuditoriaTrait;
    use \App\Traits\PrivacidadeEntidadeTrait;

    protected string $tipoEntidadeAcesso = 'cliente';
    /**
     * Timestamp atual (não utilizado diretamente pelo framework, mas pode ser útil para lógica customizada).
     *
     * @var int|null
     */
    protected $current_timestamp;

    /**
     * Nome da tabela no banco de dados.
     *
     * @var string
     */
    protected $table = 'clientes';

    /**
     * Chave primária da tabela.
     *
     * @var string
     */
    protected $primaryKey = 'id_cliente';

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
     * @var array
     */
    protected $allowedFields = [
        'tipo_cliente', // 'F = Física, J = Jurídica'
        'nome',
        'nacionalidade',
        'estado_civil',
        'profissao',
        'documento', // 'CPF para física, CNPJ para jurídica'
        'email',
        'telefone',
        'endereco',
        'numero',
        'complemento',
        'cep',
        'cidade',
        'uf',
        'razao_social',
        'ativo', // '1 = Sim, 0 = Não'
        'dataAquisicao',
        'data_nascimento',
        'pasta',
        'status_crm',
        'origem_id',
        'temperatura',
        'responsavel_id', // Advogado responsável (users.id). Não confundir com usuario_id (login do cliente na Área do Cliente)
        'visibilidade',
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

    // Datas

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

    // Validação

    /**
     * Regras de validação.
     *
     * @var array
     */
    protected $validationRules = [
        'responsavel_id' => [
            'rules'  => 'permit_empty|is_natural_no_zero|is_not_unique[users.id]',
            'errors' => ['is_not_unique' => 'Responsável não encontrado.'],
        ],
        'visibilidade' => [
            'rules'  => 'permit_empty|in_list[publico,restrito]',
            'errors' => ['in_list' => 'Visibilidade inválida.'],
        ],
    ];

    /**
     * Mensagens de validação personalizadas.
     *
     * @var array
     */
    protected $validationMessages = [];

    /**
     * Indica se deve pular a validação.
     *
     * @var bool
     */
    protected $skipValidation = false;

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
    protected $beforeInsert = ['definirResponsavelPadrao'];

    /**
     * Callbacks executados após a inserção.
     *
     * @var array
     */
    protected $afterInsert = ['auditoriaNovo', 'concederAcessoAoCriador'];

    /**
     * Callbacks executados antes da atualização.
     *
     * @var array
     */
    protected $beforeUpdate = ['auditoriaAtualizar'];

    /**
     * Callbacks executados após a atualização.
     *
     * @var array
     */
    protected $afterUpdate = [];

    /**
     * Callbacks executados antes de buscar registros.
     *
     * @var array
     */
    protected $beforeFind = [];

    /**
     * Callbacks executados após buscar registros.
     *
     * @var array
     */
    protected $afterFind = [];

    /**
     * Callbacks executados antes da exclusão.
     *
     * @var array
     */
    protected $beforeDelete = ['auditoriaDeletar'];

    /**
     * Callbacks executados após a exclusão.
     *
     * @var array
     */
    protected $afterDelete = [];

    /**
     * Verifica se um cliente já existe com base no documento.
     *
     * @param string $documento O documento do cliente (CPF ou CNPJ).
     * @return bool True se o cliente já existe, false caso contrário.
     */
    public function jaExisteCliente($documento): bool
    {
        return $this->where('documento', $documento)->countAllResults() > 0;
    }

    /**
     * Seleciona um cliente pelo nome ou razão social.
     *
     * @param string $nome O nome ou razão social do cliente.
     * @return array|null Retorna os dados do cliente ou null se não for encontrado.
     */
    public function selecionarClientePeloNome($nome)
    {
        return $this->where('nome', $nome)
            ->orWhere('razao_social', $nome)
            ->first();
    }
}
