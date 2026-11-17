<?php

namespace App\Models;

use CodeIgniter\Model;

class CrmContactModel extends Model
{
    protected $table            = 'site_crm_contacts';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'email',
        'phone',
        'message',
        'area_interesse',
        'status',
        'privacidade_aceite_em',
        'privacidade_versao',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [
        'name'           => 'required|max_length[100]',
        'email'          => 'required|valid_email|max_length[100]',
        'phone'          => 'max_length[20]',
        'message'        => 'required',
        'area_interesse' => 'required|integer',
        'status'         => 'in_list[new,contacted,qualified,converted,lost]',
        'privacidade_aceite_em' => 'permit_empty|valid_date[Y-m-d H:i:s]',
        'privacidade_versao'    => 'permit_empty|max_length[20]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];
}
