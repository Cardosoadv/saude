<?php

declare(strict_types=1);

namespace App\Repositories\Checklist;

use App\Models\ProcedimentoModel;
use App\Repositories\BaseRepository;

/**
 * Repositório para Procedimentos de Checklist.
 */
class ProcedimentosRepository extends BaseRepository
{
    /**
     * Construtor do repositório.
     * Define o modelo principal como ProcedimentoModel.
     */
    public function __construct(
        ?ProcedimentoModel $model = null
    ) {
        parent::__construct();
        $this->model = $model ?? new ProcedimentoModel();
        $this->cacheKey = 'checklist_procedimentos';
    }

    /**
     * Retorna procedimentos paginados com busca.
     */
    public function getPaged(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        $filtersKey = md5(serialize($filters));
        return $this->remember("paged_{$page}_{$perPage}_{$filtersKey}", function () use ($page, $perPage, $filters) {
            $builder = $this->model->where('deleted_at', null);

            if (!empty($filters['search'])) {
                $builder->groupStart()
                    ->like('nome', $filters['search'])
                    ->orLike('descricao', $filters['search'])
                    ->groupEnd();
            }

            return [
                'data'    => $builder->paginate($perPage, 'default', $page),
                'pager'   => $this->getPager(),
                'total'   => $this->countAll($filters),
                'perPage' => $perPage,
            ];
        });
    }

    /**
     * Conta o total de registros com base nos filtros.
     */
    public function countAll(array $filters = []): int
    {
        $builder = $this->model->where('deleted_at', null);

        if (!empty($filters['search'])) {
            $builder->groupStart()
                    ->like('nome', $filters['search'])
                    ->orLike('descricao', $filters['search'])
                    ->groupEnd();
        }

        return $builder->countAllResults(false);
    }

    /**
     * Verifica se já existe um procedimento com o nome informado.
     *
     * @param string $nome
     * @param int|null $ignoreId
     * @return bool
     */
    public function existeComNome(string $nome, ?int $ignoreId = null): bool
    {
        $builder = $this->model->where('nome', $nome)->where('deleted_at', null);

        if ($ignoreId) {
            $builder->where('id !=', $ignoreId);
        }

        return $builder->countAllResults() > 0;
    }
}
