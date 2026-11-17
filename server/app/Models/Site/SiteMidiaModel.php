<?php

declare(strict_types=1);

namespace App\Models\Site;

use App\Traits\AuditoriaTrait;
use CodeIgniter\Model;

/**
 * Biblioteca de mídias do site.
 */
class SiteMidiaModel extends Model
{
    use AuditoriaTrait;

    protected $table            = 'site_midias';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = ['arquivo', 'nome_original', 'mime', 'tamanho', 'largura', 'altura', 'alt'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'arquivo' => 'required|max_length[255]',
        'nome_original' => 'required|max_length[255]',
        'mime' => 'required|max_length[100]',
        'alt' => 'permit_empty|max_length[255]',
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $beforeDelete   = ['auditoriaDeletar'];
}
