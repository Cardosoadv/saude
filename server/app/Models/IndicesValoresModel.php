<?php

namespace App\Models;

use CodeIgniter\Model;

class IndicesValoresModel extends Model
{
    protected $table            = 'indices_valores';
    protected $primaryKey       = 'id_valor';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['indice_id', 'competencia', 'valor', 'origem', 'importado_em'];

    protected $useTimestamps = false;

    protected $validationRules = [
        'indice_id'   => 'required|integer',
        'competencia' => 'required|valid_date[Y-m-d]',
        'valor'       => 'required|decimal',
    ];
    protected $cleanValidationRules = true;
}
