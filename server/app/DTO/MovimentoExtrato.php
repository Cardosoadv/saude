<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Movimento (transação) lido de um arquivo de extrato bancário.
 */
final class MovimentoExtrato
{
    public function __construct(
        public readonly ?string $fitid,
        public readonly bool $fitidConfiavel,
        public readonly string $data,
        public readonly int $valorCentavos,
        public readonly string $tipoBanco,
        public readonly string $memo,
        public readonly string $nome,
        public readonly ?string $documento,
        public readonly int $ordemNoDia,
    ) {
    }
}
