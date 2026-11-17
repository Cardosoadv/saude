<?php

declare(strict_types=1);

namespace App\Libraries\Liquidacao;

use RuntimeException;

/**
 * Aritmética decimal exata (bcmath) para os cálculos de liquidação.
 *
 * Os números circulam como string; o arredondamento é sempre "meio para cima"
 * (afastando de zero), aplicado só ao final de cada operação que vira centavos.
 */
final class Decimal
{
    /** Casas decimais dos cálculos intermediários (fatores, taxas). */
    public const ESCALA = 12;

    private static function garantir(): void
    {
        if (!extension_loaded('bcmath')) {
            throw new RuntimeException('A extensão PHP bcmath é necessária para os cálculos de liquidação.');
        }
    }

    public static function soma(string $a, string $b, int $escala = self::ESCALA): string
    {
        self::garantir();

        return bcadd(self::limpar($a), self::limpar($b), $escala);
    }

    public static function sub(string $a, string $b, int $escala = self::ESCALA): string
    {
        self::garantir();

        return bcsub(self::limpar($a), self::limpar($b), $escala);
    }

    public static function mul(string $a, string $b, int $escala = self::ESCALA): string
    {
        self::garantir();

        return bcmul(self::limpar($a), self::limpar($b), $escala);
    }

    public static function div(string $a, string $b, int $escala = self::ESCALA): string
    {
        self::garantir();

        return bcdiv(self::limpar($a), self::limpar($b), $escala);
    }

    public static function cmp(string $a, string $b, int $escala = self::ESCALA): int
    {
        self::garantir();

        return bccomp(self::limpar($a), self::limpar($b), $escala);
    }

    /** Arredonda "meio para cima" (afastando de zero) para $casas casas decimais. */
    public static function arredonda(string $valor, int $casas = 0): string
    {
        self::garantir();
        $valor = self::limpar($valor);
        $meio  = '0.' . str_repeat('0', $casas) . '5';

        return bccomp($valor, '0', self::ESCALA) >= 0
            ? bcadd($valor, $meio, $casas)
            : bcsub($valor, $meio, $casas);
    }

    /** Arredonda para inteiro (centavos). */
    public static function centavos(string $valor): int
    {
        return (int) self::arredonda($valor, 0);
    }

    /** Percentual (ex.: "0.83" = 0,83%) como fator multiplicativo ("1.0083"). */
    public static function fatorDoPercentual(string $percentual, int $escala = self::ESCALA): string
    {
        return self::soma('1', self::div($percentual, '100', $escala + 2), $escala);
    }

    /** Aceita vírgula decimal e remove espaços; rejeita o que não for número. */
    public static function limpar(string $valor): string
    {
        $valor = str_replace(',', '.', trim($valor));
        if (!preg_match('/^-?\d+(\.\d+)?$/', $valor)) {
            throw new \InvalidArgumentException("Valor numérico inválido: '{$valor}'.");
        }

        return $valor;
    }
}
