<?php

declare(strict_types=1);

namespace App\Libraries\WebPush;

/** Resultado da entrega de uma mensagem a uma inscrição. */
final class ResultadoEnvio
{
    public function __construct(
        public readonly int $status,
        /** A inscrição não existe mais (404/410) ou é inválida: deve ser apagada. */
        public readonly bool $expirada,
        public readonly ?string $erro = null,
    ) {
    }

    public function sucesso(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
