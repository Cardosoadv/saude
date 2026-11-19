<?php

namespace App\Services\Financeiro;

use App\Repositories\Financeiro\DespesasRepository;
use App\Repositories\Financeiro\ReceitasRepository;
use App\Services\BaseService;

/**
 * Serviço responsável pela gestão financeira.
 *
 * Esta classe centraliza a lógica de negócios para operações financeiras,
 * incluindo movimentações de clientes, resumos, e geração de dados para gráficos
 * de receitas e despesas.
 */
class FinanceiroService extends BaseService
{
    /**
     * Repositório de receitas.
     *
     * @var ReceitasRepository
     */
    protected ReceitasRepository $receitasRepository;

    /**
     * Repositório de despesas.
     *
     * @var DespesasRepository
     */
    protected DespesasRepository $despesasRepository;

    /**
     * Construtor para inicializar os repositórios necessários via injeção de dependência.
     *
     * @param ReceitasRepository|null $receitasRepository
     * @param DespesasRepository|null $despesasRepository
     */
    public function __construct(
        ?ReceitasRepository $receitasRepository = null,
        ?DespesasRepository $despesasRepository = null
    ) {
        $this->receitasRepository = $receitasRepository ?? new ReceitasRepository();
        $this->despesasRepository = $despesasRepository ?? new DespesasRepository();
    }

    /**
     * Obtém todas as movimentações financeiras de um cliente com filtros opcionais.
     *
     * Busca as receitas associadas a um cliente, aplica filtros no banco de dados,
     * formata os valores e tipos para exibição.
     *
     * @param int $clienteId ID do cliente.
     * @param array $filtros Filtros de busca [categoria, data_inicio, data_fim, search].
     * @return array Array com as movimentações financeiras formatadas.
     */
    public function getMovimentacoesCliente($clienteId, array $filtros = []): array
    {
        // Buscar receitas do cliente com filtros aplicados no banco de dados
        $receitas = $this->receitasRepository->getReceitasPorCliente($clienteId, $filtros);

        // Adicionar tipo de movimentação e formatação
        foreach ($receitas as &$receita) {
            $receita['tipo']                = 'receita';
            $receita['tipo_class']          = 'success';
            $receita['valor_formatado']     = 'R$ ' . number_format((float)$receita['valor'], 2, ',', '.');
        }

        return $receitas;
    }

    /**
     * Calcula o resumo financeiro do cliente.
     *
     * Agrega os valores totais de receitas e calcula o saldo atual do cliente.
     * Otimizado: Realiza o cálculo no banco de dados.
     *
     * @param int $clienteId ID do cliente.
     * @return array Resumo contendo totais, saldo, formatação e metadados para exibição.
     */
    public function getResumoFinanceiro($clienteId): array
    {
        $resumo = $this->receitasRepository->getResumoPorCliente((int) $clienteId);

        $totalReceitas = $resumo['total_receitas'];
        $saldo = $totalReceitas;

        return [
            'total_receitas'            => $totalReceitas,
            'saldo'                     => $saldo,
            'total_receitas_formatado'  => 'R$ ' . number_format($totalReceitas, 2, ',', '.'),
            'saldo_formatado'           => 'R$ ' . number_format($saldo, 2, ',', '.'),
            'saldo_class'               => $saldo >= 0 ? 'success' : 'danger',
            'quantidade_receitas'       => $resumo['quantidade_receitas'],
            'total_movimentacoes'       => $resumo['quantidade_receitas'],
        ];
    }

    /**
     * Obtém dados para gráficos das receitas de um cliente.
     *
     * Agrupa as receitas por mês para facilitar a visualização em gráficos.
     * Otimizado: Realiza o agrupamento no banco de dados.
     *
     * @param int $clienteId ID do cliente.
     * @param string|null $dataInicio Data inicial.
     * @param string|null $dataFim Data final.
     * @return array Dados organizados por mês contendo o valor total de receitas.
     */
    public function getDadosGraficos($clienteId, ?string $dataInicio = null, ?string $dataFim = null): array
    {
        return $this->receitasRepository->getDadosGraficosPorCliente((int) $clienteId, $dataInicio, $dataFim);
    }

    /**
     * Obtém as receitas de um cliente.
     *
     * Recupera a lista bruta de receitas associadas a um cliente específico.
     *
     * @param int $clienteId ID do cliente.
     * @return array Lista de receitas.
     */
    public function getReceitasCliente($clienteId): array
    {
        return $this->receitasRepository->getReceitasPorCliente($clienteId);
    }

    /**
     * Obtém dados do gráfico de receitas por período.
     *
     * Filtra as receitas dentro de um intervalo de datas e as agrupa por mês.
     * Otimizado: Realiza o agrupamento diretamente no banco de dados.
     *
     * @param string $dataInicio Data de início do período (formato 'Y-m-d').
     * @param string $dataFim Data de fim do período (formato 'Y-m-d').
     * @return array Dados organizados para o gráfico [mes, receitas].
     */
    public function dadosGraficoReceitaPorPeriodo($dataInicio, $dataFim): array
    {
        return $this->receitasRepository->getReceitasPagasAgrupadasPorMes($dataInicio, $dataFim);
    }

    /**
     * Obtém dados do gráfico de despesas pagas por período.
     *
     * Filtra as despesas pagas dentro de um intervalo de datas e as agrupa por mês.
     * Otimizado: Realiza o agrupamento diretamente no banco de dados.
     *
     * @param string $dataInicio Data de início do período (formato 'Y-m-d').
     * @param string $dataFim Data de fim do período (formato 'Y-m-d').
     * @return array Dados organizados para o gráfico [mes, despesas].
     */
    public function dadosGraficoDespesasPagasPorPeriodo($dataInicio, $dataFim): array
    {
        return $this->despesasRepository->getDespesasPagasAgrupadasPorMes($dataInicio, $dataFim);
    }

    /**
     * Obtém as receitas de um contrato com filtros opcionais.
     *
     * Recupera a lista de receitas associadas a um contrato específico,
     * permitindo filtragem no banco de dados.
     *
     * @param int $contratoId ID do contrato.
     * @param array $filtros Filtros opcionais [categoria, data_inicio, data_fim, search].
     * @return array Lista de receitas.
     */
    public function getReceitasPorContrato(int $contratoId, array $filtros = []): array
    {
        return $this->receitasRepository->getReceitasPorContrato($contratoId, $filtros);
    }

    /**
     * Obtém as despesas de um contrato com filtros opcionais.
     *
     * Recupera a lista de despesas associadas a um contrato específico,
     * permitindo filtragem no banco de dados.
     *
     * @param int $contratoId ID do contrato.
     * @param array $filtros Filtros opcionais [categoria, data_inicio, data_fim, search].
     * @return array Lista de despesas.
     */
    public function getDespesasPorContrato(int $contratoId, array $filtros = []): array
    {
        return $this->despesasRepository->getDespesasPorContrato($contratoId, $filtros);
    }

    /**
     * Obtém as movimentações de um contrato com filtros opcionais.
     *
     * Recupera a lista de receitas e despesas associadas a um contrato específico,
     * aplicando filtros diretamente no banco de dados para melhor performance.
     *
     * @param int $contratoId ID do contrato.
     * @param array $filtros Filtros de busca [categoria, data_inicio, data_fim, search].
     * @return array Lista de movimentações filtradas e ordenadas.
     */
    public function getMovimentacoesContrato(int $contratoId, array $filtros = []): array
    {
        $receitas = $this->receitasRepository->getReceitasPorContrato($contratoId, $filtros);
        $despesas = $this->despesasRepository->getDespesasPorContrato($contratoId, $filtros);

        // Mapear campos para garantir consistência e compatibilidade com a view
        $receitas = array_map(function ($r) {
            $r['data']              ??= $r['vencimento_dt'];
            $r['descricao']         = $r['receita'] ?? $r['descricao'] ?? '';
            $r['tipo_movimentacao'] = 'receita';
            $r['categoria']         = $r['nome_categoria'] ?? $r['categoria'];
            return $r;
        }, $receitas);

        $despesas = array_map(function ($d) {
            $d['data']              ??= $d['vencimento_dt'];
            $d['descricao']         = $d['despesa'] ?? $d['descricao'] ?? '';
            $d['tipo_movimentacao'] = 'despesa';
            $d['categoria']         = $d['nome_categoria'] ?? $d['categoria'];
            return $d;
        }, $despesas);

        $movimentacoes = array_merge($receitas, $despesas);

        // Ordenação final em memória (necessária após o merge de duas fontes)
        // No entanto, o dataset já vem filtrado do banco, o que torna esta operação rápida.
        usort($movimentacoes, function ($a, $b) {
            return strtotime($b['data']) - strtotime($a['data']);
        });

        return $movimentacoes;
    }

    /**
     * Obtém o resumo financeiro de um contrato.
     *
     * Recupera o resumo financeiro de um contrato, incluindo o total de receitas,
     * o total de despesas, o saldo e as movimentações.
     * Otimizado: Realiza as agregações no banco de dados.
     *
     * @param int $contratoId ID do contrato.
     * @return array Resumo financeiro do contrato.
     */
    public function getResumoFinanceiroContrato(int $contratoId): array
    {
        $resumoReceitas = $this->receitasRepository->getResumoPorContrato($contratoId);
        $resumoDespesas = $this->despesasRepository->getResumoPorContrato($contratoId);

        $totalReceitas = $resumoReceitas['total_receitas'];
        $totalDespesas = $resumoDespesas['total_despesas'];
        $saldo = $totalReceitas - $totalDespesas;

        return [
            'total_receitas'            => $totalReceitas,
            'total_despesas'            => $totalDespesas,
            'saldo'                     => $saldo,
            'total_receitas_formatado'  => 'R$ ' . number_format($totalReceitas, 2, ',', '.'),
            'total_despesas_formatado'  => 'R$ ' . number_format($totalDespesas, 2, ',', '.'),
            'saldo_formatado'           => 'R$ ' . number_format($saldo, 2, ',', '.'),
            'saldo_class'               => $saldo >= 0 ? 'success' : 'danger',
            'quantidade_receitas'       => $resumoReceitas['quantidade_receitas'],
            'quantidade_despesas'       => $resumoDespesas['quantidade_despesas'],
            'total_movimentacoes'       => $resumoReceitas['quantidade_receitas'] + $resumoDespesas['quantidade_despesas'],
        ];
    }
    /**
     * Obtém dados para gráficos das receitas de um contrato.
     *
     * Agrupa as receitas por mês para facilitar a visualização em gráficos.
     * Otimizado: Realiza o agrupamento no banco de dados.
     *
     * @param int $contratoId ID do contrato.
     * @return array Dados organizados por mês contendo o valor total de receitas.
     */
    public function getDadosGraficosContrato(int $contratoId): array
    {
        return $this->receitasRepository->getDadosGraficosPorContrato($contratoId);
    }

    /**
     * Obtém o resultado financeiro (receitas - despesas) agrupado pela área/especialidade
     * do contrato vinculado às receitas/despesas pagas no período.
     *
     * Receitas/despesas sem contrato vinculado, ou vinculadas a um contrato sem área
     * definida, são agrupadas sob "Sem área definida".
     *
     * @param string $dataInicio Data de início do período (formato 'Y-m-d').
     * @param string $dataFim Data de fim do período (formato 'Y-m-d').
     * @return array Lista ordenada por resultado (desc) de [area, receitas, despesas, resultado].
     */
    public function getResultadoFinanceiroPorArea(string $dataInicio, string $dataFim): array
    {
        $receitas = $this->receitasRepository->getReceitasPagasAgrupadasPorArea($dataInicio, $dataFim);
        $despesas = $this->despesasRepository->getDespesasPagasAgrupadasPorArea($dataInicio, $dataFim);

        $porArea = [];

        foreach ($receitas as $linha) {
            $area = trim((string) ($linha['area'] ?? ''));
            $porArea[$area]['receitas'] = ($porArea[$area]['receitas'] ?? 0.0) + (float) ($linha['receitas'] ?? 0);
        }

        foreach ($despesas as $linha) {
            $area = trim((string) ($linha['area'] ?? ''));
            $porArea[$area]['despesas'] = ($porArea[$area]['despesas'] ?? 0.0) + (float) ($linha['despesas'] ?? 0);
        }

        $resultado = [];
        foreach ($porArea as $area => $valores) {
            $totalReceitas = $valores['receitas'] ?? 0.0;
            $totalDespesas = $valores['despesas'] ?? 0.0;

            $resultado[] = [
                'area'      => $area !== '' ? $area : 'Sem área definida',
                'receitas'  => $totalReceitas,
                'despesas'  => $totalDespesas,
                'resultado' => $totalReceitas - $totalDespesas,
            ];
        }

        usort($resultado, fn($a, $b) => $b['resultado'] <=> $a['resultado']);

        return $resultado;
    }
}
