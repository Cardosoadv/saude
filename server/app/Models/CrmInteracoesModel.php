<?php

namespace App\Models;

use CodeIgniter\Model;

class CrmInteracoesModel extends Model
{
    protected $table = 'crm_interacoes';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $allowedFields = [
        'cliente_id',
        'tipo',
        'descricao',
        'data_interacao',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
