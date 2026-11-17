<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DecisaoJudicialRepository;

/**
 * Serviço responsável pela gestão de decisões judiciais.
 */
class DecisaoJudicialService extends BaseService
{
    public function __construct(?DecisaoJudicialRepository $repository = null)
    {
        $this->repository = $repository ?? new DecisaoJudicialRepository();
    }

    public function salvarDecisao(array $dados): int
    {
        return $this->repository->salvarDecisao($dados);
    }

    public function obterDecisao(int $id): ?array
    {
        return $this->repository->obterDecisao($id);
    }

    public function listarDecisoes(): array
    {
        return $this->repository->listarDecisoes();
    }
}