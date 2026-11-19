<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\Financeiro\DespesasService;
use App\Services\TarefasService;
use CodeIgniter\API\ResponseTrait;

/**
 * Contadores globais exibidos no menu e na barra superior da SPA.
 */
class TemplateData extends BaseController
{
    use ResponseTrait;

    protected DespesasService $despesasService;
    protected TarefasService $tarefasService;

    public function __construct(?DespesasService $despesasService = null, ?TarefasService $tarefasService = null)
    {
        $this->despesasService = $despesasService ?? new DespesasService();
        $this->tarefasService  = $tarefasService ?? new TarefasService();
    }

    /**
     * GET /api/v2/template/notifications
     */
    public function notifications()
    {
        try {
            $userId = user_id();
            if (!$userId) {
                return $this->failUnauthorized();
            }

            $data = [
                'qteTarefas'          => $this->tarefasService->contarPorResponsavel($userId),
                'qteDespesasNaoPagas' => $this->despesasService->contarDespesasNaoPagas(),
            ];

            $data['notificacoes'] = $data['qteTarefas'] + $data['qteDespesasNaoPagas'];

            return $this->respond($data);
        } catch (\Exception $e) {
            log_message('error', '[Api\TemplateData::notifications] ' . $e->getMessage());

            return $this->failServerError('Erro ao carregar notificações.');
        }
    }
}
