<?php

namespace App\Repositories;

/**
 * @autor Cardoso
 * @version 1.1.1
 * @since 2026-04-04
 *
 *
 *
 * Repositório responsável pelo acesso aos dados de anotações.
 */
class AnotacoesRepository extends BaseRepository
{
    /**
     * @param \App\Models\EntidadesAnotacoesModel|null $model
     */
    public function __construct(?\App\Models\EntidadesAnotacoesModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('EntidadesAnotacoesModel');
        $this->cacheKey = 'anotacoes';
    }

    /**
     * Busca anotações por documento.
     *
     * @param int $userId ID do usuário.
     * @param string $documento Documento (CPF/CNPJ).
     * @return array Lista de anotações.
     */
    public function getByDocumento(int $userId, string $documento): array
    {
        return $this->model->where('documento', $documento)
            ->groupStart()
            ->where('user_id', $userId)
            ->orWhere('privacidade', 2) // 2 é público/compartilhado neste contexto
            ->groupEnd()
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }
}
