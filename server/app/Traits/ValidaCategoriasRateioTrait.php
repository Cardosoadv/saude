<?php

namespace App\Traits;

use InvalidArgumentException;

/**
 * Trait ValidaCategoriasRateioTrait
 *
 * Valida o rateio de categorias de uma despesa ou receita: cada item deve
 * apontar para uma categoria válida e ter um valor em R$ maior que zero, sem
 * categorias repetidas, e a soma dos valores deve fechar exatamente o valor
 * total do lançamento.
 */
trait ValidaCategoriasRateioTrait
{
    /**
     * Valida e normaliza a lista de categorias do rateio.
     *
     * @param array $categorias Lista de ['categoria_id' => int, 'valor' => float]
     * @param float $valorTotal Valor total do lançamento (despesa ou receita).
     * @param bool $obrigatorio Se true, exige ao menos uma categoria.
     * @return array Lista normalizada de ['categoria_id' => int, 'valor' => float]
     * @throws InvalidArgumentException Se o rateio for inválido.
     */
    protected function validarCategoriasRateio(array $categorias, float $valorTotal, bool $obrigatorio = true): array
    {
        if (empty($categorias)) {
            if ($obrigatorio) {
                throw new InvalidArgumentException('Selecione ao menos uma categoria.');
            }
            return [];
        }

        $normalizado = [];
        $idsVistos = [];
        $soma = 0.0;

        foreach ($categorias as $item) {
            if (empty($item['categoria_id']) || !is_numeric($item['categoria_id'])) {
                throw new InvalidArgumentException('Cada categoria do rateio precisa ser selecionada.');
            }

            if (!isset($item['valor']) || !is_numeric($item['valor']) || (float) $item['valor'] <= 0) {
                throw new InvalidArgumentException('Cada categoria do rateio precisa de um valor maior que zero.');
            }

            $categoriaId = (int) $item['categoria_id'];

            if (isset($idsVistos[$categoriaId])) {
                throw new InvalidArgumentException('A mesma categoria não pode aparecer mais de uma vez no rateio.');
            }
            $idsVistos[$categoriaId] = true;

            $valor = round((float) $item['valor'], 2);
            $soma += $valor;

            $normalizado[] = [
                'categoria_id' => $categoriaId,
                'valor'        => $valor,
            ];
        }

        // Tolerância de 1 centavo para acomodar arredondamentos.
        if (abs($soma - round($valorTotal, 2)) > 0.01) {
            throw new InvalidArgumentException(sprintf(
                'A soma das categorias (R$ %s) precisa ser igual ao valor total do lançamento (R$ %s).',
                number_format($soma, 2, ',', '.'),
                number_format($valorTotal, 2, ',', '.')
            ));
        }

        return $normalizado;
    }
}
