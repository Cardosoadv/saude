<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\AtorMcp;
use App\Mcp\Support\McpContext;
use App\Services\TarefasService;
use App\Mcp\Support\ModulosMcp;
use App\Mcp\Support\PrivacidadeMcp;
use Mcp\Capability\Attribute\McpTool;

final class TarefasTool
{
    private TarefasService $tarefasService;

    public function __construct()
    {
        $this->tarefasService = new TarefasService();
    }

    /**
     * Lista tarefas com filtros (ex.: responsavel, status, processo_id, cliente_id).
     */
    #[McpTool(name: 'listar_tarefas')]
    public function listarTarefas(array $filtros = []): array
    {
        ModulosMcp::exigir('tarefas', 'tarefas.access');

        return PrivacidadeMcp::filtrarTarefas($this->tarefasService->listarTarefas($filtros));
    }

    /**
     * Cria uma tarefa em nome de um usuário real do escritório.
     *
     * Em chamadas de um chat de IA externo (token pessoal), `usuarioId` é
     * ignorado e sempre substituído pelo usuário autenticado — a IA nunca
     * decide em nome de quem agir. Só é obrigatório em chamadas internas
     * (GeminiService/Davinci, token de serviço), que o repassam explicitamente.
     */
    #[McpTool(name: 'criar_tarefa')]
    public function criarTarefa(
        string $tarefa,
        ?int $usuarioId = null,
        ?string $prazo = null,
        ?int $responsavel = null,
        ?int $processoId = null,
        ?int $clienteId = null,
        ?string $detalhes = null,
        int $prioridade = 2
    ): array {
        $usuarioId = McpContext::resolverUsuarioId($usuarioId);
        AtorMcp::assumir($usuarioId);
        ModulosMcp::exigir('tarefas', 'tarefas.access');
        PrivacidadeMcp::exigirAcesso('processo', $processoId);

        return $this->tarefasService->salvarTarefa([
            'tarefa'      => $tarefa,
            'prazo'       => $prazo,
            'responsavel' => $responsavel ?? $usuarioId,
            'processo_id' => $processoId,
            'cliente_id'  => $clienteId,
            'detalhes'    => $detalhes,
            'prioridade'  => $prioridade,
            'status'      => 1, // TODO: confirmar id do status "pendente" na tabela de status de tarefas
        ]);
    }
}
