<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\FavoritoConhecimentoModel;

/**
 * Repositório para favoritos da Base do Conhecimento (jurisprudências e modelos).
 * Não usa cache: dados por usuário, de baixo volume e mutados a cada clique.
 */
class FavoritoConhecimentoRepository extends BaseRepository
{
    public function __construct(?FavoritoConhecimentoModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? new FavoritoConhecimentoModel();
    }

    public function buscarPorUsuario(int $userId): array
    {
        return $this->model
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    public function buscarUm(int $userId, string $tipo, int $entidadeId): ?array
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('entidade_type', $tipo)
            ->where('entidade_id', $entidadeId)
            ->first();
    }

    /**
     * Retorna os IDs de entidade (de um tipo) já favoritados pelo usuário, dentre os informados.
     * Útil para marcar a estrela em listas (jurisprudências relevantes, resultados de busca, etc).
     */
    public function buscarIdsFavoritados(int $userId, string $tipo, array $entidadeIds): array
    {
        if (empty($entidadeIds)) {
            return [];
        }

        $rows = $this->model
            ->select('entidade_id')
            ->where('user_id', $userId)
            ->where('entidade_type', $tipo)
            ->whereIn('entidade_id', $entidadeIds)
            ->findAll();

        return array_map(static fn ($row) => (int) $row['entidade_id'], $rows);
    }
}
