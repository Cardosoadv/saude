<?php

namespace App\Repositories;

use App\Models\DocumentoModeloModel;
use App\Repositories\Traits\HasEtiquetasPolimorficas;

/**
 * Repositório para gerenciar Modelos de Documentos.
 */
class DocumentoModeloRepository extends BaseRepository
{
    use HasEtiquetasPolimorficas;

    protected function etiquetaEntidadeTipo(): string
    {
        return 'modelo';
    }

    public function __construct(?DocumentoModeloModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? new DocumentoModeloModel();
        $this->cacheKey = 'doc_modelos';
    }

    /**
     * Lista modelos ativos filtrados por ramo do direito.
     */
    public function listarAtivos(?int $ramoId = null): array
    {
        $suffix = "ativos_" . ($ramoId ?? 'todos');
        return $this->remember($suffix, function() use ($ramoId) {
            $builder = $this->model->where('ativo', 1);
            if ($ramoId) {
                $builder->where('ramo_id', $ramoId);
            }
            return $builder->orderBy('titulo', 'ASC')->findAll();
        });
    }

    /**
     * Busca um modelo pelo ID, garantindo que esteja ativo.
     */
    public function findAtivo(int $id): ?array
    {
        return $this->remember("ativo_{$id}", function() use ($id) {
            return $this->model->where('ativo', 1)->find($id);
        });
    }

    /**
     * Busca modelos por relevância textual (FULLTEXT em titulo+conteudo).
     */
    public function buscarPorTermo(string $termo, int $limit = 5): array
    {
        return $this->remember("busca_termo_" . md5($termo) . "_{$limit}", function () use ($termo, $limit) {
            $query = $this->db->query(
                "SELECT *, MATCH(titulo, conteudo) AGAINST(? IN NATURAL LANGUAGE MODE) as relevancia
                 FROM doc_modelos
                 WHERE status = ? AND deleted_at IS NULL
                 HAVING relevancia > 0
                 ORDER BY relevancia DESC
                 LIMIT ?",
                [$termo, 'Publicado', $limit]
            );

            return $query->getResultArray();
        });
    }

    /**
     * Lista modelos ativos compatíveis com o tipo de alvo informado (processo/caso/ambos).
     */
    public function listarPorTipoAlvo(string $tipoAlvo): array
    {
        return $this->remember("por_tipo_alvo_{$tipoAlvo}", function () use ($tipoAlvo) {
            return $this->model->where('ativo', 1)
                ->whereIn('tipo_alvo', [$tipoAlvo, 'ambos'])
                ->orderBy('titulo', 'ASC')
                ->findAll();
        });
    }

    /**
     * Lista modelos combinando filtros opcionais (ramo, status, tipo_alvo, ativo,
     * etiqueta, busca textual), paginados. Sem cache: os filtros variam demais
     * para valer a pena versionar (mesmo critério de listarComEtiqueta()).
     */
    public function listarComFiltros(array $filtros, int $perPage = 20, ?int $page = null): array
    {
        $builder = $this->model;
        $selectCampos = 'doc_modelos.*';

        if (!empty($filtros['etiqueta_id'])) {
            $builder->join('doc_etiquetas as de', "de.entidade_id = doc_modelos.id AND de.entidade_tipo = 'modelo'")
                ->where('de.etiqueta_id', $filtros['etiqueta_id']);
        }
        if (!empty($filtros['ramo_id'])) {
            $builder->where('ramo_id', $filtros['ramo_id']);
        }
        if (!empty($filtros['status'])) {
            $builder->where('status', $filtros['status']);
        }
        if (!empty($filtros['tipo_alvo'])) {
            $builder->where('tipo_alvo', $filtros['tipo_alvo']);
        }
        if (isset($filtros['ativo']) && $filtros['ativo'] !== '') {
            $builder->where('ativo', (int) $filtros['ativo']);
        }

        if (!empty($filtros['q'])) {
            $termoEscapado = $this->db->escape($filtros['q']);
            $matchExpr = "MATCH(titulo, conteudo) AGAINST({$termoEscapado} IN NATURAL LANGUAGE MODE)";
            // Filtro em WHERE (não HAVING/alias): Model::paginate() chama countAllResults(),
            // que troca o SELECT por COUNT(*) - um agregado sem GROUP BY. Nesse caso o MySQL só
            // enxerga em HAVING as colunas da projeção do SELECT (aqui, só o COUNT(*)), então
            // qualquer HAVING que referencie colunas da tabela (via alias ou não) quebra a
            // query de contagem. WHERE é avaliado antes da agregação e não tem essa limitação.
            $selectCampos .= ", {$matchExpr} as relevancia";
            $builder->select($selectCampos)
                ->where("{$matchExpr} >", 0, false)
                ->orderBy('relevancia', 'DESC');
        } else {
            $builder->select($selectCampos)->orderBy('titulo', 'ASC');
        }

        return $builder->paginate($perPage, 'default', $page);
    }

    /**
     * Retorna o total de modelos ativos.
     */
    public function contarAtivos(): int
    {
        return $this->remember('count_ativos', function() {
            return $this->model->where('ativo', 1)->countAllResults();
        });
    }
}
