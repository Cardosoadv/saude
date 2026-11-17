<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProcessosMonitoradosRepository;
use App\Services\BaseService;

/**
 * Serviço para gestão do monitoramento de processos.
 */
class ProcessosMonitoradosService extends BaseService
{
    public function __construct(?ProcessosMonitoradosRepository $repository = null)
    {
        $this->repository = $repository ?? new ProcessosMonitoradosRepository();
    }

    public function buscarProcessosNaoMonitorados(): array
    {
        return $this->repository->buscarProcessosNaoMonitorados();
    }

    public function buscarPorNumeroProcesso(string $numeroProcesso): ?array
    {
        return $this->repository->buscarPorNumeroProcesso($numeroProcesso);
    }

    public function salvar(array $data)
    {
        if (isset($data['id_monitoramento'])) {
            $id = (int) $data['id_monitoramento'];
            unset($data['id_monitoramento']);
            return $this->update($id, $data);
        }

        return $this->create($data);
    }

    public function buscarParaAtualizacao(int $dias = 3): array
    {
        return $this->repository->buscarParaAtualizacao($dias);
    }

    public function registrarChecagem(int $id): bool
    {
        return $this->repository->registrarChecagem($id);
    }

    public function incluirProcessosEmLote(array $processos, int $usuarioId): bool
    {
        return $this->repository->incluirProcessosEmLote($processos, $usuarioId);
    }

    public function buscarPorNumeroProcessoLimpo(string $numeroProcesso): ?array
    {
        return $this->repository->buscarPorNumeroProcessoLimpo($numeroProcesso);
    }
}