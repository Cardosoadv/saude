<?php

declare(strict_types=1);

namespace App\Libraries\Contabil;

use CognitioTechny\Contabil\Application\Ports\CurrentUserInterface;

/**
 * Adaptador de identificação do usuário autenticado para lançamentos contábeis.
 *
 * @version 1.0.0
 */
class SaudeContabilCurrentUser implements CurrentUserInterface
{
    public function id(): int|string|null
    {
        return auth()->id();
    }
}
