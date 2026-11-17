<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para documentos gerados a partir de modelos (rascunho e finalizado).
 */
class DocumentoGeradoModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    protected $table            = 'doc_documentos_gerados';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields = [
        'modelo_id', 'modelo_titulo_snapshot', 'titulo',
        'entidade_tipo', 'entidade_id', 'cliente_id', 'ramo_id',
        'conteudo_html', 'placeholders_utilizados', 'status',
        'documento_arquivo_id', 'finalizado_em', 'finalizado_por', 'created_by',
    ];

    // Validation
    protected $validationRules = [
        'titulo'        => 'required|max_length[255]',
        'entidade_tipo' => 'required|in_list[processo,caso]',
        'entidade_id'   => 'required|integer',
        'conteudo_html' => 'permit_empty|string',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['prepareData'];
    protected $beforeUpdate   = ['auditoriaAtualizar', 'prepareData'];
    protected $afterInsert    = ['auditoriaNovo'];
    protected $beforeDelete   = ['auditoriaDeletar'];

    protected array $casts = [];

    protected function prepareData(array $data)
    {
        // Mesmo padrão de DocumentoModeloModel::prepareData:
        // json_encode de placeholders_utilizados quando vier como array.
        if (isset($data['data'])) {
            if (is_array($data['data']['placeholders_utilizados'] ?? null)) {
                $data['data']['placeholders_utilizados'] = json_encode($data['data']['placeholders_utilizados']);
            }
        } elseif (array_key_exists('placeholders_utilizados', $data)) {
            if (is_array($data['placeholders_utilizados'])) {
                $data['placeholders_utilizados'] = json_encode($data['placeholders_utilizados']);
            }
        }
        return $data;
    }
}
