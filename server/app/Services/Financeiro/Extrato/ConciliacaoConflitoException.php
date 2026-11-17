<?php

declare(strict_types=1);

namespace App\Services\Financeiro\Extrato;

use RuntimeException;

/** O pagamento já está vinculado a outro movimento, ou o movimento mudou de situação. A mensagem é adequada para o usuário. */
class ConciliacaoConflitoException extends RuntimeException
{
}
