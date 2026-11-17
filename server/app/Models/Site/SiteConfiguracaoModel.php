<?php

declare(strict_types=1);

namespace App\Models\Site;

use App\Traits\AuditoriaTrait;
use CodeIgniter\Model;

/**
 * Configurações do site público (chave/valor tipado).
 */
class SiteConfiguracaoModel extends Model
{
    use AuditoriaTrait;

    protected $table            = 'site_configuracoes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['chave', 'valor', 'tipo', 'grupo'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'chave' => 'required|max_length[100]|is_unique[site_configuracoes.chave,id,{id}]',
        'valor' => 'permit_empty',
        'tipo' => 'permit_empty|in_list[string,text,json,imagem,cor,bool]',
        'grupo' => 'permit_empty|in_list[identidade,contato,redes,seo,integracoes,lgpd,tema,textos,noticias]',
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $beforeDelete   = ['auditoriaDeletar'];
}
