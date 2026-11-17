<?php

declare(strict_types=1);

namespace App\Models\Documentacao;

use App\Traits\AuditoriaTrait;
use CodeIgniter\Model;

/**
 * Páginas da documentação criadas pela interface (issue #710). Toda criação,
 * alteração (inclusive publicar/despublicar) e exclusão vai para a auditoria.
 */
class DocumentacaoPaginaModel extends Model
{
    use AuditoriaTrait;

    public const STATUS = ['rascunho', 'publicado'];

    protected $table            = 'documentacao_paginas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'slug', 'titulo', 'resumo', 'ordem', 'modulo', 'permissao', 'telas', 'palavras_chave',
        'markdown', 'status', 'publicado_em', 'criado_por', 'atualizado_por',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'id'     => 'permit_empty|is_natural_no_zero',
        'slug'   => 'required|max_length[160]|is_unique[documentacao_paginas.slug,id,{id}]',
        'titulo' => 'required|max_length[120]',
        'status' => 'permit_empty|in_list[rascunho,publicado]',
    ];
    protected $validationMessages = [
        'slug' => ['is_unique' => 'Já existe uma página com este endereço.'],
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $afterInsert  = ['auditoriaNovo'];
    protected $beforeUpdate = ['auditoriaAtualizar'];
    protected $beforeDelete = ['auditoriaDeletar'];
}
