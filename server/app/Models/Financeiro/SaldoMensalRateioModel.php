<?php

namespace App\Models\Financeiro;

use CodeIgniter\Model;
use App\Traits\AuditoriaTrait;

class SaldoMensalRateioModel extends Model
{
    use AuditoriaTrait;

    protected $table            = 'fin_saldo_mensal_rateio';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'saldo_mensal_id',
        'user_id',
        'rateio_acumulado'
    ];

    // Dates
    protected $useTimestamps = false;

    // Callbacks
    protected $allowCallbacks = true;
    protected $afterInsert    = ['auditoriaNovo'];
    protected $afterUpdate    = ['auditoriaAtualizar'];
    protected $afterDelete    = ['auditoriaDeletar'];
}
