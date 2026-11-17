<?php

declare(strict_types=1);

namespace App\Models\Site;

use App\Traits\AuditoriaTrait;
use App\Traits\NotificaAlteracaoSiteTrait;
use CodeIgniter\Model;

/**
 * Seção de uma página; `dados` guarda o JSON validado pelo schema do template.
 */
class SiteSecaoModel extends Model
{
    use AuditoriaTrait;
    use NotificaAlteracaoSiteTrait;

    protected $table            = 'site_secoes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = ['pagina_id', 'tipo', 'ancora', 'ordem', 'visivel', 'dados'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'pagina_id' => 'required|is_natural_no_zero|is_not_unique[site_paginas.id]',
        'tipo' => 'required|max_length[60]',
        'ancora' => 'permit_empty|regex_match[/^[a-z0-9_-]+$/]|max_length[60]',
        'dados' => 'permit_empty|valid_json',
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $afterInsert    = ['auditoriaNovo', 'notificaAlteracaoSite'];
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $afterUpdate    = ['notificaAlteracaoSite'];
    protected $beforeDelete   = ['auditoriaDeletar'];
    protected $afterDelete    = ['notificaAlteracaoSite'];
}
