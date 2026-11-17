<?php

namespace App\Models;

use CodeIgniter\Model;

class NewsModel extends Model
{
    use \App\Traits\NotificaAlteracaoSiteTrait;

    protected $table            = 'site_news';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'category_id',
        'author_id',
        'title',
        'slug',
        'summary',
        'content',
        'meta_title',
        'meta_description',
        'status',
        'published_at'
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
        'id'               => 'permit_empty|is_natural_no_zero',
        'category_id'      => 'required|integer|is_not_unique[site_news_categories.id]',
        'author_id'        => 'required|integer',
        'title'            => 'required|max_length[255]',
        'slug'             => 'required|max_length[255]|is_unique[site_news.slug,id,{id}]',
        'summary'          => 'required|max_length[500]',
        'content'          => 'required',
        'meta_title'       => 'permit_empty|max_length[255]',
        'meta_description' => 'permit_empty|max_length[160]',
        'status'           => 'in_list[draft,published]',
        'published_at'     => 'permit_empty|valid_date[Y-m-d H:i:s]'
    ];
    protected $validationMessages   = [
        'category_id' => [
            'is_not_unique' => 'Categoria não encontrada.',
        ],
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = ['notificaAlteracaoSite'];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = ['notificaAlteracaoSite'];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = ['notificaAlteracaoSite'];
}
