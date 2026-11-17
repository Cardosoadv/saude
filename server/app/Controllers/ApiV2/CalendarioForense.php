<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Models\TarefasModel;
use App\Services\CalendarioForenseService;
use App\Services\IntimacoesService;
use CodeIgniter\API\ResponseTrait;
use Exception;
use InvalidArgumentException;

/**
 * Calendário forense (feriados e suspensões) e memória de cálculo de prazos.
 *
 * O acesso é controlado por filtros de rota: o CRUD exige `admin.settings`;
 * as memórias de cálculo exigem acesso a tarefas ou intimações.
 */
class CalendarioForense extends BaseController
{
    use ResponseTrait;

    protected CalendarioForenseService $service;

    public function __construct(?CalendarioForenseService $service = null)
    {
        $this->service = $service ?? new CalendarioForenseService();
    }

    // ---- Feriados ----------------------------------------------------

    public function listarFeriados()
    {
        $ano = $this->request->getGet('ano');

        return $this->respond([
            'success' => true,
            'data'    => $this->service->listarFeriados($ano !== null && $ano !== '' ? (int) $ano : null),
        ]);
    }

    public function salvarFeriado($id = null)
    {
        $dados = $this->request->getJSON(true) ?? [];

        return $this->salvar(fn () => $this->service->salvarFeriado($dados, $id !== null ? (int) $id : null), $id, 'Feriado');
    }

    public function excluirFeriado($id)
    {
        return $this->excluir(fn () => $this->service->excluirFeriado((int) $id), 'Feriado');
    }

    // ---- Suspensões --------------------------------------------------

    public function listarSuspensoes()
    {
        return $this->respond(['success' => true, 'data' => $this->service->listarSuspensoes()]);
    }

    public function salvarSuspensao($id = null)
    {
        $dados = $this->request->getJSON(true) ?? [];

        if (!empty($dados['data_inicio']) && !empty($dados['data_fim']) && $dados['data_fim'] < $dados['data_inicio']) {
            return $this->failValidationErrors('A data final não pode ser anterior à inicial.');
        }

        return $this->salvar(fn () => $this->service->salvarSuspensao($dados, $id !== null ? (int) $id : null), $id, 'Suspensão');
    }

    public function excluirSuspensao($id)
    {
        return $this->excluir(fn () => $this->service->excluirSuspensao((int) $id), 'Suspensão');
    }

    // ---- Cálculo e memória -------------------------------------------

    /**
     * Simula um prazo: ?data=AAAA-MM-DD&dias=15&tribunal=TJSP&uf=SP&comarca=Campinas
     */
    public function calcular()
    {
        $data = (string) $this->request->getGet('data');
        $dias = (int) $this->request->getGet('dias');

        try {
            $memoria = $this->service->memoriaCalculo(
                $data,
                $dias,
                $this->request->getGet('tribunal') ?: null,
                $this->request->getGet('uf') ?: null,
                $this->request->getGet('comarca') ?: null
            );
        } catch (InvalidArgumentException $e) {
            return $this->failValidationErrors($e->getMessage());
        }

        return $this->respond(['success' => true, 'data' => $memoria]);
    }

    /**
     * Memória de cálculo gravada na tarefa na época da criação.
     */
    public function memoriaTarefa($id)
    {
        $tarefa = (new TarefasModel())->find((int) $id);
        if (!$tarefa) {
            return $this->failNotFound('Tarefa não encontrada.');
        }

        $memoria = json_decode((string) ($tarefa['prazo_memoria_calculo'] ?? ''), true);

        return $this->respond([
            'success' => true,
            'data'    => [
                'disponivel' => is_array($memoria),
                'a_conferir' => !empty($tarefa['prazo_a_conferir']),
                'prazo'      => $tarefa['prazo'] ?? null,
                'memoria'    => is_array($memoria) ? $memoria : null,
            ],
        ]);
    }

    /**
     * Memória de cálculo da intimação, recalculada com o calendário atual.
     */
    public function memoriaIntimacao($id)
    {
        try {
            $resultado = (new IntimacoesService())->memoriaCalculoPrazo((int) $id);
        } catch (Exception $e) {
            return $this->failNotFound($e->getMessage());
        }

        return $this->respond(['success' => true, 'data' => $resultado]);
    }

    // ---- Auxiliares --------------------------------------------------

    private function salvar(callable $acao, $id, string $rotulo)
    {
        try {
            $resultado = $acao();
        } catch (Exception $e) {
            log_message('error', "Erro ao salvar {$rotulo} do calendário forense: " . $e->getMessage());
            return $this->failServerError("Erro ao salvar. Tente novamente.");
        }

        if ($resultado === false) {
            return $this->failValidationErrors($this->service->errosValidacao());
        }

        $payload = ['success' => true, 'message' => "{$rotulo} salvo com sucesso."];
        if ($id === null) {
            $payload['id'] = $resultado;
            return $this->respondCreated($payload);
        }

        return $this->respond($payload);
    }

    private function excluir(callable $acao, string $rotulo)
    {
        try {
            $acao();
        } catch (Exception $e) {
            log_message('error', "Erro ao excluir {$rotulo} do calendário forense: " . $e->getMessage());
            return $this->failServerError('Erro ao excluir.');
        }

        return $this->respondDeleted(['success' => true, 'message' => "{$rotulo} excluído com sucesso."]);
    }
}
