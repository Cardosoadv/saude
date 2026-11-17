<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Traits\AuditoriaTrait;

/**
 * Modelo para os blocos do Business Model Canvas.
 * 
 * Gerencia o conteúdo de cada uma das 9 áreas do canvas.
 */
class BusinessCanvasBlockModel extends Model
{
    use AuditoriaTrait;

    protected $table            = 'business_canvas_blocks';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'canvas_id', 
        'block_type', 
        'content', 
        'order_position'
    ];

    // Timestamps
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Callbacks de Auditoria
    protected $afterInsert   = ['auditoriaNovo'];
    protected $beforeUpdate  = ['auditoriaAtualizar'];
    protected $beforeDelete  = ['auditoriaDeletar'];

    /**
     * Busca os blocos de um canvas específico organizados por posição.
     *
     * @param int $canvasId
     * @return array
     */
    public function getBlocksByCanvas(int $canvasId): array
    {
        return $this->where('canvas_id', $canvasId)
                    ->orderBy('order_position', 'asc')
                    ->findAll();
    }

    /**
     * Atualiza ou insere o conteúdo de um bloco.
     *
     * @param int $canvasId
     * @param string $blockType
     * @param string|null $content
     * @return bool|int|string
     */
    public function updateBlock(int $canvasId, string $blockType, ?string $content)
    {
        $existing = $this->where('canvas_id', $canvasId)
                        ->where('block_type', $blockType)
                        ->first();

        if ($existing) {
            return $this->update($existing->id, ['content' => $content]);
        } else {
            return $this->insert([
                'canvas_id'      => $canvasId,
                'block_type'     => $blockType,
                'content'        => $content,
                'order_position' => $this->getOrderPosition($blockType)
            ]);
        }
    }

    /**
     * Retorna a posição padrão de cada tipo de bloco.
     *
     * @param string $blockType
     * @return int
     */
    private function getOrderPosition(string $blockType): int
    {
        $order = [
            'key_partners'           => 1,
            'key_activities'         => 2,
            'key_resources'          => 3,
            'value_proposition'      => 4,
            'customer_relationships' => 5,
            'channels'               => 6,
            'customer_segments'      => 7,
            'cost_structure'         => 8,
            'revenue_streams'        => 9
        ];
        return $order[$blockType] ?? 0;
    }
}