<?php

declare(strict_types=1);

namespace App\Models\Site;

use App\Traits\AuditoriaTrait;
use CodeIgniter\Model;

/**
 * Menu do site por local (cabecalho, rodape, lateral_areas…).
 */
class SiteMenuModel extends Model
{
    use AuditoriaTrait;

    protected $table            = 'site_menus';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['local'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'id' => 'permit_empty|is_natural_no_zero',
        'local' => 'required|alpha_dash|max_length[40]|is_unique[site_menus.local,id,{id}]',
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $beforeDelete   = ['auditoriaDeletar'];
}
