<?php

declare(strict_types=1);

namespace App\Libraries\Site;

/** CNPJ numérico: dígitos verificadores, normalização (só dígitos) e máscara de exibição. */
final class Cnpj
{
    public static function digitos(string $valor): string
    {
        return preg_replace('/\D+/', '', $valor) ?? '';
    }

    public static function valido(string $valor): bool
    {
        $cnpj = self::digitos($valor);
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        foreach ([12, 13] as $tamanho) {
            $soma = 0;
            $peso = $tamanho - 7;
            for ($i = 0; $i < $tamanho; $i++) {
                $soma += (int) $cnpj[$i] * $peso;
                $peso = $peso === 2 ? 9 : $peso - 1;
            }
            $digito = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
            if ((int) $cnpj[$tamanho] !== $digito) {
                return false;
            }
        }

        return true;
    }

    /** `00.000.000/0000-00`; se não tiver 14 dígitos, devolve o texto como veio. */
    public static function formatar(string $valor): string
    {
        $d = self::digitos($valor);

        return strlen($d) === 14
            ? sprintf('%s.%s.%s/%s-%s', substr($d, 0, 2), substr($d, 2, 3), substr($d, 5, 3), substr($d, 8, 4), substr($d, 12, 2))
            : trim($valor);
    }
}
