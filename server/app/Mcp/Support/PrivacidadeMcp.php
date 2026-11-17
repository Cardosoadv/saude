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
}
