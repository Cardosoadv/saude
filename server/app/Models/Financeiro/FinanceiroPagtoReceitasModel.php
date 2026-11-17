<?php

declare(strict_types=1);

namespace App\Models\Financeiro;

use CodeIgniter\Model;

/**
 * Modelo para a tabela de recebimento de receitas.
 *
 * Este modelo gerencia as operações de banco de dados para a tabela 'fin_pgto_receitas',
 * registrando os recebimentos efetuados, vinculando-os às receitas e contas.
 */
class FinanceiroPagtoReceitasModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    protected $table            = 'fin_pgto_receitas';
    protected $primaryKey       = 'id_pgto_receita';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields    = [
        'pagamento_receita_dt',
        'valor',
        'receita_id',
        'conta_id',
        'comentario',
        'rateio',
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
        'pagamento_receita_dt' => 'required|valid_date',
        'valor'                => 'required|numeric',
        'receita_id'           => 'required|integer',
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
