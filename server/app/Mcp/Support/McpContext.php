<?php

declare(strict_types=1);

namespace App\Mcp\Support;

/**
 * Identidade de quem está chamando a requisição /mcp atual.
 *
 * Duas origens possíveis, resolvidas por McpAuthFilter:
 *  - Chamada interna (token de serviço): sem usuário logado — cada Tool de
 *    escrita recebe `usuarioId` explícito e confia em quem chamou (hoje,
 *    apenas GeminiMcpAgentService, que sobrescreve esse argumento com o
 *    usuário real antes de despachar).
 *  - Chamada externa (token pessoal do usuário, usado por um chat de IA
 *    como Claude Desktop/ChatGPT): o usuário já está autenticado via
 *    Shield. Nesse caso o `usuarioId` vem do token, nunca de um argumento
 *    fornecido pelo modelo — impede que a IA externa aja em nome de outro
 *    usuário do escritório.
 */
final class McpContext
{
    private static ?int $usuarioExterno = null;

    public static function iniciarChamadaExterna(int $usuarioId): void
    {
        self::$usuarioExterno = $usuarioId;
    }

    public static function iniciarChamadaInterna(): void
    {
        self::$usuarioExterno = null;
    }

    public static function ehChamadaExterna(): bool
    {
        return self::$usuarioExterno !== null;
    }

    /**
     * Resolve o usuarioId a ser usado por uma Tool de escrita: se a chamada
     * é externa, o usuário autenticado sempre prevalece, ignorando o que o
     * modelo tenha enviado. Se é interna, usa o valor fornecido (o
     * chamador — GeminiMcpAgentService — já garante que é o real).
     */
    public static function resolverUsuarioId(?int $fornecidoPeloModelo): int
    {
        if (self::$usuarioExterno !== null) {
            return self::$usuarioExterno;
        }

        if ($fornecidoPeloModelo === null) {
            throw new \RuntimeException('usuarioId não informado e nenhuma sessão externa autenticada.');
        }

        return $fornecidoPeloModelo;
    }
}
