<?php

namespace App\Models;

use CodeIgniter\Model;

class IndicesEconomicosModel extends Model
{
    protected $table            = 'indices_economicos';
    protected $primaryKey       = 'id_indice';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['codigo', 'nome', 'periodicidade', 'fonte', 'codigo_serie', 'ativo'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'codigo'        => 'required|max_length[30]|alpha_dash',
        'nome'          => 'required|max_length[120]',
        'periodicidade' => 'required|in_list[mensal,diaria]',
        'fonte'         => 'required|in_list[sgs,manual,csv]',
        'codigo_serie'  => 'permit_empty|is_natural_no_zero',
        'ativo'         => 'permit_empty|in_list[0,1]',
    ];
    protected $cleanValidationRules = true;
}
