<?php

namespace App\Models;

use CodeIgniter\Model;

class IndicesImportacoesModel extends Model
{
    protected $table            = 'indices_importacoes';
    protected $primaryKey       = 'id_importacao';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['indice_id', 'executado_em', 'status', 'novos_valores', 'mensagem'];

    protected $useTimestamps = false;
}
