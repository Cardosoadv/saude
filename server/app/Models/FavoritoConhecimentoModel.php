<?php

namespace App\Models;

use CodeIgniter\Model;

class FavoritoConhecimentoModel extends Model
{
    protected $table            = 'kb_favoritos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id', 'entidade_type', 'entidade_id', 'anotacao_privada'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
