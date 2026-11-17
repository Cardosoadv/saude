<?php

namespace App\Models;

use CodeIgniter\Model;

class LiquidacoesDocumentosModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    protected $table            = 'liquidacoes_documentos';
    protected $primaryKey       = 'documento_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'processo_id',
        'liquidacao_id',
        'tipo_documento',
        'nome_arquivo',
        'url_arquivo',
        'data_upload',
        'usuario_id',
        'drive_id',
        'mime_type',
        'tamanho',
        'pagamento_id',
    ];

    // Dates
    protected $useTimestamps = false;

    // Validation
    protected $validationRules      = [
        'tipo_documento' => 'required|max_length[50]',
        'nome_arquivo'   => 'required|max_length[255]',
        'url_arquivo'    => 'required|max_length[255]',
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
    protected $beforeDelete   = ['auditoriaDeletar'];
    protected $afterDelete    = [];

    public function getTiposDocumento(): array
    {
        return [
            'sentenca'    => 'Sentença',
            'calculos'    => 'Cálculos',
            'comprovante' => 'Comprovante',
            'outros'      => 'Outros',
        ];
    }
}
