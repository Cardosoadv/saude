<?php

declare(strict_types=1);

namespace App\Models\Financeiro;

use CodeIgniter\Model;

/**
 * Uma linha por arquivo de extrato enviado (importação).
 */
class FinanceiroExtratosImportacoesModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    public const FORMATOS = ['ofx'];
    public const STATUS   = ['processando', 'concluida', 'falhou'];

    protected $table            = 'fin_extratos_importacoes';
    protected $primaryKey       = 'id_importacao';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'conta_id',
        'formato',
        'arquivo_nome',
        'arquivo_hash',
        'periodo_inicio',
        'periodo_fim',
        'saldo_final_arquivo',
        'saldo_final_data',
        'total_lidos',
        'total_novos',
        'total_duplicados',
        'total_conflitos',
        'status',
        'enviado_por',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'id_importacao'       => 'int',
        'conta_id'            => 'int',
        'saldo_final_arquivo' => '?int',
        'total_lidos'         => 'int',
        'total_novos'         => 'int',
        'total_duplicados'    => 'int',
        'total_conflitos'     => 'int',
        'enviado_por'         => '?int',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'conta_id'         => 'required|integer',
        'formato'          => 'required|in_list[ofx]',
        'arquivo_nome'     => 'permit_empty|max_length[255]',
        'arquivo_hash'     => 'required|exact_length[64]|hex',
        'periodo_inicio'   => 'permit_empty|valid_date[Y-m-d]',
        'periodo_fim'      => 'permit_empty|valid_date[Y-m-d]',
        'total_lidos'      => 'permit_empty|is_natural',
        'total_novos'      => 'permit_empty|is_natural',
        'total_duplicados' => 'permit_empty|is_natural',
        'total_conflitos'  => 'permit_empty|is_natural',
        'status'           => 'permit_empty|in_list[processando,concluida,falhou]',
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $beforeDelete   = ['auditoriaDeletar'];
}
