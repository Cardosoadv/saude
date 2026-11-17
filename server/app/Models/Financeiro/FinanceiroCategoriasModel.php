<?php

declare(strict_types=1);

namespace App\Models\Financeiro;

use CodeIgniter\Model;

/**
 * Modelo para a tabela de categorias financeiras.
 * 
 * @author Cardoso
 * @version 0.9.1
 * @since 2026-01-01
 *
 * Este modelo gerencia as operações de banco de dados para a tabela 'fin_categorias',
 * permitindo a criação, leitura, atualização e exclusão de categorias para classificar receitas e despesas.
 */
class FinanceiroCategoriasModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    protected $table            = 'fin_categorias';
    protected $primaryKey       = 'id_categoria';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields    = [
        'categoria',
        'categoria_pai_id',
        'elemento_id',
        'cor',
        'comentarios'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = false;
    // Actually simpler to just enable strict types and keep properties as is if not sure.
    // However, original had:
    // protected $useTimestamps = false;
    // protected $createdField  = 'created_at';
    // If useTimestamps is false, createdField is ignored.
    // I will look at ContasModel which had true.
    // I will toggle it to true? No, I should stick to original behavior unless I know schema.
    // Original line 105: protected $useTimestamps = false;
    // I will blindly keep it false, but formatted.

    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [
        'categoria' => 'required|string|max_length[255]',
        'cor'       => 'permit_empty|string|max_length[50]', // Assuming hex color or similar
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = ['auditoriaDeletar'];
    protected $afterDelete    = [];
}
