<?php

namespace App\Services;

use App\Repositories\PrazoEstrategicoRepository;

class PrazoEstrategicoService extends BaseService
{
    private ?CalendarioForenseService $calendario;

    public function __construct(?PrazoEstrategicoRepository $repository = null, ?CalendarioForenseService $calendario = null)
    {
        $this->repository = $repository ?? new PrazoEstrategicoRepository();
        $this->calendario = $calendario;
    }

    /**
     * Lista prazos estratégicos ativos.
     */
    public function listarAtivos(): array
    {
        return $this->repository->findAtivos();
    }

    /**
     * Calcula a data de segurança: a margem de segurança é contada em dias úteis
     * a partir da data inicial (a própria data não conta).
     */
    public function calcularDataSeguranca(string $dataInicio, int $prazoEstrategicoId, ?string $tribunal = null, ?string $uf = null, ?string $comarca = null): string
    {
        $prazo = $this->repository->findById($prazoEstrategicoId);
        if (!$prazo) return $dataInicio;

        $dias = (int) $prazo['prazo_seguranca'];
        $this->calendario ??= new CalendarioForenseService();

        return $this->calendario->adicionarDiasUteis($dataInicio, $dias, $tribunal, $uf, $comarca);
    }
}
