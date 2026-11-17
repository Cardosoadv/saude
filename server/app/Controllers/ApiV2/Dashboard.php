<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\Financeiro\DespesasService;
use App\Services\Financeiro\FinanceiroService;
use App\Services\ClienteService;
use App\Services\Financeiro\ReceitasService;
use App\Services\TarefasService;
use CodeIgniter\API\ResponseTrait;

/**
 * Controller API para fornecer dados ao Dashboard.
 */
class Dashboard extends BaseController
{
    use ResponseTrait;

    private DespesasService $despesasService;
    private ReceitasService $receitasService;
    private FinanceiroService $financeiroService;
    private TarefasService $tarefasService;
    private ClienteService $clienteService;

    public function __construct(
        ?DespesasService $despesasService = null,
        ?ReceitasService $receitasService = null,
        ?FinanceiroService $financeiroService = null,
        ?TarefasService $tarefasService = null,
        ?ClienteService $clienteService = null
    ) {
        $this->despesasService   = $despesasService ?? new DespesasService();
        $this->receitasService   = $receitasService ?? new ReceitasService();
        $this->financeiroService = $financeiroService ?? new FinanceiroService();
        $this->tarefasService    = $tarefasService ?? new TarefasService();
        $this->clienteService    = $clienteService ?? new ClienteService();
    }

    /**
     * Retorna todos os dados condensados para o Dashboard inicial.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function index()
    {
        try {
            $userId = user_id();

            // Tarefas pendentes do usuário
            $tarefas = $this->tarefasService->buscarTarefasNaoConcluidasPorUsuario($userId);

            // Versão do dashboard para invalidação de cache
            $dbVersion = cache()->get('dashboard_version') ?? 1;

            // Com a privacidade granular ativa, as contagens de clientes dependem do
            // usuário: o cache passa a ser por usuário para não servir números de outro.
            $sufixoEscopo = (new \App\Services\AcessoEntidadeService())->ignoraEscopo(null, 'cliente') ? '' : "_u{$userId}";

            // Estatísticas globais
            $cacheKey = "dashboard_stats_global_v{$dbVersion}{$sufixoEscopo}";
            $stats = cache()->get($cacheKey);
            if (!$stats) {
                $resumoClientes = $this->clienteService->obterEstatisticas();
                $resumoDespesas = $this->despesasService->getResumoNaoPagasMesCurso();

                $stats = [
                    'qteClientes'           => (int) ($resumoClientes['total'] ?? 0),
                    'qteClientesAtivos'     => (int) ($resumoClientes['ativos'] ?? 0),
                    'qteDespesasVencendo'   => $resumoDespesas['total'],
                    'valorDespesasVencendo' => $resumoDespesas['soma'],
                    'valorReceitaMes'       => (float) $this->receitasService->getTotalReceitasPagasPorPeriodo(date('Y-m-01'), date('Y-m-t')),
                    'valorDespesasMes'      => (float) $this->despesasService->somarDespesasPagasPorPeriodo(date('Y-m-01'), date('Y-m-t')),
                ];
                cache()->save($cacheKey, $stats, 3600);
            }

            // Dados dos gráficos (janeiro a dezembro do ano atual)
            $inicioAno = date('Y-01-01');
            $fimAno    = date('Y-12-31');

            $cacheKeyCharts = "dashboard_charts_global_v{$dbVersion}{$sufixoEscopo}";
            $graficos = cache()->get($cacheKeyCharts);

            if (!$graficos) {
                $graficos = [
                    'receitas'        => $this->financeiroService->dadosGraficoReceitaPorPeriodo($inicioAno, $fimAno),
                    'despesas'        => $this->financeiroService->dadosGraficoDespesasPagasPorPeriodo($inicioAno, $fimAno),
                    'resultadoPorArea' => $this->financeiroService->getResultadoFinanceiroPorArea($inicioAno, $fimAno),
                ];
                cache()->save($cacheKeyCharts, $graficos, 3600);
            }
        } catch (\Exception $e) {
            log_message('error', '[Api\Dashboard::index] ' . $e->getMessage());
            return $this->failServerError('Ocorreu um erro ao processar os dados do dashboard.');
        }

        return $this->respond([
            'status' => 'success',
            'data'   => [
                'metrics' => [
                    'clientesAtivos'      => (int) ($stats['qteClientesAtivos'] ?? 0),
                    'clientesTotal'       => (int) ($stats['qteClientes'] ?? 0),
                    'despesasMes'         => $stats['valorDespesasMes'] ?? 0.0,
                    'receitasMes'         => $stats['valorReceitaMes'] ?? 0.0,
                    'qteDespesasVencendo' => (int) ($stats['qteDespesasVencendo'] ?? 0),
                ],
                'charts' => $graficos,
                'tasks'  => $this->formatarTarefas($tarefas),
            ]
        ]);
    }

    /**
     * Formata a lista de tarefas para o frontend.
     */
    private function formatarTarefas(array $tarefas): array
    {
        return array_map(function ($tarefa) {
            $prazoTimestamp = strtotime($tarefa['prazo'] ?? '');
            $hojeTimestamp = strtotime('today');
            $diff = ($prazoTimestamp - $hojeTimestamp) / 86400;

            $statusPrazo = 'normal';
            if ($diff < 0) {
                $statusPrazo = 'atrasada';
            } elseif ($diff <= 3) {
                $statusPrazo = 'urgente';
            }

            return [
                'id_tarefa'       => $tarefa['id_tarefa'],
                'tarefa'          => $tarefa['tarefa'],
                'prazo'           => $tarefa['prazo'] ?? null,
                'cliente_id'      => $tarefa['cliente_id'] ?? null,
                'vinculo_tipo'    => $tarefa['vinculo_tipo'] ?? null,
                'vinculo_info'    => $tarefa['vinculo_info'] ?? null,
                'statusPrazo'     => $statusPrazo,
                'concluida'       => $tarefa['status'] == 4,
            ];
        }, $tarefas);
    }
}
