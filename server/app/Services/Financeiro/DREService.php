<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Repositories\Financeiro\CategoriasRepository;
use App\Services\BaseService;

/**
 * Serviço responsável por gerar o Demonstrativo de Resultados do Exercício (DRE).
 *
 * @version 1.0.0
 * @since 2026-04-08
 */
class DREService extends BaseService
{
    public function __construct(?CategoriasRepository $repository = null)
    {
        $this->repository = $repository ?? new CategoriasRepository();
    }

    /**
     * Gera os dados estruturados da DRE para um período.
     *
     * @param string|null $dataInicio
     * @param string|null $dataFim
     * @return array
     */
    public function gerarDRE(?string $dataInicio = null, ?string $dataFim = null): array
    {
        // 1. Obter dados brutos consolidados por categoria (já inclui soma de filhos)
        $categorias = $this->repository->getRelatorioData($dataInicio, $dataFim);

        // 2. Estrutura inicial da DRE
        $dre = [
            'receita_bruta' => [
                'titulo' => 'RECEITA OPERACIONAL BRUTA',
                'valor'  => 0.0,
                'itens'  => [],
            ],
            'deducoes' => [
                'titulo' => '(-) DEDUÇÕES DA RECEITA BRUTA',
                'valor'  => 0.0,
                'itens'  => [],
            ],
            'receita_liquida' => [
                'titulo' => '(=) RECEITA OPERACIONAL LÍQUIDA',
                'valor'  => 0.0,
            ],
            'despesas_operacionais' => [
                'titulo' => '(-) DESPESAS OPERACIONAIS',
                'valor'  => 0.0,
                'itens'  => [],
            ],
            'resultado_operacional' => [
                'titulo' => '(=) RESULTADO OPERACIONAL (EBITDA)',
                'valor'  => 0.0,
            ],
            'nao_operacional' => [
                'titulo' => '(+/-) RESULTADO NÃO OPERACIONAL',
                'valor'  => 0.0,
                'itens'  => [],
            ],
            'resultado_liquido' => [
                'titulo' => '(=) RESULTADO LÍQUIDO DO EXERCÍCIO',
                'valor'  => 0.0,
            ],
        ];

        // 3. Classificar categorias (Apenas nível 0 para evitar duplicidade na soma dos grupos principais)
        foreach ($categorias as $cat) {
            if ($cat['level'] > 0) {
                continue;
            }

            $nomeLower = mb_strtolower($cat['categoria']);
            $elementoNome = $cat['elemento_nome'] ?? '';
            $valorReceita = (float) $cat['total_receitas_com_filhos'];
            $valorDespesa = (float) $cat['total_despesas_com_filhos'];
            $saldo = $valorReceita - $valorDespesa;

            // Lógica de classificação
            if ($elementoNome === 'Receita') {
                if (str_contains($nomeLower, 'não operacional')) {
                    $dre['nao_operacional']['itens'][] = $cat;
                    $dre['nao_operacional']['valor'] += $saldo;
                } else {
                    $dre['receita_bruta']['itens'][] = $cat;
                    $dre['receita_bruta']['valor'] += $saldo;
                }
            } elseif ($elementoNome === 'Despesa') {
                if (str_contains($nomeLower, 'impostos') || str_contains($nomeLower, 'taxas') || str_contains($nomeLower, 'deduções')) {
                    $dre['deducoes']['itens'][] = $cat;
                    $dre['deducoes']['valor'] += $valorDespesa; // Deduções entram como valor positivo para serem subtraídas depois
                } elseif (str_contains($nomeLower, 'não operacional')) {
                    $dre['nao_operacional']['itens'][] = $cat;
                    $dre['nao_operacional']['valor'] -= $valorDespesa;
                } else {
                    $dre['despesas_operacionais']['itens'][] = $cat;
                    $dre['despesas_operacionais']['valor'] += $valorDespesa;
                }
            }
        }

        // 4. Calcular Totais Intermediários
        $dre['receita_liquida']['valor'] = $dre['receita_bruta']['valor'] - $dre['deducoes']['valor'];
        $dre['resultado_operacional']['valor'] = $dre['receita_liquida']['valor'] - $dre['despesas_operacionais']['valor'];
        $dre['resultado_liquido']['valor'] = $dre['resultado_operacional']['valor'] + $dre['nao_operacional']['valor'];

        return $dre;
    }
}
