<?php

declare(strict_types=1);

namespace App\Libraries\Contabil;

use CognitioTechny\Contabil\Application\Ports\CurrentEntityInterface;
use Config\Contabil;

/**
 * Adaptador de identificação da entidade contábil ativa no OrdoJuris.
 *
 * @version 1.0.0
 */
class OrdoJurisContabilCurrentEntity implements CurrentEntityInterface
{
    public function id(): int|string|null
    {
        /** @var Contabil $config */
        $config = config(Contabil::class);
        return $config->defaultEntityId ?? 1;
    }
}
