<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Services\VisibilidadeModulosService;
use Mcp\Exception\ToolCallException;

/**
 * Módulos ativos e permissões nas Tools do MCP, com as mesmas regras da API.
 *
 * O usuário é o assumido pelo McpAuthFilter. Chamada interna sem usuário
 * (token de serviço) só respeita o módulo: não há de quem checar permissão.
 */
final class ModulosMcp
{
    /**
     * @throws ToolCallException Módulo desativado ou usuário sem permissão.
     */
    public static function exigir(string $slug, string ...$permissoes): void
    {
        if (!service('modulos')->isActive($slug)) {
            throw new ToolCallException('Este módulo está desativado.');
        }

        if (!self::visibilidade()->liberado($slug, ...$permissoes)) {
            throw new ToolCallException('Sem permissão de acesso a este módulo.');
        }
    }

    /**
     * Esvazia os blocos de módulos desativados ou sem permissão.
     *
     * @param array<string, list<string>> $blocos chave => [slug, permissão, ...]
     */
    public static function ocultarBlocos(array $dados, array $blocos): array
    {
        return self::visibilidade()->ocultarBlocos($dados, $blocos);
    }

    private static function visibilidade(): VisibilidadeModulosService
    {
        return new VisibilidadeModulosService(null, static function (string ...$permissoes): bool {
            $usuario = auth()->user();

            return $usuario === null || $usuario->can(...$permissoes);
        });
    }
}
