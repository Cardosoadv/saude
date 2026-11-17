<?php

declare(strict_types=1);

namespace App\Libraries\Liquidacao;

use DateTimeImmutable;

/**
 * Critério de cálculo de um item (ou critério padrão da liquidação).
 *
 * Estrutura (todas as chaves são opcionais):
 *
 *   usar_padrao: true                          calcula o item só com o critério padrão da liquidação
 *   correcao:   { indice, data_inicial, mes_inicial, mes_final }
 *   juros:      { tipo: simples|compostos|nenhum, taxa_mensal | indice, data_inicial | "vencimento", base: corrigido|nominal }
 *   mudancas:   [ { a_partir_de, correcao: { indice }, juros: { tipo, taxa_mensal | indice } } ]
 *   parcelas:   [ { data, valor } ]            competências do item; cada uma corrigida desde a sua data
 *   abatimentos:[ { data, valor } ]            pagamentos/depósitos, descontados na data em que ocorreram (valor positivo)
 *   percentual: "10.5"                         itens multa/honorários: percentual sobre `base_percentual`
 *   base_percentual: principal | principal_juros | total
 *
 * Nada aqui é fixo no código: índice, taxa e termos iniciais vêm sempre do cadastro do usuário.
 */
final class CriterioCalculo
{
    public const CHAVES = ['usar_padrao', 'correcao', 'juros', 'mudancas', 'parcelas', 'abatimentos', 'percentual', 'base_percentual'];

    public const TIPOS_JUROS = ['simples', 'compostos', 'nenhum'];
    public const BASES_JUROS = ['corrigido', 'nominal'];
    public const BASES_PERCENTUAL = ['principal', 'principal_juros', 'total'];
    public const REGRAS_MES = ['cheio', 'pro_rata', 'excluir'];

    /**
     * Valida e normaliza. Devolve o critério limpo (só chaves conhecidas).
     *
     * @param array|string|null $bruto array ou JSON
     *
     * @throws LiquidacaoException 422 com o campo e o motivo
     */
    public static function normalizar(array|string|null $bruto): array
    {
        if ($bruto === null || $bruto === '' || $bruto === []) {
            return [];
        }
        if (is_string($bruto)) {
            $decodificado = json_decode($bruto, true);
            if (!is_array($decodificado)) {
                throw LiquidacaoException::invalida('Critério de cálculo inválido (JSON malformado).', ['criterio' => 'JSON malformado.']);
            }
            $bruto = $decodificado;
        }

        $desconhecidas = array_diff(array_keys($bruto), self::CHAVES);
        if ($desconhecidas !== []) {
            throw self::erro('criterio', 'Chaves desconhecidas: ' . implode(', ', $desconhecidas) . '.');
        }

        $criterio = [];
        if (!empty($bruto['usar_padrao'])) {
            $criterio['usar_padrao'] = true;
        }
        if (isset($bruto['correcao'])) {
            $criterio['correcao'] = self::correcao($bruto['correcao'], 'correcao');
        }
        if (isset($bruto['juros'])) {
            $criterio['juros'] = self::juros($bruto['juros'], 'juros');
        }
        if (!empty($bruto['mudancas'])) {
            $mudancas = [];
            foreach ((array) $bruto['mudancas'] as $i => $m) {
                $campo = "mudancas.{$i}";
                if (!is_array($m)) {
                    throw self::erro($campo, 'Mudança inválida.');
                }
                $item = ['a_partir_de' => self::data($m['a_partir_de'] ?? null, "{$campo}.a_partir_de")];
                if (isset($m['correcao'])) {
                    $item['correcao'] = self::correcao($m['correcao'], "{$campo}.correcao");
                }
                if (isset($m['juros'])) {
                    $item['juros'] = self::juros($m['juros'], "{$campo}.juros");
                }
                if (count($item) === 1) {
                    throw self::erro($campo, 'A mudança precisa trocar a correção ou os juros.');
                }
                $mudancas[] = $item;
            }
            usort($mudancas, static fn ($a, $b) => $a['a_partir_de'] <=> $b['a_partir_de']);
            $criterio['mudancas'] = $mudancas;
        }
        foreach (['parcelas', 'abatimentos'] as $lista) {
            if (!empty($bruto[$lista])) {
                $criterio[$lista] = [];
                foreach ((array) $bruto[$lista] as $i => $p) {
                    if (!is_array($p) || !isset($p['valor']) || !is_numeric($p['valor']) || (int) $p['valor'] != $p['valor']) {
                        throw self::erro("{$lista}.{$i}", 'Informe `data` e `valor` inteiro em centavos.');
                    }
                    $criterio[$lista][] = [
                        'data'  => self::data($p['data'] ?? null, "{$lista}.{$i}.data"),
                        'valor' => (int) $p['valor'],
                    ];
                }
            }
        }
        if (isset($bruto['percentual']) && $bruto['percentual'] !== '') {
            $criterio['percentual'] = self::percentual($bruto['percentual'], 'percentual');
        }
        if (isset($bruto['base_percentual'])) {
            if (!in_array($bruto['base_percentual'], self::BASES_PERCENTUAL, true)) {
                throw self::erro('base_percentual', 'Use ' . implode(', ', self::BASES_PERCENTUAL) . '.');
            }
            $criterio['base_percentual'] = $bruto['base_percentual'];
        }

        return $criterio;
    }

    /** Critério efetivo: o do item sobrepõe, chave a chave, o padrão da liquidação. */
    public static function mesclar(array $padrao, array $item): array
    {
        return array_replace($padrao, $item);
    }

    private static function correcao(mixed $c, string $campo): array
    {
        if (!is_array($c) || empty($c['indice']) || !is_string($c['indice'])) {
            throw self::erro($campo, 'Informe o `indice` da correção.');
        }
        $saida = ['indice' => strtoupper(trim($c['indice']))];
        if (!empty($c['data_inicial'])) {
            $saida['data_inicial'] = self::data($c['data_inicial'], "{$campo}.data_inicial");
        }
        foreach (['mes_inicial', 'mes_final'] as $regra) {
            if (isset($c[$regra])) {
                if (!in_array($c[$regra], self::REGRAS_MES, true)) {
                    throw self::erro("{$campo}.{$regra}", 'Use ' . implode(', ', self::REGRAS_MES) . '.');
                }
                $saida[$regra] = $c[$regra];
            }
        }

        return $saida;
    }

    private static function juros(mixed $j, string $campo): array
    {
        if (!is_array($j) || !in_array($j['tipo'] ?? null, self::TIPOS_JUROS, true)) {
            throw self::erro("{$campo}.tipo", 'Use ' . implode(', ', self::TIPOS_JUROS) . '.');
        }
        $saida = ['tipo' => $j['tipo']];
        if ($j['tipo'] === 'nenhum') {
            return $saida;
        }

        $temTaxa   = isset($j['taxa_mensal']) && $j['taxa_mensal'] !== '';
        $temIndice = !empty($j['indice']);
        if ($temTaxa === $temIndice) {
            throw self::erro($campo, 'Informe `taxa_mensal` ou `indice` (um dos dois).');
        }
        if ($temTaxa) {
            $saida['taxa_mensal'] = self::percentual($j['taxa_mensal'], "{$campo}.taxa_mensal");
        } else {
            $saida['indice'] = strtoupper(trim((string) $j['indice']));
        }
        if (!empty($j['data_inicial'])) {
            $saida['data_inicial'] = $j['data_inicial'] === 'vencimento'
                ? 'vencimento'
                : self::data($j['data_inicial'], "{$campo}.data_inicial");
        }
        if (isset($j['base'])) {
            if (!in_array($j['base'], self::BASES_JUROS, true)) {
                throw self::erro("{$campo}.base", 'Use ' . implode(', ', self::BASES_JUROS) . '.');
            }
            $saida['base'] = $j['base'];
        }

        return $saida;
    }

    private static function percentual(mixed $valor, string $campo): string
    {
        $texto = str_replace(',', '.', trim((string) $valor));
        if (!preg_match('/^\d+(\.\d+)?$/', $texto)) {
            throw self::erro($campo, 'Percentual inválido.');
        }

        return $texto;
    }

    private static function data(mixed $valor, string $campo): string
    {
        $d = is_string($valor) ? DateTimeImmutable::createFromFormat('!Y-m-d', $valor) : false;
        if ($d === false || $d->format('Y-m-d') !== $valor) {
            throw self::erro($campo, 'Data inválida (use AAAA-MM-DD).');
        }

        return $valor;
    }

    private static function erro(string $campo, string $motivo): LiquidacaoException
    {
        return LiquidacaoException::invalida("Critério de cálculo inválido em {$campo}: {$motivo}", [$campo => $motivo]);
    }
}
