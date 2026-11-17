<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use InvalidArgumentException;

/**
 * Gera a chave de idempotência dos movimentos de extrato bancário.
 *
 * Serviço puro (sem banco): a mesma entrada sempre produz a mesma chave.
 *
 *   com FITID : sha256( banco | agencia | conta | FITID )
 *   sem FITID : sha256( banco | agencia | conta | data | centavos | memo_normalizado | n )
 *
 * `n` é a ordem de ocorrência do mesmo trio (data, valor, memo) no arquivo,
 * começando em 1, o que separa duas operações idênticas legítimas no mesmo dia.
 * Valores entram sempre em centavos inteiros (nunca float). CHECKNUM e REFNUM
 * não fazem parte da chave: em alguns bancos são constantes no arquivo todo.
 */
final class ChaveMovimentoExtrato
{
    public const TIPO_FITID = 'fitid';
    public const TIPO_HASH  = 'hash';

    private const SEPARADOR      = '|';
    private const FITID_MAX      = 255;
    private const MEMO_MAX_CHAVE = 500;

    /**
     * Normaliza banco, agência ou conta: só dígitos, sem zeros à esquerda.
     * Se sobrar vazio (ex.: "000"), devolve "0"; sem nenhum dígito, devolve "".
     */
    public static function normalizarIdentificador(?string $valor): string
    {
        $digitos = preg_replace('/\D+/', '', (string) $valor) ?? '';
        if ($digitos === '') {
            return '';
        }

        $semZeros = ltrim($digitos, '0');

        return $semZeros === '' ? '0' : $semZeros;
    }

    /**
     * Normaliza o memo: UTF-8, minúsculas, sem acentos, sem números de
     * protocolo variáveis conhecidos e com espaços colapsados.
     */
    public static function normalizarMemo(?string $memo): string
    {
        $texto = self::paraUtf8((string) $memo);

        $texto = mb_strtolower($texto, 'UTF-8');

        if (class_exists(\Normalizer::class)) {
            $decomposto = \Normalizer::normalize($texto, \Normalizer::FORM_D);
            if ($decomposto !== false) {
                $texto = preg_replace('/\p{Mn}+/u', '', $decomposto) ?? $texto;
            }
        } else {
            $texto = strtr($texto, [
                'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
                'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
                'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
                'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
                'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
                'ç' => 'c', 'ñ' => 'n',
            ]);
        }

        // Protocolos/autenticações variáveis: "protocolo 12345", "prot. 12345", "autenticacao: ab12cd34".
        $texto = preg_replace('/\b(protocolo|prot|autenticacao|aut)\.?\s*[:#-]?\s*[a-z0-9]{4,}\b/u', ' ', $texto) ?? $texto;

        // O separador da chave não pode aparecer dentro do campo.
        $texto = str_replace(self::SEPARADOR, ' ', $texto);

        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    /**
     * Converte um valor monetário para centavos inteiros, sem passar por float
     * quando a entrada é texto. Aceita "-12.34", "12,34", "1.234,56" e inteiros.
     *
     * @param string|int $valor Texto decimal ou, se int, já em centavos.
     */
    public static function paraCentavos(string|int $valor): int
    {
        if (is_int($valor)) {
            return $valor;
        }

        $texto = trim($valor);
        if ($texto === '') {
            throw new InvalidArgumentException('Valor monetário vazio.');
        }

        $sinal = 1;
        if ($texto[0] === '-' || $texto[0] === '+') {
            $sinal = $texto[0] === '-' ? -1 : 1;
            $texto = ltrim(substr($texto, 1));
        }

        $ultimoPonto   = strrpos($texto, '.');
        $ultimaVirgula = strrpos($texto, ',');

        if ($ultimoPonto !== false && $ultimaVirgula !== false) {
            // O último separador é o decimal; o outro é de milhar.
            $decimal = max($ultimoPonto, $ultimaVirgula);
            $inteiro = preg_replace('/[.,]/', '', substr($texto, 0, $decimal)) ?? '';
            $frac    = substr($texto, $decimal + 1);
        } elseif ($ultimoPonto !== false || $ultimaVirgula !== false) {
            $posicao = (int) ($ultimoPonto !== false ? $ultimoPonto : $ultimaVirgula);
            $inteiro = substr($texto, 0, $posicao);
            $frac    = substr($texto, $posicao + 1);
            if (preg_match('/[.,]/', $inteiro)) {
                throw new InvalidArgumentException('Valor monetário inválido.');
            }
        } else {
            $inteiro = $texto;
            $frac    = '';
        }

        if ($inteiro === '') {
            $inteiro = '0';
        }
        if (!ctype_digit($inteiro) || ($frac !== '' && !ctype_digit($frac)) || strlen($frac) > 2) {
            throw new InvalidArgumentException('Valor monetário inválido.');
        }

        $centavos = (int) $inteiro * 100 + (int) str_pad($frac, 2, '0');

        return $sinal * $centavos;
    }

    /**
     * Gera a chave de um movimento. Usa o FITID quando ele é válido; caso
     * contrário, usa o fallback por hash.
     *
     * @param string|int $valor Texto decimal (ex.: "-12.34") ou centavos inteiros.
     * @param int        $n     Ordem de ocorrência do trio (data, valor, memo) no arquivo (>= 1).
     * @return array{chave:string,chave_tipo:string}
     */
    public static function gerar(
        ?string $banco,
        ?string $agencia,
        ?string $conta,
        ?string $fitid,
        string $data,
        string|int $valor,
        ?string $memo,
        int $n = 1
    ): array {
        $fitidLimpo = self::fitidValido($fitid);

        if ($fitidLimpo !== null) {
            return [
                'chave'      => self::chavePorFitid($banco, $agencia, $conta, $fitidLimpo),
                'chave_tipo' => self::TIPO_FITID,
            ];
        }

        return [
            'chave'      => self::chaveFallback($banco, $agencia, $conta, $data, $valor, $memo, $n),
            'chave_tipo' => self::TIPO_HASH,
        ];
    }

    public static function chavePorFitid(?string $banco, ?string $agencia, ?string $conta, string $fitid): string
    {
        $fitidLimpo = self::fitidValido($fitid);
        if ($fitidLimpo === null) {
            throw new InvalidArgumentException('FITID vazio ou inválido.');
        }

        return hash('sha256', implode(self::SEPARADOR, [
            self::normalizarIdentificador($banco),
            self::normalizarIdentificador($agencia),
            self::normalizarIdentificador($conta),
            $fitidLimpo,
        ]));
    }

    /**
     * @param string|int $valor Texto decimal ou centavos inteiros.
     */
    public static function chaveFallback(
        ?string $banco,
        ?string $agencia,
        ?string $conta,
        string $data,
        string|int $valor,
        ?string $memo,
        int $n = 1
    ): string {
        if ($n < 1) {
            throw new InvalidArgumentException('A ordem de ocorrência (n) começa em 1.');
        }

        $dataNormalizada = self::normalizarData($data);
        $memoNormalizado = mb_substr(self::normalizarMemo($memo), 0, self::MEMO_MAX_CHAVE, 'UTF-8');

        return hash('sha256', implode(self::SEPARADOR, [
            self::normalizarIdentificador($banco),
            self::normalizarIdentificador($agencia),
            self::normalizarIdentificador($conta),
            $dataNormalizada,
            (string) self::paraCentavos($valor),
            $memoNormalizado,
            (string) $n,
        ]));
    }

    /**
     * Gera as chaves de uma lista de movimentos de um mesmo arquivo, calculando
     * `n` (ordem do trio data+valor+memo) para os itens sem FITID válido.
     *
     * Cada item: ['fitid' => ?string, 'data' => 'Y-m-d', 'valor' => string|int, 'memo' => ?string].
     * O retorno preserva as posições e acrescenta 'chave' e 'chave_tipo'.
     *
     * @param array<int,array<string,mixed>> $itens
     * @return array<int,array<string,mixed>>
     */
    public static function gerarLote(?string $banco, ?string $agencia, ?string $conta, array $itens): array
    {
        $ocorrencias = [];
        $resultado   = [];

        foreach ($itens as $indice => $item) {
            $data  = (string) ($item['data'] ?? '');
            $valor = $item['valor'] ?? 0;
            $memo  = isset($item['memo']) ? (string) $item['memo'] : '';

            $n = 1;
            if (self::fitidValido($item['fitid'] ?? null) === null) {
                $trio = implode(self::SEPARADOR, [
                    self::normalizarData($data),
                    (string) self::paraCentavos($valor),
                    self::normalizarMemo($memo),
                ]);
                $ocorrencias[$trio] = ($ocorrencias[$trio] ?? 0) + 1;
                $n = $ocorrencias[$trio];
            }

            $resultado[$indice] = $item + self::gerar(
                $banco,
                $agencia,
                $conta,
                isset($item['fitid']) ? (string) $item['fitid'] : null,
                $data,
                $valor,
                $memo,
                $n
            );
        }

        return $resultado;
    }

    /**
     * FITID válido: não vazio depois do trim, sem caracteres de controle e até 255 caracteres.
     */
    private static function fitidValido(?string $fitid): ?string
    {
        if ($fitid === null) {
            return null;
        }

        $limpo = trim(self::paraUtf8($fitid));
        if ($limpo === '' || mb_strlen($limpo, 'UTF-8') > self::FITID_MAX || preg_match('/[\x00-\x1F\x7F]/', $limpo)) {
            return null;
        }

        return $limpo;
    }

    private static function normalizarData(string $data): string
    {
        $data = trim($data);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            throw new InvalidArgumentException('Data inválida: use o formato AAAA-MM-DD.');
        }

        [$ano, $mes, $dia] = array_map('intval', explode('-', $data));
        if (!checkdate($mes, $dia, $ano)) {
            throw new InvalidArgumentException('Data inválida: ' . $data);
        }

        return $data;
    }

    /**
     * Garante UTF-8; texto que não é UTF-8 válido é tratado como Windows-1252.
     */
    private static function paraUtf8(string $texto): string
    {
        if (mb_check_encoding($texto, 'UTF-8')) {
            return $texto;
        }

        return mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
    }
}
