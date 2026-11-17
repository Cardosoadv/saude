<?php

namespace App\Repositories;

use App\Models\EtiquetasModel;
use Exception;

/**
 * Repositório para gerenciar etiquetas.
 * Lida com operações de banco de dados e limpeza de cache.
 */
class EtiquetasRepository extends BaseRepository
{
    public function __construct(?EtiquetasModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('EtiquetasModel');
        $this->cacheKey = 'etiquetas';
    }

    /**
     * Sobrescreve clearCache para incluir a limpeza do cache de processos.
     */
    public function clearCache(): void
    {
        parent::clearCache();

        // Incrementa a versão do cache de processos para refletir mudanças em etiquetas
        $processosRepo = new ProcessosRepository();
        $processosRepo->clearCache();
    }

    /**
     * Salva uma etiqueta (cria ou atualiza).
     *
     * @param array $data Dados da etiqueta.
     * @return int ID da etiqueta salva.
     * @throws Exception
     */
    public function salvarEtiqueta(array $data): int
    {
        $id = isset($data['id_etiqueta']) && !empty($data['id_etiqueta']) ? (int) $data['id_etiqueta'] : null;

        try {
            if ($id) {
                $this->update($id, $data);
            } else {
                $id = $this->create($data);
            }

            return $id;
        } catch (Exception $e) {
            log_message('error', 'Erro ao salvar etiqueta: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Deleta uma etiqueta.
     *
     * @param int $id ID da etiqueta.
     * @return bool
     * @throws Exception
     */
    public function deletarEtiqueta(int $id): bool
    {
        $this->db->transStart();

        try {
            // Remove vínculos com processos antes
            $this->db->table('processos_etiquetas')->where('etiqueta_id', $id)->delete();

            // Remove vínculos com modelos/documentos gerados (pivot polimórfica)
            $this->db->table('doc_etiquetas')->where('etiqueta_id', $id)->delete();

            $result = $this->delete($id);

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new Exception('Erro ao deletar etiqueta.');
            }

            return $result;
        } catch (Exception $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Lista todas as etiquetas com cache.
     *
     * @return array
     */
    public function listarEtiquetas(string $orderby = 'nome', string $order = 'asc'): array
    {
        return $this->remember("lista_{$orderby}_{$order}", function () use ($orderby, $order) {
            return $this->model->orderBy($orderby, $order)->findAll();
        });
    }

    /**
     * Busca uma etiqueta pelo nome.
     *
     * @param string|null $nome
     * @return array|null
     */
    public function getByNome(?string $nome): ?array
    {
        return $this->model->where('nome', $nome)->first();
    }

    /**
     * Retorna o ID da última inserção.
     *
     * @return int
     */
    public function getInsertID(): int
    {
        return (int) $this->model->getInsertID();
    }
}
