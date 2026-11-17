<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Repositories\Financeiro\PagamentoReceitasRateioRepository;
use App\Repositories\Financeiro\PagamentoReceitasRepository;
use App\Repositories\Financeiro\ReceitasRepository;
use App\Services\BaseService;
use App\Traits\FormataValorTrait;
use App\Traits\LiberaMovimentosExtratoTrait;
use Exception;

/**
 * Serviço responsável pela lógica de negócio dos pagamentos de receitas.
 * Gerencia a persistência, filtragem e estatísticas de recebimentos.
 * v1.1.1
 *
 * @property PagamentoReceitasRepository $repository
 */
class PagamentoReceitasService extends BaseService
{
    use FormataValorTrait;
    use LiberaMovimentosExtratoTrait;


    /**
     * @var ReceitasRepository
     */
    protected ReceitasRepository $receitasRepository;

    /**
     * @var PagamentoReceitasRateioRepository
     */
    protected PagamentoReceitasRateioRepository $rateioRepository;

    /**
     * @var SaldoMensalService
     */
    protected SaldoMensalService $saldoMensalService;

    /**
     * Inicializa os repositórios necessários via injeção de dependência.
     *
     * @param PagamentoReceitasRepository|null $repository
     * @param ReceitasRepository|null $receitasRepository
     * @param PagamentoReceitasRateioRepository|null $rateioRepository
     * @param SaldoMensalService|null $saldoMensalService
     */
    public function __construct(
        ?PagamentoReceitasRepository $repository = null,
        ?ReceitasRepository $receitasRepository = null,
        ?PagamentoReceitasRateioRepository $rateioRepository = null,
        ?SaldoMensalService $saldoMensalService = null
    ) {
        $this->repository           = $repository ?? new PagamentoReceitasRepository();
        $this->receitasRepository   = $receitasRepository ?? new ReceitasRepository();
        $this->rateioRepository     = $rateioRepository ?? new PagamentoReceitasRateioRepository();
        $this->saldoMensalService   = $saldoMensalService ?? new SaldoMensalService();
    }

    /**
     * Lista os pagamentos de receitas aplicando os filtros fornecidos.
     *
     * @param array $filtros Array associativo com critérios de busca.
     * @return array
     */
    public function listarComFiltros(array $filtros = [])
    {
        return $this->repository->listarComFiltros($filtros, 0, 0);
    }

    /**
     * Recupera todos os pagamentos vinculados a um cliente específico.
     *
     * @param int $clienteId ID do cliente.
     * @return array
     */
    public function getPagamentosPorCliente(int $clienteId)
    {
        return $this->repository->listarComFiltros(['cliente_id' => $clienteId]);
    }

    /**
     * Lista os pagamentos com paginação e filtros.
     *
     * @param array $filtros Filtros de busca.
     * @param int $perPage Quantidade de registros por página.
     * @param int $page Página atual.
     * @return array
     */
    public function listarPaginado(array $filtros = [], int $perPage = 25, int $page = 1): array
    {
        $result = $this->repository->listarPaginado($filtros, $perPage, $page);
        // Fora do cache da listagem: o total muda a cada upload ou remoção de comprovante.
        $result['data'] = (new ComprovantesService())->anexarTotais('receita', $result['data'] ?? [], 'id_pgto_receita');

        return $result;
    }


    /**
     * Cria um novo registro de pagamento de receita.
     * Realiza o tratamento de valores e codificação de rateio antes de persistir.
     *
     * @param array $data Dados do novo pagamento.
     * @return int ID do registro criado.
     * @throws Exception Caso ocorra erro na transação.
     */
    public function create(array $data): int
    {
        if (isset($data['pagamento_receita_dt'])) {
            $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($data['conta_id'] ?? 0), $data['pagamento_receita_dt']);
        }

        if (isset($data['valor'])) {
            $data['valor'] = $this->formatarValorParaBanco($data['valor']);
        }

        $rateio = null;
        if (isset($data['rateio'])) {
            $rateio = $data['rateio'];
            unset($data['rateio']);
        }

        $id = parent::create($data);

        if ($id && !empty($rateio) && is_array($rateio)) {
            $this->salvarRateio($id, $rateio);
        }

        return $id;
    }

    /**
     * Atualiza os dados de um pagamento de receita existente.
     *
     * @param int $id ID do pagamento.
     * @param array $data Novos dados.
     * @return bool True em caso de sucesso.
     * @throws Exception Caso ocorra erro na transação.
     */
    public function update(int $id, array $data): bool
    {
        $pagto = $this->getById($id);
        if ($pagto) {
            $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($pagto['conta_id'] ?? 0), $pagto['pagamento_receita_dt'] ?? date('Y-m-d'));

            if (isset($data['pagamento_receita_dt']) && $data['pagamento_receita_dt'] !== $pagto['pagamento_receita_dt']) {
                $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($data['conta_id'] ?? $pagto['conta_id']), $data['pagamento_receita_dt']);
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

        $result = parent::update($id, $data);

        if ($result && $rateio !== null && is_array($rateio)) {
            $this->rateioRepository->deletarPorPagamento($id);
            $this->salvarRateio($id, $rateio);
        }

        return $result;
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
                'pagto_receita_id' => $pagtoId,
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
     * Exclui um registro de pagamento de receita.
     *
     * @param int $id ID do pagamento.
     * @return bool True em caso de sucesso.
     */
    public function delete(int $id): bool
    {
        $pagto = $this->getById($id);
        if ($pagto) {
            $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($pagto['conta_id'] ?? 0), $pagto['pagamento_receita_dt'] ?? date('Y-m-d'));
        }

        // O movimento do extrato que originou o recebimento volta para pendente, na mesma transação.
        $excluido = $this->excluirLiberandoExtrato('receita', [$id], fn () => parent::delete($id));
        if ($excluido) {
            try {
                (new ComprovantesService())->removerPorPagamentos('receita', [$id]);
            } catch (Exception $e) {
                // Nunca bloqueia o estorno por causa do Drive.
                log_message('error', '[PagamentoReceitasService] Falha ao remover comprovantes: ' . $e->getMessage());
            }
        }

        return $excluido;
    }

    /**
     * Busca um pagamento pelo seu ID e decodifica o campo de rateio se necessário.
     *
     * @param int $id ID do pagamento.
     * @return array|null Dados do pagamento ou null se não encontrado.
     */
    public function getById(int $id): ?array
    {
        $pagamento = parent::getById($id);
        if ($pagamento && !empty($pagamento['rateio']) && is_string($pagamento['rateio'])) {
            $pagamento['rateio'] = json_decode($pagamento['rateio'], true);
        }
        return $pagamento;
    }

    /**
     * @deprecated Use getById() instead.
     */
    public function find(int $id): ?array
    {
        return $this->getById($id);
    }

    /**
     * Obtém as estatísticas financeiras de recebimentos do mês atual.
     *
     * @return array Totais, contagem e média.
     */
    public function getEstatisticas(): array
    {
        $mes = date('Y-m');
        return $this->repository->getEstatisticasMes($mes);
    }

    /**
     * Obtém o resumo de recebimentos agrupado por conta para o mês vigente.
     * Formata os valores monetários para exibição.
     *
     * @return array Lista formatada com nome da conta, total e quantidade.
     */
    public function getResumoPorConta(): array
    {
        $primeiroDia = date('Y-m-01');
        $ultimoDia = date('Y-m-t');

        $resumo = $this->repository->getResumoPorConta($primeiroDia, $ultimoDia);

        foreach ($resumo as &$item) {
            $item['total_formatado'] = number_format((float) ($item['total'] ?? 0), 2, ',', '.');
        }

        return $resumo;
    }

    /**
     * Recupera os últimos recebimentos realizados.
     *
     * @return array Lista dos 5 recebimentos mais recentes.
     */
    public function getUltimosRecebimentos(): array
    {
        return $this->repository->getUltimosRecebimentos(5);
    }

    /**
     * Retorna todos os dados de pagamentos formatados para exportação.
     *
     * @return array
     */
    public function listarParaExportacao(): array
    {
        return $this->repository->findAllForExport();
    }

    /**
     * Busca múltiplos pagamentos por IDs.
     *
     * @param array $ids
     * @return array
     */
    public function listarPorIds(array $ids): array
    {
        return $this->repository->findByIds($ids);
    }

    /**
     * Prepara os dados para o recebimento de uma receita.
     *
     * @param int $receitaId
     * @return array|null
     */
    public function prepararPagamento(int $receitaId): ?array
    {
        $receita = $this->receitasRepository->find($receitaId);

        if (!$receita) {
            return null;
        }

        // Decodifica rateio se for string
        $rateio = $receita['rateio'] ?? null;
        if (is_string($rateio)) {
            $rateio = json_decode($rateio, true);
        }

        $rateioEmReais = [];
        if (is_array($rateio)) {
            $rateioEmReais = $this->tratarRateio($rateio, (float)$receita['valor']);
        }

        return [
            'rateio'     => $rateioEmReais,
            'receita_id' => $receitaId,
            'valor'      => $receita['valor']
        ];
    }

    /**
     * Busca um recebimento pelo ID da receita.
     *
     * @param int $receitaId ID da receita.
     * @return array|null Dados do recebimento ou null se não encontrado.
     */
    public function findByReceitaId(int $receitaId): ?array
    {
        return $this->repository->findByReceitaId($receitaId);
    }
}
