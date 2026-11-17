<?php

namespace App\Traits;

/**
 * Trait para utilitários de busca, como sanitização e tratamento de strings.
 */
trait SearchTrait
{
    /**
     * Sanitiza strings de busca (remove acentos e converte para minúsculas).
     *
     * @param string $search String de busca.
     * @return string String sanitizada.
     */
    protected function sanitizeSearch(string $search): string
    {
        $sanitized = mb_strtolower($search, 'UTF-8');
        return strtr(
            $sanitized,
            'áàâãäÁÀÂÃÄéèêëÉÈÊËíìîïÍÌÎÏóòôõöÓÒÔÕÖúùûüÚÙÛÜçÇ',
            'aaaaaAAAAAeeeeEEEEiiiiIIIIoooooOOOOOuuuuUUUUcC'
        );
    }

    /**
     * Converte a string do GROUP_CONCAT em array de etiquetas (Legado).
     * 
     * @deprecated Use o hidratador de etiquetas direto para performance superior.
     *
     * @param string|null $etiquetasJson String no formato: "1:|:Nome1:|:#FF0000,|,2:|:Nome2:|:#00FF00"
     * @return array Array de etiquetas.
     */
    protected function parseEtiquetasFromGroupConcat(?string $etiquetasJson): array
    {
        if (empty($etiquetasJson)) {
            return [];
        }

        $etiquetasJson = str_replace('`', '', $etiquetasJson);
        $items = explode(',|,', $etiquetasJson);

        $etiquetas = [];

        foreach ($items as $item) {
            $item = trim($item);
            if (empty($item)) continue;

            $parts = explode(':|:', $item);
            if (count($parts) === 3) {
                $etiquetas[] = [
                    'id_etiqueta' => $parts[0],
                    'nome' => $parts[1],
                    'cor' => $parts[2]
                ];
            }
        }
        return $etiquetas;
    }
}
