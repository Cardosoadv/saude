<?php

namespace App\Models;

use CodeIgniter\Model;

class PrazoEstrategicoModel extends Model
{
    protected $table            = 'kb_prazos_estrategicos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'titulo', 'prazo_legal', 'prazo_seguranca', 'descricao', 'status'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'titulo'          => 'required|min_length[3]|max_length[150]',
        'prazo_legal'     => 'required|numeric',
        'prazo_seguranca' => 'required|numeric'
    ];
}
