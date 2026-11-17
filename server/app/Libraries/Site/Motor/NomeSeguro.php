<?php

declare(strict_types=1);

namespace App\Libraries\Site\Motor;

/** Evita que `tipo`/`layout` vindos do banco virem caminho de arquivo. */
final class NomeSeguro
{
    public static function valido(string $nome): bool
    {
        return preg_match('/^[a-z][a-z0-9_]*$/', $nome) === 1 && strlen($nome) <= 60;
    }
}
