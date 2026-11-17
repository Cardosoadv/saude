<?php

declare(strict_types=1);

namespace App\Libraries\Site\Motor;

use CodeIgniter\View\View;
use Config\View as ViewConfig;

/**
 * Motor para templates `core`/`cli`: views PHP do CodeIgniter. Roda código,
 * portanto nunca é usado para templates enviados pelo painel.
 *
 * Nas views, `rich_text` e `texto_inline` já chegam sanitizados e podem ser
 * impressos sem escape; qualquer outro texto deve passar por `esc()`.
 */
final class PhpMotor implements MotorDeView
{
    public function secao(string $diretorio, string $tipo, array $vars): ?string
    {
        return NomeSeguro::valido($tipo) ? $this->renderiza($diretorio, "sections/{$tipo}", $vars) : null;
    }

    public function layout(string $diretorio, string $nome, array $vars): ?string
    {
        return NomeSeguro::valido($nome) ? $this->renderiza($diretorio, "layouts/{$nome}", $vars) : null;
    }

    public function pagina(string $diretorio, string $nome, array $vars): ?string
    {
        return NomeSeguro::valido($nome) ? $this->renderiza($diretorio, "pages/{$nome}", $vars) : null;
    }

    private function renderiza(string $diretorio, string $view, array $vars): ?string
    {
        if (!is_file(rtrim($diretorio, '/\\') . "/{$view}.php")) {
            return null;
        }

        // debug=false: sem os comentários DEBUG-VIEW (que vazariam caminhos do servidor no HTML).
        $renderer = new View(config(ViewConfig::class), rtrim($diretorio, '/\\'), service('locator'), false, service('logger'));

        return $renderer->setData($vars)->render($view, ['saveData' => false]);
    }
}
