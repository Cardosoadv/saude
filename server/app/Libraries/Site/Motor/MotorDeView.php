<?php

declare(strict_types=1);

namespace App\Libraries\Site\Motor;

/**
 * Renderiza partials de um template. `null` significa "este template não tem
 * esse arquivo"; o chamador decide o fallback. Erros de renderização lançam.
 */
interface MotorDeView
{
    /** Nomes (`tipo` da seção, `layout`) só aceitam `[a-z][a-z0-9_]*`. */
    public function secao(string $diretorio, string $tipo, array $vars): ?string;

    public function layout(string $diretorio, string $nome, array $vars): ?string;

    /** Tela especial do template (`pages/<nome>`): notícias, área do cliente… */
    public function pagina(string $diretorio, string $nome, array $vars): ?string;
}
