<?php

declare(strict_types=1);

namespace App\Repositories\Relatorios;

use App\Libraries\Liquidacao\LiquidacaoDominio;
use App\Repositories\BaseRepository;
use App\Models\ProcessosModel;
use App\Repositories\Traits\EscopoPrivacidadeTrait;

/**
 * Repositório especializado para métricas e relatórios de Legal Ops.
 */
class LegalOpsRepository extends BaseRepository
{
    use EscopoPrivacidadeTrait;

    /**
     * @var string Chave de cache para o repositório.
     */
    protected $cacheKey = 'legal_ops_bi';

    /**
     * Construtor da classe LegalOpsRepository.
     */
    public function __construct(?ProcessosModel $model = null)
    {
        parent::__construct();
        $this->model = $model ?? model('ProcessosModel');
    }

    /**
     * Obtém a distribuição de processos por probabilidade de risco.
     *
     * @param int|null $clienteId
     * @return array
     */
    public function getDistribuicaoRisco(?int $clienteId = null): array
    {
        $suffix = 'distribuicao_risco' . ($clienteId ? "_c{$clienteId}" : '') . $this->sufixoEscopo('processo');
        return $this->remember($suffix, function() use ($clienteId) {
            $builder = $this->escopo($this->model->builder(), 'processo');
            $builder->select('risco as labels, COUNT(*) as `values`')
                    ->where('deleted_at', null)
                    ->where('risco !=', '')
                    ->groupBy('risco');

            if ($clienteId) {
                $builder->where('cliente_id', $clienteId);
            }

            return $builder->get()->getResultArray();
        });
    }

    /**
     * Obtém o valor total agrupado por risco.
     *
     * Para processos com liquidação em fase de discussão ou pagamento (apresentada, impugnada,
     * homologada ou em pagamento) vale o valor atualizado da liquidação mais recente (zero se já quitada);
     * nos demais, o valor da causa. Rascunhos e liquidações em cálculo não substituem o valor da causa.
     *
     * @param int|null $clienteId
     * @return array
     */
    public function getValoresPorRisco(?int $clienteId = null): array
    {
        $suffix = 'valores_por_risco_liq' . ($clienteId ? "_c{$clienteId}" : '') . $this->sufixoEscopo('processo');
        return $this->remember($suffix, function() use ($clienteId) {
            $liq    = $this->db->prefixTable('liquidacoes');
            $status = "'" . implode("','", LiquidacaoDominio::STATUS_COM_VALOR_DEFINIDO) . "'";
            $ultima = "(SELECT l.processo_id, CASE WHEN l.status = 'quitada' THEN 0 ELSE l.valor_total END AS valor_total FROM {$liq} l WHERE l.liquidacao_id = "
                . "(SELECT MAX(l2.liquidacao_id) FROM {$liq} l2 WHERE l2.processo_id = l.processo_id AND l2.status IN ({$status})))";

            $proc = $this->db->prefixTable('processos');

            $builder = $this->escopo($this->model->builder(), 'processo');
            $builder->select("{$proc}.risco, (SUM(COALESCE(liq.valor_total, {$proc}.valorCausa))/100) as total_causa", false)
                    ->join("{$ultima} liq", "liq.processo_id = {$proc}.id_processo", 'left', false)
                    ->where('processos.deleted_at', null)
                    ->where('processos.risco !=', '')
                    ->groupBy('processos.risco');

            if ($clienteId) {
                $builder->where('processos.cliente_id', $clienteId);
            }

            return $builder->get()->getResultArray();
        });
    }

    /**
     * Obtém estatísticas de performance (Sucesso vs Derrota).
     *
     * @param int|null $clienteId
     * @return array
     */
    public function getEstatisticasResultado(?int $clienteId = null): array
    {
        $suffix = 'estatisticas_resultado' . ($clienteId ? "_c{$clienteId}" : '') . $this->sufixoEscopo('processo');
        return $this->remember($suffix, function() use ($clienteId) {
            $builder = $this->escopo($this->model->builder(), 'processo');
            $builder->select('resultado as labels, COUNT(*) as `values`')
                    ->where('deleted_at', null)
                    ->where('resultado !=', 'Não Finalizado')
                    ->where('resultado !=', '')
                    ->groupBy('resultado');

            if ($clienteId) {
                $builder->where('cliente_id', $clienteId);
            }

            return $builder->get()->getResultArray();
        });
    }

    /**
     * Obtém o tempo médio de vida dos processos encerrados (Cycle Time).
     * Retorna a média em dias.
     *
     * @param int|null $clienteId
     * @return int
     */
    public function getTempoMedioCiclo(?int $clienteId = null): int
    {
        $suffix = 'tempo_medio_ciclo' . ($clienteId ? "_c{$clienteId}" : '') . $this->sufixoEscopo('processo');
        return (int) $this->remember($suffix, function() use ($clienteId) {
            $builder = $this->escopo($this->model->builder(), 'processo');
            $builder->select('AVG(DATEDIFF(data_encerramento, dataDistribuicao)) as media_dias')
                    ->where('deleted_at', null)
                    ->where('encerrado', 1)
                    ->where('data_encerramento !=', null)
                    ->where('dataDistribuicao !=', null);

            if ($clienteId) {
                $builder->where('cliente_id', $clienteId);
            }

            $result = $builder->get()->getRowArray();
            return (float) ($result['media_dias'] ?? 0);
        });
    }

    /**
     * Obtém o Ticket Médio de Condenação em casos de derrota.
     *
     * @param int|null $clienteId
     * @return float
     */
    public function getTicketMedioCondenacao(?int $clienteId = null): float
    {
        $suffix = 'ticket_medio_condenacao' . ($clienteId ? "_c{$clienteId}" : '') . $this->sufixoEscopo('processo');
        return (float) $this->remember($suffix, function() use ($clienteId) {
            $builder = $this->escopo($this->model->builder(), 'processo');
            $builder->select('(AVG(valorCondenacao)/100) as ticket_medio')
                    ->where('deleted_at', null)
                    ->where('resultado', 'Derrota');

            if ($clienteId) {
                $builder->where('cliente_id', $clienteId);
            }

            $result = $builder->get()->getRowArray();
            return (float) ($result['ticket_medio'] ?? 0);
        });
    }

    /**
     * Obtém a taxa de sucesso por órgão julgador (Performance).
     *
     * @param int|null $clienteId
     * @return array
     */
    public function getPerformancePorOrgao(?int $clienteId = null): array
    {
        $suffix = 'performance_por_orgao' . ($clienteId ? "_c{$clienteId}" : '') . $this->sufixoEscopo('processo');
        return $this->remember($suffix, function() use ($clienteId) {
            $builder = $this->escopo($this->model->builder(), 'processo');
            $builder->select('nomeOrgao as label')
                    ->select("SUM(CASE WHEN resultado IN ('Sucesso', 'Sucesso Parcial') THEN 1 ELSE 0 END) as sucessos")
                    ->select("SUM(CASE WHEN resultado = 'Derrota' THEN 1 ELSE 0 END) as derrotas")
                    ->where('deleted_at', null)
                    ->where('resultado !=', 'Não Finalizado')
                    ->where('resultado !=', '')
                    ->where('nomeOrgao !=', '')
                    ->groupBy('nomeOrgao')
                    ->orderBy('sucessos', 'DESC')
                    ->limit(5);

            if ($clienteId) {
                $builder->where('cliente_id', $clienteId);
            }

            return $builder->get()->getResultArray();
        });
    }

    /**
     * Obtém o ranking de riscos por órgão julgador (Top 5).
     *
     * @param int|null $clienteId
     * @return array
     */
    public function getExposicaoPorOrgao(?int $clienteId = null): array
    {
        $suffix = 'exposicao_por_orgao' . ($clienteId ? "_c{$clienteId}" : '') . $this->sufixoEscopo('processo');
        return $this->remember($suffix, function() use ($clienteId) {
            $builder = $this->escopo($this->model->builder(), 'processo');
            $builder->select('nomeOrgao as label, (SUM(valorCausa)/100) as `value`')
                    ->where('deleted_at', null)
                    ->where('nomeOrgao !=', '')
                    ->groupBy('nomeOrgao')
                    ->orderBy('value', 'DESC')
                    ->limit(5);

            if ($clienteId) {
                $builder->where('cliente_id', $clienteId);
            }

            return $builder->get()->getResultArray();
        });
    }
}
