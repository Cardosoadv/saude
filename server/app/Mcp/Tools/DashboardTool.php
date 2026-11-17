<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\ClienteService;
use App\Services\TarefasService;
use Mcp\Capability\Attribute\McpTool;

/**
 * Indicadores agregados da clínica, para a IA responder perguntas
 * gerenciais sem precisar orquestrar várias tools de detalhe.
 */
final class DashboardTool
{
    private ClienteService $clienteService;
    private TarefasService $tarefasService;

    public function __construct()
    {
        $this->clienteService = new ClienteService();
        $this->tarefasService = new TarefasService();
    }

    #[McpTool(name: 'obter_indicadores_dashboard')]
    public function obterIndicadores(): array
    {
        return [
            'clientes'  => $this->clienteService->obterEstatisticas(),
            'tarefas'   => $this->tarefasService->obterEstatisticas(),
        ];
    }
}
