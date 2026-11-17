<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Services\ConfiguracoesService;
use Throwable;

/**
 * Limites da importação e da conciliação de extratos, editáveis em Configurações.
 *
 * Valor ausente, não numérico ou fora da faixa volta ao padrão (nunca derruba a importação).
 */
class ExtratoConfig
{
    public const MAX_MB            = 'EXTRATOS_MAX_MB';
    public const JANELA_DIAS       = 'EXTRATOS_JANELA_DIAS';
    public const LIMITE_LOTE       = 'EXTRATOS_LIMITE_LOTE';
    public const LIMITE_LOTE_CONCILIACAO = 'EXTRATOS_LIMITE_LOTE_CONCILIACAO';

    public const MAX_MB_PADRAO     = 5;
    public const JANELA_PADRAO     = 3;
    public const LOTE_PADRAO       = 50;
    public const LOTE_CONCILIACAO_PADRAO = 500;

    /** nome => [padrão, mínimo, máximo, descrição] */
    public const DEFINICOES = [
        self::MAX_MB                  => [self::MAX_MB_PADRAO, 1, 20, 'Tamanho máximo (MB) do arquivo OFX de extrato bancário.'],
        self::JANELA_DIAS             => [self::JANELA_PADRAO, 0, 30, 'Janela (dias, antes e depois) para sugerir conciliação de movimentos do extrato com pagamentos.'],
        self::LIMITE_LOTE             => [self::LOTE_PADRAO, 1, 200, 'Máximo de movimentos de extrato lançados de uma vez (lote).'],
        self::LIMITE_LOTE_CONCILIACAO => [self::LOTE_CONCILIACAO_PADRAO, 1, 1000, 'Máximo de movimentos de extrato conciliados de uma vez (lote).'],
    ];

    private ?ConfiguracoesService $config;

    public function __construct(?ConfiguracoesService $config = null)
    {
        $this->config = $config;
    }

    public function tamanhoMaxBytes(): int
    {
        return $this->inteiro(self::MAX_MB) * 1048576;
    }

    public function janelaDias(): int
    {
        return $this->inteiro(self::JANELA_DIAS);
    }

    public function limiteLote(): int
    {
        return $this->inteiro(self::LIMITE_LOTE);
    }

    public function limiteLoteConciliacao(): int
    {
        return $this->inteiro(self::LIMITE_LOTE_CONCILIACAO);
    }

    private function inteiro(string $nome): int
    {
        [$padrao, $min, $max] = self::DEFINICOES[$nome];

        try {
            $this->config ??= new ConfiguracoesService();
            $bruto = $this->config->getValor($nome, null);
        } catch (Throwable) {
            return $padrao; // sem tabela de configurações (ex.: testes) ou banco indisponível
        }

        if ($bruto === null || $bruto === '' || !is_numeric($bruto)) {
            return $padrao;
        }
        $valor = (int) $bruto;

        return $valor < $min || $valor > $max ? $padrao : $valor;
    }
}
