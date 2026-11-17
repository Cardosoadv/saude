<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Repositories\Financeiro\CategoriasRepository;
use Exception;
use App\Services\BaseService;

/**
 * @version 0.9.1
 * @since 2026-01-01
 *
 * Serviço para categorias financeiras.
 *
 * @property CategoriasRepository $repository
 */
class CategoriasService extends BaseService
{
    public function __construct(?CategoriasRepository $repository = null)
    {
        $this->repository = $repository ?? new CategoriasRepository();
    }

    public function listar(int $perPage = 25, string $search = ''): array
    {
        // Se houver busca, paginamos normal. Se não, idealmente mostramos arvore.
        // Mas manteremos compatibilidade.
        return $this->repository->paginate($perPage, search: $search);
    }

    public function listarArvore(?string $search = null): array
    {
        return $this->repository->findAllTree($search);
    }

    public function listarTodas(): array
    {
        return $this->repository->findAll();
    }


    public function buscarPorId(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    public function criar(array $data): int
    {
        // Sanitização de FKs nullable
        $data['categoria_pai_id'] = !empty($data['categoria_pai_id']) ? (int) $data['categoria_pai_id'] : null;
        $data['elemento_id'] = !empty($data['elemento_id']) ? (int) $data['elemento_id'] : null;

        // Regra de Negócio: Herança de Elemento
        if (!empty($data['categoria_pai_id'])) {
            $parent = $this->buscarPorId($data['categoria_pai_id']);
            if ($parent) {
                $data['elemento_id'] = $parent['elemento_id'];
            }
        }

        try {
            return $this->repository->create($data);
        } catch (Exception $e) {
            log_message('error', '[CategoriasService] Erro ao criar categoria: ' . $e->getMessage());
            throw $e;
        }
    }

    public function atualizar(int $id, array $data): bool
    {
        // Sanitização de FKs nullable
        $data['categoria_pai_id'] = !empty($data['categoria_pai_id']) ? (int) $data['categoria_pai_id'] : null;
        $data['elemento_id'] = !empty($data['elemento_id']) ? (int) $data['elemento_id'] : null;

        // Regra de Negócio: Validação de Auto-referência
        if (!empty($data['categoria_pai_id']) && (int) $data['categoria_pai_id'] === $id) {
            throw new Exception('Uma categoria não pode ser pai de si mesma.');
        }

        // Regra de Negócio: Herança de Elemento
        if (!empty($data['categoria_pai_id'])) {
            $parent = $this->buscarPorId($data['categoria_pai_id']);
            if ($parent) {
                // Verificar Ciclo (A não pode ser pai de B se A já é filho de B)
                if ($this->detectarCiclo($id, (int) $data['categoria_pai_id'])) {
                    throw new Exception('Ciclo detectado: Uma categoria não pode ser subcategoria de sua própria descendência.');
                }

                $data['elemento_id'] = $parent['elemento_id'];
            }
        }

        try {
            return $this->repository->update($id, $data);
        } catch (Exception $e) {
            log_message('error', "[CategoriasService] Erro ao atualizar categoria {$id}: " . $e->getMessage());
            throw $e;
        }
    }

    // Novo método para suporte a formulários e relatórios
    public function buscarPais(string $term = ''): array
    {
        return $this->repository->searchParents($term);
    }

    public function dadosRelatorio(?string $dataInicio = null, ?string $dataFim = null): array
    {
        return $this->repository->getRelatorioData($dataInicio, $dataFim);
    }

    public function listarElementos(): array
    {
        return $this->repository->findAllElementos();
    }

    public function excluir(int $id): bool
    {
        try {
            return $this->repository->delete($id);
        } catch (Exception $e) {
            log_message('error', "[CategoriasService] Erro ao excluir categoria {$id}: " . $e->getMessage());
            throw $e;
        }
    }

    private function detectarCiclo(int $categoriaId, int $propostoPaiId): bool
    {
        // Se o pai proposto for a própria categoria, é ciclo imediato
        if ($categoriaId === $propostoPaiId) {
            return true;
        }

        // Subir a árvore a partir do pai proposto
        // Se encontrarmos 'categoriaId' na ascendência do 'propostoPaiId', é ciclo.
        $atualId = $propostoPaiId;

        // Proteção contra loop infinito em dados já corrompidos: limite de profundidade
        $depth = 0;
        while ($atualId && $depth < 100) {
            $pai = $this->repository->findById($atualId);
            if (!$pai || empty($pai['categoria_pai_id'])) {
                break; // Chegou na raiz
            }

            $ascendenteId = (int) $pai['categoria_pai_id'];
            if ($ascendenteId === $categoriaId) {
                return true; // Encontrou a categoria alvo na linhagem ascendente
            }

            $atualId = $ascendenteId;
            $depth++;
        }

        return false;
    }
}
