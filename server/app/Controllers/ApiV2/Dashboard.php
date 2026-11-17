<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\Financeiro\DespesasService;
use App\Services\Financeiro\FinanceiroService;
use App\Services\ClienteService;
use App\Services\Financeiro\ReceitasService;
use App\Services\ProcessosService;
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
    private ProcessosService $processoService;
    private TarefasService $tarefasService;
    private ClienteService $clienteService;

    public function __construct(
        ?DespesasService $despesasService = null,
        ?ReceitasService $receitasService = null,
        ?FinanceiroService $financeiroService = null,
        ?ProcessosService $processoService = null,
        ?TarefasService $tarefasService = null,
        ?ClienteService $clienteService = null
    ) {
        $this->despesasService   = $despesasService ?? new DespesasService();
        $this->receitasService   = $receitasService ?? new ReceitasService();
        $this->financeiroService = $financeiroService ?? new FinanceiroService();
        $this->processoService   = $processoService ?? new ProcessosService();
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
            $isClientOnly = !$this->verificaPermissao('module.processos') && $this->verificaPermissao('exclusive.cliente');
            $clienteId = null;

        if ($isClientOnly) {
            $clienteId = $this->clienteService->getClienteIdByUserId((int) $userId);
        }

        // Tarefas Pendentes
        $tarefas = $this->tarefasService->buscarTarefasNaoConcluidasPorUsuario($userId);

        // Versão do dashboard para invalidação de cache
        $dbVersion = cache()->get('dashboard_version') ?? 1;

        // Com a privacidade granular ativa, as contagens de processos dependem do
        // usuário: o cache passa a ser por usuário para não servir números de outro.
        $sufixoEscopo = (new \App\Services\AcessoEntidadeService())->ignoraEscopo(null, 'processo') ? '' : "_u{$userId}";

        // Estatísticas Globais
        if ($isClientOnly && $clienteId) {
            $cacheKey = "dashboard_stats_cliente_{$clienteId}_v{$dbVersion}";
            $stats = cache()->get($cacheKey);
            if ($stats === null) {
                $resumoProcessos = $this->processoService->getResumoGeral($clienteId);

                $stats = [
                    'qteProcessos'           => $resumoProcessos['ativos'],
                    'qteProcessosEncerrados' => $resumoProcessos['encerrados'],
                    'qteDespesasVencendo'    => 0,
                    'valorDespesasVencendo'  => 0.0,
                    'valorReceitaMes'        => 0.0,
                    'valorDespesasMes'       => 0.0,
                ];
                cache()->save($cacheKey, $stats, 3600);
            }
        } else {
            $cacheKey = "dashboard_stats_global_v{$dbVersion}{$sufixoEscopo}";
            $stats = cache()->get($cacheKey);
            if (!$stats) {
                $resumoProcessos = $this->processoService->getResumoGeral();
                $resumoDespesas  = $this->despesasService->getResumoNaoPagasMesCurso();

                $stats = [
                    'qteProcessos'           => $resumoProcessos['ativos'],
                    'qteProcessosEncerrados' => $resumoProcessos['encerrados'],
                    'qteDespesasVencendo'    => $resumoDespesas['total'],
                    'valorDespesasVencendo'  => $resumoDespesas['soma'],
                    'valorReceitaMes'        => (float) $this->receitasService->getTotalReceitasPagasPorPeriodo(date('Y-m-01'), date('Y-m-t')),
                    'valorDespesasMes'        => (float) $this->despesasService->somarDespesasPagasPorPeriodo(date('Y-m-01'), date('Y-m-t')),
                ];
                cache()->save($cacheKey, $stats, 3600);
            }
        }

        // Dados dos Gráficos (Janeiro a Dezembro do ano atual)
            $inicioAno = date('Y-01-01');
            $fimAno    = date('Y-12-31');

            $cacheKeyCharts = ($isClientOnly && $clienteId)
                ? "dashboard_charts_cliente_{$clienteId}_v{$dbVersion}"
                : "dashboard_charts_global_v{$dbVersion}{$sufixoEscopo}";

            $graficos = cache()->get($cacheKeyCharts);

            if (!$graficos) {
                if ($isClientOnly && $clienteId) {
                    // Para clientes, filtramos apenas os dados deles
                    $graficos = [
                        'receitas'             => $this->financeiroService->getDadosGraficos($clienteId, $inicioAno, $fimAno),
                        'despesas'             => [], // Clientes geralmente não veem gráficos de despesas globais
                        'processosCadastrados' => $this->processoService->getDadosGraficoProcessosPorPeriodo('created_at', $inicioAno, $fimAno, $clienteId),
                        'processosEncerrados'  => $this->processoService->getDadosGraficoProcessosPorPeriodo('data_encerramento', $inicioAno, $fimAno, $clienteId),
                        'resultadoPorRamo'     => [], // Clientes não veem o resultado financeiro global por ramo
                    ];
                } else {
                    $graficos = [
                        'receitas'             => $this->financeiroService->dadosGraficoReceitaPorPeriodo($inicioAno, $fimAno),
                        'despesas'             => $this->financeiroService->dadosGraficoDespesasPagasPorPeriodo($inicioAno, $fimAno),
                        'processosCadastrados' => $this->processoService->getDadosGraficoProcessosPorPeriodo('created_at', $inicioAno, $fimAno),
                        'processosEncerrados'  => $this->processoService->getDadosGraficoProcessosPorPeriodo('data_encerramento', $inicioAno, $fimAno),
                        'resultadoPorRamo'     => $this->financeiroService->getResultadoFinanceiroPorRamo($inicioAno, $fimAno),
                    ];
                }
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
                    'processosAtivos'      => (int) ($stats['qteProcessos'] ?? 0),
                    'processosEncerrados'  => (int) ($stats['qteProcessosEncerrados'] ?? 0),
                    'despesasMes'          => $stats['valorDespesasMes'] ?? 0.0,
                    'receitasMes'          => $stats['valorReceitaMes'] ?? 0.0,
                    'qteDespesasVencendo'  => (int) ($stats['qteDespesasVencendo'] ?? 0),
                ],
                'charts' => $graficos,
                'tasks'  => $this->formatarTarefas($tarefas),
            ]
        ]);
    }

    /**
     * Verifica permissão via trait ou manual.
     */
    private function verificaPermissao(string $permissao): bool
    {
        return auth()->user()->can($permissao);
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
                'processo_numero' => $tarefa['processo_numero'] ?? null,
                'processo_id'     => $tarefa['processo_id'] ?? null,
                'cliente_id'      => $tarefa['cliente_id'] ?? null,
                'caso_id'         => $tarefa['caso_id'] ?? null,
                'vinculo_tipo'    => $tarefa['vinculo_tipo'] ?? null,
                'vinculo_info'    => $tarefa['vinculo_info'] ?? null,
                'statusPrazo'     => $statusPrazo,
                'concluida'       => $tarefa['status'] == 4,
            ];
        }, $tarefas);
    }
}
