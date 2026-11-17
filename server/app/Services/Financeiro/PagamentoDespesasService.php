<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use Exception;
use App\Repositories\Financeiro\ContasRepository;
use App\Repositories\Financeiro\DespesasRepository;
use App\Repositories\Financeiro\PagamentoDespesasRateioRepository;
use App\Repositories\Financeiro\PagamentoDespesasRepository;
use App\Repositories\Financeiro\ReceitasRepository;
use App\Services\BaseService;
use App\Traits\FormataValorTrait;
use App\Traits\LiberaMovimentosExtratoTrait;

/**
 * Serviço responsável pelo processamento de pagamentos de despesas e reembolsos.
 * v1.1.1
 *
 * @property PagamentoDespesasRepository $repository
 */
class PagamentoDespesasService extends BaseService
{
    use FormataValorTrait;
    use LiberaMovimentosExtratoTrait;


    /**
     * @var DespesasRepository
     */
    protected DespesasRepository $despesasRepository;

    /**
     * @var ContasRepository
     */
    protected ContasRepository $contasRepository;

    /**
     * @var ReceitasRepository
     */
    protected ReceitasRepository $receitasRepository;

    /**
     * @var PagamentoDespesasRateioRepository
     */
    protected PagamentoDespesasRateioRepository $rateioRepository;

    /**
     * @var SaldoMensalService
     */
    protected SaldoMensalService $saldoMensalService;

    /**
     * Construtor do serviço com injeção de dependência.
     *
     * @param PagamentoDespesasRepository|null $repository
     * @param DespesasRepository|null $despesasRepository
     * @param ContasRepository|null $contasRepository
     * @param ReceitasRepository|null $receitasRepository
     * @param PagamentoDespesasRateioRepository|null $rateioRepository
     * @param SaldoMensalService|null $saldoMensalService
     */
    public function __construct(
        ?PagamentoDespesasRepository $repository = null,
        ?DespesasRepository $despesasRepository = null,
        ?ContasRepository $contasRepository = null,
        ?ReceitasRepository $receitasRepository = null,
        ?PagamentoDespesasRateioRepository $rateioRepository = null,
        ?SaldoMensalService $saldoMensalService = null
    ) {
        $this->repository         = $repository ?? new PagamentoDespesasRepository();
        $this->despesasRepository = $despesasRepository ?? new DespesasRepository();
        $this->contasRepository   = $contasRepository ?? new ContasRepository();
        $this->receitasRepository = $receitasRepository ?? new ReceitasRepository();
        $this->rateioRepository   = $rateioRepository ?? new PagamentoDespesasRateioRepository();
        $this->saldoMensalService = $saldoMensalService ?? new SaldoMensalService();
    }

    /**
     * Busca pagamentos com filtros
     *
     * @param array $filters
     * @param int $perPage
     * @param int $page
     * @return array
     */
    public function listarComFiltros(array $filters, int $perPage = 25, int $page = 1): array
    {
        $result = $this->repository->findByFilters($filters, $perPage, $page);
        // Fora do cache da listagem: o total muda a cada upload ou remoção de comprovante.
        $result['data'] = (new ComprovantesService())->anexarTotais('despesa', $result['data'] ?? [], 'id_pgto_despesa');

        return $result;
    } 

    /**
     * Busca pagamentos para exportação
     *
     * @param array $filters
     * @return array
     */
    public function listarParaExportacao(array $filters): array
    {
        return $this->repository->findAllForExport($filters);
    }

    /**
     * Cria um novo registro de pagamento de despesa.
     *
     * @param array $data
     * @return int
     */
    public function create(array $data): int
    {
        if (isset($data['pagamento_despesa_dt'])) {
            $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($data['conta_id'] ?? 0), $data['pagamento_despesa_dt']);
        }

        if (isset($data['valor'])) {
            $data['valor'] = $this->formatarValorParaBanco($data['valor']);
        }

        $rateio = null;
        if (isset($data['rateio'])) {
            $rateio = $data['rateio'];
            unset($data['rateio']);
        }

        if (is_string($rateio)) {
            $rateio = json_decode($rateio, true);
        }

        $id = parent::create($data);

        if ($id && !empty($rateio) && is_array($rateio)) {
            $this->salvarRateio($id, $rateio);
        }

        return $id;
    }

    /**
     * Atualiza um registro de pagamento de despesa.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $pagto = $this->getById($id);
        if ($pagto) {
            $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($pagto['conta_id'] ?? 0), $pagto['pagamento_despesa_dt'] ?? date('Y-m-d'));

            if (isset($data['pagamento_despesa_dt']) && $data['pagamento_despesa_dt'] !== $pagto['pagamento_despesa_dt']) {
                $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($data['conta_id'] ?? $pagto['conta_id']), $data['pagamento_despesa_dt']);
            }
        }

        if (isset($data['valor'])) {
            $data['valor'] = $this->formatarValorParaBanco($data['valor']);
        }

        $rateio = null;
        if (isset($data['rateio'])) {
            $rateio = $data['rateio'];
            unset($data['rateio']);
        }

        if (is_string($rateio)) {
            $rateio = json_decode($rateio, true);
        }

        $result = parent::update($id, $data);

        if ($result && $rateio !== null && is_array($rateio)) {
            $this->rateioRepository->deletarPorPagamento($id);
            $this->salvarRateio($id, $rateio);
        }

        return $result;
    }

    /**
     * Exclui um pagamento
     */
    public function delete(int $id): bool
    {
        $pagto = $this->getById($id);
        if ($pagto) {
            $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($pagto['conta_id'] ?? 0), $pagto['pagamento_despesa_dt'] ?? date('Y-m-d'));
        }

        // O movimento do extrato que originou o pagamento volta para pendente, na mesma transação.
        $excluido = $this->excluirLiberandoExtrato('despesa', [$id], fn () => parent::delete($id));
        if ($excluido) {
            $this->removerComprovantes([$id]);
        }

        return $excluido;
    }

    /**
     * Exclui múltiplos pagamentos em lote com validação de bloqueio mensal.
     *
     * @param array $ids IDs dos pagamentos.
     * @return int Quantidade de registros excluídos.
     */
    public function deleteBatch(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $pagamentos = $this->repository->findByIds($ids);

        if (empty($pagamentos)) {
            return 0;
        }

        // Agrupar por conta e mês para evitar múltiplas chamadas ao serviço de bloqueio
        $verificacoes = [];
        foreach ($pagamentos as $pagto) {
            $contaId = (int)($pagto['conta_id'] ?? 0);
            $data = $pagto['pagamento_despesa_dt'] ?? date('Y-m-d');
            $mesAno = date('Y-m', strtotime($data));

            $verificacoes["{$contaId}_{$mesAno}"] = [
                'conta_id' => $contaId,
                'data' => $data
            ];
        }

        foreach ($verificacoes as $v) {
            $this->saldoMensalService->verificarBloqueioMensal('CONTA', $v['conta_id'], $v['data']);
        }

        $excluidos = $this->excluirLiberandoExtrato('despesa', array_map('intval', $ids), fn () => $this->repository->deleteBatch($ids));
        if ($excluidos) {
            $this->removerComprovantes($ids);
        }

        return $excluidos;
    }

    /**
     * Remove os comprovantes (Drive + registro) dos pagamentos excluídos, sem nunca bloquear o estorno.
     *
     * @param int[] $ids
     */
    private function removerComprovantes(array $ids): void
    {
        try {
            (new ComprovantesService())->removerPorPagamentos('despesa', $ids);
        } catch (Exception $e) {
            log_message('error', '[PagamentoDespesasService] Falha ao remover comprovantes: ' . $e->getMessage());
        }
    }

    /**
     * Salva o rateio de um pagamento.
     *
     * @param int $pagtoId
     * @param array $rateio
     * @return void
     */
    private function salvarRateio(int $pagtoId, array $rateio): void
    {
        $batch = [];
        foreach ($rateio as $item) {
            if (empty($item['id']) || empty($item['valor'])) {
                continue;
            }
            $batch[] = [
                'pagto_despesa_id' => $pagtoId,
                'user_id'          => $item['id'],
                'valor'            => $this->formatarValorParaBanco($item['valor']),
            ];
        }

        if (!empty($batch)) {
            // insertBatch devolve false quando o modelo recusa uma linha: não pode passar em silêncio.
            if ($this->rateioRepository->insertBatch($batch) === false) {
                throw new \RuntimeException('Não foi possível gravar o rateio do pagamento.');
            }
        }
    }

    /**
     * Lança um reembolso baseado em uma despesa.
     *
     * @param int $despesa_id ID da despesa a ser reembolsada.
     * @param int $cliente_id ID do cliente responsável pelo reembolso.
     * @return array Retorna array com 'success', 'message' e 'receita_id' (se sucesso).
     */
    public function lancarReembolso($despesa_id, $cliente_id): array
    {
        try {
            if (!is_numeric($despesa_id) || !is_numeric($cliente_id)) {
                return ['success' => false, 'message' => 'IDs devem ser numéricos'];
            }

            $despesa = $this->despesasRepository->find((int)$despesa_id);
            if (!$despesa) {
                return ['success' => false, 'message' => 'Despesa não encontrada'];
            }

            if ($this->verificarReembolsoExistente((int)$despesa_id)) {
                return ['success' => false, 'message' => 'Já existe um reembolso para esta despesa'];
            }

            $receita = [
                'cliente_id' => $cliente_id,
                'valor' => $despesa['valor'],
                'receita' => 'Reembolso de despesa: ' . ($despesa['despesa'] ?? 'Sem descrição'),
                'vencimento_dt' => date('Y-m-d'),
                'rateio' => $despesa['rateio'] ?? null,
                'categoria_id' => $despesa['categoria_id'] ?? null,
                'despesa_reembolso_id' => $despesa_id,
                'tipo' => 'reembolso',
            ];

            $receita_id = $this->receitasRepository->create($receita);

            if (!$receita_id) {
                throw new Exception('Falha ao inserir receita de reembolso');
            }

            // Atualizar despesa como reembolsada (se necessário logica adicional na despesa)
            $this->despesasRepository->update((int)$despesa_id, [
                'reembolsado' => 1,
                'receita_reembolso_id' => $receita_id,
                'data_reembolso' => date('Y-m-d'),
            ]);

            return ['success' => true, 'message' => 'Reembolso lançado com sucesso', 'receita_id' => $receita_id];
        } catch (Exception $e) {
            log_message('error', "Erro ao lançar reembolso: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno ao processar reembolso: ' . $e->getMessage()];
        }
    }

    private function verificarReembolsoExistente($despesa_id): bool
    {
        $reembolso = $this->receitasRepository->buscarReembolsoPorDespesa((int) $despesa_id);
        return !empty($reembolso);
    }

    public function cancelarReembolso($despesa_id): array
    {
        try {
            $receita = $this->receitasRepository->buscarReembolsoPorDespesa((int) $despesa_id);

            if (!$receita) {
                return ['success' => false, 'message' => 'Reembolso não encontrado'];
            }

            $this->receitasRepository->delete($receita['id_receita']); // Assuming id_receita is PK
            $this->despesasRepository->update((int)$despesa_id, ['reembolsado' => 0, 'receita_reembolso_id' => null, 'data_reembolso' => null]);

            return ['success' => true, 'message' => 'Reembolso cancelado com sucesso'];
        } catch (Exception $e) {
            log_message('error', "Erro ao cancelar reembolso: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao cancelar reembolso: ' . $e->getMessage()];
        }
    }

    public function listarReembolsosCliente($cliente_id): array
    {
        return $this->receitasRepository->buscarReembolsosDoCliente((int) $cliente_id);
    }

    /**
     * Prepara os dados para o pagamento de uma despesa.
     *
     * @param int $despesaId
     * @return array|null
     */
    public function prepararPagamento(int $despesaId): ?array
    {
        $despesa = $this->despesasRepository->find($despesaId);

        if (!$despesa) {
            return null;
        }

        // Decodifica rateio se for string
        $rateio = $despesa['rateio'] ?? null;
        if (is_string($rateio)) {
            $rateio = json_decode($rateio, true);
        }

        $rateioEmReais = [];
        if (is_array($rateio)) {
            $rateioEmReais = $this->tratarRateio($rateio, (float)$despesa['valor']);
        }

        return [
            'despesa_id' => $despesaId,
            'valor'      => $despesa['valor'],
            'rateio'     => $rateioEmReais
        ];
    }

    /**
     * Obtém as estatísticas financeiras de pagamentos de despesas do mês atual.
     * Otimizado para usar agregados SQL via repositório.
     *
     * @return array Totais, contagem e média.
     */
    public function getEstatisticasMes()
    {
        $mes = date('Y-m');
        $stats = $this->repository->getEstatisticasMes($mes);

        return [
            'total_pago_mes' => $stats['total_pago'],
            'total_pagamentos_mes' => $stats['count'],
            'media_pagamento' => $stats['media'],
        ];
    }

    /**
     * Obtém o resumo de pagamentos agrupado por conta para o mês vigente.
     * Otimizado para evitar N+1 queries via repositório.
     *
     * @return array Lista formatada com nome da conta, total e quantidade.
     */
    public function getResumoPorConta()
    {
        $primeiroDia = date('Y-m-01');
        $proximoMes = date('Y-m-d', strtotime('first day of next month'));

        $resumo = $this->repository->getResumoPorConta($primeiroDia, $proximoMes);

        foreach ($resumo as &$item) {
            $item['total_formatado'] = number_format((float) ($item['total'] ?? 0), 2, ',', '.');
        }

        return $resumo;
    }

    /**
     * @param int $despesaId
     * @return array|null
     */
    public function findByDespesaId(int $despesaId): ?array
    {
        return $this->repository->findByDespesaId($despesaId);
    }
}
