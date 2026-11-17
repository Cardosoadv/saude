<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\DocumentoGeradoModel;
use App\Repositories\Traits\HasEtiquetasPolimorficas;

/**
 * Repositório para gerenciar Documentos Gerados a partir de Modelos.
 */
class DocumentoGeradoRepository extends BaseRepository
{
    use HasEtiquetasPolimorficas;

    protected function etiquetaEntidadeTipo(): string
    {
        return 'documento_gerado';
    }

    public function __construct(?DocumentoGeradoModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? new DocumentoGeradoModel();
        $this->cacheKey = 'doc_documentos_gerados';
    }

    /**
     * Histórico de documentos gerados a partir de um processo ou caso.
     */
    public function listarPorEntidade(string $tipo, int $id): array
    {
        return $this->remember("por_entidade_{$tipo}_{$id}", function () use ($tipo, $id) {
            return $this->model
                ->where('entidade_tipo', $tipo)
                ->where('entidade_id', $id)
                ->orderBy('created_at', 'DESC')
                ->findAll();
        });
    }

    /**
     * Lista documentos gerados com filtros diversos, paginados.
     */
    public function listarComFiltros(array $filtros, int $perPage = 20, ?int $page = null): array
    {
        $builder = $this->model;

        if (!empty($filtros['status'])) {
            $builder->where('status', $filtros['status']);
        }
        if (!empty($filtros['modelo_id'])) {
            $builder->where('modelo_id', $filtros['modelo_id']);
        }
        if (!empty($filtros['cliente_id'])) {
            $builder->where('cliente_id', $filtros['cliente_id']);
        }
        if (!empty($filtros['ramo_id'])) {
            $builder->where('ramo_id', $filtros['ramo_id']);
        }
        if (!empty($filtros['data_inicio'])) {
            $builder->where('created_at >=', $filtros['data_inicio']);
        }
        if (!empty($filtros['data_fim'])) {
            $builder->where('created_at <=', $filtros['data_fim']);
        }
        if (!empty($filtros['etiqueta_id'])) {
            $builder->join(
                'doc_etiquetas as de',
                "de.entidade_id = doc_documentos_gerados.id AND de.entidade_tipo = 'documento_gerado'"
            )->where('de.etiqueta_id', $filtros['etiqueta_id']);
        }

        return $builder->orderBy('created_at', 'DESC')->paginate($perPage, 'default', $page);
    }

    /**
     * Marca um documento gerado como finalizado, vinculando o arquivo definitivo.
     */
    public function marcarFinalizado(int $id, int $documentoArquivoId, int $userId): bool
    {
        return $this->update($id, [
            'status'               => 'Finalizado',
            'documento_arquivo_id' => $documentoArquivoId,
            'finalizado_em'        => date('Y-m-d H:i:s'),
            'finalizado_por'       => $userId,
        ]);
    }
}
