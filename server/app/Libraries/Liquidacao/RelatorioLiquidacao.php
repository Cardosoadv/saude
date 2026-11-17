<?php

declare(strict_types=1);

namespace App\Libraries\Liquidacao;

/**
 * Monta, a partir da liquidação e da memória de cálculo gravada em cada item, os dados do
 * relatório (PDF, CSV e XLSX). Não recalcula nada: só apresenta o que o motor guardou.
 *
 * Valores monetários ficam em centavos inteiros; a formatação (R$) é de quem apresenta.
 */
final class RelatorioLiquidacao
{
    public const COLUNAS = ['Item', 'Tipo', 'Data', 'Valor original', 'Fator de correção', 'Valor corrigido', 'Juros', 'Subtotal', 'Observação'];

    /**
     * @param array                  $liquidacao liquidação com `itens` e `pagamentos` (LiquidacoesService::getById)
     * @param array<int,array|null>  $memorias   memória de cálculo por `item_id`
     * @param array                  $contexto   `processo` (numero, cliente), `poloAtivo`, `poloPassivo` (listas de nomes)
     *
     * @return array{cabecalho: array, itens: list<array>, abatimentos: list<array>, totais: array, linhas: list<list<int|string|null>>, avisos: list<string>}
     */
    public static function montar(array $liquidacao, array $memorias, array $contexto = []): array
    {
        $itens       = [];
        $abatimentos = [];
        $linhas      = [];
        $avisos      = [];

        foreach ($liquidacao['itens'] ?? [] as $item) {
            $memoria = $memorias[(int) $item['item_id']] ?? null;
            $nome    = (string) ($item['descricao'] ?? '');
            $tipo    = (string) ($item['tipo_item'] ?? '');
            $valor   = (int) ($item['valor_calculado'] ?? 0);

            $blocoLinhas = [];
            $criterio    = [];

            if ($memoria === null || ($memoria['tipo'] ?? 'manual') === 'manual') {
                $blocoLinhas[] = [$nome, $tipo, $liquidacao['data_base'] ?? null, (int) ($item['valor_base'] ?? $valor), null, null, null, $valor, 'Valor informado manualmente'];
                $criterio[]    = 'Valor informado manualmente (sem critério de cálculo).';
            } elseif ($memoria['tipo'] === 'percentual') {
                $blocoLinhas[] = [$nome, $tipo, $memoria['data_base'] ?? null, (int) $memoria['base_valor'], null, null, null, (int) $memoria['valor'],
                    self::numero((string) $memoria['percentual']) . '% sobre ' . self::baseLegivel((string) ($memoria['base_percentual'] ?? 'principal'))];
                $criterio[] = self::numero((string) $memoria['percentual']) . '% sobre ' . self::baseLegivel((string) ($memoria['base_percentual'] ?? 'principal')) . '.';
            } else {
                $criterio = self::descreverCriterio($memoria['criterio'] ?? []);
                foreach ($memoria['parcelas'] ?? [] as $p) {
                    $abatimento = !empty($p['abatimento']);
                    $linha = [
                        $nome, $tipo, $p['data'] ?? null,
                        (int) $p['valor'],
                        self::fator((string) ($p['fator_correcao'] ?? '1')),
                        (int) ($p['corrigido'] ?? $p['valor']),
                        (int) ($p['juros']['valor'] ?? 0),
                        (int) ($p['subtotal'] ?? $p['valor']),
                        $abatimento ? 'Abatimento' : null,
                    ];
                    $blocoLinhas[] = $linha;
                    if ($abatimento) {
                        $abatimentos[] = ['item' => $nome, 'data' => $p['data'] ?? null, 'valor' => abs((int) $p['valor'])];
                    }
                }
                foreach ($memoria['avisos'] ?? [] as $a) {
                    $avisos[] = ($nome !== '' ? $nome . ': ' : '') . $a;
                }
            }

            array_push($linhas, ...$blocoLinhas);
            $itens[] = ['descricao' => $nome, 'tipo' => $tipo, 'valor' => $valor, 'criterio' => $criterio, 'linhas' => $blocoLinhas];
        }

        $linhas[] = ['TOTAL', null, $liquidacao['data_base'] ?? null, null, null, null, null, (int) ($liquidacao['valor_total'] ?? 0), 'Valor atualizado na data-base'];

        return [
            'cabecalho' => [
                'liquidacao_id' => (int) $liquidacao['liquidacao_id'],
                'processo'      => $contexto['processo'] ?? null,
                'cliente'       => $contexto['cliente'] ?? null,
                'poloAtivo'     => $contexto['poloAtivo'] ?? [],
                'poloPassivo'   => $contexto['poloPassivo'] ?? [],
                'status'        => $liquidacao['status'] ?? null,
                'data_base'     => $liquidacao['data_base'] ?? null,
                'calculado_em'  => $liquidacao['calculado_em'] ?? null,
                'criterio_padrao' => self::descreverCriterio($liquidacao['criterio_padrao'] ?? []),
            ],
            'itens'       => $itens,
            'abatimentos' => $abatimentos,
            'totais'      => ['valor_total' => (int) ($liquidacao['valor_total'] ?? 0), 'abatimentos' => array_sum(array_column($abatimentos, 'valor'))],
            'linhas'      => $linhas,
            'avisos'      => $avisos,
        ];
    }

    /** Frases curtas que descrevem o critério usado (para o cabeçalho do relatório). */
    public static function descreverCriterio(array $criterio): array
    {
        $frases = [];
        if (!empty($criterio['correcao']['indice'])) {
            $c = $criterio['correcao'];
            $frases[] = 'Correção monetária pelo ' . $c['indice']
                . (!empty($c['data_inicial']) && $c['data_inicial'] !== 'vencimento' ? ', a partir de ' . self::data($c['data_inicial']) : '') . '.';
        }
        if (!empty($criterio['juros']) && ($criterio['juros']['tipo'] ?? 'nenhum') !== 'nenhum') {
            $j = $criterio['juros'];
            $taxa = isset($j['indice']) ? 'pelo índice ' . $j['indice'] : 'à taxa de ' . self::numero((string) ($j['taxa_mensal'] ?? '0')) . '% ao mês';
            $inicio = !empty($j['data_inicial']) && $j['data_inicial'] !== 'vencimento' ? 'a partir de ' . self::data($j['data_inicial']) : 'a partir do vencimento';
            $frases[] = 'Juros ' . ($j['tipo'] === 'compostos' ? 'compostos' : 'simples') . ' ' . $taxa . ', ' . $inicio . ', sobre o valor ' . ($j['base'] ?? 'corrigido') . '.';
        } elseif (isset($criterio['juros']) || isset($criterio['correcao'])) {
            $frases[] = 'Sem juros.';
        }
        foreach ($criterio['mudancas'] ?? [] as $m) {
            $partes = [];
            if (!empty($m['correcao']['indice'])) {
                $partes[] = 'correção pelo ' . $m['correcao']['indice'];
            }
            if (!empty($m['juros'])) {
                $partes[] = ($m['juros']['tipo'] ?? '') === 'nenhum' ? 'sem juros' : 'juros ' . ($m['juros']['tipo'] ?? '') . (isset($m['juros']['indice']) ? ' pelo ' . $m['juros']['indice'] : ' a ' . self::numero((string) ($m['juros']['taxa_mensal'] ?? '0')) . '% a.m.');
            }
            $frases[] = 'A partir de ' . self::data($m['a_partir_de']) . ': ' . implode('; ', $partes) . '.';
        }

        return $frases;
    }

    /** CSV em UTF-8 com BOM, separador `;` e valores em reais com vírgula (abre direto no Excel pt-BR). */
    public static function csv(array $relatorio): string
    {
        $saida = fopen('php://temp', 'r+');
        fwrite($saida, "\xEF\xBB\xBF");
        fputcsv($saida, self::cabecalhoPlanilha($relatorio), ';');
        fputcsv($saida, self::COLUNAS, ';');
        foreach ($relatorio['linhas'] as $l) {
            fputcsv($saida, [
                $l[0], self::tipoLegivel((string) $l[1]), self::data($l[2]),
                self::reais($l[3]), str_replace('.', ',', (string) ($l[4] ?? '')), self::reais($l[5]), self::reais($l[6]), self::reais($l[7]), $l[8] ?? '',
            ], ';');
        }
        rewind($saida);
        $conteudo = stream_get_contents($saida);
        fclose($saida);

        return $conteudo;
    }

    /** Linhas para o XLSX: valores numéricos em reais (a planilha formata) e datas no formato brasileiro. */
    public static function linhasXlsx(array $relatorio): array
    {
        $linhas = [[self::cabecalhoPlanilha($relatorio)[0]], self::COLUNAS];
        foreach ($relatorio['linhas'] as $l) {
            $linhas[] = [
                $l[0], self::tipoLegivel((string) $l[1]), self::data($l[2]),
                self::paraReais($l[3]), $l[4], self::paraReais($l[5]), self::paraReais($l[6]), self::paraReais($l[7]), $l[8],
            ];
        }

        return $linhas;
    }

    private static function cabecalhoPlanilha(array $relatorio): array
    {
        $c = $relatorio['cabecalho'];

        return [trim(sprintf('Liquidação #%d%s - data-base %s', $c['liquidacao_id'], $c['processo'] ? ' - processo ' . $c['processo'] : '', self::data($c['data_base'])))];
    }

    public static function reais(int|string|null $centavos): string
    {
        return $centavos === null || $centavos === '' ? '' : number_format(((int) $centavos) / 100, 2, ',', '.');
    }

    public static function paraReais(int|string|null $centavos): ?float
    {
        return $centavos === null || $centavos === '' ? null : round(((int) $centavos) / 100, 2);
    }

    public static function data(?string $data): string
    {
        if ($data === null || $data === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $data, $m)) {
            return (string) $data;
        }

        return "{$m[3]}/{$m[2]}/{$m[1]}";
    }

    /** Fator com no máximo 8 casas, sem zeros à direita. */
    public static function fator(string $fator): string
    {
        return self::numero(bcadd($fator, '0', 8), true);
    }

    private static function numero(string $valor, bool $ponto = false): string
    {
        $valor = str_contains($valor, '.') ? rtrim(rtrim($valor, '0'), '.') : $valor;
        $valor = $valor === '' ? '0' : $valor;

        return $ponto ? $valor : str_replace('.', ',', $valor);
    }

    private static function baseLegivel(string $base): string
    {
        return match ($base) {
            'principal_juros' => 'o principal corrigido com juros',
            'total'           => 'o total dos demais itens',
            default           => 'o principal corrigido',
        };
    }

    private static function tipoLegivel(string $tipo): string
    {
        return match ($tipo) {
            'principal'  => 'Principal',
            'juros'      => 'Juros',
            'multa'      => 'Multa',
            'honorarios' => 'Honorários',
            'custas'     => 'Custas',
            'outros'     => 'Outros',
            default      => $tipo,
        };
    }
}
