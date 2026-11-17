<?php

declare(strict_types=1);

namespace App\Repositories\Financeiro;

use App\Models\Financeiro\FinanceiroPagtoComprovantesModel;
use App\Repositories\BaseRepository;

/**
 * Repositório dos comprovantes de pagamento (despesas e receitas).
 */
class ComprovantesRepository extends BaseRepository
{
    protected $cacheKey = 'fin_pgto_comprovantes';

    public function __construct(?FinanceiroPagtoComprovantesModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? new FinanceiroPagtoComprovantesModel();
    }

    /**
     * Lista os comprovantes de um pagamento.
     *
     * @param string $tipo 'despesa' ou 'receita'.
     */
    public function listarPorPagamento(string $tipo, int $pagamentoId): array
    {
        return $this->model
            ->where('tipo', $tipo)
            ->where('pagamento_id', $pagamentoId)
            ->orderBy('id_comprovante', 'ASC')
            ->findAll();
    }

    /**
     * Lista os comprovantes de vários pagamentos de uma vez.
     *
     * @param int[] $pagamentoIds
     */
    public function listarPorPagamentos(string $tipo, array $pagamentoIds): array
    {
        $pagamentoIds = array_values(array_unique(array_filter(array_map('intval', $pagamentoIds))));
        if (!$pagamentoIds) {
            return [];
        }

        return $this->model
            ->where('tipo', $tipo)
            ->whereIn('pagamento_id', $pagamentoIds)
            ->findAll();
    }

    /**
     * Conta os comprovantes de vários pagamentos de uma vez (evita N+1 nas listagens).
     *
     * @param int[] $pagamentoIds
     * @return array<int,int> pagamento_id => total
     */
    public function contarPorPagamentos(string $tipo, array $pagamentoIds): array
    {
        $pagamentoIds = array_values(array_unique(array_filter(array_map('intval', $pagamentoIds))));
        if (!$pagamentoIds) {
            return [];
        }

        $linhas = $this->model->builder()
            ->select('pagamento_id, COUNT(*) AS total')
            ->where('tipo', $tipo)
            ->whereIn('pagamento_id', $pagamentoIds)
            ->where('deleted_at', null)
            ->groupBy('pagamento_id')
            ->get()->getResultArray();

        $mapa = [];
        foreach ($linhas as $linha) {
            $mapa[(int) $linha['pagamento_id']] = (int) $linha['total'];
        }

        return $mapa;
    }

    /**
     * Conta os comprovantes ativos que apontam para o mesmo arquivo do Drive
     * (um arquivo de lote pode estar ligado a vários pagamentos).
     *
     * @param int|null $exceto Comprovante a desconsiderar na contagem.
     */
    public function contarPorDriveId(string $driveId, ?int $exceto = null): int
    {
        $consulta = $this->model->where('drive_id', $driveId);
        if ($exceto !== null) {
            $consulta->where('id_comprovante !=', $exceto);
        }

        return (int) $consulta->countAllResults();
    }

    /**
     * Pagamentos de um mês que ainda não têm nenhum comprovante, por conta.
     *
     * `do_extrato` conta quantos desses pagamentos estão vinculados a um movimento de extrato bancário.
     *
     * @return array<int,array{conta_id:int,conta:string,tipo:string,total:int,valor:float,do_extrato:int}>
     */
    public function pendenciasDoMes(int $ano, int $mes): array
    {
        $inicio = sprintf('%04d-%02d-01', $ano, $mes);
        $fim    = date('Y-m-t', strtotime($inicio));
        $db     = db_connect();
        $linhas = [];

        $origens = [
            'despesa' => ['fin_pgto_despesas', 'id_pgto_despesa', 'pagamento_despesa_dt'],
            'receita' => ['fin_pgto_receitas', 'id_pgto_receita', 'pagamento_receita_dt'],
        ];

        foreach ($origens as $tipo => [$tabela, $chave, $campoData]) {
            $resultado = $db->table($tabela . ' p')
                ->select(
                    'p.conta_id, c.conta, COUNT(*) AS total, SUM(p.valor) AS valor, '
                    . "SUM(CASE WHEN EXISTS (SELECT 1 FROM " . $db->prefixTable('fin_extratos_movimentos') . " m WHERE m.pagamento_tipo = " . $db->escape($tipo)
                    . " AND m.pagamento_id = p.{$chave} AND m.status IN ('conciliado', 'lancado') AND m.deleted_at IS NULL)"
                    . ' THEN 1 ELSE 0 END) AS do_extrato',
                    false
                )
                ->join('fin_contas c', 'c.id_conta = p.conta_id', 'left')
                ->where('p.deleted_at IS NULL')
                ->where("p.{$campoData} >=", $inicio)
                ->where("p.{$campoData} <=", $fim)
                ->where(
                    "NOT EXISTS (SELECT 1 FROM " . $db->prefixTable('fin_pgto_comprovantes') . " x WHERE x.tipo = " . $db->escape($tipo)
                    . " AND x.pagamento_id = p.{$chave} AND x.deleted_at IS NULL)",
                    null,
                    false
                )
                ->groupBy('p.conta_id, c.conta')
                ->get()->getResultArray();

            foreach ($resultado as $linha) {
                $linhas[] = [
                    'conta_id' => (int) $linha['conta_id'],
                    'conta'    => (string) ($linha['conta'] ?? ''),
                    'tipo'     => $tipo,
                    'total'    => (int) $linha['total'],
                    'valor'    => (float) $linha['valor'],
                    'do_extrato' => (int) $linha['do_extrato'],
                ];
            }
        }

        return $linhas;
    }
}
