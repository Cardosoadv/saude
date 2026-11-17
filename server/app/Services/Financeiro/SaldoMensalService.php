<?php

namespace App\Services\Financeiro;

use App\Repositories\Financeiro\ContasRepository;
use App\Repositories\Financeiro\TransferenciasRateioRepository;
use App\Repositories\Financeiro\PagamentoDespesasRateioRepository;
use App\Repositories\Financeiro\PagamentoDespesasRepository;
use App\Repositories\Financeiro\PagamentoReceitasRateioRepository;
use App\Repositories\Financeiro\PagamentoReceitasRepository;
use App\Repositories\Financeiro\SaldoMensalRepository;
use App\Services\BaseService;
use App\Services\Financeiro\ExtratoService;
use Exception;
use App\Repositories\Financeiro\TransferenciasRepository;

/**
 * @property SaldoMensalRepository $repository
 * v1.4.1
 */
class SaldoMensalService extends BaseService
{

    /**
     * @var PagamentoReceitasRepository
     */
    protected PagamentoReceitasRepository $receitasRepository;

    /**
     * @var PagamentoDespesasRepository
     */
    protected PagamentoDespesasRepository $despesasRepository;

    /**
     * @var PagamentoReceitasRateioRepository
     */
    protected PagamentoReceitasRateioRepository $receitasRateioRepository;

    /**
     * @var PagamentoDespesasRateioRepository
     */
    protected PagamentoDespesasRateioRepository $despesasRateioRepository;

    /**
     * @var TransferenciasRateioRepository
     */
    protected TransferenciasRateioRepository $transferenciasRateioRepository;

    /**
     * @var TransferenciasRepository
     */
    protected TransferenciasRepository $transferenciasRepository;

    /**
     * @var ContasRepository
     */
    protected ContasRepository $contasRepository;

    /**
     * @var ExtratoService
     */
    protected ExtratoService $extratoService;

    /**
     * Construtor do serviço com injeção de dependência.
     *
     * @param SaldoMensalRepository|null $repository
     * @param ContasRepository|null $contasRepository
     * @param ExtratoService|null $extratoService
     * @param PagamentoReceitasRepository|null $receitasRepository
     * @param PagamentoDespesasRepository|null $despesasRepository
     * @param PagamentoReceitasRateioRepository|null $receitasRateioRepository
     * @param PagamentoDespesasRateioRepository|null $despesasRateioRepository
     * @param TransferenciasRepository|null $transferenciasRepository
     */
    public function __construct(
        ?SaldoMensalRepository $repository = null,
        ?ContasRepository $contasRepository = null,
        ?ExtratoService $extratoService = null,
        ?PagamentoReceitasRepository $receitasRepository = null,
        ?PagamentoDespesasRepository $despesasRepository = null,
        ?PagamentoReceitasRateioRepository $receitasRateioRepository = null,
        ?PagamentoDespesasRateioRepository $despesasRateioRepository = null,
        ?TransferenciasRateioRepository $transferenciasRateioRepository = null,
        ?TransferenciasRepository $transferenciasRepository = null
    ) {
        $this->repository               = $repository ?? new SaldoMensalRepository();
        $this->contasRepository         = $contasRepository ?? new ContasRepository();
        $this->extratoService           = $extratoService ?? new ExtratoService();
        $this->receitasRepository       = $receitasRepository ?? new PagamentoReceitasRepository();
        $this->despesasRepository       = $despesasRepository ?? new PagamentoDespesasRepository();
        $this->receitasRateioRepository = $receitasRateioRepository ?? new PagamentoReceitasRateioRepository();
        $this->despesasRateioRepository = $despesasRateioRepository ?? new PagamentoDespesasRateioRepository();
        $this->transferenciasRateioRepository = $transferenciasRateioRepository ?? new TransferenciasRateioRepository();
        $this->transferenciasRepository = $transferenciasRepository ?? new TransferenciasRepository();
    }

    /**
     * Verifica se o mês está fechado e lança exceção se estiver.
     * Utilizado para bloquear lançamentos (receitas, despesas, transferências).
     *
     * @param string $tipo 'CONTA' ou 'GLOBAL' (normalmente 'CONTA' para lançamentos diretos)
     * @param int|null $contaId ID da conta
     * @param string $data Data do lançamento (Y-m-d)
     * @throws Exception
     */
    public function verificarBloqueioMensal(string $tipo, ?int $contaId, string $data): void
    {
        $ano = (int) date('Y', strtotime($data));
        $mes = (int) date('m', strtotime($data));

        if ($this->repository->isMesFechado($tipo, $contaId, $ano, $mes)) {
            $periodo = sprintf('%02d/%d', $mes, $ano);
            throw new Exception("O período {$periodo} já está fechado financeiramente. Não é possível realizar lançamentos.");
        }
    }

    /**
     * Processa o fechamento de todas as contas para um mês específico.
     */
    public function fecharMesGlobal(int $ano, int $mes, int $userId): array
    {
        // Validação de sequência
        $this->verificarSequenciaFechamento($ano, $mes);

        $contas = $this->contasRepository->findAll();
        $resultados = [];

        foreach ($contas as $conta) {
            try {
                $this->fecharMesConta((int)$conta['id_conta'], $ano, $mes, $userId);
                $resultados[] = [
                    'conta' => $conta['conta'],
                    'status' => 'success',
                    'message' => 'Fechado com sucesso.'
                ];
            } catch (Exception $e) {
                $resultados[] = [
                    'conta' => $conta['conta'],
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }

        return $resultados;
    }

    /**
     * Fecha o mês de uma conta específica.
     * v1.2.0 - Refatorado para usar getSaldoPeriodoPorConta
     */
    public function fecharMesConta(int $contaId, int $ano, int $mes, int $userId): int
    {
        $dataInicial = "{$ano}-{$mes}-01";
        $dataFinal = date('Y-m-t', strtotime($dataInicial));

        // 1. Busca o saldo anterior fechado
        $ultimoSaldo = $this->repository->getUltimoSaldoFechado($contaId, $dataInicial);
        $saldoAnterior = (float) ($ultimoSaldo['saldo_acumulado'] ?? 0.0);

        // 2. Calcula as movimentações do mês (Lógica de getSaldoPeriodoPorConta)
        $receitas = $this->receitasRepository->getSaldoPeriodoPorConta($contaId, $dataInicial, $dataFinal);
        $despesas = $this->despesasRepository->getSaldoPeriodoPorConta($contaId, $dataInicial, $dataFinal);
        $transf   = $this->transferenciasRepository->getSaldoPeriodoPorConta($contaId, $dataInicial, $dataFinal);

        $saldoMes = $receitas - $despesas + $transf['in'] - $transf['out'];
        $saldoFinal = $saldoAnterior + $saldoMes;

        $dadosSaldo = [
            'tipo'            => 'CONTA',
            'conta_id'        => $contaId,
            'ano'             => $ano,
            'mes'             => $mes,
            'saldo_acumulado' => $saldoFinal,
            'fechado_em'      => date('Y-m-d H:i:s'),
            'fechado_por'     => $userId,
            'status'          => 'FECHADO'
        ];

        // 3. Calcula o rateio acumulado
        $rateioAnterior = [];
        if ($ultimoSaldo) {
            $ultimoDetalhe = $this->repository->getSaldoMensal('CONTA', $contaId, (int)$ultimoSaldo['ano'], (int)$ultimoSaldo['mes']);
            if (isset($ultimoDetalhe['rateios'])) {
                foreach ($ultimoDetalhe['rateios'] as $r) {
                    $rateioAnterior[$r['user_id']] = (float)$r['rateio_acumulado'];
                }
            }
        }

        $movReceitasRateio = $this->receitasRateioRepository->getSaldoPeriodoPorConta($contaId, $dataInicial, $dataFinal);
        $movDespesasRateio = $this->despesasRateioRepository->getSaldoPeriodoPorConta($contaId, $dataInicial, $dataFinal);
        $movTransfRateio   = $this->transferenciasRateioRepository->getSaldoPeriodoPorConta($contaId, $dataInicial, $dataFinal);

        // Processa as movimentações de rateio do mês
        $rateioMes = [];
        foreach ($movReceitasRateio as $m) {
            $rateioMes[$m['user_id']] = ($rateioMes[$m['user_id']] ?? 0.0) + (float)$m['valor'];
        }
        foreach ($movDespesasRateio as $m) {
            $rateioMes[$m['user_id']] = ($rateioMes[$m['user_id']] ?? 0.0) - (float)$m['valor'];
        }
        foreach ($movTransfRateio as $m) {
            $rateioMes[$m['user_id']] = ($rateioMes[$m['user_id']] ?? 0.0) + (float)$m['valor'];
        }

        // Consolida o rateio acumulado final
        $dadosRateio = [];
        $todosUsers = array_unique(array_merge(array_keys($rateioAnterior), array_keys($rateioMes)));

        foreach ($todosUsers as $uId) {
            $acumulado = ($rateioAnterior[$uId] ?? 0.0) + ($rateioMes[$uId] ?? 0.0);
            $dadosRateio[] = [
                'user_id'          => $uId,
                'rateio_acumulado' => $acumulado
            ];
        }

        return $this->repository->fecharMes($dadosSaldo, $dadosRateio);
    }

    /**
     * Reabre um mês.
     */
    public function reabrirMes(string $tipo, ?int $contaId, int $ano, int $mes): bool
    {
        // Validação: permitir abertura apenas do mês mais recente
        $ultimo = $this->repository->getUltimoFechamento($tipo === 'CONTA' ? 'CONTA' : 'GLOBAL', $contaId);
        
        if ($ultimo) {
            if ((int)$ultimo['ano'] !== $ano || (int)$ultimo['mes'] !== $mes) {
                $mesFormatado = sprintf('%02d/%d', $ultimo['mes'], $ultimo['ano']);
                throw new Exception("Não é possível reabrir este período. Só é possível reabrir o mês mais recente fechado ({$mesFormatado}).");
            }
        }

        return $this->repository->reabrirMes($tipo, $contaId, $ano, $mes);
    }

    /**
     * Lista fechamentos paginados.
     */
    public function listarFechamentos(int $limit = 20, int $offset = 0): array
    {
        return $this->repository->listarFechamentos($limit, $offset);
    }

    /**
     * Verifica se o fechamento segue a sequência cronológica (sem pular meses).
     */
    protected function verificarSequenciaFechamento(int $ano, int $mes): void
    {
        $ultimo = $this->repository->getUltimoFechamento();
        if (!$ultimo) {
            return; // Primeiro fechamento
        }

        // Calcula o mês esperado (próximo ao último)
        $ultimoTimestamp = strtotime("{$ultimo['ano']}-{$ultimo['mes']}-01");
        $proximoEsperado = date('Y-m', strtotime("+1 month", $ultimoTimestamp));
        $solicitado = sprintf('%d-%02d', $ano, $mes);

        if ($solicitado < date('Y-m', $ultimoTimestamp)) {
             // Re-fechamento de mês anterior é permitido (sobrescreve)
             return;
        }

        if ($solicitado !== $proximoEsperado && $solicitado > $proximoEsperado) {
            $esperadoFormatado = date('m/Y', strtotime($proximoEsperado . "-01"));
            throw new Exception("Não é possível pular meses. O próximo mês a ser fechado é {$esperadoFormatado}.");
        }
    }

    /**
     * Sugere o próximo mês para fechamento baseado no último registro.
     */
    public function getSugestaoProximoFechamento(): array
    {
        $ultimo = $this->repository->getUltimoFechamento();
        
        if (!$ultimo) {
            // Sugere o mês com a movimentação financeira mais antiga
            $datas = [];
            
            $receita = $this->receitasRepository->getModel()->builder()->selectMin('pagamento_receita_dt')->where('deleted_at is null')->get()->getRowArray();
            if (!empty($receita['pagamento_receita_dt'])) {
                $datas[] = $receita['pagamento_receita_dt'];
            }
            
            $despesa = $this->despesasRepository->getModel()->builder()->selectMin('pagamento_despesa_dt')->where('deleted_at is null')->get()->getRowArray();
            if (!empty($despesa['pagamento_despesa_dt'])) {
                $datas[] = $despesa['pagamento_despesa_dt'];
            }
            
            $transf = $this->transferenciasRepository->getModel()->builder()->selectMin('data_transferencia')->where('deleted_at is null')->get()->getRowArray();
            if (!empty($transf['data_transferencia'])) {
                $datas[] = $transf['data_transferencia'];
            }
            
            if (!empty($datas)) {
                $dataMaisAntiga = min($datas);
                return [
                    'ano' => (int)date('Y', strtotime($dataMaisAntiga)),
                    'mes' => (int)date('m', strtotime($dataMaisAntiga))
                ];
            }
            
            // Fallback se não houver nenhuma movimentação
            return [
                'ano' => (int)date('Y'),
                'mes' => (int)date('m', strtotime('-1 month'))
            ];
        }

        $proximo = strtotime("+1 month", strtotime("{$ultimo['ano']}-{$ultimo['mes']}-01"));
        
        return [
            'ano' => (int)date('Y', $proximo),
            'mes' => (int)date('m', $proximo)
        ];
    }
}
