<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Arquivos de contratos guardados no Google Drive (um registro por arquivo).
 */
class ContratosArquivosModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    public const CATEGORIAS = ['Contrato original', 'Aditivo', 'Anexo', 'Outros'];

    protected $table            = 'contratos_arquivos';
    protected $primaryKey       = 'id_contrato_arquivo';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields    = [
        'contrato_id',
        'categoria',
        'nome',
        'mime_type',
        'tamanho',
        'drive_id',
        'drive_link',
        'enviado_por',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'contrato_id' => 'required|integer',
        'categoria'   => 'required|in_list[Contrato original,Aditivo,Anexo,Outros]',
        'nome'        => 'required|max_length[255]',
        'drive_id'    => 'required|max_length[255]',
        'drive_link'  => 'required|max_length[500]',
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeUpdate   = ['auditoriaAtualizar'];
    protected $beforeDelete   = ['auditoriaDeletar'];
}
