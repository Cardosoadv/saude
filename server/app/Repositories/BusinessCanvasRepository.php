<?php

namespace App\Repositories;

use App\Models\BusinessCanvasModel;
use App\Models\BusinessCanvasBlockModel;

/**
 * Repositório para o módulo de Business Model Canvas.
 * 
 * Centraliza o acesso aos dados do canvas e seus respectivos blocos,
 * utilizando o sistema de cache versionado da BaseRepository.
 */
class BusinessCanvasRepository extends BaseRepository
{
    /**
     * @var BusinessCanvasBlockModel Modelo para os blocos do canvas.
     */
    protected BusinessCanvasBlockModel $blockModel;

    /**
     * @var string Chave base para o sistema de cache.
     */
    protected $cacheKey = 'business_canvas';

    /**
     * Construtor do repositório.
     *
     * @param BusinessCanvasModel|null $model
     * @param BusinessCanvasBlockModel|null $blockModel
     */
    public function __construct(?BusinessCanvasModel $model = null, ?BusinessCanvasBlockModel $blockModel = null)
    {
        parent::__construct();
        $this->model = $model ?? new BusinessCanvasModel();
        $this->blockModel = $blockModel ?? new BusinessCanvasBlockModel();
    }

    /**
     * Busca todos os canvases de um usuário.
     *
     * @param int $userId ID do usuário.
     * @param string $status Status do canvas (default: 'active').
     * @return array
     */
    public function getAllByUser(int $userId, string $status = 'active'): array
    {
        return $this->remember("user_{$userId}_{$status}", function () use ($userId, $status) {
            return $this->model->where('user_id', $userId)
                                 ->where('status', $status)
                                 ->orderBy('created_at', 'DESC')
                                 ->findAll();
        });
    }

    /**
     * Busca um canvas completo, incluindo seus blocos de conteúdo.
     *
     * @param int $canvasId
     * @return array|null
     */
    public function getCanvasWithBlocks(int $canvasId): ?array
    {
        return $this->remember("full_{$canvasId}", function () use ($canvasId) {
            $canvas = $this->model->find($canvasId);

            if ($canvas) {
                $canvas = (array) $canvas;
                $blocks = $this->blockModel->getBlocksByCanvas($canvasId);
                $canvas['blocks'] = array_map(fn($b) => (array) $b, $blocks);
            }

            return $canvas;
        });
    }

    /**
     * Atualiza ou cria o conteúdo de um bloco específico dentro de um canvas.
     *
     * @param int $canvasId
     * @param string $blockType
     * @param string|null $content
     * @return bool
     */
    public function updateCanvasBlock(int $canvasId, string $blockType, ?string $content): bool
    {
        $result = $this->blockModel->updateBlock($canvasId, $blockType, $content);
        if ($result) {
            $this->clearCache();
        }
        return $result !== false;
    }

    /**
     * Retorna o ID do usuário proprietário do canvas.
     *
     * @param int $canvasId
     * @return int|null
     */
    public function getCanvasUserId(int $canvasId): ?int
    {
        return $this->remember("user_id_{$canvasId}", function () use ($canvasId) {
            $canvas = $this->model->select('user_id')->find($canvasId);
            return $canvas ? (int) $canvas->user_id : null;
        });
    }
}