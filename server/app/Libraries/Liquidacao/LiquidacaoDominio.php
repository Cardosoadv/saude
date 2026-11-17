<?php

declare(strict_types=1);

namespace App\Libraries\Liquidacao;

/**
 * Domínio (status e tipos) do módulo de liquidação.
 *
 * As colunas são VARCHAR; os valores guardados são os slugs abaixo.
 */
final class LiquidacaoDominio
{
    public const STATUS_RASCUNHO     = 'rascunho';
    public const STATUS_EM_CALCULO   = 'em_calculo';
    public const STATUS_APRESENTADA  = 'apresentada';
    public const STATUS_IMPUGNADA    = 'impugnada';
    public const STATUS_HOMOLOGADA   = 'homologada';
    public const STATUS_EM_PAGAMENTO = 'em_pagamento';
    public const STATUS_QUITADA      = 'quitada';

    /** Status em que o valor atualizado já vale como valor do crédito (BI/risco): fora rascunho e em cálculo. */
    public const STATUS_COM_VALOR_DEFINIDO = [self::STATUS_APRESENTADA, self::STATUS_IMPUGNADA, self::STATUS_HOMOLOGADA, self::STATUS_EM_PAGAMENTO, self::STATUS_QUITADA];

    public const TIPO_POR_CALCULO         = 'por_calculo';
    public const TIPO_POR_ARBITRAMENTO    = 'por_arbitramento';
    public const TIPO_PROCEDIMENTO_COMUM  = 'procedimento_comum';

    public const PARCELA_PENDENTE  = 'pendente';
    public const PARCELA_PAGA      = 'paga';
    public const PARCELA_VENCIDA   = 'vencida';
    public const PARCELA_CANCELADA = 'cancelada';

    public const ITEM_PRINCIPAL  = 'principal';
    public const ITEM_JUROS      = 'juros';
    public const ITEM_MULTA      = 'multa';
    public const ITEM_HONORARIOS = 'honorarios';
    public const ITEM_CUSTAS     = 'custas';
    public const ITEM_OUTROS     = 'outros';

    /** @return array<string, string> slug => rótulo */
    public static function status(): array
    {
        return [
            self::STATUS_RASCUNHO     => 'Rascunho',
            self::STATUS_EM_CALCULO   => 'Em cálculo',
            self::STATUS_APRESENTADA  => 'Apresentada',
            self::STATUS_IMPUGNADA    => 'Impugnada',
            self::STATUS_HOMOLOGADA   => 'Homologada',
            self::STATUS_EM_PAGAMENTO => 'Em pagamento',
            self::STATUS_QUITADA      => 'Quitada',
        ];
    }

    /** @return array<string, string> slug => rótulo */
    public static function tipos(): array
    {
        return [
            self::TIPO_POR_CALCULO        => 'Por cálculo',
            self::TIPO_POR_ARBITRAMENTO   => 'Por arbitramento',
            self::TIPO_PROCEDIMENTO_COMUM => 'Pelo procedimento comum',
        ];
    }

    /** @return array<string, string> slug => rótulo */
    public static function tiposItem(): array
    {
        return [
            self::ITEM_PRINCIPAL  => 'Principal',
            self::ITEM_JUROS      => 'Juros',
            self::ITEM_MULTA      => 'Multa',
            self::ITEM_HONORARIOS => 'Honorários',
            self::ITEM_CUSTAS     => 'Custas',
            self::ITEM_OUTROS     => 'Outros',
        ];
    }

    /** @return array<string, string> slug => rótulo */
    public static function statusParcela(): array
    {
        return [
            self::PARCELA_PENDENTE  => 'Pendente',
            self::PARCELA_PAGA      => 'Paga',
            self::PARCELA_VENCIDA   => 'Vencida',
            self::PARCELA_CANCELADA => 'Cancelada',
        ];
    }
}
