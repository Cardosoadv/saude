<?php

namespace App\Models;

use CodeIgniter\Model;

class LiquidacoesHistoricoCalculosModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    protected $table            = 'liquidacoes_historico_calculos';
    protected $primaryKey       = 'historico_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'item_id',
        'data_calculo',
        'valor_anterior',
        'valor_novo',
        'usuario_id',
        'motivo_alteracao'
    ];

    // Dates
    protected $useTimestamps = false;

    // Validation
    protected $validationRules      = [
        'item_id' => 'required|integer',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    // Audit might be redundant if this table IS an audit log, but keeping trait for consistency
    protected $beforeInsert   = [];
    protected $afterInsert    = []; // No need to audit the history log itself usually
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];
}
