<?php

declare(strict_types=1);

namespace App\Services\Relatorios;

use App\Repositories\Relatorios\LegalOpsRepository;
use App\Services\BaseService;

/**
 * Serviço responsável pela lógica de negócios do Legal Ops BI.
 */
class LegalOpsService extends BaseService
{

    /**
     * Probabilidades médias para cálculo de provisão baseadas nos intervalos fornecidos.
     */
    private const PESOS_RISCO = [
        'Remoto'   => 0.15, // Intervalo 0-33%
        'Possível' => 0.50, // Intervalo 33-66%
        'Provável' => 1.00, // Intervalo 66-100% - Normalmente 100% para provável
    ];

    public function __construct(?LegalOpsRepository $repository = null)
    {
        $this->repository = $repository ?? new LegalOpsRepository();
    }

    /**
     * Consolida todos os dados para o dashboard de Legal Ops.
     * 
     * @param int|null $clienteId
     * @return array
     */
    public function getDashboardData(?int $clienteId = null): array
    {
        $dbVersion = cache()->get('dashboard_version') ?? 1;
        $cacheKey = "legal_ops_dashboard_data_v{$dbVersion}" . ($clienteId ? "_c{$clienteId}" : "");
        $cachedData = cache()->get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        $resumoValores = $this->repository->getValoresPorRisco($clienteId);
        $provisaoTotal = $this->calcularProvisaoTotal($resumoValores);
        $exposureTotal = $this->calcularExposicaoTotal($resumoValores);

        $data = [
            'kpis' => [
                'provisao_total' => $provisaoTotal,
                'exposicao_total' => $exposureTotal,
                'tempo_medio'    => $this->repository->getTempoMedioCiclo($clienteId),
                'ticket_medio'   => $this->repository->getTicketMedioCondenacao($clienteId),
            ],
            'charts' => [
                'risco_distribuicao' => $this->repository->getDistribuicaoRisco($clienteId),
                'performance_resultado' => $this->repository->getEstatisticasResultado($clienteId),
                'exposicao_orgao' => $this->repository->getExposicaoPorOrgao($clienteId),
                'performance_orgao' => $this->repository->getPerformancePorOrgao($clienteId),
                'valores_risco' => $resumoValores
            ],
            'timestamp' => date('c')
        ];

        // Cache por 1 hora
        cache()->save($cacheKey, $data, 3600);

        return $data;
    }

    /**
     * Calcula o valor total de provisão financeira baseada nos pesos de risco.
     * 
     * @param array $resumoValores
     * @return float
     */
    private function calcularProvisaoTotal(array $resumoValores): float
    {
        $total = 0.0;
        foreach ($resumoValores as $item) {
            $risco = $item['risco'];
            $valor = (float) $item['total_causa'];
            
            $peso = self::PESOS_RISCO[$risco] ?? 0.0;
            $total += ($valor * $peso);
        }
        return $total;
    }

    /**
     * Calcula a exposição total (soma simples de todos os valores de causa em aberto).
     * 
     * @param array $resumoValores
     * @return float
     */
    private function calcularExposicaoTotal(array $resumoValores): float
    {
        return array_sum(array_column($resumoValores, 'total_causa'));
    }

    /**
     * Retorna os dados formatados para exportação.
     * 
     * @param int|null $clienteId
     * @return array
     */
    public function getExportData(?int $clienteId = null): array
    {
        // Por enquanto retorna o mesmo que o dashboard, mas poderia ser mais detalhado (processo a processo)
        return $this->getDashboardData($clienteId);
    }
}
