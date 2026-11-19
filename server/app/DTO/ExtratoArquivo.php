<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Conteúdo já interpretado de um arquivo de extrato bancário (OFX).
 */
final class ExtratoArquivo
{
    /**
     * @param list<MovimentoExtrato> $movimentos
     */
    public function __construct(
        public readonly string $banco,
        public readonly string $agencia,
        public readonly string $conta,
        public readonly string $tipoConta,
        public readonly string $moeda,
        public readonly ?string $periodoInicio,
        public readonly ?string $periodoFim,
        public readonly ?int $saldoFinalCentavos,
        public readonly ?string $saldoData,
        public readonly array $movimentos,
    ) {
    }
}
