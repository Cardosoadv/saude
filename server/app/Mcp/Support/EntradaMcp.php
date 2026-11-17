<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use Mcp\Exception\ToolCallException;

/**
 * Conversão dos valores que o modelo envia às Tools de escrita do MCP:
 * datas em formato brasileiro, opções sem distinção de acento e caixa.
 * Valor inválido vira ToolCallException com a mensagem para o modelo corrigir.
 */
final class EntradaMcp
{
    /** "dd/mm/aaaa" ou "aaaa-mm-dd" → "aaaa-mm-dd"; vazio → null. */
    public static function data(?string $valor, string $rotulo): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $valor, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $valor, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        throw new ToolCallException("{$rotulo}: data inválida \"{$valor}\" (use dd/mm/aaaa).");
    }

    /**
     * Uma das opções aceitas, comparando sem acento e caixa; vazio → null.
     *
     * @param list<string> $opcoes
     */
    public static function opcao(?string $valor, array $opcoes, string $rotulo): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        $chave = self::comparavel($valor);
        foreach ($opcoes as $opcao) {
            if (self::comparavel($opcao) === $chave) {
                return $opcao;
            }
        }

        throw new ToolCallException("{$rotulo}: \"{$valor}\" inválido. Use: " . implode(', ', $opcoes) . '.');
    }

    /** Texto aparado; vazio → null. */
    public static function texto(?string $valor, int $limite = 255): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : mb_substr($valor, 0, $limite);
    }

    /**
     * Responsável informado: precisa ser profissional ativo; null mantém o padrão
     * do cadastro (quem cria).
     */
    public static function responsavel(?int $responsavelId): ?int
    {
        if ($responsavelId === null) {
            return null;
        }
        if (!(new \App\Services\AcessoEntidadeService())->ehProfissionalAtivo($responsavelId)) {
            throw new ToolCallException('Responsável: informe um profissional ativo.');
        }

        return $responsavelId;
    }

    /** Texto em forma comparável: minúsculas, sem acento e com espaços simples. */
    public static function comparavel(?string $texto): string
    {
        $texto     = mb_strtolower(trim((string) $texto));
        $semAcento = class_exists(\Transliterator::class)
            ? \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC')?->transliterate($texto)
            : null;
        $texto = $semAcento ?? strtr($texto, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e',
            'í' => 'i', 'ì' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);

        return trim((string) preg_replace('/\s+/', ' ', $texto));
    }
}
