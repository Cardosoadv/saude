<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Repositories\Financeiro\CashFlowForecastRepository;
use App\Repositories\Financeiro\ContasRepository;
use App\Repositories\Financeiro\SaldoMensalRepository;
use App\Services\BaseService;

/**
 * Serviço de previsão de fluxo de caixa e detecção de anomalias financeiras.
 *
 * Projeta o saldo dos próximos meses por conta com base na tendência histórica
 * de `fin_saldo_mensal`/`fin_pgto_despesas`/`fin_pgto_receitas`, e sinaliza
 * lançamentos de `fin_despesas`/`fin_receitas` que fogem do padrão estatístico
 * de sua categoria.
 *
 * @property CashFlowForecastRepository $repository
 */
class CashFlowForecastService extends BaseService
{
    /**
     * Limite de desvios-padrão (z-score) a partir do qual um lançamento é
     * considerado anômalo em relação à sua categoria.
     */
    private const Z_SCORE_LIMITE = 2.0;

    /**
     * Quantidade mínima de lançamentos numa categoria para que seja possível
     * calcular uma média/desvio padrão estatisticamente significativo.
     */
    private const AMOSTRA_MINIMA_CATEGORIA = 3;

    protected ContasRepository $contasRepository;
    protected SaldoMensalRepository $saldoMensalRepository;

    public function __construct(
        ?CashFlowForecastRepository $repository = null,
        ?ContasRepository $contasRepository = null,
        ?SaldoMensalRepository $saldoMensalRepository = null
    ) {
        $this->repository            = $repository ?? new CashFlowForecastRepository();
        $this->contasRepository      = $contasRepository ?? new ContasRepository();
        $this->saldoMensalRepository = $saldoMensalRepository ?? new SaldoMensalRepository();
    }

    /**
     * Consolida a previsão de fluxo de caixa e as anomalias detectadas para o dashboard.
     *
     * @param int|null $contaId Conta específica, ou null para a visão consolidada de todas as contas.
     * @param int $mesesProjecao Quantos meses futuros projetar (3 a 6, conforme a proposta da feature).
     * @param int $mesesHistorico Quantos meses de histórico usar para calcular a tendência.
     * @param int $mesesAnalise Quantos meses de lançamentos considerar na detecção de anomalias.
     * @return array
     */
    public function getDashboardData(?int $contaId = null, int $mesesProjecao = 6, int $mesesHistorico = 12, int $mesesAnalise = 6): array
    {
        $dbVersion = cache()->get('dashboard_version') ?? 1;
        $cacheKey = "cash_flow_forecast_dashboard_v{$dbVersion}_{$mesesProjecao}_{$mesesHistorico}_{$mesesAnalise}" . ($contaId ? "_c{$contaId}" : '');
        $cachedData = cache()->get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        $data = [
            'previsao'   => $this->getForecast($contaId, $mesesProjecao, $mesesHistorico),
            'anomalias'  => $this->getAnomalias($mesesAnalise),
            'contas'     => $this->contasRepository->listarContasOrdenadas(),
            'timestamp'  => date('c'),
        ];

        cache()->save($cacheKey, $data, 3600);

        return $data;
    }

    /**
     * Projeta o saldo dos próximos meses de uma conta (ou de todas, consolidado) com
     * base na tendência linear do histórico recente.
     *
     * @param int|null $contaId
     * @param int $mesesProjecao
     * @param int $mesesHistorico
     * @return array
     */
    public function getForecast(?int $contaId = null, int $mesesProjecao = 6, int $mesesHistorico = 12): array
    {
        $mesesProjecao = max(1, min(12, $mesesProjecao));
        $mesesHistorico = max(2, $mesesHistorico);

        $despesasPorMes = $this->repository->getTotaisMensaisDespesas($contaId, $mesesHistorico);
        $receitasPorMes = $this->repository->getTotaisMensaisReceitas($contaId, $mesesHistorico);

        $chavesMeses = $this->gerarUltimosMeses($mesesHistorico);

        $primeiroDia = $chavesMeses[0] . '-01';
        $ultimoFechamento = $this->saldoMensalRepository->getUltimoSaldoFechado($contaId, $primeiroDia);
        $saldoBase = $ultimoFechamento ? (float) $ultimoFechamento['saldo_acumulado'] : 0.0;
        $saldoEstimado = $ultimoFechamento === null;

        $historico = [];
        $saldoAcumulado = $saldoBase;
        $pontosRegressao = [];
        $indice = 0;

        foreach ($chavesMeses as $chave) {
            $receitas = $receitasPorMes[$chave] ?? 0.0;
            $despesas = $despesasPorMes[$chave] ?? 0.0;
            $saldoLiquido = $receitas - $despesas;
            $saldoAcumulado += $saldoLiquido;

            [$ano, $mes] = array_map('intval', explode('-', $chave));

            $historico[] = [
                'ano'             => $ano,
                'mes'             => $mes,
                'label'           => sprintf('%02d/%d', $mes, $ano),
                'receitas'        => round($receitas, 2),
                'despesas'        => round($despesas, 2),
                'saldo_liquido'   => round($saldoLiquido, 2),
                'saldo_acumulado' => round($saldoAcumulado, 2),
            ];

            $pontosRegressao[] = [$indice, $saldoAcumulado];
            $indice++;
        }

        $regressao = $this->regressaoLinear($pontosRegressao);

        $projecao = [];
        $ultimaChave = end($chavesMeses);
        $chavesFuturas = $this->gerarProximosMeses($ultimaChave, $mesesProjecao);

        foreach ($chavesFuturas as $chave) {
            [$ano, $mes] = array_map('intval', explode('-', $chave));
            $saldoProjetado = $regressao['intercepto'] + $regressao['inclinacao'] * $indice;

            $projecao[] = [
                'ano'             => $ano,
                'mes'             => $mes,
                'label'           => sprintf('%02d/%d', $mes, $ano),
                'saldo_projetado' => round($saldoProjetado, 2),
            ];
            $indice++;
        }

        $direcao = 'estavel';
        if ($regressao['inclinacao'] > 0.01) {
            $direcao = 'alta';
        } elseif ($regressao['inclinacao'] < -0.01) {
            $direcao = 'queda';
        }

        return [
            'conta_id'                => $contaId,
            'meses_historico'         => $mesesHistorico,
            'meses_projecao'          => $mesesProjecao,
            'saldo_inicial_estimado'  => $saldoEstimado,
            'saldo_inicial'           => round($saldoBase, 2),
            'historico'               => $historico,
            'projecao'                => $projecao,
            'tendencia'               => [
                'inclinacao_mensal' => round($regressao['inclinacao'], 2),
                'r2'                => round($regressao['r2'], 4),
                'direcao'           => $direcao,
            ],
        ];
    }

    /**
     * Detecta lançamentos de despesas e receitas que se desviam significativamente
     * do padrão histórico (média/desvio padrão) de sua categoria.
     *
     * @param int $mesesAnalise Janela de lançamentos considerada, em meses.
     * @return array Lista de anomalias, ordenada pela maior severidade (|z-score|) primeiro.
     */
    public function getAnomalias(int $mesesAnalise = 6): array
    {
        $mesesAnalise = max(1, $mesesAnalise);

        $despesas = $this->repository->getLancamentosComCategoria('despesa', $mesesAnalise);
        $receitas = $this->repository->getLancamentosComCategoria('receita', $mesesAnalise);

        $anomalias = array_merge(
            $this->detectarAnomaliasPorCategoria($despesas, 'despesa'),
            $this->detectarAnomaliasPorCategoria($receitas, 'receita')
        );

        usort($anomalias, static fn (array $a, array $b) => abs($b['z_score']) <=> abs($a['z_score']));

        return $anomalias;
    }

    /**
     * Agrupa lançamentos por categoria e sinaliza os que estiverem a mais de
     * self::Z_SCORE_LIMITE desvios-padrão da média da categoria.
     *
     * @param array $lancamentos
     * @param string $tipo 'despesa' ou 'receita'
     * @return array
     */
    private function detectarAnomaliasPorCategoria(array $lancamentos, string $tipo): array
    {
        $porCategoria = [];
        foreach ($lancamentos as $lancamento) {
            $categoriaId = $lancamento['categoria_id'] ?? 0;
            $porCategoria[$categoriaId][] = $lancamento;
        }

        $anomalias = [];

        foreach ($porCategoria as $itens) {
            if (count($itens) < self::AMOSTRA_MINIMA_CATEGORIA) {
                // Amostra pequena demais para uma média/desvio padrão confiável.
                continue;
            }

            $valores = array_column($itens, 'valor');
            $media = array_sum($valores) / count($valores);

            $somaQuadrados = 0.0;
            foreach ($valores as $valor) {
                $somaQuadrados += ($valor - $media) ** 2;
            }
            $desvioPadrao = sqrt($somaQuadrados / count($valores));

            if ($desvioPadrao <= 0.0) {
                // Sem variação histórica: qualquer valor igual à média, nada a sinalizar.
                continue;
            }

            foreach ($itens as $item) {
                $zScore = ($item['valor'] - $media) / $desvioPadrao;

                if (abs($zScore) < self::Z_SCORE_LIMITE) {
                    continue;
                }

                $anomalias[] = [
                    'id'                      => $item['id'],
                    'tipo'                    => $tipo,
                    'descricao'               => $item['descricao'],
                    'categoria_id'            => $item['categoria_id'],
                    'categoria_nome'          => $item['categoria_nome'],
                    'data'                    => $item['data'],
                    'valor'                   => round($item['valor'], 2),
                    'media_categoria'         => round($media, 2),
                    'desvio_padrao_categoria' => round($desvioPadrao, 2),
                    'z_score'                 => round($zScore, 2),
                    'severidade'              => abs($zScore) >= self::Z_SCORE_LIMITE * 1.5 ? 'alta' : 'media',
                ];
            }
        }

        return $anomalias;
    }

    /**
     * Calcula a regressão linear simples (mínimos quadrados) de uma série de pontos.
     *
     * @param array<int, array{0: int, 1: float}> $pontos Lista de pares [x, y].
     * @return array{inclinacao: float, intercepto: float, r2: float}
     */
    private function regressaoLinear(array $pontos): array
    {
        $n = count($pontos);

        if ($n === 0) {
            return ['inclinacao' => 0.0, 'intercepto' => 0.0, 'r2' => 0.0];
        }

        if ($n === 1) {
            return ['inclinacao' => 0.0, 'intercepto' => $pontos[0][1], 'r2' => 0.0];
        }

        $somaX = $somaY = $somaXY = $somaXX = 0.0;
        foreach ($pontos as [$x, $y]) {
            $somaX += $x;
            $somaY += $y;
            $somaXY += $x * $y;
            $somaXX += $x * $x;
        }

        $denominador = ($n * $somaXX) - ($somaX * $somaX);

        if (abs($denominador) < 1e-9) {
            $media = $somaY / $n;
            return ['inclinacao' => 0.0, 'intercepto' => $media, 'r2' => 0.0];
        }

        $inclinacao = (($n * $somaXY) - ($somaX * $somaY)) / $denominador;
        $intercepto = ($somaY - ($inclinacao * $somaX)) / $n;

        $mediaY = $somaY / $n;
        $ssTot = 0.0;
        $ssRes = 0.0;
        foreach ($pontos as [$x, $y]) {
            $predito = $intercepto + $inclinacao * $x;
            $ssTot += ($y - $mediaY) ** 2;
            $ssRes += ($y - $predito) ** 2;
        }

        $r2 = $ssTot > 0.0 ? 1 - ($ssRes / $ssTot) : 0.0;

        return ['inclinacao' => $inclinacao, 'intercepto' => $intercepto, 'r2' => $r2];
    }

    /**
     * Gera as chaves 'YYYY-MM' dos últimos $quantidade meses, terminando no mês atual (inclusive).
     *
     * @param int $quantidade
     * @return array<int, string>
     */
    private function gerarUltimosMeses(int $quantidade): array
    {
        $inicio = strtotime(date('Y-m-01', strtotime('-' . max(0, $quantidade - 1) . ' months')));

        $chaves = [];
        for ($i = 0; $i < $quantidade; $i++) {
            $chaves[] = date('Y-m', strtotime("+{$i} months", $inicio));
        }

        return $chaves;
    }

    /**
     * Gera as chaves 'YYYY-MM' dos $quantidade meses seguintes a $apartirDe.
     *
     * @param string $apartirDe Chave 'YYYY-MM' do último mês conhecido.
     * @param int $quantidade
     * @return array<int, string>
     */
    private function gerarProximosMeses(string $apartirDe, int $quantidade): array
    {
        $inicio = strtotime($apartirDe . '-01 +1 month');

        $chaves = [];
        for ($i = 0; $i < $quantidade; $i++) {
            $chaves[] = date('Y-m', strtotime("+{$i} months", $inicio));
        }

        return $chaves;
    }
}
