<?php

declare(strict_types=1);

namespace App\Services\Financeiro\Extrato;

use RuntimeException;

/** Conta ou importação inexistente. */
class ExtratoNaoEncontradoException extends RuntimeException
{
}
