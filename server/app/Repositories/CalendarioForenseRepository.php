<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\FeriadosModel;
use App\Models\SuspensoesExpedienteModel;

/**
 * Acesso aos dados do calendário forense (feriados e suspensões de expediente).
 * Não usa cache: a contagem de prazos precisa sempre enxergar o calendário atual.
 */
class CalendarioForenseRepository
{
    protected FeriadosModel $feriados;
    protected SuspensoesExpedienteModel $suspensoes;

    public function __construct(?FeriadosModel $feriados = null, ?SuspensoesExpedienteModel $suspensoes = null)
    {
        $this->feriados   = $feriados ?? new FeriadosModel();
        $this->suspensoes = $suspensoes ?? new SuspensoesExpedienteModel();
    }

    /**
     * Feriados com data dentro do período, mais os recorrentes (que valem todo ano).
     */
    public function feriadosNoPeriodo(string $inicio, string $fim): array
    {
        return $this->feriados
            ->groupStart()
                ->where('data >=', $inicio)->where('data <=', $fim)
            ->groupEnd()
            ->orWhere('recorrente', 1)
            ->orderBy('data')
            ->findAll();
    }

    /**
     * Suspensões que se sobrepõem ao período.
     */
    public function suspensoesNoPeriodo(string $inicio, string $fim): array
    {
        return $this->suspensoes
            ->where('data_inicio <=', $fim)
            ->where('data_fim >=', $inicio)
            ->orderBy('data_inicio')
            ->findAll();
    }

    public function listarFeriados(?int $ano = null): array
    {
        if ($ano !== null) {
            $this->feriados->where('data >=', "{$ano}-01-01")->where('data <=', "{$ano}-12-31");
        }

        return $this->feriados->orderBy('data')->findAll();
    }

    public function listarSuspensoes(): array
    {
        return $this->suspensoes->orderBy('data_inicio', 'DESC')->findAll();
    }

    public function salvarFeriado(array $dados, ?int $id = null)
    {
        return $this->salvar($this->feriados, $dados, $id);
    }

    public function salvarSuspensao(array $dados, ?int $id = null)
    {
        return $this->salvar($this->suspensoes, $dados, $id);
    }

    public function excluirFeriado(int $id): bool
    {
        return (bool) $this->feriados->delete($id);
    }

    public function excluirSuspensao(int $id): bool
    {
        return (bool) $this->suspensoes->delete($id);
    }

    public function errosValidacao(): array
    {
        return array_merge($this->feriados->errors(), $this->suspensoes->errors());
    }

    /**
     * @return int|bool Id inserido, true na atualização ou false se a validação falhar.
     */
    private function salvar($model, array $dados, ?int $id)
    {
        if ($id !== null) {
            return $model->update($id, $dados);
        }

        return $model->insert($dados);
    }
}
