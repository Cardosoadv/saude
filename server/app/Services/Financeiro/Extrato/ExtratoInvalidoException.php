<?php

declare(strict_types=1);

namespace App\Services\Financeiro\Extrato;

use RuntimeException;

/** Arquivo de extrato inválido ou não suportado. A mensagem é adequada para exibir ao usuário. */
class ExtratoInvalidoException extends RuntimeException
{
}
