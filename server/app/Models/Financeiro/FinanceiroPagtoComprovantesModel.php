<?php

declare(strict_types=1);

namespace App\Models\Financeiro;

use CodeIgniter\Model;

/**
 * Modelo para a tabela de comprovantes de pagamento.
 *
 * Cada registro aponta para um arquivo no Google Drive, anexado a um pagamento
 * de despesa ou de receita (`tipo` + `pagamento_id`).
 */
class FinanceiroPagtoComprovantesModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    public const TIPOS = ['despesa', 'receita'];

    protected $table            = 'fin_pgto_comprovantes';
    protected $primaryKey       = 'id_comprovante';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields    = [
        'tipo',
        'pagamento_id',
        'nome',
        'mime_type',
        'tamanho',
        'drive_id',
        'drive_link',
        'pasta_mes',
        'enviado_por',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules = [
        'tipo'         => 'required|in_list[despesa,receita]',
        'pagamento_id' => 'required|integer',
        'nome'         => 'required|max_length[255]',
        'drive_id'     => 'required|max_length[255]',
        'drive_link'   => 'required|max_length[500]',
        'pasta_mes'    => 'required|exact_length[7]',
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $beforeDelete   = ['auditoriaDeletar'];
}
