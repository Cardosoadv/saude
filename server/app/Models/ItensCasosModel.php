<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para a tabela de itens de checklist vinculados a um caso.
 * 
 * @package App\Models
 */
class ItensCasosModel extends Model
{
    protected $table            = 'chk_itens_caso';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['checklist_id', 'secao_original_id', 'item_template_id', 'secao_titulo', 'item_titulo', 'observacao', 'cumprido', 'cumprido_em', 'cumprido_por', 'ordem', 'is_avulso', 'tarefa_id', 'created_by'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $deletedField  = '';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];
}
