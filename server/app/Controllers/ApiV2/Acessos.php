<?php

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\AcessoEntidadeService;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use Exception;

/**
 * Privacidade granular: visibilidade e profissionais com acesso a
 * clientes e contratos.
 *
 * Rotas em /api/v2/acessos/{tipo}/{id}, com tipo = cliente | contrato.
 * Registro inexistente ou sem acesso responde 404 (não revela a existência de
 * registros restritos); com acesso mas sem poder de gestão responde 403.
 */
class Acessos extends BaseController
{
    use ResponseTrait;

    protected AcessoEntidadeService $service;

    public function __construct(?AcessoEntidadeService $service = null)
    {
        $this->service = $service ?? new AcessoEntidadeService();
    }

    /**
     * GET /api/v2/acessos/profissionais?q=
     * Profissionais ativos para escolha de responsável e concessão de acesso.
     */
    public function profissionais(): ResponseInterface
    {
        $termo = (string) ($this->request->getGet('q') ?? '');

        return $this->respond([
            'success' => true,
            'data'    => $this->service->buscarProfissionais($termo),
        ]);
    }

    /**
     * GET /api/v2/acessos/{tipo}/{id}
     */
    public function listar(string $tipo, int $id): ResponseInterface
    {
        if ($erro = $this->verificarAcesso($tipo, $id)) {
            return $erro;
        }

        $acessos = $this->service->listarAcessos($tipo, $id);
        if ($acessos === null) {
            return $this->naoEncontrado();
        }

        return $this->respond([
            'success' => true,
            'data'    => $acessos + ['pode_gerenciar' => $this->service->podeGerenciar($tipo, $id)],
        ]);
    }

    /**
     * POST /api/v2/acessos/{tipo}/{id}/visibilidade  { visibilidade: 'publico' | 'restrito' }
     */
    public function visibilidade(string $tipo, int $id): ResponseInterface
    {
        if ($erro = $this->verificarGestao($tipo, $id)) {
            return $erro;
        }

        $visibilidade = (string) ($this->dados()['visibilidade'] ?? '');
        if (!in_array($visibilidade, AcessoEntidadeService::VISIBILIDADES, true)) {
            return $this->failValidationErrors('Visibilidade inválida. Use "publico" ou "restrito".');
        }

        return $this->executar(function () use ($tipo, $id, $visibilidade) {
            $this->service->definirVisibilidade($tipo, $id, $visibilidade);

            return ['message' => 'Visibilidade atualizada.', 'data' => $this->service->listarAcessos($tipo, $id)];
        });
    }

    /**
     * POST /api/v2/acessos/{tipo}/{id}/usuarios  { user_id }
     */
    public function conceder(string $tipo, int $id): ResponseInterface
    {
        if ($erro = $this->verificarGestao($tipo, $id)) {
            return $erro;
        }

        $userId = (int) ($this->dados()['user_id'] ?? 0);
        if ($userId <= 0 || !$this->service->ehProfissionalAtivo($userId)) {
            return $this->failValidationErrors('Selecione um profissional ativo.');
        }

        return $this->executar(function () use ($tipo, $id, $userId) {
            $novo = $this->service->conceder($tipo, $id, $userId, user_id());

            return [
                'message' => $novo ? 'Acesso concedido.' : 'O profissional já tinha acesso.',
                'data'    => $this->service->listarAcessos($tipo, $id),
            ];
        });
    }

    /**
     * DELETE /api/v2/acessos/{tipo}/{id}/usuarios/{userId}
     */
    public function revogar(string $tipo, int $id, int $userId): ResponseInterface
    {
        if ($erro = $this->verificarGestao($tipo, $id)) {
            return $erro;
        }

        return $this->executar(function () use ($tipo, $id, $userId) {
            $this->service->revogar($tipo, $id, $userId);

            return ['message' => 'Acesso removido.', 'data' => $this->service->listarAcessos($tipo, $id)];
        });
    }

    /**
     * POST /api/v2/acessos/cliente/{id}/propagar  { copiar_visibilidade?: bool }
     * Herança opcional: copia os acessos do cliente para seus contratos.
     */
    public function propagar(int $clienteId): ResponseInterface
    {
        if ($erro = $this->verificarGestao('cliente', $clienteId)) {
            return $erro;
        }

        $copiarVisibilidade = filter_var($this->dados()['copiar_visibilidade'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $this->executar(function () use ($clienteId, $copiarVisibilidade) {
            $afetados = $this->service->propagarDoCliente($clienteId, user_id(), $copiarVisibilidade);

            return [
                'message' => "Acessos aplicados a {$afetados['contratos']} contrato(s).",
                'data'    => $afetados,
            ];
        });
    }

    /**
     * Tipo válido, permissão no módulo e acesso ao registro.
     */
    private function verificarAcesso(string $tipo, int $id): ?ResponseInterface
    {
        $meta = AcessoEntidadeService::TIPOS[$tipo] ?? null;
        if ($meta === null) {
            return $this->naoEncontrado();
        }

        if (!auth()->user()->can($meta['permissao'] . '.access')) {
            return $this->respond(['success' => false, 'message' => 'Você não tem permissão para acessar este módulo.'], 403);
        }

        if (!$this->service->podeAcessar($tipo, $id)) {
            return $this->naoEncontrado();
        }

        return null;
    }

    private function verificarGestao(string $tipo, int $id): ?ResponseInterface
    {
        if ($erro = $this->verificarAcesso($tipo, $id)) {
            return $erro;
        }

        if ($this->service->listarAcessos($tipo, $id) === null) {
            return $this->naoEncontrado();
        }

        if (!$this->service->podeGerenciar($tipo, $id)) {
            return $this->respond([
                'success' => false,
                'message' => 'Apenas o responsável ou um administrador pode alterar os acessos deste registro.',
            ], 403);
        }

        return null;
    }

    private function executar(callable $acao): ResponseInterface
    {
        try {
            return $this->respond(['success' => true] + $acao());
        } catch (Exception $e) {
            log_message('error', 'Erro ao gerenciar acessos: ' . $e->getMessage());

            return $this->respond(['success' => false, 'message' => 'Erro interno ao atualizar os acessos.'], 500);
        }
    }

    private function naoEncontrado(): ResponseInterface
    {
        return $this->respond(['success' => false, 'message' => 'Registro não encontrado ou sem permissão de acesso.'], 404);
    }

    private function dados(): array
    {
        return $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
    }
}
