<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Feriados (nacionais, estaduais e municipais) usados no calendário forense.
 */
class FeriadosModel extends Model
{
    protected $table            = 'feriados';
    protected $primaryKey       = 'id_feriado';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['data', 'nome', 'abrangencia', 'uf', 'municipio', 'tribunal', 'recorrente'];
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $validationRules = [
        'data'        => 'required|valid_date[Y-m-d]',
        'nome'        => 'required|max_length[150]',
        'abrangencia' => 'required|in_list[nacional,estadual,municipal]',
        'uf'          => 'permit_empty|exact_length[2]',
        'municipio'   => 'permit_empty|max_length[120]',
        'tribunal'    => 'permit_empty|max_length[20]',
    ];
}
