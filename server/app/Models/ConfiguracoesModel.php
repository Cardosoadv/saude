<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Traits\AuditoriaTrait;

/**
 * Modelo para a tabela de configurações do sistema.
 * 
 * @version 1.0.0
 * @since 2026-05-09
 */
class ConfiguracoesModel extends Model
{
    use AuditoriaTrait;

    protected $table            = 'configuracoes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = ['nome', 'valor', 'descricao'];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [
        'id'    => 'permit_empty|is_natural_no_zero',
        'nome'  => 'required|max_length[100]|is_unique[configuracoes.nome,id,{id}]',
        'valor' => 'permit_empty',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $afterDelete    = ['auditoriaDeletar'];
}
