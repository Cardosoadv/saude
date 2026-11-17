<?php

declare(strict_types=1);

namespace App\Models\Site;

use App\Traits\AuditoriaTrait;
use App\Traits\NotificaAlteracaoSiteTrait;
use CodeIgniter\Model;

/**
 * Páginas do site. O conteúdo fica nas seções (SiteSecaoModel).
 */
class SitePaginaModel extends Model
{
    use AuditoriaTrait;
    use NotificaAlteracaoSiteTrait;

    protected $table            = 'site_paginas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = ['slug', 'titulo', 'tipo', 'layout', 'meta_title', 'meta_description', 'og_image_id', 'status', 'publicado_em', 'ordem', 'exibir_sitemap', 'prioridade_sitemap', 'changefreq'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'id' => 'permit_empty|is_natural_no_zero',
        'slug' => 'required|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]|max_length[120]|is_unique[site_paginas.slug,id,{id}]',
        'titulo' => 'required|max_length[255]',
        'tipo' => 'permit_empty|in_list[home,pagina,area,lista_noticias,sistema]',
        'layout' => 'permit_empty|max_length[60]',
        'meta_title' => 'permit_empty|max_length[255]',
        'meta_description' => 'permit_empty|max_length[160]',
        'status' => 'permit_empty|in_list[rascunho,publicado]',
        'changefreq' => 'permit_empty|in_list[always,hourly,daily,weekly,monthly,yearly,never]',
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
