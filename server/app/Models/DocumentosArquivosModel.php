<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentosArquivosModel extends Model
{
    use \App\Traits\AuditoriaTrait;
    protected $table            = 'documentos_arquivos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'nome',
        'descricao',
        'drive_id',
        'drive_link',
        'mime_type',
        'tamanho',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [
        'nome'       => 'required|max_length[255]',
        'drive_id'   => 'required|max_length[255]',
        'drive_link' => 'required|valid_url|max_length[500]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;
    protected $allowCallbacks = true;
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeDelete   = ['auditoriaDeletar'];
}
