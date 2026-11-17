<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\Financeiro\DespesasService;
use App\Services\MensagensService;
use App\Services\TarefasService;
use App\Services\IntimacoesService;
use CodeIgniter\API\ResponseTrait;

/**
 * Controller API para fornecer dados ao Dashboard de Legal Ops.
 * @version 2.0.6
 */
class TemplateData extends BaseController
{
    use ResponseTrait;

    protected MensagensService $mensagensService;
    protected DespesasService $despesasService;
    protected TarefasService $tarefasService;
    protected IntimacoesService $intimacoesService;

    public function __construct(?MensagensService $mensagensService = null,
                                ?DespesasService $despesasService = null,
                                ?TarefasService $tarefasService = null,
                                ?IntimacoesService $intimacoesService = null)
    {
        $this->mensagensService  = $mensagensService ?? new MensagensService();
        $this->despesasService   = $despesasService  ?? new DespesasService();
        $this->tarefasService    = $tarefasService   ?? new TarefasService();
        $this->intimacoesService = $intimacoesService ?? new IntimacoesService();
    }

    /**
     * Retorna todos os dados de notificação consolidados.
     * GET /api/template/notifications
     */
    public function notifications()
    {
        try {
            $userId = user_id();
            if (!$userId) {
                return $this->failUnauthorized();
            }

            $data = [
                'mensagensNaoLidas'        => $this->mensagensService->getMensagensNaoLidas($userId) ?: [],
                'qteMensagensNaoLidas'     => $this->mensagensService->countUnread($userId),
                'qteTarefas'               => $this->tarefasService->contarPorResponsavel($userId),
                'qteDespesasNaoPagas'      => $this->despesasService->contarDespesasNaoPagas(),
                'qteIntimacoesNaoTratadas' => $this->intimacoesService->contarNaoTratadas(),
            ];

            $data['notificacoes'] = $data['qteMensagensNaoLidas'] + $data['qteTarefas'] + $data['qteDespesasNaoPagas'] + $data['qteIntimacoesNaoTratadas'];

            return $this->respond($data);
        } catch (\Exception $e) {
            log_message('error', '[Api\TemplateData::notifications] ' . $e->getMessage());
            return $this->failServerError('Erro ao carregar notificações.');
        }
    }

    /**
     * Retorna apenas dados de mensagens.
     * GET /api/template/messages
     */
    public function messages()
    {
        $userId = user_id();
        if (!$userId) {
            return $this->failUnauthorized();
        }

        $data = [
            'mensagensNaoLidas'    => $this->mensagensService->getMensagensNaoLidas($userId) ?: [],
            'qteMensagensNaoLidas' => $this->mensagensService->countUnread($userId),
        ];

        return $this->respond($data);
    }

    /**
     * Retorna apenas dados de tarefas.
     * GET /api/template/tasks
     */
    public function tasks()
    {
        $userId = user_id();
        if (!$userId) {
            return $this->failUnauthorized();
        }

        $data = [
            'qteTarefas' => $this->tarefasService->contarPorResponsavel($userId),
        ];

        return $this->respond($data);
    }

    /**
     * Retorna apenas dados de despesas.
     * GET /api/template/expenses
     */
    public function expenses()
    {
        $data = [
            'qteDespesasNaoPagas' => $this->despesasService->contarDespesasNaoPagas(),
        ];

        return $this->respond($data);
    }

    public function index()
    {
        $data = [
            'nomeEmpresa'       => env('nomeEmpresa') ?? 'Minha Empresa',
            'logoEmpresa'       => env('logoEmpresa') ?? 'public/dist/assets/img/logo.png',
            'cnpjEmpresa'       => env('cnpjEmpresa')?? '00.000.000/0001-00',
            'enderecoEmpresa'   => env('enderecoEmpresa'),
        ];

        return $this->respond($data);
    }
}
