<?php

declare(strict_types=1);

namespace App\Models\Financeiro;

use CodeIgniter\Model;

/**
 * Uma linha por operação bancária importada de um extrato.
 *
 * O índice único (conta_id, chave) é a garantia final de idempotência. A linha
 * excluída (soft delete) continua ocupando a chave, de propósito.
 */
class FinanceiroExtratosMovimentosModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    public const CHAVE_TIPOS      = ['fitid', 'hash'];
    public const STATUS           = ['pendente', 'conciliado', 'lancado', 'ignorado'];
    public const PAGAMENTO_TIPOS  = ['despesa', 'receita', 'transferencia'];

    protected $table            = 'fin_extratos_movimentos';
    protected $primaryKey       = 'id_movimento';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'conta_id',
        'importacao_id',
        'chave',
        'chave_tipo',
        'fitid',
        'data',
        'valor_centavos',
        'tipo_banco',
        'memo',
        'nome_contraparte',
        'status',
        'pagamento_tipo',
        'pagamento_id',
        'motivo_ignorado',
        'conciliado_por',
        'conciliado_em',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id_movimento'   => 'int',
        'conta_id'       => 'int',
        'importacao_id'  => 'int',
        'valor_centavos' => 'int',
        'pagamento_id'   => '?int',
        'conciliado_por' => '?int',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'conta_id'       => 'required|integer',
        'importacao_id'  => 'required|integer',
        'chave'          => 'required|exact_length[64]|hex',
        'chave_tipo'     => 'required|in_list[fitid,hash]',
        'fitid'          => 'permit_empty|max_length[255]',
        'data'           => 'required|valid_date[Y-m-d]',
        'valor_centavos' => 'required|integer',
        'tipo_banco'     => 'permit_empty|max_length[20]',
        'memo'           => 'permit_empty|max_length[500]',
        'nome_contraparte' => 'permit_empty|max_length[255]',
        'status'         => 'permit_empty|in_list[pendente,conciliado,lancado,ignorado]',
        'pagamento_tipo' => 'permit_empty|in_list[despesa,receita,transferencia]',
        'pagamento_id'   => 'permit_empty|integer',
        'motivo_ignorado' => 'permit_empty|max_length[255]',
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $beforeDelete   = ['auditoriaDeletar'];
}
