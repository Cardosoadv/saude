<?php

declare(strict_types=1);

namespace App\Repositories\Financeiro;

use App\Models\Financeiro\FinanceiroCategoriasModel;
use App\Repositories\BaseRepository;

/**
 * Repository de Categorias
 * @author Cardoso
 * @version 0.9.1
 * @since 2026-01-01
 *
 * @property FinanceiroCategoriasModel $model
 */
class CategoriasRepository extends BaseRepository
{
    public function __construct(?FinanceiroCategoriasModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('Financeiro/FinanceiroCategoriasModel');
        $this->cacheKey = 'financeiro_categorias';
    }

    /**
     * Busca todas as categorias ordenadas por nome.
     * Sobrescreve o método base para garantir a ordenação.
     *
     * @param int $limit Limite de registros.
     * @param int $offset Deslocamento.
     * @return array
     */
    public function findAll(int $limit = 0, int $offset = 0): array
    {
        return $this->model->orderBy('categoria', 'ASC')->findAll($limit, $offset);
    }

    public function paginate(int $perPage = 20, string $group = 'default', ?int $page = null, string $search = ''): array
    {
        $this->model->orderBy('categoria', 'ASC');

        if (!empty($search)) {
            $this->model->like('categoria', $search);
        }

        return parent::paginate($perPage, $group, $page);
    }

    /**
     * Retorna a árvore de categorias (pais e filhos) de forma plana com indicação de nível.
     * Utiliza cache versionado.
     *
     * @param string|null $search Termo de busca opcional.
     * @return array
     */
    public function findAllTree(?string $search = null): array
    {
        $suffix = empty($search) ? 'arvore_categorias' : 'arvore_categorias_search_' . md5($search);

        return $this->remember($suffix, function () use ($search) {
            $builder = $this->model->select('fin_categorias.*, fin_elementos.nome as elemento_nome, pai.categoria as pai_nome')
                ->join('fin_elementos', 'fin_elementos.id = fin_categorias.elemento_id', 'left')
                ->join('fin_categorias as pai', 'pai.id_categoria = fin_categorias.categoria_pai_id', 'left')
                ->orderBy('fin_categorias.categoria', 'ASC');

            if (!empty($search)) {
                $builder->groupStart()
                    ->like('fin_categorias.categoria', $search)
                    ->orLike('pai.categoria', $search)
                    ->groupEnd();
            }

            $all = $builder->findAll();

            // Se houver busca, retornamos lista plana apenas filtrada
            if (!empty($search)) {
                foreach ($all as &$c) {
                    $c['level'] = 0;
                }
                return $all;
            }

            // Agrupar por pai
            $grouped = [];
            foreach ($all as $cat) {
                $paiId = $cat['categoria_pai_id'] ?? 'root';
                if ($cat['categoria_pai_id'] === null) {
                    $paiId = 'root';
                }
                $grouped[$paiId][] = $cat;
            }

            // Recursividade para aplanar
            return $this->recursiveFlatten($grouped, 'root', 0);
        });
    }

    private function recursiveFlatten(array $grouped, $paiId, int $level): array
    {
        $result = [];
        if (isset($grouped[$paiId])) {
            foreach ($grouped[$paiId] as $cat) {
                $cat['level'] = $level;
                $result[] = $cat;

                // Buscar filhos
                $children = $this->recursiveFlatten($grouped, $cat['id_categoria'], $level + 1);

                // Merge dos filhos
                if (!empty($children)) {
                    $result = array_merge($result, $children);
                }
            }
        }
        return $result;
    }

    public function searchParents(string $term): array
    {
        return $this->model
            ->select('id_categoria, categoria, elemento_id')
            ->like('categoria', $term)
            ->orderBy('categoria', 'ASC')
            ->findAll(10);
    }

    /**
     * Busca dados para o relatório financeiro consolidado por categoria.
     *
     * @param string|null $dataInicio Data inicial (Y-m-d).
     * @param string|null $dataFim Data final (Y-m-d).
     * @return array
     */
    public function getRelatorioData(?string $dataInicio = null, ?string $dataFim = null): array
    {
        // Passo 1: Obter estrutura de categorias (Reutilizando lógica confiável do findAllTree)
        $categorias = $this->findAllTree();

        if (empty($categorias)) {
            return [];
        }

        // Passo 2: Buscar totais de receitas por categoria usando Query Builder.
        // Soma o valor rateado (fin_receitas_categorias.valor) por categoria, já que uma
        // mesma receita pode estar classificada em mais de uma categoria.
        $builderReceitas = $this->db->table('fin_receitas_categorias as rc')
            ->select('rc.categoria_id as categoria, SUM(rc.valor) as total')
            ->join('fin_receitas as r', 'r.id_receita = rc.receita_id')
            ->where('r.deleted_at', null)
            ->groupBy('rc.categoria_id');

        if ($dataInicio) {
            $builderReceitas->where('r.vencimento_dt >=', $dataInicio);
        }
        if ($dataFim) {
            $builderReceitas->where('r.vencimento_dt <=', $dataFim);
        }
        $receitasQuery = $builderReceitas->get()->getResultArray();

        // Passo 3: Buscar totais de despesas por categoria usando Query Builder.
        // Mesma lógica de rateio aplicada às despesas.
        $builderDespesas = $this->db->table('fin_despesas_categorias as dc')
            ->select('dc.categoria_id as categoria, SUM(dc.valor) as total')
            ->join('fin_despesas as d', 'd.id_despesa = dc.despesa_id')
            ->where('d.deleted_at', null)
            ->groupBy('dc.categoria_id');

        if ($dataInicio) {
            $builderDespesas->where('d.vencimento_dt >=', $dataInicio);
        }
        if ($dataFim) {
            $builderDespesas->where('d.vencimento_dt <=', $dataFim);
        }
        $despesasQuery = $builderDespesas->get()->getResultArray();

        // Criar mapas de totais
        $mapReceitas = [];
        foreach ($receitasQuery as $r) {
            $mapReceitas[(int) $r['categoria']] = (float) $r['total'];
        }

        $mapDespesas = [];
        foreach ($despesasQuery as $d) {
            $mapDespesas[(int) $d['categoria']] = (float) $d['total'];
        }

        // Passo 4: Mapear valores iniciais na lista plana
        // Como findAllTree já retorna flat list, podemos iterar diretamente.
        // Precisamos criar um índice por ID para referência rápida durante a soma recursiva.
        $byId = [];
        foreach ($categorias as &$cat) {
            $catId = (int) $cat['id_categoria'];
            $cat['total_receitas_proprio'] = $mapReceitas[$catId] ?? 0.0;
            $cat['total_despesas_proprio'] = $mapDespesas[$catId] ?? 0.0;

            $cat['total_receitas_com_filhos'] = $cat['total_receitas_proprio'];
            $cat['total_despesas_com_filhos'] = $cat['total_despesas_proprio'];

            $byId[$catId] = &$cat;
        }
        unset($cat);

        // Passo 5: Somar recursivamente (de baixo para cima)
        // findAllTree retorna pais antes de filhos. Para somar filhos aos pais, iteramos de trás para frente.
        for ($i = count($categorias) - 1; $i >= 0; $i--) {
            $cat = $categorias[$i];

            if (!empty($cat['categoria_pai_id'])) {
                $paiId = (int) $cat['categoria_pai_id'];
                if (isset($byId[$paiId])) {
                    $pai = &$byId[$paiId];
                    $pai['total_receitas_com_filhos'] += $cat['total_receitas_com_filhos'];
                    $pai['total_despesas_com_filhos'] += $cat['total_despesas_com_filhos'];
                }
            }
        }

        return $categorias;
    }


    public function findAllElementos(): array
    {
        return \Config\Database::connect()->table('fin_elementos')->orderBy('nome', 'ASC')->get()->getResultArray();
    }
}
