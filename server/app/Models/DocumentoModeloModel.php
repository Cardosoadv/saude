<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo para a tabela de modelos de documentos.
 */
class DocumentoModeloModel extends Model
{
    use \App\Traits\AuditoriaTrait;

    protected $table            = 'doc_modelos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields = [
        'status', 'titulo', 'ramo_id', 'tipo_alvo', 'conteudo', 'placeholders',
        'ultima_revisao_em', 'alerta_vencimento', 'versao', 'ativo'
    ];

    // Validation
    protected $validationRules = [
        'titulo' => 'required|min_length[3]|max_length[255]',
        'conteudo' => 'required'
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
        // Para Model::save(), insert(), update()
        if (isset($data['data'])) {
            if (!isset($data['data']['placeholders']) || is_null($data['data']['placeholders'])) {
                $data['data']['placeholders'] = '[]';
            } elseif (is_array($data['data']['placeholders'])) {
                $data['data']['placeholders'] = json_encode($data['data']['placeholders']);
            }
        } elseif (isset($data['placeholders']) || array_key_exists('placeholders', $data)) {
             if (is_null($data['placeholders'])) {
                $data['placeholders'] = '[]';
            } elseif (is_array($data['placeholders'])) {
                $data['placeholders'] = json_encode($data['placeholders']);
            }
        }
        return $data;
    }
}
