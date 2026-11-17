<?php

declare(strict_types=1);

namespace App\Libraries\Liquidacao;

/**
 * Totais das parcelas de uma liquidação: pago, a receber, vencido e próxima parcela.
 */
final class ResumoPagamentos
{
    /**
     * @param list<array> $pagamentos linhas de liquidacoes_pagamentos
     * @param int         $valorTotal valor atualizado da liquidação (centavos)
     * @param string      $hoje       AAAA-MM-DD (injetável para teste)
     *
     * @return array{total_parcelas: int, valor_parcelado: int, total_pago: int, total_vencido: int, saldo_a_receber: int, proxima_parcela: ?array{numero_parcela: int, data_vencimento: string, valor_parcela: int}, diferenca_parcelamento: ?int}
     */
    public static function calcular(array $pagamentos, int $valorTotal, string $hoje): array
    {
        $parcelado = 0;
        $pago      = 0;
        $vencido   = 0;
        $ativas    = 0;
        $proxima   = null;

        foreach ($pagamentos as $p) {
            $status = $p['status_pagamento'] ?? '';
            if ($status === LiquidacaoDominio::PARCELA_CANCELADA) {
                continue;
            }
            $ativas++;
            $valor      = (int) $p['valor_parcela'];
            $parcelado += $valor;

            if ($status === LiquidacaoDominio::PARCELA_PAGA) {
                $pago += $valor;
                continue;
            }
            if ($p['data_vencimento'] < $hoje || $status === LiquidacaoDominio::PARCELA_VENCIDA) {
                $vencido += $valor;
            }
            if ($proxima === null || $p['data_vencimento'] < $proxima['data_vencimento']) {
                $proxima = [
                    'numero_parcela'  => (int) $p['numero_parcela'],
                    'data_vencimento' => $p['data_vencimento'],
                    'valor_parcela'   => $valor,
                ];
            }
        }

        return [
            'total_parcelas'         => $ativas,
            'valor_parcelado'        => $parcelado,
            'total_pago'             => $pago,
            'total_vencido'          => $vencido,
            'saldo_a_receber'        => max($valorTotal - $pago, 0),
            'proxima_parcela'        => $proxima,
            // se o valor atualizado mudou depois de parcelar, o plano deixa de fechar com o total
            'diferenca_parcelamento' => $ativas > 0 ? $valorTotal - $parcelado : null,
        ];
    }
}
