<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Repositories\Financeiro\DespesasRepository;
use App\Services\Financeiro\PagamentoDespesasService;
use App\Traits\FormataValorTrait;
use App\Traits\ValidaCategoriasRateioTrait;
use App\Services\BaseService;
use Exception;

/**
 * @property DespesasRepository $repository
 */
class DespesasService extends BaseService
{
    use FormataValorTrait;
    use ValidaCategoriasRateioTrait;


    /**
     * @var PagamentoDespesasService
     */
    protected PagamentoDespesasService $pagamentoDespesasService;

    /**
     * @var SaldoMensalService
     */
    protected SaldoMensalService $saldoMensalService;

    /**
     * @var \App\Repositories\ContratosRepository
     */
    protected \App\Repositories\ContratosRepository $contratosRepository;

    /**
     * Construtor do DespesasService com injeção de dependência.
     *
     * @param DespesasRepository|null $repository
     * @param SaldoMensalService|null $saldoMensalService
     * @param PagamentoDespesasService|null $pagamentoDespesasService
     * @param \App\Repositories\ContratosRepository|null $contratosRepository
     */
    public function __construct(
        ?DespesasRepository $repository = null,
        ?SaldoMensalService $saldoMensalService = null,
        ?PagamentoDespesasService $pagamentoDespesasService = null,
        ?\App\Repositories\ContratosRepository $contratosRepository = null
    ) {
        $this->repository               = $repository ?? new DespesasRepository();
        $this->saldoMensalService       = $saldoMensalService ?? new SaldoMensalService();
        $this->pagamentoDespesasService = $pagamentoDespesasService ?? new PagamentoDespesasService();
        $this->contratosRepository      = $contratosRepository ?? new \App\Repositories\ContratosRepository();
    }


    /**
     * Lista as despesas não pagas no mês atual.
     *
     * Calcula o intervalo de datas do mês atual e utiliza o repositório para buscar
     * as despesas que ainda não foram quitadas dentro desse período.
     *
     * @param array $filtros Filtros opcionais para refinar a consulta (ex: categoria, fornecedor).
     * @param int $limit O número máximo de registros a serem retornados. 0 para sem limite.
     * @param int $offset O número de registros a pular para paginação.
     * @return array Uma lista de despesas não pagas encontradas no mês atual.
     */
    public function listarDespesasNaoPagasMesCurso(array $filtros = [], int $limit = 0, int $offset = 0): array
    {
        $hoje = date('Y-m-d');
        $inicioMes = date('Y-m-01', strtotime($hoje));
        $fimMes = date('Y-m-t', strtotime($hoje));
        $filtros['data_inicio'] = $inicioMes;
        $filtros['data_fim'] = $fimMes;

        return $this->repository->listarDespesasNaoPagas($filtros, $limit, $offset);
    }

    public function somarDespesasNaoPagasMesCurso(array $filtros = []): float
    {
        $hoje = date('Y-m-d');
        $inicioMes = date('Y-m-01', strtotime($hoje));
        $fimMes = date('Y-m-t', strtotime($hoje));
        $filtros['data_inicio'] = $inicioMes;
        $filtros['data_fim'] = $fimMes;

        return $this->repository->somarDespesasNaoPagas($filtros);
    }

    public function contarDespesasNaoPagasMesCurso(array $filtros = []): int
    {
        $hoje = date('Y-m-d');
        $inicioMes = date('Y-m-01', strtotime($hoje));
        $fimMes = date('Y-m-t', strtotime($hoje));
        $filtros['data_inicio'] = $inicioMes;
        $filtros['data_fim'] = $fimMes;

        return $this->repository->contarDespesasNaoPagas($filtros);
    }

    /**
     * Retorna o resumo (total e soma) das despesas não pagas no mês atual.
     */
    public function getResumoNaoPagasMesCurso(): array
    {
        $hoje = date('Y-m-d');
        $inicioMes = date('Y-m-01', strtotime($hoje));
        $fimMes = date('Y-m-t', strtotime($hoje));

        return $this->repository->getResumoNaoPagas([
            'data_inicio' => $inicioMes,
            'data_fim'    => $fimMes
        ]);
    }

    /**
     * Obtém as despesas que estão vencendo.
     *
     * Retorna uma lista de despesas cuja data de vencimento está próxima ou já passou,
     * conforme a lógica definida no repositório.
     *
     * @return array Uma lista de despesas que estão em situação de vencimento.
     */
    public function getDespesasVencendo(): array
    {
        return $this->repository->getDespesasVencendo();
    }

    /**
     * Conta as despesas que ainda não foram pagas.
     *
     * @param array $filtros Filtros opcionais.
     * @return int Quantidade de despesas pendentes.
     */
    public function contarDespesasNaoPagas(array $filtros = []): int
    {
        return $this->repository->contarDespesasNaoPagas($filtros);
    }

    /**
     * Lista despesas não pagas.
     *
     * @param array $filtros
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function listarDespesasNaoPagas(array $filtros = [], int $limit = 0, int $offset = 0): array
    {
        return $this->repository->listarDespesasNaoPagas($filtros, $limit, $offset);
    }

    /**
     * Obtém as despesas pagas em um determinado período.
     *
     * Busca todas as despesas que foram efetivamente pagas dentro do intervalo de datas especificado.
     *
     * @param string $dataInicio A data de início do período no formato 'Y-m-d'.
     * @param string $dataFim A data de término do período no formato 'Y-m-d'.
     * @return array Uma lista de despesas pagas no período informado.
     */
    public function getDespesasPagasPorPeriodo($dataInicio, $dataFim): array
    {
        return $this->repository->getDespesasPagasPorPeriodo($dataInicio, $dataFim);
    }

    public function somarDespesasPagasPorPeriodo($dataInicio, $dataFim): float
    {
        return $this->repository->somarDespesasPagasPorPeriodo($dataInicio, $dataFim);
    }

    /**
     * Lista as últimas despesas pagas.
     *
     * @param int $limit
     * @return array
     */
    public function listarUltimasPagas(int $limit = 5): array
    {
        return $this->repository->findLastPaid($limit);
    }

    /**
     * Exclui múltiplas despesas.
     *
     * @param array $ids
     * @return int Número de despesas excluídas.
     */
    public function excluirEmLote(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $deleted = 0;
        foreach ($ids as $id) {
            if ($this->delete($id)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Lista despesas por IDs.
     *
     * @param array $ids
     * @return array
     */
    public function listarPorIds(array $ids): array
    {
        return $this->repository->findByIds($ids);
    }







    ####################################


    /**
     * Cria uma nova despesa
     *
     * @param array $data
     * @return int ID da despesa criada
     * @throws Exception
     */
    public function create(array $data): int
    {
        if (isset($data['data'])) {
            $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($data['conta_id'] ?? 0), $data['data']);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            if (isset($data['valor'])) {
                $data['valor'] = $this->formatarValorParaBanco($data['valor']);
            }

            $data['categorias'] = $this->validarCategoriasRateio(
                (array) ($data['categorias'] ?? []),
                (float) ($data['valor'] ?? 0),
                true
            );

            // Use repository through create method to trigger cache invalidation
            $id = $this->repository->create($data);

            // Lançar reembolso se necessário
            if (!empty($data['cliente_reembolso'])) {
                $resultadoReembolso = $this->pagamentoDespesasService->lancarReembolso($id, $data['cliente_reembolso']);
                if (!$resultadoReembolso['success']) {
                    throw new Exception('Erro ao lançar reembolso: ' . $resultadoReembolso['message']);
                }
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new Exception('Falha na transação ao criar despesa.');
            }

            return (int) $id;
        } catch (Exception $e) {
            $db->transRollback();
            throw $e;
        }
    }

    /**
     * Atualiza uma despesa existente
     *
     * @param int $id
     * @param array $data
     * @return bool
     * @throws Exception
     */
    public function update(int $id, array $data): bool
    {
        $despesa = $this->getById($id);
        if ($despesa) {
            // Verifica bloqueio na data original
            $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($despesa['conta_id'] ?? 0), $despesa['data'] ?? date('Y-m-d'));

            // Se a data estiver sendo alterada, verifica bloqueio na nova data também
            if (isset($data['data']) && $data['data'] !== $despesa['data']) {
                $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($data['conta_id'] ?? $despesa['conta_id']), $data['data']);
            }
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            if (isset($data['valor'])) {
                $data['valor'] = $this->formatarValorParaBanco($data['valor']);
            }

            if (isset($data['categorias'])) {
                $valorReferencia = $data['valor'] ?? (float) ($despesa['valor'] ?? 0);
                $data['categorias'] = $this->validarCategoriasRateio((array) $data['categorias'], (float) $valorReferencia, true);
            }

            $this->repository->update($id, $data);

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new Exception('Falha na transação ao atualizar despesa.');
            }

            return true;
        } catch (Exception $e) {
            $db->transRollback();
            throw $e;
        }
    }

    /**
     * Exclui uma despesa
     *
     * @param int $id
     * @return bool
     * @throws Exception
     */
    public function delete(int $id): bool
    {
        $despesa = $this->getById($id);
        if ($despesa) {
            $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($despesa['conta_id'] ?? 0), $despesa['data'] ?? date('Y-m-d'));
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Repository delete handles errors
            $this->repository->delete($id);

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new Exception('Falha na transação ao excluir despesa.');
            }

            return true;
        } catch (Exception $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function find(int $id): ?array
    {
        $despesa = $this->repository->find($id);

        if (!$despesa) {
            return null;
        }

        // Hydrate contract details
        if (!empty($despesa['contrato_id'])) {
            $contrato = $this->contratosRepository->getDadosContrato((int) $despesa['contrato_id']);

            if ($contrato) {
                $despesa['contrato_numero'] = $contrato['numero_contrato'];
                $despesa['contrato_nome']   = $contrato['nome_cliente'];
            }
        }

        return $despesa;
    } 

    /**
     * Obtém as 5 despesas mais recentes.
     * @return array
     */
    public function getRecentes(): array
    {
        /** @var DespesasRepository $repo */
        $repo = $this->repository;
        return $repo->getRecentes();
    }

    /**
     * Obtém estatísticas básicas das despesas.
     * @return array
     */
    public function getEstatisticas(): array
    {
        /** @var DespesasRepository $repo */
        $repo = $this->repository;
        return $repo->getEstatisticas();
    }

    /**
     * Obtém resumo estatístico de despesas a pagar.
     * Otimizado: Delega agregação para o banco de dados.
     *
     * @return array
     */
    public function getResumoEstatisticas(): array
    {
        return $this->repository->getResumoEstatisticas();
    }

    /**
     * Processa o salvamento de uma despesa, tratando criação (com ou sem pagamento) ou atualização.
     *
     * @param array $data Dados da despesa provenientes do formulário.
     * @param int|null $id ID da despesa para atualização.
     * @return int ID da despesa salva.
     * @throws Exception
     */
    public function processarSalvamento(array $data, ?int $id = null): int
    {
        // Normalização de valores
        if (isset($data['valor']) && is_string($data['valor'])) {
            $data['valor'] = $this->formatarValorParaBanco($data['valor']);
        }

        // Normalização de tipos
        if (isset($data['contrato_id'])) {
            $data['contrato_id'] = $data['contrato_id'] ? (int)$data['contrato_id'] : null;
        }

        if ($id === null) {
            if (isset($data['pagarDespesa']) && $data['pagarDespesa'] == 1) {
                $pagamentoData = [
                    'conta_id'             => $data['conta_id'] ?? null,
                    'pagamento_despesa_dt' => $data['pagamento_dt'] ?? null,
                    'rateio'               => $data['rateio'] ?? [],
                    'comentario'           => $data['comentario'] ?? null,
                ];

                return $this->createWithPayment($data, $pagamentoData);
            }
            return $this->create($data);
        }

        $this->update($id, $data);

        if (isset($data['pagarDespesa']) && $data['pagarDespesa'] == 1) {
            $pagamentoData = [
                'despesa_id'           => $id,
                'conta_id'             => $data['conta_id'] ?? null,
                'pagamento_despesa_dt' => $data['pagamento_dt'] ?? null,
                'valor'                => $data['valor'] ?? null,
                'observacoes'          => $data['comentario'] ?? null,
                'rateio'               => $data['rateio'] ?? []
            ];

            $this->pagamentoDespesasService->create($pagamentoData);
        }

        return $id;
    }

    /**
     * Cria uma despesa e registra o pagamento imediato.
     *
     * @param array $data Dados da despesa.
     * @param array $pagamentoData Dados do pagamento.
     * @return int ID da despesa criada.
     * @throws Exception
     */
    public function createWithPayment(array $data, array $pagamentoData): int
    {
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $despesaId = $this->create($data);

            $pagamentoData['despesa_id'] = $despesaId;

            // Se o valor não foi passado no pagamentoData, usa o da despesa
            if (!isset($pagamentoData['valor'])) {
                $pagamentoData['valor'] = $data['valor'];
            }

            // Calcula rateio se necessário
            if (isset($pagamentoData['rateio']) && is_array($pagamentoData['rateio'])) {
                $pagamentoData['rateio'] = $this->pagamentoDespesasService->tratarRateio($pagamentoData['rateio'], (float) $pagamentoData['valor']);
            }

            $this->pagamentoDespesasService->create($pagamentoData);

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new Exception('Erro ao processar transação de despesa com pagamento.');
            }

            return $despesaId;
        } catch (Exception $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
