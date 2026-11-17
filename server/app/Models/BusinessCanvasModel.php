<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Traits\AuditoriaTrait;

/**
 * Modelo para o Business Model Canvas.
 * 
 * Gerencia os metadados do canvas (título, descrição, status).
 */
class BusinessCanvasModel extends Model
{
    use AuditoriaTrait;

    protected $table            = 'business_canvas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id', 
        'title', 
        'description', 
        'status'
    ];

    // Timestamps
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validação
    protected $validationRules = [
        'title'   => 'required|min_length[3]|max_length[255]',
        'user_id' => 'required|integer'
    ];

    // Callbacks de Auditoria
    protected $afterInsert   = ['auditoriaNovo'];
    protected $beforeUpdate  = ['auditoriaAtualizar'];
    protected $beforeDelete  = ['auditoriaDeletar'];
}