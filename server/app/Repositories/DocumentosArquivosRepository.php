<?php

namespace App\Repositories;

use App\Models\DocumentosArquivosModel;

/**
 * Repositório para gerenciar metadados de arquivos de documentos.
 */
class DocumentosArquivosRepository extends BaseRepository
{
    /**
     * Construtor da classe DocumentosArquivosRepository.
     */
    public function __construct(?DocumentosArquivosModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('DocumentosArquivosModel');
        $this->cacheKey = 'documentos_arquivos';
    }

    /**
     * Lista todos os documentos ordenados por data de criação decrescente.
     *
     * @return array
     */
    public function listarTodos(): array
    {
        return $this->remember('todos', function() {
            return $this->model->orderBy('created_at', 'DESC')->findAll();
        });
    }
}
