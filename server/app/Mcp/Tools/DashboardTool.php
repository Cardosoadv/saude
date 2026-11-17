<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\ClienteService;
use App\Services\CasosService;
use App\Services\TarefasService;
use Mcp\Capability\Attribute\McpTool;

/**
 * Indicadores agregados do escritório, para a IA responder perguntas
 * gerenciais sem precisar orquestrar várias tools de detalhe.
 */
final class DashboardTool
{
    private ClienteService $clienteService;
    private CasosService $casosService;
    private TarefasService $tarefasService;

    public function __construct()
    {
        $this->clienteService = new ClienteService();
        $this->casosService   = new CasosService();
        $this->tarefasService = new TarefasService();
    }

    #[McpTool(name: 'obter_indicadores_dashboard')]
    public function obterIndicadores(): array
    {
        return [
            'processos' => $this->casosService->obterEstatisticasGerais(),
            'clientes'  => $this->clienteService->obterEstatisticas(),
            'tarefas'   => $this->tarefasService->obterEstatisticas(),
        ];
    }
}
