<?php

namespace App\Repositories\Financeiro;

use App\Repositories\BaseRepository;
use App\Models\Financeiro\SaldoMensalModel;
use App\Models\Financeiro\SaldoMensalRateioModel;

/**
 * v1.1.0
 */
class SaldoMensalRepository extends BaseRepository
{
    protected SaldoMensalRateioModel $rateioModel;

    /**
     * @param SaldoMensalModel|null $model
     * @param SaldoMensalRateioModel|null $rateioModel
     */
    public function __construct(?SaldoMensalModel $model = null, ?SaldoMensalRateioModel $rateioModel = null)
    {
        parent::__construct();
        $this->model = $model ?? new SaldoMensalModel();
        $this->rateioModel = $rateioModel ?? new SaldoMensalRateioModel();
        $this->cacheKey = 'saldo_mensal';
    }

    /**
     * Verifica se o mês está fechado para uma conta específica ou globalmente.
     *
     * @param string $tipo 'CONTA' ou 'GLOBAL'
     * @param int|null $contaId ID da conta se tipo for 'CONTA'
     * @param int $ano
     * @param int $mes
     * @return bool
     */
    public function isMesFechado(string $tipo, ?int $contaId, int $ano, int $mes): bool
    {
        $cacheSuffix = "fechado_{$tipo}_" . ($contaId ?? '0') . "_{$ano}_{$mes}";

        return (bool) $this->remember($cacheSuffix, function () use ($tipo, $contaId, $ano, $mes) {
            $query = $this->model->where('tipo', $tipo)
                ->where('ano', $ano)
                ->where('mes', $mes)
                ->where('status', 'FECHADO');

            if ($tipo === 'CONTA' && $contaId) {
                $query->where('conta_id', $contaId);
            }

            return $query->first() !== null;
        });
    }

    /**
     * Busca o saldo mensal e seus rateios.
     */
    public function getSaldoMensal(string $tipo, ?int $contaId, int $ano, int $mes): ?array
    {
        $cacheSuffix = "detalhe_{$tipo}_" . ($contaId ?? '0') . "_{$ano}_{$mes}";

        return $this->remember($cacheSuffix, function () use ($tipo, $contaId, $ano, $mes) {
            $query = $this->model->where('tipo', $tipo)
                ->where('ano', $ano)
                ->where('mes', $mes);

            if ($tipo === 'CONTA' && $contaId) {
                $query->where('conta_id', $contaId);
            }

            $saldo = $query->first();

            if ($saldo) {
                $saldo['rateios'] = $this->rateioModel->where('saldo_mensal_id', $saldo['id'])->findAll();
            }

            return $saldo;
        });
    }

    /**
     * Busca o último saldo fechado anterior a uma data.
     */
    public function getUltimoSaldoFechado(?int $contaId, string $dataReferencia): ?array
    {
        $ano = (int) date('Y', strtotime($dataReferencia));
        $mes = (int) date('m', strtotime($dataReferencia));

        $cacheSuffix = "ultimo_fechado_" . ($contaId ?? '0') . "_{$ano}_{$mes}";

        return $this->remember($cacheSuffix, function () use ($contaId, $ano, $mes) {
            $query = $this->model->where('status', 'FECHADO');

            if ($contaId) {
                $query->where('conta_id', $contaId)
                    ->where('tipo', 'CONTA');
            } else {
                $query->where('tipo', 'GLOBAL');
            }

            return $query
                ->where("(ano < {$ano} OR (ano = {$ano} AND mes < {$mes}))")
                ->orderBy('ano', 'DESC')
                ->orderBy('mes', 'DESC')
                ->first();
        });
    }
    /**
     * Registra o fechamento do mês.
     */
    public function fecharMes(array $dadosSaldo, array $dadosRateio): int
    {
        $this->db->transStart();

        // Remove se já existir (re-fechamento)
        $this->reabrirMes($dadosSaldo['tipo'], $dadosSaldo['conta_id'] ?? null, $dadosSaldo['ano'], $dadosSaldo['mes']);

        $saldoId = $this->create($dadosSaldo);

        foreach ($dadosRateio as $rateio) {
            $rateio['saldo_mensal_id'] = $saldoId;
            $this->rateioModel->insert($rateio);
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return 0;
        }

        return $saldoId;
    }

    /**
     * Reabre o mês (exclui o registro de fechamento).
     */
    public function reabrirMes(string $tipo, ?int $contaId, int $ano, int $mes): bool
    {
        $query = $this->model->where('tipo', $tipo)
            ->where('ano', $ano)
            ->where('mes', $mes);

        if ($tipo === 'CONTA' && $contaId) {
            $query->where('conta_id', $contaId);
        }

        $saldo = $query->first();

        if ($saldo) {
            // AuditoriaTrait cuidará do log se usarmos o model
            $this->delete((int)$saldo['id']);
            return true;
        }

        return false;
    }

    /**
     * Lista todos os fechamentos para exibição.
     */
    public function listarFechamentos(int $limit = 20, int $offset = 0): array
    {
        return $this->remember("lista_{$limit}_{$offset}", function () use ($limit, $offset) {
            return $this->model->select('fin_saldo_mensal.*, fin_contas.conta as conta_nome, users.username as fechado_por_nome')
                ->join('fin_contas', 'fin_contas.id_conta = fin_saldo_mensal.conta_id', 'left')
                ->join('users', 'users.id = fin_saldo_mensal.fechado_por', 'left')
                ->orderBy('ano', 'DESC')
                ->orderBy('mes', 'DESC')
                ->findAll($limit, $offset);
        });
    }
    /**
     * Busca o último fechamento realizado (global ou por conta).
     */
    public function getUltimoFechamento(?string $tipo = null, ?int $contaId = null): ?array
    {
        $query = $this->model->where('status', 'FECHADO');
        if ($tipo) {
            $query->where('tipo', $tipo);
        }
        if ($contaId) {
            $query->where('conta_id', $contaId);
        }
        return $query->orderBy('ano', 'DESC')
            ->orderBy('mes', 'DESC')
            ->first();
    }
}
