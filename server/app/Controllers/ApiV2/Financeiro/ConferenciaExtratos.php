<?php

namespace App\Controllers\ApiV2\Financeiro;

use App\Controllers\BaseController;
use App\Services\Financeiro\ConferenciaExtratoService;
use App\Traits\RespostaExtratosTrait;
use App\Traits\VerificaPermissoesTrait;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Conferência informativa do extrato bancário importado (nunca bloqueia o fechamento do mês).
 *
 * Rotas em /api/v2/financeiro/extratos/conferencia e /extratos/resumo-mes.
 */
class ConferenciaExtratos extends BaseController
{
    use ResponseTrait;
    use VerificaPermissoesTrait;
    use RespostaExtratosTrait;

    protected ConferenciaExtratoService $service;
    private $permissaoNecessaria = 'financeiro';

    public function __construct(?ConferenciaExtratoService $service = null)
    {
        $this->service = $service ?? new ConferenciaExtratoService();
    }

    /**
     * GET /api/v2/financeiro/extratos/conferencia?conta_id=&ano=&mes=
     * Saldo do banco (LEDGERBAL) x saldo do sistema na mesma data, com os pendentes que explicam a diferença.
     */
    public function conferir(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        return $this->executar(
            fn () => ['data' => $this->service->conferir(
                (int) $this->request->getGet('conta_id'),
                (int) $this->request->getGet('ano'),
                (int) $this->request->getGet('mes')
            )],
            'conferir'
        );
    }

    /**
     * GET /api/v2/financeiro/extratos/resumo-mes?ano=&mes=
     * Movimentos pendentes, ignorados, conciliados e lançados no mês, por conta (para o fechamento).
     */
    public function resumoMes(): ResponseInterface
    {
        if (!$this->verificaPermissao($this->permissaoNecessaria, 'access')) {
            return $this->respond(['success' => false, 'message' => 'Sem permissão'], 403);
        }

        return $this->executar(
            fn () => ['data' => $this->service->resumoDoMes((int) $this->request->getGet('ano'), (int) $this->request->getGet('mes'))],
            'resumoMes'
        );
    }
}
