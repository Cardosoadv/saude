<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use RuntimeException;

/**
 * Assume, na requisição MCP atual, a identidade do usuário real em nome de
 * quem a IA está agindo.
 *
 * O AuditoriaTrait e o helper `user_id()` dependem de `auth()->id()`. Como
 * quem chama /mcp é o próprio backend (não um usuário logado via Shield),
 * todo Tool que grava dados deve receber `usuarioId` explicitamente e
 * chamar `assumir()` antes de delegar ao Service — mesmo padrão de
 * "login server-side" já usado em ClienteAreaService::autenticarPorLink().
 */
final class AtorMcp
{
    public static function assumir(int $usuarioId): void
    {
        $usuario = auth()->getProvider()->findById($usuarioId);

        if ($usuario === null) {
            throw new RuntimeException(
                "usuarioId={$usuarioId} não corresponde a um usuário válido — a ação não pode ser atribuída a ninguém."
            );
        }

        auth()->loginById($usuarioId);
    }
}
