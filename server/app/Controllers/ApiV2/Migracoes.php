<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\MigracoesService;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Controller API V2 que expõe as migrations do banco via HTTP
 * (proxy do `php spark migrate`). Restrito a administradores.
 *
 * @version 1.0.0
 * @since 2026-09-19
 */
class Migracoes extends BaseController
{
    use ResponseTrait;

    protected MigracoesService $service;

    public function __construct(?MigracoesService $service = null)
    {
        $this->service = $service ?? new MigracoesService();
    }

    /**
     * Lista as migrations e seu estado (aplicada/pendente).
     */
    public function status(): ResponseInterface
    {
        if (! auth()->user()->can('admin.access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para consultar as migrations.',
            ], 403);
        }

        try {
            return $this->respond(['success' => true, 'data' => $this->service->status()]);
        } catch (Throwable $e) {
            log_message('error', 'Erro ao consultar migrations API V2: ' . $e->getMessage());

            return $this->respond([
                'success' => false,
                'message' => 'Erro interno ao consultar migrations.',
            ], 500);
        }
    }

    /**
     * Executa todas as migrations pendentes.
     */
    public function executar(): ResponseInterface
    {
        if (! auth()->user()->can('admin.access')) {
            return $this->respond([
                'success' => false,
                'message' => 'Você não tem permissão para executar migrations.',
            ], 403);
        }

        // Uma migration longa não deve ser interrompida se o navegador desistir da requisição.
        ignore_user_abort(true);
        set_time_limit(0);

        try {
            $resultado = $this->service->executar();
            log_message('warning', sprintf(
                'Migrations executadas via HTTP pelo usuário #%d: %s (%d aplicada(s)).',
                auth()->id(),
                $resultado['sucesso'] ? 'sucesso' : 'falha',
                count($resultado['executadas']),
            ));

            return $this->respond([
                'success' => $resultado['sucesso'],
                'message' => $resultado['mensagem'],
                'data'    => [
                    'executadas' => $resultado['executadas'],
                    'mensagens'  => $resultado['mensagens'],
                ],
            ], $resultado['sucesso'] ? 200 : 500);
        } catch (RuntimeException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            log_message('error', 'Erro ao executar migrations API V2: ' . $e->getMessage());

            return $this->respond([
                'success' => false,
                'message' => 'Erro interno ao executar migrations.',
            ], 500);
        }
    }
}
