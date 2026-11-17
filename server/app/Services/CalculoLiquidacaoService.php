<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Liquidacao\CriterioCalculo;
use App\Libraries\Liquidacao\Decimal;
use App\Libraries\Liquidacao\LiquidacaoDominio;
use App\Libraries\Liquidacao\LiquidacaoException;
use DateTimeImmutable;

/**
 * Motor de cálculo da liquidação: correção monetária, juros de mora, multa e
 * honorários, com memória de cálculo passo a passo.
 *
 * Regras do motor (nenhum critério jurídico está fixo aqui; índice, taxa, termo
 * inicial e base vêm do critério informado):
 *  - valores em centavos; aritmética com bcmath; arredondamento "meio para cima"
 *    (afastando de zero) ao centavo, aplicado ao valor corrigido de cada parcela,
 *    aos juros de cada parcela e a cada item de percentual;
 *  - correção: valor × fator acumulado do índice entre a data da parcela e a
 *    data-base (mês inicial/final conforme `mes_inicial`/`mes_final`; meses finais
 *    ainda não divulgados ficam de fora e são avisados na memória);
 *  - juros: período em meses = meses fechados + dias restantes/30, da data inicial
 *    (padrão: a da parcela) até a data-base, sem contar a data-base. Simples:
 *    base × taxa × meses. Compostos com taxa fixa: (1+taxa)^meses_inteiros × (1+taxa × dias/30).
 *    Com índice: simples soma as variações do índice; compostos usa o fator acumulado;
 *  - troca de critério (`mudancas`): vale a partir do primeiro dia do mês da data
 *    informada (a correção do mês é de um único índice);
 *  - abatimentos entram como parcelas negativas, corrigidas desde a sua data.
 */
class CalculoLiquidacaoService
{
    /** Tipos cujo valor compõe a base "principal". */
    private const TIPOS_PRINCIPAIS = [
        LiquidacaoDominio::ITEM_PRINCIPAL,
        LiquidacaoDominio::ITEM_CUSTAS,
        LiquidacaoDominio::ITEM_OUTROS,
    ];

    protected IndicesEconomicosService $indices;

    public function __construct(?IndicesEconomicosService $indices = null)
    {
        $this->indices = $indices ?? new IndicesEconomicosService();
    }

    /**
     * Calcula todos os itens da liquidação (os de percentual por último).
     *
     * @param list<array> $itens itens com `item_id`, `tipo_item`, `descricao`, `valor_base`,
     *                           `valor_calculado` (valor manual) e `criterio` (array ou JSON)
     *
     * @return array<int|string, array{valor: int, memoria: array}> por chave do item na lista de entrada
     */
    public function calcularLiquidacao(array $itens, string $dataBase, array $criterioPadrao = []): array
    {
        $this->validarData($dataBase);
        $criterioPadrao = CriterioCalculo::normalizar($criterioPadrao);

        $resultado   = [];
        $percentuais = [];
        foreach ($itens as $chave => $item) {
            $proprio = CriterioCalculo::normalizar($item['criterio'] ?? null);
            if (isset($proprio['percentual']) && in_array($item['tipo_item'] ?? '', [LiquidacaoDominio::ITEM_MULTA, LiquidacaoDominio::ITEM_HONORARIOS], true)) {
                $percentuais[$chave] = $proprio;
                continue;
            }
            $resultado[$chave] = $this->calcularItemComContexto($item, $proprio, $dataBase, $criterioPadrao);
        }

        // multa antes de honorários: honorários sobre o "total" incluem a multa
        foreach ([LiquidacaoDominio::ITEM_MULTA, LiquidacaoDominio::ITEM_HONORARIOS] as $tipo) {
            foreach ($percentuais as $chave => $criterio) {
                if ($itens[$chave]['tipo_item'] === $tipo) {
                    $resultado[$chave] = $this->calcularPercentual($itens[$chave], $criterio, $itens, $resultado, $dataBase);
                }
            }
        }

        $ordenado = [];
        foreach (array_keys($itens) as $chave) {
            $ordenado[$chave] = $resultado[$chave];
        }

        return $ordenado;
    }

    /**
     * Calcula um item isolado (simulação). Itens de percentual não se aplicam aqui.
     *
     * @return array{valor: int, memoria: array}
     */
    public function calcularItem(array $item, string $dataBase, array $criterioPadrao = []): array
    {
        $this->validarData($dataBase);

        return $this->calcularItemComContexto(
            $item,
            CriterioCalculo::normalizar($item['criterio'] ?? null),
            $dataBase,
            CriterioCalculo::normalizar($criterioPadrao)
        );
    }

    // ---- Item "calculado" ---------------------------------------------

    private function calcularItemComContexto(array $item, array $proprio, string $dataBase, array $padrao): array
    {
        if ($proprio === []) {
            $valor = (int) ($item['valor_calculado'] ?? $item['valor_base'] ?? 0);

            return ['valor' => $valor, 'memoria' => ['versao' => 1, 'tipo' => 'manual', 'data_base' => $dataBase, 'valor' => $valor]];
        }

        $efetivo = CriterioCalculo::mesclar($padrao, $proprio);
        unset($efetivo['usar_padrao']);

        try {
            return $this->calcular($item, $efetivo, $dataBase);
        } catch (LiquidacaoException $e) {
            $nome = trim((string) ($item['descricao'] ?? ''));
            throw new LiquidacaoException(($nome !== '' ? "Item \"{$nome}\": " : '') . $e->getMessage(), $e->status(), $e->erros());
        }
    }

    private function calcular(array $item, array $criterio, string $dataBase): array
    {
        $base = new DateTimeImmutable($dataBase);

        // parcelas do item e abatimentos
        $lancamentos = [];
        if (!empty($criterio['parcelas'])) {
            foreach ($criterio['parcelas'] as $p) {
                $lancamentos[] = $p + ['abatimento' => false];
            }
        } else {
            $data = $criterio['correcao']['data_inicial'] ?? $criterio['juros']['data_inicial'] ?? null;
            if ($data === null || $data === 'vencimento') {
                $temJuros = !empty($criterio['juros']) && $criterio['juros']['tipo'] !== 'nenhum';
                if (!empty($criterio['correcao']) || $temJuros) {
                    throw LiquidacaoException::invalida('Informe a data inicial da correção (ou as parcelas) para calcular o item.');
                }
                $data = $dataBase;
            }
            $lancamentos[] = ['data' => $data, 'valor' => (int) ($item['valor_base'] ?? 0), 'abatimento' => false];
        }
        foreach ($criterio['abatimentos'] ?? [] as $a) {
            $lancamentos[] = ['data' => $a['data'], 'valor' => -abs($a['valor']), 'abatimento' => true];
        }

        $parcelasMemoria = [];
        $totalCorrigido  = 0;
        $totalJuros      = 0;
        $avisos          = [];

        foreach ($lancamentos as $lanc) {
            $d = $this->calcularLancamento($lanc, $criterio, $base);
            $parcelasMemoria[] = $d['memoria'];
            $totalCorrigido   += $d['corrigido'];
            $totalJuros       += $d['juros'];
            array_push($avisos, ...$d['avisos']);
        }

        $valor = $totalCorrigido + $totalJuros;

        return [
            'valor'   => $valor,
            'memoria' => [
                'versao'          => 1,
                'tipo'            => 'calculado',
                'data_base'       => $dataBase,
                'criterio'        => $criterio,
                'parcelas'        => $parcelasMemoria,
                'total_corrigido' => $totalCorrigido,
                'total_juros'     => $totalJuros,
                'valor'           => $valor,
                'avisos'          => array_values(array_unique($avisos)),
            ],
        ];
    }

    /** @return array{corrigido: int, juros: int, memoria: array, avisos: list<string>} */
    private function calcularLancamento(array $lanc, array $criterio, DateTimeImmutable $base): array
    {
        $inicio = new DateTimeImmutable($lanc['data']);
        $avisos = [];
        $valor  = $lanc['valor'];

        $memoria = ['data' => $lanc['data'], 'valor' => $valor, 'abatimento' => $lanc['abatimento'], 'correcao' => [], 'fator_correcao' => '1'];

        if ($inicio > $base) {
            $avisos[] = "Lançamento de {$lanc['data']} é posterior à data-base; não recebe correção nem juros.";

            return [
                'corrigido' => $valor, 'juros' => 0, 'avisos' => $avisos,
                'memoria'   => $memoria + ['corrigido' => $valor, 'juros' => ['tipo' => 'nenhum', 'valor' => 0], 'subtotal' => $valor],
            ];
        }

        $periodos = $this->periodos($criterio, $inicio, $base);

        // ---- correção
        $fator = '1';
        $ultimo = count($periodos) - 1;
        foreach ($periodos as $i => $p) {
            if (empty($p['correcao'])) {
                continue;
            }
            $regra = [
                'mes_inicial'          => $i === 0 ? ($p['correcao']['mes_inicial'] ?? 'cheio') : 'cheio',
                'mes_final'            => $i === $ultimo ? ($p['correcao']['mes_final'] ?? 'cheio') : 'cheio',
                'tolerar_lacuna_final' => $i === $ultimo,
            ];
            $fim = $p['fim_exclusivo']->modify($i === $ultimo ? '+0 days' : '-1 day');
            if ($p['inicio'] > $fim) {
                continue;
            }
            $d = $this->indices->fatorAcumuladoDetalhado($p['correcao']['indice'], $p['inicio']->format('Y-m-d'), $fim->format('Y-m-d'), $regra);
            $fator = Decimal::mul($fator, $d['fator']);
            $memoria['correcao'][] = [
                'indice' => $p['correcao']['indice'], 'de' => $p['inicio']->format('Y-m-d'), 'ate' => $fim->format('Y-m-d'),
                'fator' => $d['fator'], 'passos' => $d['passos'],
            ];
            if ($d['ignoradas'] !== []) {
                $avisos[] = "Índice {$p['correcao']['indice']} ainda não divulgado para " . implode(', ', $d['ignoradas'])
                    . '; esses meses ficaram fora da correção.';
            }
        }
        $corrigido = Decimal::centavos(Decimal::mul((string) $valor, $fator));
        $memoria['fator_correcao'] = $fator;
        $memoria['corrigido']      = $corrigido;

        // ---- juros
        $juros = $this->calcularJuros($criterio, $periodos, $inicio, $valor, $corrigido, $avisos);
        $memoria['juros'] = $juros['memoria'];

        $memoria['subtotal'] = $corrigido + $juros['valor'];

        return ['corrigido' => $corrigido, 'juros' => $juros['valor'], 'memoria' => $memoria, 'avisos' => $avisos];
    }

    /**
     * Períodos entre a data da parcela e a data-base, cada um com a correção e os juros em vigor.
     *
     * @return list<array{inicio: DateTimeImmutable, fim_exclusivo: DateTimeImmutable, correcao: ?array, juros: ?array}>
     */
    private function periodos(array $criterio, DateTimeImmutable $inicio, DateTimeImmutable $base): array
    {
        $cfg = ['correcao' => $criterio['correcao'] ?? null, 'juros' => $criterio['juros'] ?? null];
        $mesDoInicio = $inicio->modify('first day of this month');

        $proximas = [];
        foreach ($criterio['mudancas'] ?? [] as $m) {
            $limite = (new DateTimeImmutable($m['a_partir_de']))->modify('first day of this month');
            if ($limite <= $mesDoInicio) {
                $cfg = array_replace($cfg, array_intersect_key($m, ['correcao' => 1, 'juros' => 1]));
            } elseif ($limite <= $base) {
                $proximas[] = [$limite, $m];
            }
        }

        $periodos = [];
        $atual = $inicio;
        foreach ($proximas as [$limite, $m]) {
            $periodos[] = ['inicio' => $atual, 'fim_exclusivo' => $limite] + $cfg;
            $cfg   = array_replace($cfg, array_intersect_key($m, ['correcao' => 1, 'juros' => 1]));
            $atual = $limite;
        }
        $periodos[] = ['inicio' => $atual, 'fim_exclusivo' => $base] + $cfg;

        return $periodos;
    }

    /** @return array{valor: int, memoria: array} */
    private function calcularJuros(array $criterio, array $periodos, DateTimeImmutable $inicioLancamento, int $valor, int $corrigido, array &$avisos): array
    {
        $configInicial = null;
        $declarada     = null;
        foreach ($periodos as $p) {
            if (!empty($p['juros']) && $p['juros']['tipo'] !== 'nenhum') {
                $configInicial ??= $p['juros'];
                $declarada     ??= $p['juros']['data_inicial'] ?? null;
            }
        }
        if ($configInicial === null) {
            return ['valor' => 0, 'memoria' => ['tipo' => 'nenhum', 'valor' => 0]];
        }

        $inicioJuros = $inicioLancamento;
        if ($declarada !== null && $declarada !== 'vencimento') {
            $inicioJuros = max($inicioLancamento, new DateTimeImmutable($declarada));
        }

        $baseJuros = ($configInicial['base'] ?? 'corrigido') === 'nominal' ? $valor : $corrigido;
        $acumulado = '0';
        $segmentos = [];

        foreach ($periodos as $p) {
            $juros = $p['juros'] ?? null;
            if ($juros === null || $juros['tipo'] === 'nenhum') {
                continue;
            }
            $de  = max($p['inicio'], $inicioJuros);
            $ate = $p['fim_exclusivo'];
            if ($de >= $ate) {
                continue;
            }

            $dif   = $de->diff($ate);
            $meses = ($dif->y * 12) + $dif->m;
            $dias  = $dif->d;
            $mesesDecimal = Decimal::soma((string) $meses, Decimal::div((string) $dias, '30'));

            $seg = ['tipo' => $juros['tipo'], 'de' => $de->format('Y-m-d'), 'ate_exclusive' => $ate->format('Y-m-d'), 'meses' => $mesesDecimal];

            if (isset($juros['indice'])) {
                $ultimoDia = $ate->modify('-1 day');
                $d = $this->indices->fatorAcumuladoDetalhado($juros['indice'], $de->format('Y-m-d'), $ultimoDia->format('Y-m-d'), ['tolerar_lacuna_final' => true]);
                $seg['indice'] = $juros['indice'];
                $seg['passos'] = $d['passos'];
                if ($d['ignoradas'] !== []) {
                    $avisos[] = "Índice {$juros['indice']} (juros) ainda não divulgado para " . implode(', ', $d['ignoradas']) . '.';
                }
                if ($juros['tipo'] === 'simples') {
                    $taxa = '0';
                    foreach ($d['passos'] as $passo) {
                        $taxa = Decimal::soma($taxa, Decimal::mul(Decimal::div($passo['valor'], '100', 14), $passo['fracao'], 14));
                    }
                    $seg['taxa_acumulada'] = $taxa;
                    $acumulado = Decimal::soma($acumulado, Decimal::mul((string) $baseJuros, $taxa));
                } else {
                    $seg['fator'] = $d['fator'];
                    $acumulado = Decimal::sub(Decimal::mul(Decimal::soma((string) $baseJuros, $acumulado), $d['fator']), (string) $baseJuros);
                }
            } else {
                $taxa = Decimal::div($juros['taxa_mensal'], '100', 14);
                $seg['taxa_mensal'] = $juros['taxa_mensal'];
                if ($juros['tipo'] === 'simples') {
                    $seg['taxa_acumulada'] = Decimal::mul($taxa, $mesesDecimal);
                    $acumulado = Decimal::soma($acumulado, Decimal::mul((string) $baseJuros, $seg['taxa_acumulada']));
                } else {
                    $potencia = bcpow(Decimal::soma('1', $taxa), (string) $meses, Decimal::ESCALA);
                    $parcial  = Decimal::soma('1', Decimal::mul($taxa, Decimal::div((string) $dias, '30')));
                    $seg['fator'] = Decimal::mul($potencia, $parcial);
                    $acumulado = Decimal::sub(Decimal::mul(Decimal::soma((string) $baseJuros, $acumulado), $seg['fator']), (string) $baseJuros);
                }
            }
            $segmentos[] = $seg;
        }

        $valorJuros = Decimal::centavos($acumulado);

        return ['valor' => $valorJuros, 'memoria' => [
            'base'      => $configInicial['base'] ?? 'corrigido',
            'base_valor' => $baseJuros,
            'segmentos' => $segmentos,
            'valor'     => $valorJuros,
        ]];
    }

    // ---- Item de percentual (multa, honorários) --------------------------

    /**
     * @param array<int|string, array> $itens
     * @param array<int|string, array{valor: int, memoria: array}> $calculados
     */
    private function calcularPercentual(array $item, array $criterio, array $itens, array $calculados, string $dataBase): array
    {
        $tipoBase = $criterio['base_percentual'] ?? 'principal';
        $ehHonorarios = $item['tipo_item'] === LiquidacaoDominio::ITEM_HONORARIOS;

        $base = 0;
        foreach ($calculados as $chave => $c) {
            $tipo = $itens[$chave]['tipo_item'] ?? '';

            if (($c['memoria']['tipo'] ?? '') === 'percentual') {
                // a multa em percentual só entra na base "total" dos honorários
                if ($ehHonorarios && $tipoBase === 'total' && $tipo === LiquidacaoDominio::ITEM_MULTA) {
                    $base += $c['valor'];
                }
                continue;
            }

            $pertence = match ($tipoBase) {
                'principal'       => in_array($tipo, self::TIPOS_PRINCIPAIS, true),
                'principal_juros' => in_array($tipo, [...self::TIPOS_PRINCIPAIS, LiquidacaoDominio::ITEM_JUROS], true),
                default           => true,
            };
            if ($pertence) {
                // base "principal" = valor corrigido, sem os juros do item
                $base += $tipoBase === 'principal' && isset($c['memoria']['total_corrigido'])
                    ? $c['memoria']['total_corrigido']
                    : $c['valor'];
            }
        }

        $valor = Decimal::centavos(Decimal::div(Decimal::mul((string) $base, $criterio['percentual']), '100'));

        return ['valor' => $valor, 'memoria' => [
            'versao'          => 1,
            'tipo'            => 'percentual',
            'data_base'       => $dataBase,
            'percentual'      => $criterio['percentual'],
            'base_percentual' => $tipoBase,
            'base_valor'      => $base,
            'valor'           => $valor,
        ]];
    }

    private function validarData(string $data): void
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if ($d === false || $d->format('Y-m-d') !== $data) {
            throw LiquidacaoException::invalida("Data-base inválida: {$data}.");
        }
    }
}
