<?php

namespace App\Models;

use CodeIgniter\Model;

class ArgumentosModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    protected $table            = 'argumentos';
    protected $primaryKey       = 'id_argumento';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'titulo',
        'ramo_id',
        'estrategia_recomendada',
        'teses_relacionadas',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Callbacks for audit
    protected $afterInsert  = ['auditoriaNovo'];
    protected $beforeUpdate = ['auditoriaAtualizar'];
    protected $beforeDelete = ['auditoriaDeletar'];
}
