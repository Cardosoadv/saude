<?php

declare(strict_types=1);

namespace App\Libraries\Site\Template;

use App\Libraries\Site\HtmlSanitizer;

/**
 * Prepara o `dados` de uma seção para ser gravado:
 *  - mantém só os campos declarados no manifesto (e, em listas, só os campos do item);
 *  - descarta campos `dinamico` (vêm de provedores, não são gravados);
 *  - sanitiza `rich_text` (mesma lista de tags do editor da SPA);
 *  - remove vazios (`null`, `''`) para o JSON ficar enxuto.
 *
 * Roda ANTES da validação; erros de formato ficam por conta de SecaoDadosValidator.
 */
class SecaoDadosNormalizador
{
    /** @param array<string, array> $campos */
    public function normalizar(array $campos, array $dados): array
    {
        $saida = [];
        foreach ($campos as $nome => $def) {
            $tipo = $def['tipo'] ?? '';
            if ($tipo === 'dinamico' || !array_key_exists($nome, $dados)) {
                continue;
            }

            $valor = $dados[$nome];
            if ($tipo === 'rich_text' && is_string($valor)) {
                $valor = HtmlSanitizer::richText($valor);
            } elseif ($tipo === 'lista' && is_array($valor)) {
                $valor = array_values(array_map(
                    fn ($item) => is_array($item) ? $this->normalizar($def['itens'] ?? [], $item) : $item,
                    $valor
                ));
            }

            if ($valor !== null && $valor !== '') {
                $saida[$nome] = $valor;
            }
        }
        return $saida;
    }

    /**
     * Chaves de `dados` que o tipo não declara (serão descartadas), para avisar o editor.
     *
     * @return list<string>
     */
    public function descartadas(array $campos, array $dados): array
    {
        return array_values(array_diff(array_keys($dados), array_keys($campos)));
    }
}
