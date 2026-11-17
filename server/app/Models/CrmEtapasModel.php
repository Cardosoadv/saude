<?php

namespace App\Models;

use CodeIgniter\Model;

class CrmEtapasModel extends Model
{
    protected $table = 'crm_etapas';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $allowedFields = ['nome', 'cor', 'ordem'];
    protected $useTimestamps = true;
}
