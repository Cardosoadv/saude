<?php

declare(strict_types=1);

namespace App\Services\Financeiro;

use App\Repositories\Financeiro\TransferenciasRateioRepository;
use App\Repositories\Financeiro\TransferenciasRepository;
use App\Services\BaseService;
use App\Traits\LiberaMovimentosExtratoTrait;
use Exception;

class TransferenciasService extends BaseService
{
    use LiberaMovimentosExtratoTrait;

    
/**
 * @property TransferenciasRepository $repository
 */
    protected TransferenciasRateioRepository $rateioRepository;
    protected SaldoMensalService $saldoMensalService;

    public function __construct(
        ?TransferenciasRepository $repository = null,
        ?TransferenciasRateioRepository $rateioRepository = null,
        ?SaldoMensalService $saldoMensalService = null
    ) {
        $this->repository = $repository ?? new TransferenciasRepository();
        $this->rateioRepository = $rateioRepository ?? new TransferenciasRateioRepository();
        $this->saldoMensalService = $saldoMensalService ?? new SaldoMensalService();
    }

    public function listar(int $perPage = 25): array
    {
        return $this->repository->paginate($perPage);
    }


    public function buscarPorId(int $id): ?array
    {
        $transferencia = $this->repository->findById($id);
        if ($transferencia) {
            $transferencia['rateio'] = $this->rateioRepository->buscarPorTransferencia($id);
        }
        return $transferencia;
    }

    public function criar(array $data): int
    {
        if (isset($data['data_transferencia'])) {
            if (!empty($data['id_conta_origem'] ?? $data['conta_origem_id'] ?? null)) {
                $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($data['id_conta_origem'] ?? $data['conta_origem_id']), $data['data_transferencia']);
            }
            if (!empty($data['id_conta_destino'] ?? $data['conta_destino_id'] ?? null)) {
                $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($data['id_conta_destino'] ?? $data['conta_destino_id']), $data['data_transferencia']);
            }
        }

        $rateio = null;
        if (isset($data['rateio'])) {
            $rateio = $data['rateio'];
            unset($data['rateio']);
        }

        $id = $this->create($data); // Parent create

        if ($id && !empty($rateio)) {
            $this->salvarRateio($id, $rateio);
        }

        return $id;
    }

    public function atualizar(int $id, array $data): bool
    {
        $transf = $this->buscarPorId($id);
        if ($transf) {
            $dataTransf = $transf['data_transferencia'] ?? date('Y-m-d');
            if (!empty($transf['id_conta_origem'] ?? $transf['conta_origem_id'] ?? null)) {
                $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($transf['id_conta_origem'] ?? $transf['conta_origem_id']), $dataTransf);
            }
            if (!empty($transf['id_conta_destino'] ?? $transf['conta_destino_id'] ?? null)) {
                $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($transf['id_conta_destino'] ?? $transf['conta_destino_id']), $dataTransf);
            }

            if (isset($data['data_transferencia']) && $data['data_transferencia'] !== $dataTransf) {
                if (!empty($data['id_conta_origem'] ?? $data['conta_origem_id'] ?? null)) {
                    $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($data['id_conta_origem'] ?? $data['conta_origem_id']), $data['data_transferencia']);
                }
                if (!empty($data['id_conta_destino'] ?? $data['conta_destino_id'] ?? null)) {
                    $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($data['id_conta_destino'] ?? $data['conta_destino_id']), $data['data_transferencia']);
                }
            }
        }

        $rateio = null;
        if (isset($data['rateio'])) {
            $rateio = $data['rateio'];
            unset($data['rateio']);
        }

        $result = $this->update($id, $data); // Parent update

        if ($result && isset($rateio)) {
            $this->rateioRepository->deleteWhere(['transferencia_id' => $id]);
            if (!empty($rateio)) {
                $this->salvarRateio($id, $rateio);
            }
        }

        return $result;
    }

    private function salvarRateio(int $transferenciaId, array $rateio): void
    {
        $batch = [];
        foreach ($rateio as $item) {
            if (empty($item['id']) || empty($item['valor'])) {
                continue;
            }
            $batch[] = [
                'transferencia_id' => $transferenciaId,
                'user_id'          => $item['id'],
                'valor'            => $item['valor'],
            ];
        }

        if (!empty($batch)) {
            $this->rateioRepository->insertBatch($batch);
        }
    }

    public function delete(int $id): bool
    {
        $transf = $this->buscarPorId($id);
        if ($transf) {
            $dataTransf = $transf['data_transferencia'] ?? date('Y-m-d');
            if (!empty($transf['id_conta_origem'] ?? $transf['conta_origem_id'] ?? null)) {
                $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($transf['id_conta_origem'] ?? $transf['conta_origem_id']), $dataTransf);
            }
            if (!empty($transf['id_conta_destino'] ?? $transf['conta_destino_id'] ?? null)) {
                $this->saldoMensalService->verificarBloqueioMensal('CONTA', (int)($transf['id_conta_destino'] ?? $transf['conta_destino_id']), $dataTransf);
            }
        }

        // Os dois movimentos de extrato ligados à transferência voltam para pendente, na mesma transação.
        return (bool) $this->excluirLiberandoExtrato('transferencia', [$id], fn () => parent::delete($id));
    }
}