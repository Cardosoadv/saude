<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

/**
 * Feriados nacionais, fixos e móveis, usados na contagem de prazos processuais.
 *
 * Fixos: Lei 662/1949 (1/1, 1/5, 7/9, 15/11, 25/12), Lei 10.607/2002 (2/11),
 * Lei 6.802/1980 (12/10), Lei 14.759/2023 (20/11) e 21/4 (Tiradentes).
 * Móvel: Sexta-feira da Paixão (dois dias antes da Páscoa).
 *
 * Carnaval, Corpus Christi e feriados locais variam por tribunal e comarca
 * e devem ser cadastrados no calendário forense.
 */
class FeriadosNacionais
{
    private const FIXOS = [
        '01-01' => 'Confraternização Universal',
        '04-21' => 'Tiradentes',
        '05-01' => 'Dia do Trabalho',
        '09-07' => 'Independência do Brasil',
        '10-12' => 'Nossa Senhora Aparecida',
        '11-02' => 'Finados',
        '11-15' => 'Proclamação da República',
        '11-20' => 'Consciência Negra',
        '12-25' => 'Natal',
    ];

    /**
     * @return array<string, string> data (Y-m-d) => nome
     */
    public static function doAno(int $ano): array
    {
        $feriados = [];
        foreach (self::FIXOS as $mesDia => $nome) {
            $feriados["{$ano}-{$mesDia}"] = $nome;
        }

        $sextaSanta = self::pascoa($ano)->modify('-2 days');
        $feriados[$sextaSanta->format('Y-m-d')] = 'Sexta-feira da Paixão';

        ksort($feriados);

        return $feriados;
    }

    /**
     * Domingo de Páscoa (algoritmo de Meeus/Jones/Butcher).
     */
    public static function pascoa(int $ano): DateTimeImmutable
    {
        $a = $ano % 19;
        $b = intdiv($ano, 100);
        $c = $ano % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $ano, $mes, $dia));
    }
}
