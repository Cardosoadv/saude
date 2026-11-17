<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Suspensões de expediente e de prazos registradas por tribunal.
 */
class SuspensoesExpedienteModel extends Model
{
    protected $table            = 'suspensoes_expediente';
    protected $primaryKey       = 'id_suspensao';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['tribunal', 'data_inicio', 'data_fim', 'motivo'];
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $validationRules = [
        'data_inicio' => 'required|valid_date[Y-m-d]',
        'data_fim'    => 'required|valid_date[Y-m-d]',
        'motivo'      => 'required|max_length[250]',
        'tribunal'    => 'permit_empty|max_length[20]',
    ];
}
