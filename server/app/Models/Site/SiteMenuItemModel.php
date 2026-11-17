<?php

declare(strict_types=1);

namespace App\Models\Site;

use App\Traits\AuditoriaTrait;
use CodeIgniter\Model;

/**
 * Item de menu: aponta para uma página, uma URL ou uma âncora.
 */
class SiteMenuItemModel extends Model
{
    use AuditoriaTrait;

    protected $table            = 'site_menu_itens';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['menu_id', 'parent_id', 'rotulo', 'tipo', 'pagina_id', 'url', 'ancora', 'destaque', 'ordem'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'menu_id' => 'required|is_natural_no_zero|is_not_unique[site_menus.id]',
        'rotulo' => 'required|max_length[100]',
        'tipo' => 'permit_empty|in_list[pagina,url,ancora]',
        'url' => 'permit_empty|max_length[500]',
        'ancora' => 'permit_empty|max_length[60]',
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $beforeDelete   = ['auditoriaDeletar'];
}
