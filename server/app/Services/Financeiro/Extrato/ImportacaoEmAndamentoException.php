<?php

declare(strict_types=1);

namespace App\Services\Financeiro\Extrato;

use RuntimeException;

/** Já existe uma importação em andamento para a mesma conta. */
class ImportacaoEmAndamentoException extends RuntimeException
{
}
