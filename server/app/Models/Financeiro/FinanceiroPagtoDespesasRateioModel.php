<?php

declare(strict_types=1);

namespace App\Models\Financeiro;

use CodeIgniter\Model;

/**
 * Modelo para a tabela de rateio de pagamentos de despesas.
 * v1.0.0
 */
class FinanceiroPagtoDespesasRateioModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    protected $table            = 'fin_pgto_despesas_rateio';
    protected $primaryKey       = 'id_rateio';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'pagto_despesa_id',
        'user_id',
        'valor',
    ];

    // Dates
    protected $useTimestamps = false; // A migração não criou colunas de data para esta tabela

    // Validation rules
    protected $validationRules = [
        'pagto_despesa_id' => 'required|integer',
        'user_id'          => 'required|integer',
        'valor'            => 'required|numeric',
    ];

    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $beforeDelete   = ['auditoriaDeletar'];
}
