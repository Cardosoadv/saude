<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

/**
 * Compara agência e conta do cadastro com as do arquivo OFX.
 *
 * Os bancos mandam (e as pessoas cadastram) o dígito verificador de formas diferentes:
 * separado por hífen ("0001-9"), colado ao número ("00019") ou ausente. Esta conferência
 * aceita essas variações, mas continua recusando números realmente diferentes.
 *
 * Regras (sobre os dígitos, ignorando zeros à esquerda e pontuação):
 *  1. iguais;
 *  2. quando um dos lados traz hífen, barra ou ponto, o trecho antes do último separador
 *     (o número sem o dígito) é comparado com o outro lado inteiro ou sem o último dígito;
 *  3. um lado é igual ao outro com exatamente um dígito a mais no fim (dígito colado),
 *     desde que o menor tenha pelo menos 3 dígitos para não aceitar coincidências curtas.
 *
 * Serviço puro, sem banco.
 */
final class ConferenciaContaExtrato
{
    private const MIN_DIGITOS_DV_COLADO = 3;

    public static function confere(?string $cadastro, ?string $arquivo): bool
    {
        $a = self::variantes((string) $cadastro);
        $b = self::variantes((string) $arquivo);
        if ($a['completo'] === '' || $b['completo'] === '') {
            return false;
        }

        if ($a['completo'] === $b['completo']) {
            return true;
        }

        // Dígito separado por pontuação de um lado: o número base precisa bater com o outro lado.
        foreach ([[$a, $b], [$b, $a]] as [$x, $y]) {
            if ($x['base'] !== null && ($x['base'] === $y['completo'] || ($y['base'] !== null && $x['base'] === $y['base']))) {
                return true;
            }
        }

        // Dígito colado: um lado é o outro mais um dígito no fim.
        foreach ([[$a, $b], [$b, $a]] as [$curto, $longo]) {
            // Aqui os zeros à esquerda contam: "0001" + "9" = "00019".
            $base = $curto['base_bruta'] ?? $curto['bruto'];
            if (strlen($base) >= self::MIN_DIGITOS_DV_COLADO
                && strlen($longo['bruto']) === strlen($base) + 1
                && str_starts_with($longo['bruto'], $base)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{completo:string,base:?string,bruto:string,base_bruta:?string} `bruto`/`base_bruta`: os mesmos
     *         valores mantendo zeros à esquerda; `completo`: todos os dígitos sem zeros à esquerda;
     *         `base`: dígitos antes do último separador (sem o dígito verificador), ou null se não há separador.
     */
    private static function variantes(string $valor): array
    {
        $valor = trim($valor);
        $base  = null;
        $baseBruta = null;

        if (preg_match('/^(.*\d)\s*[-\/.]\s*(\d{1,2})$/', $valor, $m) === 1) {
            $baseBruta = preg_replace('/\D+/', '', $m[1]) ?? '';
            $base      = ltrim($baseBruta, '0');
        }

        $bruto    = preg_replace('/\D+/', '', $valor) ?? '';
        $completo = ltrim($bruto, '0');

        return [
            'completo'   => $completo,
            'base'       => $base === '' ? null : $base,
            'bruto'      => $bruto,
            'base_bruta' => $baseBruta,
        ];
    }
}
