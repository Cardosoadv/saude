<?php

declare(strict_types=1);

namespace App\Models\Financeiro;

use CodeIgniter\Model;

/**
 * Regras de classificação de movimentos de extrato. Só geram sugestão.
 */
class FinanceiroExtratosRegrasModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    public const CAMPOS = ['contraparte', 'memo', 'ambos'];
    public const SINAIS = ['entrada', 'saida', 'ambos'];

    protected $table            = 'fin_extratos_regras';
    protected $primaryKey       = 'id_regra';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'nome',
        'conta_id',
        'campo',
        'padrao',
        'sinal',
        'fornecedor_id',
        'cliente_id',
        'categoria_id',
        'rateio',
        'ativo',
        'criado_por',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id_regra'      => 'int',
        'conta_id'      => '?int',
        'fornecedor_id' => '?int',
        'cliente_id'    => '?int',
        'categoria_id'  => '?int',
        'ativo'         => 'int',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'nome'          => 'required|max_length[100]',
        'conta_id'      => 'permit_empty|integer',
        'campo'         => 'required|in_list[contraparte,memo,ambos]',
        'padrao'        => 'required|min_length[3]|max_length[255]',
        'sinal'         => 'required|in_list[entrada,saida,ambos]',
        'fornecedor_id' => 'permit_empty|integer',
        'cliente_id'    => 'permit_empty|integer',
        'categoria_id'  => 'permit_empty|integer',
        'ativo'         => 'permit_empty|in_list[0,1]',
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $beforeDelete   = ['auditoriaDeletar'];
}
