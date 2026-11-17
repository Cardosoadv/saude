<?php

namespace App\Repositories;

use App\Models\FornecedoresModel;

/**
 * Repositório para operações de banco de dados relacionadas a fornecedores.
 *
 * Esta classe encapsula todas as consultas ao banco de dados relacionadas
 * a fornecedores, fornecendo uma camada de abstração entre o serviço e o modelo.
 */
class FornecedorRepository extends BaseRepository
{
    /**
     * Construtor do FornecedorRepository.
     */
    public function __construct(?FornecedoresModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('FornecedoresModel');
        $this->cacheKey = 'fornecedores';
    }

    /**
     * Busca fornecedores com filtros e paginação.
     *
     * @param array $filters Filtros de busca
     * @param int $perPage Itens por página
     * @param int $page Número da página
     * @return array
     */
    public function buscarFornecedores(array $filters = [], int $perPage = 25, int $page = 1): array
    {
        $filtersKey = md5(json_encode($filters));

        return $this->remember("buscar_{$filtersKey}_page_{$page}_per_{$perPage}", function () use ($filters, $perPage, $page) {
            $builder = $this->model->orderBy('nome', 'ASC');

            if (!empty($filters['search'])) {
                $builder->groupStart()
                    ->like('nome', $filters['search'])
                    ->orLike('documento', $filters['search'])
                    ->groupEnd();
            }

            return [
                'fornecedores' => $builder->paginate($perPage, 'default', $page),
                'pager'        => $builder->pager,
            ];
        });
    }

    /**
     * Verifica se existe fornecedor com o documento.
     *
     * @param string $documento
     * @param int|null $excluirId ID para excluir da verificação
     * @return bool
     */
    public function existeDocumento(string $documento, ?int $excluirId = null): bool
    {
        $builder = $this->model->where('documento', $documento);

        if ($excluirId !== null) {
            $builder->where('id_fornecedor !=', $excluirId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Retorna estatísticas resumidas de fornecedores.
     *
     * @return array
     */
    public function getEstatisticas(): array
    {
        return $this->remember('estatisticas', function () {
            $query = $this->db->table($this->model->table)
                ->select('COUNT(*) as total')
                ->select('SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as ativos')
                ->select('SUM(CASE WHEN ativo = 0 THEN 1 ELSE 0 END) as inativos')
                ->get();

            $result = $query->getRowArray();
            return [
                'total'    => (int) ($result['total'] ?? 0),
                'ativos'   => (int) ($result['ativos'] ?? 0),
                'inativos' => (int) ($result['inativos'] ?? 0),
            ];
        });
    }

    /**
     * Retorna os fornecedores mais recentes.
     *
     * @param int $limit
     * @return array
     */
    public function getRecentes(int $limit = 5): array
    {
        return $this->remember("recentes_{$limit}", function () use ($limit) {
            return $this->model->select('id_fornecedor, nome, documento, created_at')
                ->orderBy('created_at', 'DESC')
                ->limit($limit)
                ->findAll();
        });
    }

    /**
     * Realiza uma busca leve para autocomplete.
     * Otimizado para selecionar apenas campos necessários e evitar COUNT(*) de paginação.
     *
     * @param string $term Termo de busca
     * @param int $limit Limite de resultados
     * @return array
     */
    public function search(string $term, int $limit = 15): array
    {
        $normalizedTerm = mb_strtolower(trim($term));
        $cacheKey = "search_" . md5($normalizedTerm) . "_lim_{$limit}";

        return $this->remember($cacheKey, function () use ($term, $limit) {
            return $this->model
                ->select('id_fornecedor, nome, documento')
                ->groupStart()
                    ->like('nome', $term)
                    ->orLike('documento', $term)
                ->groupEnd()
                ->orderBy('nome', 'ASC')
                ->findAll($limit);
        });
    }
}
