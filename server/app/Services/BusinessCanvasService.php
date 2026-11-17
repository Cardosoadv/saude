<?php

namespace App\Services;

use App\Repositories\BusinessCanvasRepository;
use Exception;

/**
 * Serviço responsável pela lógica de negócio do Business Model Canvas.
 * 
 * Gerencia validações, persistência e estrutura dos blocos do canvas.
 */
class BusinessCanvasService extends BaseService
{


    /**
     * Construtor do serviço.
     *
     * @param BusinessCanvasRepository|null $repository
     */
    public function __construct(?BusinessCanvasRepository $repository = null)
    {
        $this->repository = $repository ?? new BusinessCanvasRepository();
    }

    /**
     * Retorna todos os canvases ativos de um usuário.
     *
     * @param int $userId
     * @return array
     */
    public function getUserCanvases(int $userId): array
    {
        return $this->repository->getAllByUser($userId);
    }

    /**
     * Busca os detalhes de um canvas, incluindo seus blocos.
     *
     * @param int $canvasId
     * @return array|null
     */
    public function getCanvas(int $canvasId): ?array
    {
        return $this->repository->getCanvasWithBlocks($canvasId);
    }

    /**
     * Cria um novo canvas com validação de dados.
     *
     * @param int $userId
     * @param string $title
     * @param string|null $description
     * @return int
     * @throws Exception
     */
    public function createCanvas(int $userId, string $title, ?string $description = null): int
    {
        if (empty(trim($title))) {
            throw new Exception('O título do canvas é obrigatório.');
        }

        $data = [
            'user_id'     => $userId,
            'title'       => $title,
            'description' => $description,
            'status'      => 'active'
        ];

        return (int) $this->repository->create($data);
    }

    /**
     * Atualiza os dados de um canvas.
     *
     * @param int $canvasId
     * @param array $data
     * @param int $userId
     * @return bool
     */
    public function updateCanvas(int $canvasId, array $data, int $userId): bool
    {
        // Verifica se o título não está vazio se foi enviado
        if (isset($data['title']) && empty(trim($data['title']))) {
            return false;
        }

        return $this->repository->update($canvasId, $data);
    }

    /**
     * Exclui um canvas.
     *
     * @param int $canvasId
     * @param int $userId
     * @return bool
     */
    public function deleteCanvas(int $canvasId, int $userId): bool
    {
        return $this->repository->delete($canvasId);
    }

    /**
     * Salva ou atualiza o conteúdo de um bloco do canvas.
     *
     * @param int $canvasId
     * @param string $blockType
     * @param string|null $content
     * @param int $userId
     * @return bool
     */
    public function saveBlock(int $canvasId, string $blockType, ?string $content, int $userId): bool
    {
        $validBlocks = array_keys($this->getBlockStructure());
        if (!in_array($blockType, $validBlocks)) {
            return false;
        }

        return $this->repository->updateCanvasBlock($canvasId, $blockType, $content);
    }

    /**
     * Retorna o ID do usuário proprietário do canvas.
     *
     * @param int $canvasId
     * @return int|null
     */
    public function getCanvasUserId(int $canvasId): ?int
    {
        return $this->repository->getCanvasUserId($canvasId);
    }

    /**
     * Retorna a estrutura estática dos blocos do BMC.
     * 
     * Define nomes, descrições, cores e ícones para cada uma das 9 áreas.
     *
     * @return array
     */
    public function getBlockStructure(): array
    {
        return [
            'key_partners' => [
                'name'        => 'Parceiros-chave',
                'description' => 'Quem são seus parceiros e fornecedores?',
                'color'       => '#FF6B6B',
                'icon'        => 'fa-handshake'
            ],
            'key_activities' => [
                'name'        => 'Atividades-chave',
                'description' => 'Quais atividades são essenciais para sua proposta de valor?',
                'color'       => '#4ECDC4',
                'icon'        => 'fa-tasks'
            ],
            'key_resources' => [
                'name'        => 'Recursos-chave',
                'description' => 'Quais recursos são necessários para suas atividades?',
                'color'       => '#45B7D1',
                'icon'        => 'fa-database'
            ],
            'value_proposition' => [
                'name'        => 'Proposta de Valor',
                'description' => 'Qual problema você resolve? Que valor você entrega?',
                'color'       => '#96CEB4',
                'icon'        => 'fa-gem'
            ],
            'customer_relationships' => [
                'name'        => 'Relacionamento com Clientes',
                'description' => 'Como você interage com seus clientes?',
                'color'       => '#FFEAA7',
                'icon'        => 'fa-users'
            ],
            'channels' => [
                'name'        => 'Canais',
                'description' => 'Como você alcança seus clientes?',
                'color'       => '#DDA0DD',
                'icon'        => 'fa-bullhorn'
            ],
            'customer_segments' => [
                'name'        => 'Segmentos de Clientes',
                'description' => 'Para quem você cria valor?',
                'color'       => '#98D8C8',
                'icon'        => 'fa-user-tag'
            ],
            'cost_structure' => [
                'name'        => 'Estrutura de Custos',
                'description' => 'Quais são os principais custos do seu negócio?',
                'color'       => '#FFB347',
                'icon'        => 'fa-coins'
            ],
            'revenue_streams' => [
                'name'        => 'Fontes de Receita',
                'description' => 'Como seu negócio gera receita?',
                'color'       => '#6C5CE7',
                'icon'        => 'fa-chart-line'
            ]
        ];
    }
}