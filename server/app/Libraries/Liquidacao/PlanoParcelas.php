<?php

declare(strict_types=1);

namespace App\Libraries\Liquidacao;

use DateTimeImmutable;

/**
 * Gera o plano de parcelas de uma liquidação (entrada + N parcelas).
 *
 * Os centavos que sobram da divisão vão para a última parcela.
 */
final class PlanoParcelas
{
    public const MAX_PARCELAS = 360;

    /** Periodicidade => meses (ou dias) entre vencimentos. */
    private const PERIODICIDADES = [
        'semanal'    => ['dias', 7],
        'quinzenal'  => ['dias', 15],
        'mensal'     => ['meses', 1],
        'bimestral'  => ['meses', 2],
        'trimestral' => ['meses', 3],
        'semestral'  => ['meses', 6],
        'anual'      => ['meses', 12],
    ];

    /** @return list<string> */
    public static function periodicidades(): array
    {
        return array_keys(self::PERIODICIDADES);
    }

    /**
     * @param int         $total               valor a parcelar (centavos), incluindo a entrada
     * @param int         $entrada             entrada (centavos), 0 se não houver; vira a parcela número 0
     * @param int         $numeroParcelas      quantidade de parcelas (sem contar a entrada)
     * @param string      $primeiroVencimento  AAAA-MM-DD da 1ª parcela
     * @param string      $periodicidade       semanal, quinzenal, mensal, bimestral, trimestral, semestral, anual
     * @param string|null $dataEntrada         AAAA-MM-DD da entrada (padrão: o primeiro vencimento)
     *
     * @return list<array{numero_parcela: int, valor_parcela: int, data_vencimento: string}>
     *
     * @throws LiquidacaoException
     */
    public static function gerar(int $total, int $entrada, int $numeroParcelas, string $primeiroVencimento, string $periodicidade = 'mensal', ?string $dataEntrada = null): array
    {
        if ($total <= 0) {
            throw LiquidacaoException::invalida('O valor a parcelar precisa ser maior que zero.');
        }
        if ($entrada < 0 || $entrada >= $total) {
            throw LiquidacaoException::invalida('A entrada precisa ser menor que o valor a parcelar.', ['entrada' => 'Entrada inválida.']);
        }
        if ($numeroParcelas < 1 || $numeroParcelas > self::MAX_PARCELAS) {
            throw LiquidacaoException::invalida('Número de parcelas deve ficar entre 1 e ' . self::MAX_PARCELAS . '.', ['numero_parcelas' => 'Valor inválido.']);
        }
        if (!isset(self::PERIODICIDADES[$periodicidade])) {
            throw LiquidacaoException::invalida('Periodicidade inválida. Use: ' . implode(', ', self::periodicidades()) . '.', ['periodicidade' => 'Valor inválido.']);
        }
        $primeira = self::data($primeiroVencimento, 'primeiro_vencimento');
        $entradaEm = $dataEntrada !== null ? self::data($dataEntrada, 'data_entrada') : $primeira;

        $restante = $total - $entrada;
        $base     = intdiv($restante, $numeroParcelas);
        $sobra    = $restante - ($base * $numeroParcelas);

        $parcelas = [];
        if ($entrada > 0) {
            $parcelas[] = ['numero_parcela' => 0, 'valor_parcela' => $entrada, 'data_vencimento' => $entradaEm->format('Y-m-d')];
        }
        [$unidade, $passo] = self::PERIODICIDADES[$periodicidade];
        for ($i = 0; $i < $numeroParcelas; $i++) {
            $vence = $unidade === 'dias'
                ? $primeira->modify('+' . ($i * $passo) . ' days')
                : self::somarMeses($primeira, $i * $passo);
            $parcelas[] = [
                'numero_parcela'  => $i + 1,
                'valor_parcela'   => $base + ($i === $numeroParcelas - 1 ? $sobra : 0),
                'data_vencimento' => $vence->format('Y-m-d'),
            ];
        }

        return $parcelas;
    }

    /** Soma meses preservando o dia de origem; no mês mais curto cai no último dia (31/01 + 1 mês = 28/02). */
    private static function somarMeses(DateTimeImmutable $data, int $meses): DateTimeImmutable
    {
        $alvo = $data->modify('first day of this month')->modify("+{$meses} months");
        $dia  = min((int) $data->format('j'), (int) $alvo->format('t'));

        return $alvo->setDate((int) $alvo->format('Y'), (int) $alvo->format('n'), $dia);
    }

    private static function data(string $valor, string $campo): DateTimeImmutable
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        if ($d === false || $d->format('Y-m-d') !== $valor) {
            throw LiquidacaoException::invalida("Data inválida em {$campo} (use AAAA-MM-DD).", [$campo => 'Data inválida.']);
        }

        return $d;
    }
}
