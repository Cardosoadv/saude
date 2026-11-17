<?php

namespace App\Controllers\ApiV2\Financeiro;

use App\Controllers\BaseController;
use App\Services\Financeiro\LancamentoExtratoService;
use App\Traits\RespostaExtratosTrait;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Lançamento no financeiro a partir de movimentos de extrato sem lançamento correspondente.
 *
 * Rotas em /api/v2/financeiro/extratos/movimentos/*. Corpo das requisições POST: JSON (ou formulário).
 */
class LancamentoExtratos extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;
    use RespostaExtratosTrait;

    protected LancamentoExtratoService $service;
    private $permissaoNecessaria = 'financeiro';

    public function __construct(?LancamentoExtratoService $service = null)
    {
        $this->service = $service ?? new LancamentoExtratoService();
    }

    /** GET /api/v2/financeiro/extratos/movimentos/{id}/sugestao-lancamento — tipo, regra que casou e contrapartes parecidas. */
    public function sugestao(int $id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        return $this->executar(fn () => ['data' => $this->service->sugestao($id)], 'sugestao');
    }

    /**
     * POST /api/v2/financeiro/extratos/movimentos/{id}/lancar
     *
     * Sem mais nada, cria despesa+pagamento (saída) ou receita+recebimento (entrada): fornecedor_id ou
     * novo_fornecedor, cliente_id, categoria_id/categorias, rateio [{id, percentual}], descricao, comentario,
     * criar_regra.
     * Com `quitar` {tipo, id}: quita a despesa/receita em aberto.
     * Com `movimento_par_id`: cria uma transferência entre as contas dos dois movimentos.
     */
    public function lancar(int $id): ResponseInterface
    {
        if (!$this->podeEditar()) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        $corpo = $this->corpo();

        return $this->executar(function () use ($id, $corpo): array {
            $user = $this->userId();
            if ((int) ($corpo['movimento_par_id'] ?? 0) > 0) {
                return ['message' => 'Transferência lançada.', 'data' => $this->service->lancarTransferencia($id, (int) $corpo['movimento_par_id'], $corpo, $user)];
            }
            if (isset($corpo['quitar']) && is_array($corpo['quitar'])) {
                return [
                    'message' => 'Lançamento em aberto quitado.',
                    'data'    => $this->service->quitar($id, (string) ($corpo['quitar']['tipo'] ?? ''), (int) ($corpo['quitar']['id'] ?? 0), $corpo, $user),
                ];
            }

            return ['message' => 'Movimento lançado.', 'data' => $this->service->lancar($id, $corpo, $user)];
        }, 'lancar');
    }

    /**
     * POST /api/v2/financeiro/extratos/movimentos/lancar-lote
     * Corpo: itens=[{movimento_id, ...dados do lançamento}] (até 50). Itens inválidos voltam com o motivo.
     */
    public function lancarLote(): ResponseInterface
    {
        if (!$this->podeEditar()) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        $corpo = $this->corpo();
        $itens = isset($corpo['itens']) && is_array($corpo['itens']) ? array_values($corpo['itens']) : [];

        return $this->executar(
            fn () => ['message' => 'Lote processado.', 'data' => $this->service->lancarLote($itens, $this->userId())],
            'lancarLote'
        );
    }
}
