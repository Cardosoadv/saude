<?php

declare(strict_types=1);

namespace App\Repositories\Traits;

/**
 * Vínculo de etiquetas (pivot polimórfica `doc_etiquetas`) para repositórios
 * de modelos e documentos gerados. Segue o mesmo padrão sem Model dedicado já
 * usado por `ProcessosModel` sobre `processos_etiquetas`.
 *
 * Requer que a classe que usa o trait tenha a propriedade $db (BaseRepository).
 */
trait HasEtiquetasPolimorficas
{
    /**
     * Valor de `entidade_tipo` usado pela entidade dona do vínculo (ex.: 'modelo', 'documento_gerado').
     */
    abstract protected function etiquetaEntidadeTipo(): string;

    /**
     * Busca as etiquetas vinculadas a uma entidade.
     */
    public function joinEtiquetas(int $entidadeId): array
    {
        return $this->db->table('doc_etiquetas as de')
            ->join('etiquetas as e', 'de.etiqueta_id = e.id_etiqueta', 'left')
            ->select('e.id_etiqueta, e.id_etiqueta AS id, e.nome, e.cor')
            ->where('de.entidade_tipo', $this->etiquetaEntidadeTipo())
            ->where('de.entidade_id', $entidadeId)
            ->get()->getResultArray();
    }

    /**
     * Substitui todas as etiquetas vinculadas a uma entidade pela lista informada.
     */
    public function sincronizarEtiquetas(int $entidadeId, array $etiquetaIds): void
    {
        $tipo = $this->etiquetaEntidadeTipo();

        $this->db->table('doc_etiquetas')
            ->where('entidade_tipo', $tipo)->where('entidade_id', $entidadeId)->delete();

        $linhas = array_map(fn ($etiquetaId) => [
            'entidade_tipo' => $tipo, 'entidade_id' => $entidadeId, 'etiqueta_id' => $etiquetaId,
        ], $etiquetaIds);

        if ($linhas) {
            $this->db->table('doc_etiquetas')->insertBatch($linhas);
        }
    }
}
