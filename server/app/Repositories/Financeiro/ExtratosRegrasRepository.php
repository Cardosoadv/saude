<?php

declare(strict_types=1);

namespace App\Repositories\Financeiro;

use App\Models\Financeiro\FinanceiroExtratosRegrasModel;
use App\Repositories\BaseRepository;

/**
 * Repositório das regras de classificação de extratos.
 */
class ExtratosRegrasRepository extends BaseRepository
{
    protected $cacheKey = 'fin_extratos_regras';

    public function __construct(?FinanceiroExtratosRegrasModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? new FinanceiroExtratosRegrasModel();
    }

    public function obter(int $id): ?array
    {
        $this->model->resetQuery();

        return $this->model->find($id) ?: null;
    }

    /**
     * Regras ativas aplicáveis à conta (as da própria conta e as de todas as contas).
     *
     * @return list<array<string,mixed>>
     */
    public function listarAtivasDaConta(int $contaId): array
    {
        $this->model->resetQuery();

        return $this->model
            ->where('ativo', 1)
            ->groupStart()
                ->where('conta_id', null)
                ->orWhere('conta_id', $contaId)
            ->groupEnd()
            ->orderBy('id_regra', 'ASC')
            ->findAll();
    }

    /** @return list<array<string,mixed>> */
    public function listar(?int $contaId = null, bool $apenasAtivas = false): array
    {
        $this->model->resetQuery();

        if ($contaId !== null) {
            $this->model->groupStart()->where('conta_id', null)->orWhere('conta_id', $contaId)->groupEnd();
        }
        if ($apenasAtivas) {
            $this->model->where('ativo', 1);
        }

        return $this->model->orderBy('nome', 'ASC')->orderBy('id_regra', 'ASC')->findAll();
    }

    /** @throws \RuntimeException Dados recusados pela validação do model. */
    public function criar(array $dados): int
    {
        $this->model->resetQuery();
        $id = $this->model->insert($dados, true);
        if ($id === false) {
            throw new \InvalidArgumentException(implode(' ', $this->model->errors()) ?: 'Não foi possível gravar a regra.');
        }

        return (int) $id;
    }

    public function atualizar(int $id, array $dados): bool
    {
        $this->model->resetQuery();
        if ($this->model->update($id, $dados) === false) {
            throw new \InvalidArgumentException(implode(' ', $this->model->errors()) ?: 'Não foi possível atualizar a regra.');
        }

        return true;
    }

    public function excluir(int $id): bool
    {
        $this->model->resetQuery();

        return (bool) $this->model->delete($id);
    }
}
