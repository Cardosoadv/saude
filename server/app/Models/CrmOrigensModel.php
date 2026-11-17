<?php

namespace App\Models;

use CodeIgniter\Model;

class CrmOrigensModel extends Model
{
    protected $table = 'crm_origens';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $allowedFields = ['nome', 'icone'];
    protected $useTimestamps = true;
}
