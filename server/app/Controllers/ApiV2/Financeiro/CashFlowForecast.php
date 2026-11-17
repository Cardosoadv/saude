<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2\Financeiro;

use App\Controllers\BaseController;
use App\Services\Financeiro\CashFlowForecastService;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;

/**
 * Controller API para a Previsão de Fluxo de Caixa e Detecção de Anomalias.
 */
class CashFlowForecast extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;

    protected CashFlowForecastService $service;

    protected string $permissaoNecessaria = 'financeiro';

    public function __construct(?CashFlowForecastService $service = null)
    {
        $this->service = $service ?? new CashFlowForecastService();
    }

    /**
     * Retorna a previsão de saldo (histórico + projeção) e as anomalias detectadas
     * para o dashboard de Previsão de Fluxo de Caixa.
     *
     * GET /api/v2/financeiro/previsao-fluxo-caixa/dashboard
     */
    public function dashboard()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->failForbidden('Sem permissão para acessar a previsão de fluxo de caixa.');
        }

        try {
            $contaId = $this->request->getGet('conta_id') ? (int) $this->request->getGet('conta_id') : null;
            $mesesProjecao = $this->request->getGet('meses_projecao') ? (int) $this->request->getGet('meses_projecao') : 6;
            $mesesHistorico = $this->request->getGet('meses_historico') ? (int) $this->request->getGet('meses_historico') : 12;
            $mesesAnalise = $this->request->getGet('meses_analise') ? (int) $this->request->getGet('meses_analise') : 6;

            $data = $this->service->getDashboardData($contaId, $mesesProjecao, $mesesHistorico, $mesesAnalise);

            return $this->respond($data);
        } catch (\Exception $e) {
            log_message('error', '[Api\Financeiro\CashFlowForecast::dashboard] ' . $e->getMessage());
            return $this->failServerError('Ocorreu um erro ao processar a previsão de fluxo de caixa.');
        }
    }

    /**
     * Retorna apenas a lista de anomalias detectadas nos lançamentos financeiros.
     *
     * GET /api/v2/financeiro/previsao-fluxo-caixa/anomalias
     */
    public function anomalias()
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->failForbidden('Sem permissão para acessar as anomalias financeiras.');
        }

        try {
            $mesesAnalise = $this->request->getGet('meses_analise') ? (int) $this->request->getGet('meses_analise') : 6;

            return $this->respond($this->service->getAnomalias($mesesAnalise));
        } catch (\Exception $e) {
            log_message('error', '[Api\Financeiro\CashFlowForecast::anomalias] ' . $e->getMessage());
            return $this->failServerError('Ocorreu um erro ao detectar anomalias financeiras.');
        }
    }
}
