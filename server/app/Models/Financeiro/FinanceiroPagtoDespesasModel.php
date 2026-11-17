<?php

declare(strict_types=1);

namespace App\Models\Financeiro;

use CodeIgniter\Model;

/**
 * Modelo para a tabela de pagamento de despesas.
 *
 * Este modelo gerencia as operações de banco de dados para a tabela 'fin_pgto_despesas',
 * registrando os pagamentos efetuados, vinculando-os às despesas, contas e clientes.
 */
class FinanceiroPagtoDespesasModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    protected $table            = 'fin_pgto_despesas';
    protected $primaryKey       = 'id_pgto_despesa';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields    = [
        'pagamento_despesa_dt',
        'valor',
        'despesa_id',
        'conta_id',
        'comentario',
        'rateio',
        'cliente_reembolso',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules = [
        'pagamento_despesa_dt' => 'required|valid_date',
        'valor'                => 'required|numeric',
        'despesa_id'           => 'required|integer',
        'conta_id'             => 'permit_empty|integer',
        'rateio'               => 'permit_empty',
    ];

    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = ['auditoriaDeletar'];
    protected $afterDelete    = [];

}
