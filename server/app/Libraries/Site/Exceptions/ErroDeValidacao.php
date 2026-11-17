<?php

declare(strict_types=1);

namespace App\Libraries\Site\Exceptions;

/**
 * Entrada recusada. `erros()` usa o caminho do campo como chave
 * (`titulo`, `dados.botoes.1.rotulo`), pronto para a resposta 422 da API.
 */
class ErroDeValidacao extends \RuntimeException
{
    /**
     * @param array<string, string> $erros
     * @param list<string>|array<string, string> $avisos Avisos que acompanham a recusa (ex.: relatório de validação)
     */
    public function __construct(private array $erros, private array $avisos = [])
    {
        parent::__construct(implode(' ', $erros) ?: 'Dados inválidos.');
    }

    /** @return array<string, string> */
    public function erros(): array
    {
        return $this->erros;
    }

    /** @return array<int|string, string> */
    public function avisos(): array
    {
        return $this->avisos;
    }

    public static function campo(string $campo, string $mensagem): self
    {
        return new self([$campo => $mensagem]);
    }
}
