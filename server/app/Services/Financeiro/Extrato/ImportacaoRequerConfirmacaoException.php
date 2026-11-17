<?php

declare(strict_types=1);

namespace App\Services\Financeiro\Extrato;

use RuntimeException;

/** A importação só continua se o usuário confirmar (`confirmar=true`) depois de ler os alertas. */
class ImportacaoRequerConfirmacaoException extends RuntimeException
{
    /** @param list<array<string,mixed>> $alertas */
    public function __construct(string $mensagem, private readonly array $alertas)
    {
        parent::__construct($mensagem);
    }

    /** @return list<array<string,mixed>> */
    public function alertas(): array
    {
        return $this->alertas;
    }
}
