<?php

namespace App\Controllers\ApiV2\Financeiro;

use App\Controllers\BaseController;
use App\Services\Financeiro\RegrasExtratoService;
use App\Traits\RespostaExtratosTrait;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Regras de classificação de movimentos de extrato (padrão → fornecedor/cliente, categoria e rateio padrão).
 * As regras só geram sugestão. Rotas em /api/v2/financeiro/extratos/regras.
 */
class RegrasExtratos extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;
    use RespostaExtratosTrait;

    protected RegrasExtratoService $service;
    private $permissaoNecessaria = 'financeiro';

    public function __construct(?RegrasExtratoService $service = null)
    {
        $this->service = $service ?? new RegrasExtratoService();
    }

    /** GET /api/v2/financeiro/extratos/regras?conta_id=&ativas=1 */
    public function listar(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        $conta = (int) $this->request->getGet('conta_id');
        $ativas = filter_var($this->request->getGet('ativas'), FILTER_VALIDATE_BOOLEAN);

        return $this->executar(fn () => ['data' => $this->service->listar($conta > 0 ? $conta : null, $ativas)], 'listar');
    }

    /** GET /api/v2/financeiro/extratos/regras/{id} */
    public function detalhar(int $id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        return $this->executar(fn () => ['data' => $this->service->obter($id)], 'detalhar');
    }

    /** POST /api/v2/financeiro/extratos/regras — nome, padrao, campo, sinal, conta_id, fornecedor_id|cliente_id, categoria_id, rateio. */
    public function criar(): ResponseInterface
    {
        if (!$this->podeEditar()) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        $corpo = $this->corpo();

        return $this->executar(fn () => ['message' => 'Regra criada.', 'data' => $this->service->criar($corpo, $this->userId())], 'criar');
    }

    /** PUT /api/v2/financeiro/extratos/regras/{id} — só os campos enviados são alterados. */
    public function atualizar(int $id): ResponseInterface
    {
        if (!$this->podeEditar()) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        $corpo = $this->corpo();

        return $this->executar(fn () => ['message' => 'Regra atualizada.', 'data' => $this->service->atualizar($id, $corpo, $this->userId())], 'atualizar');
    }

    /** DELETE /api/v2/financeiro/extratos/regras/{id} */
    public function excluir(int $id): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'extratos_lancar')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        return $this->executar(function () use ($id): array {
            $this->service->excluir($id, $this->userId());

            return ['message' => 'Regra excluída.'];
        }, 'excluir');
    }
}
