<?php

namespace App\Models\Financeiro;

use CodeIgniter\Model;
use App\Traits\AuditoriaTrait;

class SaldoMensalModel extends Model
{
    use AuditoriaTrait;

    protected $table            = 'fin_saldo_mensal';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'tipo',
        'conta_id',
        'ano',
        'mes',
        'saldo_acumulado',
        'fechado_em',
        'fechado_por',
        'status'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = ['auditoriaAtualizar'];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = ['auditoriaDeletar'];
}
