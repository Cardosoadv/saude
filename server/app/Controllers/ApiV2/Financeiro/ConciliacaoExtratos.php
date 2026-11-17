<?php

namespace App\Controllers\ApiV2\Financeiro;

use App\Controllers\BaseController;
use App\Services\Financeiro\ConciliacaoExtratoService;
use App\Services\Financeiro\Extrato\ConciliacaoConflitoException;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use App\Services\Financeiro\Extrato\ExtratoNaoEncontradoException;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Conciliação dos movimentos de extrato com pagamentos já lançados.
 *
 * Rotas em /api/v2/financeiro/extratos/movimentos/* e /extratos/conciliacao/*.
 * Corpo das requisições POST: JSON (ou formulário).
 */
class ConciliacaoExtratos extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;

    protected ConciliacaoExtratoService $service;
    private $permissaoNecessaria = 'financeiro';

    public function __construct(?ConciliacaoExtratoService $service = null)
    {
        $this->service = $service ?? new ConciliacaoExtratoService();
    }

    /** GET /api/v2/financeiro/extratos/movimentos/{id}/sugestoes?janela=3 */
    public function sugestoes(int $id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        return $this->executar(
            fn () => ['data' => $this->service->sugestoes($id, $this->janela())],
            'sugestoes'
        );
    }

    /** GET /api/v2/financeiro/extratos/conciliacao/lote?conta_id=&janela= — sugestões de confiança alta. */
    public function previaLote(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        $conta = (int) $this->request->getGet('conta_id');
        if ($conta <= 0) {
            return $this->respond(['success' => false, 'message' => 'Informe a conta (conta_id).'], 422);
        }

        return $this->executar(
            fn () => ['data' => $this->service->sugestoesLote($conta, $this->janela())],
            'previaLote'
        );
    }

    /**
     * POST /api/v2/financeiro/extratos/movimentos/{id}/conciliar
     * Corpo: pagamento_tipo (despesa|receita|transferencia), pagamento_id e, opcional (transferência), movimento_par_id.
     */
    public function conciliar(int $id): ResponseInterface
    {
        if (!$this->podeEditar()) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        $corpo = $this->corpo();
        $par   = isset($corpo['movimento_par_id']) && (int) $corpo['movimento_par_id'] > 0 ? (int) $corpo['movimento_par_id'] : null;

        return $this->executar(
            fn () => [
                'message' => 'Movimento conciliado.',
                'data'    => $this->service->conciliar($id, (string) ($corpo['pagamento_tipo'] ?? ''), (int) ($corpo['pagamento_id'] ?? 0), $this->userId(), $par),
            ],
            'conciliar'
        );
    }

    /** POST /api/v2/financeiro/extratos/movimentos/{id}/desconciliar */
    public function desconciliar(int $id): ResponseInterface
    {
        if (!$this->podeEditar()) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        return $this->executar(
            fn () => ['message' => 'Movimento voltou para pendente.', 'data' => $this->service->desconciliar($id, $this->userId())],
            'desconciliar'
        );
    }

    /** POST /api/v2/financeiro/extratos/movimentos/{id}/ignorar — corpo: motivo. */
    public function ignorar(int $id): ResponseInterface
    {
        if (!$this->podeEditar()) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        $corpo = $this->corpo();

        return $this->executar(
            fn () => ['message' => 'Movimento ignorado.', 'data' => $this->service->ignorar($id, (string) ($corpo['motivo'] ?? ''), $this->userId())],
            'ignorar'
        );
    }

    /**
     * POST /api/v2/financeiro/extratos/conciliacao/lote
     * Corpo: confirmar=true e itens=[{movimento_id, pagamento_tipo, pagamento_id}], vindos da prévia.
     */
    public function conciliarLote(): ResponseInterface
    {
        if (!$this->podeEditar()) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        $corpo = $this->corpo();
        $itens = isset($corpo['itens']) && is_array($corpo['itens']) ? array_values($corpo['itens']) : [];

        return $this->executar(
            fn () => [
                'message' => 'Lote processado.',
                'data'    => $this->service->conciliarLote(
                    $itens,
                    filter_var($corpo['confirmar'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    $this->userId(),
                    $this->janela()
                ),
            ],
            'conciliarLote'
        );
    }

    private function podeEditar(): bool
    {
        return $this->verificaPermissao($this->permissaoNecessaria, 'extratos_conciliar');
    }

    private function userId(): ?int
    {
        return auth()->id() ? (int) auth()->id() : null;
    }

    private function janela(): int
    {
        $j = $this->request->getGet('janela');

        return $j === null || $j === '' ? (new \App\Services\Financeiro\ExtratoConfig())->janelaDias() : (int) $j;
    }

    /** @return array<string,mixed> */
    private function corpo(): array
    {
        try {
            $json = $this->request->getJSON(true);
        } catch (\Throwable) {
            $json = null;
        }

        return is_array($json) ? $json : (array) $this->request->getPost();
    }

    private function executar(callable $acao, string $nome): ResponseInterface
    {
        try {
            return $this->respond(['success' => true] + $acao());
        } catch (ExtratoNaoEncontradoException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (ExtratoInvalidoException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (ConciliacaoConflitoException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (\Exception $e) {
            log_message('error', "[Financeiro\\ConciliacaoExtratos::{$nome}] " . $e->getMessage());

            return $this->respond(['success' => false, 'message' => 'Erro ao processar a conciliação.'], 500);
        }
    }
}
