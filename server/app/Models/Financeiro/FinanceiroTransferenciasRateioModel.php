<?php

declare(strict_types=1);

namespace App\Models\Financeiro;

use CodeIgniter\Model;

/**
 * Modelo para a tabela de rateio de transferências financeiras.
 * v1.0.0
 */
class FinanceiroTransferenciasRateioModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    protected $table            = 'fin_transferencias_rateios';
    protected $primaryKey       = 'id_rateio';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'transferencia_id',
        'user_id',
        'valor',
    ];

    // Dates
    protected $useTimestamps = false;

    // Validation rules
    protected $validationRules = [
        'transferencia_id' => 'required|integer',
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
