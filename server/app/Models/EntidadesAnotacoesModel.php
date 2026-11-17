<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * @autor Cardoso
 * @version 0.8.0
 * @since 2025-12-20
 * 
 * Modelo para a tabela de anotações de entidades (clientes/fornecedores).
 */
class EntidadesAnotacoesModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    protected $table            = 'entidades_anotacoes';
    protected $primaryKey       = 'id_anotacao';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'titulo',
        'anotacao',
        'privacidade', // 1 - Privado, 2 - Público/Compartilhado
        'user_id',
        'documento',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Callbacks
    protected $allowCallbacks = true;
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $beforeDelete   = ['auditoriaDeletar'];
}
