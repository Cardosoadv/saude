<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Repositories\Financeiro\ReceitasRepository;
use App\Traits\FormataValorTrait;
use App\Traits\ValidaCategoriasRateioTrait;
use Exception;
use App\Services\BaseService;

/**
 * @property ReceitasRepository $repository
 */
class ReceitasService extends BaseService
{
    use FormataValorTrait;
    use ValidaCategoriasRateioTrait;


    /**
     * @var SaldoMensalService
     */
    protected SaldoMensalService $saldoMensalService;

    /**
     * @var \App\Repositories\ContratosRepository
     */
    protected \App\Repositories\ContratosRepository $contratosRepository;

    /**
     * @var PagamentoReceitasService
     */
    protected PagamentoReceitasService $pagamentoReceitasService;

    /**
     * Construtor do ReceitasService com injeção de dependência.
     *
     * @param ReceitasRepository|null $repository
     * @param SaldoMensalService|null $saldoMensalService
     * @param \App\Repositories\ContratosRepository|null $contratosRepository
     * @param PagamentoReceitasService|null $pagamentoReceitasService
     */
    public function __construct(
        ?ReceitasRepository $repository = null,
        ?SaldoMensalService $saldoMensalService = null,
        ?\App\Repositories\ContratosRepository $contratosRepository = null,
        ?PagamentoReceitasService $pagamentoReceitasService = null
    ) {
        $this->repository               = $repository ?? new ReceitasRepository();
        $this->saldoMensalService       = $saldoMensalService ?? new SaldoMensalService();
        $this->contratosRepository      = $contratosRepository ?? new \App\Repositories\ContratosRepository();
        $this->pagamentoReceitasService = $pagamentoReceitasService ?? new PagamentoReceitasService();
    }



    /**
     * Cria uma nova receita
     */
    public function create(array $data): int
    {
        if (isset($data['data'])) {
            $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($data['conta_id'] ?? 0), $data['data']);
        }

        if (isset($data['valor'])) {
            $data['valor'] = $this->formatarValorParaBanco($data['valor']);
        }

        $data['categorias'] = $this->validarCategoriasRateio(
            (array) ($data['categorias'] ?? []),
            (float) ($data['valor'] ?? 0),
            false
        );

        return parent::create($data);
    }

    /**
     * Atualiza uma receita existente
     */
    public function update(int $id, array $data): bool
    {
        $receita = $this->getById($id);
        if ($receita) {
            // Verifica bloqueio na data original
            $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($receita['conta_id'] ?? 0), $receita['data'] ?? date('Y-m-d'));

            // Se a data estiver sendo alterada, verifica bloqueio na nova data também
            if (isset($data['data']) && $data['data'] !== $receita['data']) {
                $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($data['conta_id'] ?? $receita['conta_id']), $data['data']);
            }
        }

        if (isset($data['valor'])) {
            $data['valor'] = $this->formatarValorParaBanco($data['valor']);
        }

        if (isset($data['categorias'])) {
            $valorReferencia = $data['valor'] ?? (float) ($receita['valor'] ?? 0);
            $data['categorias'] = $this->validarCategoriasRateio((array) $data['categorias'], (float) $valorReferencia, false);
        }

        return parent::update($id, $data);
    }

    /**
     * Exclui uma receita
     */
    public function delete(int $id): bool
    {
        $receita = $this->getById($id);
        if ($receita) {
            $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($receita['conta_id'] ?? 0), $receita['data'] ?? date('Y-m-d'));
        }
        return parent::delete($id);
    }

    public function getById(int $id): ?array
    {
        $receita = $this->repository->find($id); // Repository override handles joins

        if (!$receita) {
            return null;
        }

        if (isset($receita['rateio']) && is_string($receita['rateio'])) {
            $receita['rateio'] = json_decode($receita['rateio'], true);
        }

        // Hydrate contract details
        if (!empty($receita['contrato_id'])) {
            $contrato = $this->contratosRepository->getDadosContrato((int) $receita['contrato_id']);

            if ($contrato) {
                $receita['contrato_numero'] = $contrato['numero_contrato'];
                $receita['contrato_nome']   = $contrato['nome_cliente'];
            }
        }

        return $receita;
    }

    /**
     * @deprecated Use getById() instead.
     */
    public function find(int $id): ?array
    {
        return $this->getById($id);
    }

    public function listarClientes(): array
    {
        return $this->repository->listarClientes();
    }

    /**
     * Lista as receitas não pagas no mês atual.
     *
     * Calcula o intervalo de datas do mês corrente e busca as receitas
     * que ainda não foram recebidas dentro desse período.
     *
     * @param array $filtros Filtros opcionais para refinar a consulta (ex: cliente, categoria).
     * @param int $limit O número máximo de registros a serem retornados. 0 para sem limite.
     * @param int $offset O número de registros a pular para paginação.
     * @return array Uma lista de receitas não pagas encontradas no mês atual.
     */
    public function listarReceitasNaoPagasMesCurso(array $filtros = [], int $limit = 0, int $offset = 0): array
    {
        $hoje = date('Y-m-d');
        $inicioMes = date('Y-m-01', strtotime($hoje));
        $fimMes = date('Y-m-t', strtotime($hoje));
        $filtros['data_inicio'] = $inicioMes;
        $filtros['data_fim'] = $fimMes;

        return $this->repository->listarReceitasNaoPagas($filtros, $limit, $offset);
    }

    /**
     * Obtém as receitas pagas em um determinado período.
     *
     * Busca todas as receitas que foram efetivamente recebidas dentro do intervalo de datas especificado.
     *
     * @param string $dataInicio A data de início do período no formato 'Y-m-d'.
     * @param string $dataFim A data de término do período no formato 'Y-m-d'.
     * @return array Uma lista de receitas pagas no período informado.
     */
    public function getReceitasPagasPorPeriodo($dataInicio, $dataFim): array
    {
        return $this->repository->getReceitasPagasPorPeriodo($dataInicio, $dataFim);
    }

    public function getTotalReceitasPagasPorPeriodo(string $dataInicio, string $dataFim): float
    {
        return $this->repository->somarReceitasPagasPorPeriodo($dataInicio, $dataFim);
    }

    /**
     * Conta as receitas que ainda não foram recebidas.
     *
     * @param array $filtros Filtros opcionais.
     * @return int Quantidade de receitas pendentes.
     */
    public function contarReceitasNaoRecebidas(array $filtros = []): int
    {
        return $this->repository->contarReceitasNaoRecebidas($filtros);
    }

    /**
     * Lista receitas não pagas/recebidas.
     *
     * @param array $filtros
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function listarReceitasNaoRecebidas(array $filtros = [], int $limit = 0, int $offset = 0): array
    {
        return $this->repository->listarReceitasNaoPagas($filtros, $limit, $offset);
    }

    /**
     * Obtém resumo estatístico de receitas a receber.
     * Otimizado: Delega agregação para o banco de dados.
     *
     * @return array
     */
    public function getResumoEstatisticas(): array
    {
        return $this->repository->getResumoEstatisticas();
    }

    /**
     * Processa o salvamento de uma receita, tratando criação (com ou sem pagamento) ou atualização.
     *
     * @param array $data Dados da receita provenientes do formulário.
     * @param int|null $id ID da receita para atualização.
     * @return int ID da receita salva.
     * @throws Exception
     */
    public function processarSalvamento(array $data, ?int $id = null): int
    {
        // Normalização de valores
        if (isset($data['valor']) && is_string($data['valor'])) {
            $data['valor'] = $this->formatarValorParaBanco($data['valor']);
        }

        // Normalização de tipos
        if (isset($data['cliente_id'])) {
            $data['cliente_id'] = is_numeric($data['cliente_id']) ? (int)$data['cliente_id'] : null;
        }
        if (isset($data['contrato_id'])) {
            $data['contrato_id'] = (!empty($data['contrato_id']) && is_numeric($data['contrato_id'])) ? (int)$data['contrato_id'] : null;
        }

        if ($id === null) {
            if (isset($data['receberReceita']) && $data['receberReceita'] == 1) {
                $pagamentoData = [
                    'conta_id'             => $data['conta_id'] ?? null,
                    'pagamento_receita_dt' => $data['recebimento_dt'] ?? $data['pagamento_dt'] ?? null,
                    'rateio'               => $data['rateio'] ?? [],
                    'comentario'           => $data['comentario'] ?? null,
                ];

                return $this->createWithPayment($data, $pagamentoData);
            }
            return $this->create($data);
        }

        $this->update($id, $data);
        
        if (isset($data['receberReceita']) && $data['receberReceita'] == 1) {
            $pagamentoData = [
                'receita_id'           => $id,
                'conta_id'             => $data['conta_id'] ?? null,
                'pagamento_receita_dt' => $data['recebimento_dt'] ?? $data['pagamento_dt'] ?? null,
                'valor'                => $data['valor'] ?? null,
                'rateio'               => $data['rateio'] ?? [],
                'comentario'           => $data['comentario'] ?? null,
            ];

            // Nota: o rateio já vem do frontend em valores absolutos (R$), não em
            // percentuais — não deve ser recalculado via tratarRateio() aqui.
            $this->pagamentoReceitasService->create($pagamentoData);
        }

        return $id;
    }

    /**
     * Cria uma receita e registra o recebimento imediato.
     *
     * @param array $data Dados da receita.
     * @param array $pagamentoData Dados do pagamento.
     * @return int ID da receita criada.
     * @throws Exception
     */
    public function createWithPayment(array $data, array $pagamentoData): int
    {
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $receitaId = $this->create($data);

            $pagamentoData['receita_id'] = $receitaId;

            // Se o valor não foi passado no pagamentoData, usa o da receita
            if (!isset($pagamentoData['valor'])) {
                $pagamentoData['valor'] = $data['valor'];
            }

            // Nota: o rateio já vem do frontend em valores absolutos (R$), não em
            // percentuais — não deve ser recalculado via tratarRateio() aqui.
            $this->pagamentoReceitasService->create($pagamentoData);

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new Exception('Erro ao processar transação de receita com pagamento.');
            }

            return $receitaId;
        } catch (Exception $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
