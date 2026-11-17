<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Services\AcessoEntidadeService;
use Mcp\Exception\ToolCallException;

/**
 * Privacidade granular nas Tools do MCP. O usuário é o assumido pelo
 * McpAuthFilter (token pessoal ou, no agente interno, quem pergunta).
 */
final class PrivacidadeMcp
{
    /**
     * @throws ToolCallException Registro inexistente ou restrito ao usuário.
     */
    public static function exigirAcesso(string $tipo, ?int $id): void
    {
        if ($id && !(new AcessoEntidadeService())->podeAcessar($tipo, $id)) {
            throw new ToolCallException('Registro não encontrado ou sem permissão de acesso.');
        }
    }

    /**
     * Remove intimações de processos que o usuário não vê.
     */
    public static function filtrarIntimacoes(array $intimacoes): array
    {
        $ocultos = (new AcessoEntidadeService())->processosOcultos();
        if ($ocultos === null) {
            return $intimacoes;
        }

        return array_values(array_filter(
            $intimacoes,
            static fn ($i) => !isset($ocultos['numeros'][(string) ($i['numero_processo'] ?? '')])
        ));
    }

    /**
     * Remove tarefas de processos que o usuário não vê, exceto as dele.
     */
    public static function filtrarTarefas(array $tarefas): array
    {
        $ocultos = (new AcessoEntidadeService())->processosOcultos();
        if ($ocultos === null) {
            return $tarefas;
        }

        $usuario = (int) user_id();

        return array_values(array_filter($tarefas, static function ($t) use ($ocultos, $usuario) {
            $processoId = (int) ($t['processo_id'] ?? 0);

            return !$processoId || !isset($ocultos['ids'][$processoId]) || (int) ($t['responsavel'] ?? 0) === $usuario;
        }));
    }
}
