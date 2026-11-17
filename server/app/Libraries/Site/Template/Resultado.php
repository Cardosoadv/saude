<?php

declare(strict_types=1);

namespace App\Libraries\Site\Template;

/**
 * Resultado de uma validação: erros bloqueiam; avisos não.
 * As chaves seguem o caminho do campo (`botoes.1.rotulo`).
 */
final class Resultado
{
    /** @var array<string, string> */
    private array $erros = [];

    /** @var array<string, string> */
    private array $avisos = [];

    public function erro(string $caminho, string $mensagem): void
    {
        $this->erros[$caminho] = $mensagem;
    }

    public function aviso(string $caminho, string $mensagem): void
    {
        $this->avisos[$caminho] = $mensagem;
    }

    public function valido(): bool
    {
        return $this->erros === [];
    }

    /** @return array<string, string> */
    public function erros(): array
    {
        return $this->erros;
    }

    /** @return array<string, string> */
    public function avisos(): array
    {
        return $this->avisos;
    }
}
