<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BaseRepository;
use Exception;

class BaseService
{
    protected BaseRepository $repository;

    public function listAll(): array
    {
        return $this->repository->findAll();
    }

    public function getById(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    public function create(array $data): int
    {
        // Simple transaction wrapper for consistency
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $id = $this->repository->create($data);
            $db->transComplete();
            return $id;
        } catch (Exception $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function update(int $id, array $data): bool
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $result = $this->repository->update($id, $data);
            $db->transComplete();
            return $result;
        } catch (Exception $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }

    public function paginate(int $perPage = 20, string $group = 'default', int $page = 1): array
    {
        return $this->repository->paginate($perPage, $group, $page);
    }

    public function getPager(): ?object
    {
        return $this->repository->getPager();
    }

    /**
     * Retorna os erros de validação do repositório/modelo.
     */
    public function getErrors(): array
    {
        return $this->repository->getErrors();
    }

    /**
     * Retorna a instância do repositório.
     * @deprecated Use os métodos do serviço sempre que possível para manter o desacoplamento.
     */
    public function getRepository(): BaseRepository
    {
        return $this->repository;
    }

    /**
     * Proxy para o Query Builder do repositório.
     */
    public function where($field, $value = null)
    {
        return $this->repository->where($field, $value);
    }
}
