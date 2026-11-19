<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Repositories\Financeiro\ContasRepository;
use Exception;
use App\Services\BaseService;

/**
 * ContasService
 *
 * @version 0.9.1
 * @since 2026-01-01
 *
 * @property ContasRepository $repository
 */
class ContasService extends BaseService
{
    public function __construct(?ContasRepository $repository = null)
    {
        $this->repository = $repository ?? new ContasRepository();
    }

    /**
     * Retorna as contas paginadas.
     *
     * @param int $perPage Número de contas por página
     *
     * @return array
     */
    public function listar(int $perPage = 25): array
    {
        return $this->repository->paginate($perPage);
    }

    /**
     * Retorna todas as contas (sem paginação).
     *
     * @return array
     */
    public function listarTodas(): array
    {
        return $this->repository->findAll();
    }

    /**
     * Retorna todas as contas ordenadas por nome (sem paginação).
     *
     * @return array
     */
    public function listarTodasOrdenadas(): array
    {
        return $this->repository->listarContasOrdenadas();
    }


    /**
     * Retorna uma conta pelo ID.
     *
     * @param int $id ID da conta
     *
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * @deprecated Use getById instead
     */
    public function buscarPorId(int $id): ?array
    {
        return $this->getById($id);
    }

    /**
     * Cria uma nova conta
     *
     * @param array $data Dados da conta
     *
     * @return int ID da conta criada
     */
    public function create(array $data): int
    {
        try {
            return parent::create($data);
        } catch (Exception $e) {
            log_message('error', '[ContasService] Erro ao criar conta: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @deprecated Use create instead
     */
    public function criar(array $data): int
    {
        return $this->create($data);
    }

    /**
     * Atualiza uma conta
     *
     * @param int $id ID da conta
     * @param array $data Dados da conta
     *
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        try {
            return parent::update($id, $data);
        } catch (Exception $e) {
            log_message('error', "[ContasService] Erro ao atualizar conta {$id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @deprecated Use update instead
     */
    public function atualizar(int $id, array $data): bool
    {
        return $this->update($id, $data);
    }

    /**
     * Exclui uma conta
     * @param int $id ID da conta
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            return parent::delete($id);
        } catch (Exception $e) {
            log_message('error', "[ContasService] Erro ao excluir conta {$id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Lista o que está ligado à conta (impede a exclusão, que apagaria
     * transferências e saldos em cascata).
     *
     * @return string[] Descrições dos vínculos, ex.: "3 transferência(s)".
     */
    public function vinculos(int $id): array
    {
        $db = db_connect();
        $checagens = [
            'pagamento(s) de despesa'   => ['fin_pgto_despesas', ['conta_id']],
            'pagamento(s) de receita'   => ['fin_pgto_receitas', ['conta_id']],
            'transferência(s)'          => ['fin_transferencias', ['id_conta_origem', 'id_conta_destino']],
            'saldo(s) mensal(is)'       => ['fin_saldo_mensal', ['conta_id']],
            'extrato(s) importado(s)'   => ['fin_extratos_importacoes', ['conta_id']],
        ];

        $vinculos = [];
        foreach ($checagens as $rotulo => [$tabela, $colunas]) {
            $builder = $db->table($tabela)->groupStart();
            foreach ($colunas as $i => $coluna) {
                $i === 0 ? $builder->where($coluna, $id) : $builder->orWhere($coluna, $id);
            }
            $total = $builder->groupEnd()->countAllResults();
            if ($total > 0) {
                $vinculos[] = $total . ' ' . $rotulo;
            }
        }

        return $vinculos;
    }

    /**
     * @deprecated Use delete instead
     */
    public function excluir(int $id): bool
    {
        return $this->delete($id);
    }
}
