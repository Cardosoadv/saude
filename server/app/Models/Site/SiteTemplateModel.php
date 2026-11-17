<?php

declare(strict_types=1);

namespace App\Models\Site;

use App\Traits\AuditoriaTrait;
use CodeIgniter\Model;

/**
 * Templates instalados do site. Apenas um fica ativo (ver SiteTemplateRepository::ativar).
 */
class SiteTemplateModel extends Model
{
    use AuditoriaTrait;

    protected $table            = 'site_templates';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['slug', 'nome', 'versao', 'autor', 'descricao', 'manifest', 'origem', 'ativo'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'id' => 'permit_empty|is_natural_no_zero',
        'slug' => 'required|alpha_dash|max_length[60]|is_unique[site_templates.slug,id,{id}]',
        'nome' => 'required|max_length[100]',
        'versao' => 'required|max_length[20]',
        'origem' => 'permit_empty|in_list[core,upload,cli]',
        'ativo' => 'permit_empty|in_list[0,1]',
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $beforeDelete   = ['auditoriaDeletar'];
}
