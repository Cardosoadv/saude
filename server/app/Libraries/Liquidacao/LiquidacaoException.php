<?php

declare(strict_types=1);

namespace App\Libraries\Liquidacao;

use RuntimeException;

/**
 * Erro de regra do módulo de liquidação, com o status HTTP que a API devolve.
 */
class LiquidacaoException extends RuntimeException
{
    /** @var array<string, string> */
    private array $erros;

    /** @param array<string, string> $erros */
    public function __construct(string $mensagem, int $status, array $erros = [])
    {
        parent::__construct($mensagem, $status);
        $this->erros = $erros;
    }

    public static function naoEncontrada(string $rotulo = 'Liquidação'): self
    {
        return new self("{$rotulo} não encontrada.", 404);
    }

    /** @param array<string, string> $erros */
    public static function invalida(string $mensagem, array $erros = []): self
    {
        return new self($mensagem, 422, $erros);
    }

    /** Operação incompatível com o estado atual do registro (HTTP 409). */
    public static function conflito(string $mensagem): self
    {
        return new self($mensagem, 409);
    }

    public function status(): int
    {
        return $this->getCode();
    }

    /** @return array<string, string> */
    public function erros(): array
    {
        return $this->erros;
    }
}
