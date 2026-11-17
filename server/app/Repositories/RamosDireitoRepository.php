<?php

namespace App\Repositories;

use App\Models\RamosDireitoModel;

/**
 * Repositório para gerenciar Ramos do Direito.
 */
class RamosDireitoRepository extends BaseRepository
{
    public function __construct(?RamosDireitoModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? new RamosDireitoModel();
        $this->cacheKey = 'ramos_direito';
    }

    /**
     * Retorna todos os ramos em formato de árvore ou lista plana.
     */
    public function listarTodosHierarquico(): array
    {
        return $this->remember('hierarquico', function() {
            $todos = $this->model->orderBy('nome', 'ASC')->findAll();
            return $this->buildTree($todos);
        });
    }

    /**
     * Helper para montar a árvore hierárquica.
     */
    private function buildTree(array $elements, $parentId = 0) {
        $branch = array();
        foreach ($elements as $element) {
            $elementPaiId = isset($element['pai_id']) ? (int)$element['pai_id'] : 0;
            if ($elementPaiId === (int)$parentId) {
                $children = $this->buildTree($elements, $element['id']);
                if ($children) {
                    $element['filhos'] = $children;
                } else {
                    $element['filhos'] = [];
                }
                $branch[] = $element;
            }
        }
        return $branch;
    }

    /**
     * Busca sub-ramos de um determinado pai.
     */
    public function findByPai(?int $paiId = null): array
    {
        return $this->remember("pai_{$paiId}", function() use ($paiId) {
            return $this->model->where('pai_id', $paiId)->orderBy('nome', 'ASC')->findAll();
        });
    }

    /**
     * Busca ramos pelo nome.
     *
     * Mantém LIKE em vez de FULLTEXT (padrão usado em Modelos/Argumentos): a tabela
     * só tem `nome` como coluna pesquisável e baixo volume de registros (taxonomia
     * fixa de ramos do direito), então um índice FULLTEXT não traria ganho de
     * relevância sobre um LIKE simples. Ver issue #604.
     */
    public function buscarPorNome(string $nome, int $limit = 5): array
    {
        return $this->remember("busca_nome_" . md5($nome) . "_{$limit}", function() use ($nome, $limit) {
            return $this->model->like('nome', $nome)->findAll($limit);
        });
    }

    /**
     * Retorna o total de ramos.
     */
    public function contarTodos(): int
    {
        return $this->remember('count_all', function() {
            return $this->model->countAllResults();
        });
    }

    /**
     * Retorna um mapa plano [id => nome] de todos os ramos cadastrados.
     */
    public function mapaIdNome(): array
    {
        return $this->remember('mapa_id_nome', function() {
            $todos = $this->model->select('id, nome')->findAll();
            $mapa = [];
            foreach ($todos as $ramo) {
                $mapa[(int) $ramo['id']] = $ramo['nome'];
            }
            return $mapa;
        });
    }
}
