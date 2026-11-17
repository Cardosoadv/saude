<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\MensagensService;
use App\Traits\VerificaPermissoesTrait;
use App\Traits\AcessoProcessoTrait;
use CodeIgniter\API\ResponseTrait;
use Exception;

/**
 * Controller API V2 para gerenciar mensagens internas do sistema.
 */
class Mensagens extends BaseController
{
    use ResponseTrait;
    use AcessoProcessoTrait;
    use VerificaPermissoesTrait;

    private MensagensService $service;

    public function __construct(?MensagensService $service = null)
    {
        $this->service = $service ?? new MensagensService();
    }

    /**
     * Lista as mensagens do usuário logado (com suporte a filtros, busca e paginação).
     * GET /api/v2/mensagens
     */
    public function index()
    {
        if (!$this->verificaPermissao('mensagens', 'access')) {
            return $this->failForbidden('Sem permissão para acessar mensagens.');
        }

        try {
            $userId = user_id();
            if (!$userId) return $this->failUnauthorized();

            $s = $this->request->getGet('s') ?: null;
            $filter = $this->request->getGet('filter') ?: null;
            $sort = $this->request->getGet('sort') ?: 'recente';
            $page = (int) ($this->request->getGet('page') ?? 1);
            $perPage = (int) ($this->request->getGet('perPage') ?? 25);

            $mensagens = $this->service->listar($userId, $s, $filter, $sort, $perPage, $page);
            $pager = $this->service->getPager();

            return $this->respond([
                'success' => true,
                'data' => $mensagens,
                'pager' => $pager ? [
                    'currentPage' => $pager->getCurrentPage(),
                    'pageCount' => $pager->getPageCount(),
                    'total' => $pager->getTotal(),
                    'hasMore' => $pager->hasMore(),
                ] : null
            ]);
        } catch (Exception $e) {
            log_message('error', '[ApiV2\Mensagens::index] ' . $e->getMessage());
            return $this->failServerError('Erro ao listar mensagens: ' . $e->getMessage());
        }
    }

    /**
     * Retorna a lista de mensagens não lidas.
     * GET /api/v2/mensagens/naoLidas
     */
    public function naoLidas()
    {
        if (!$this->verificaPermissao('mensagens', 'access')) {
            return $this->failForbidden('Sem permissão para acessar mensagens.');
        }

        try {
            $userId = user_id();
            if (!$userId) return $this->failUnauthorized();

            $mensagens = $this->service->getMensagensNaoLidas($userId);
            $count = $this->service->countUnread($userId);

            return $this->respond([
                'success' => true,
                'data' => $mensagens,
                'total' => $count
            ]);
        } catch (Exception $e) {
            return $this->failServerError('Erro ao buscar mensagens não lidas.');
        }
    }

    /**
     * Exibe o conteúdo de uma mensagem e marca como lida se for destinatário.
     * GET /api/v2/mensagens/detalhes/(:num)
     */
    public function detalhes($id)
    {
        if (!$this->verificaPermissao('mensagens', 'access')) {
            return $this->failForbidden('Sem permissão para visualizar mensagens.');
        }

        try {
            $userId = user_id();
            if (!$userId) return $this->failUnauthorized();

            $mensagem = $this->service->ler((int) $id, $userId);
            
            return $this->respond([
                'success' => true,
                'data' => $mensagem
            ]);
        } catch (Exception $e) {
            return $this->failNotFound($e->getMessage());
        }
    }

    /**
     * Salva e envia uma nova mensagem.
     * POST /api/v2/mensagens/salvar
     */
    public function salvar()
    {
        if (!$this->verificaPermissao('mensagens', 'create')) {
            return $this->failForbidden('Sem permissão para enviar mensagens.');
        }

        try {
            $userId = user_id();
            if (!$userId) return $this->failUnauthorized();

            $rules = [
                'destinatario_id' => 'required|integer',
                'assunto'         => 'required|max_length[255]',
                'conteudo'        => 'required',
                'processo_id'     => 'permit_empty|integer',
            ];

            if (!$this->validate($rules)) {
                return $this->failValidationErrors($this->validator->getErrors());
            }

            $destinatarioId = (int) $this->request->getPost('destinatario_id');
            $assunto        = (string) $this->request->getPost('assunto');
            $conteudo       = (string) $this->request->getPost('conteudo');
            $processoIdRaw  = $this->request->getPost('processo_id');
            $processoId     = ($processoIdRaw !== null && $processoIdRaw !== '') ? (int) $processoIdRaw : null;

            if ($processoId && ($negado = $this->acessoAoProcessoNegado($processoId))) {
                return $negado;
            }

            $this->service->enviar($userId, $destinatarioId, $assunto, $conteudo, $processoId);

            return $this->respondCreated([
                'success' => true,
                'message' => 'Mensagem enviada com sucesso!'
            ]);
        } catch (Exception $e) {
            log_message('error', '[ApiV2\Mensagens::salvar] ' . $e->getMessage());
            return $this->failServerError('Erro ao enviar mensagem: ' . $e->getMessage());
        }
    }

    /**
     * Marca uma mensagem específica como lida.
     * POST /api/v2/mensagens/marcarLida/(:num)
     */
    public function marcarLida($id)
    {
        if (!$this->verificaPermissao('mensagens', 'edit')) {
            return $this->failForbidden('Sem permissão para marcar mensagens como lidas.');
        }

        try {
            $userId = user_id();
            if (!$userId) return $this->failUnauthorized();

            $this->service->marcarLida((int) $id, $userId);

            return $this->respond([
                'success' => true,
                'message' => 'Mensagem marcada como lida'
            ]);
        } catch (Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * Marca todas as mensagens não lidas do usuário como lidas.
     * POST /api/v2/mensagens/marcarTodasLidas
     */
    public function marcarTodasLidas()
    {
        if (!$this->verificaPermissao('mensagens', 'edit')) {
            return $this->failForbidden('Sem permissão para marcar mensagens como lidas.');
        }

        try {
            $userId = user_id();
            if (!$userId) return $this->failUnauthorized();

            $this->service->marcarTodasLidas($userId);

            return $this->respond([
                'success' => true,
                'message' => 'Todas as mensagens foram marcadas como lidas'
            ]);
        } catch (Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * Classifica a urgência e o sentimento de uma mensagem via IA.
     * GET /api/v2/mensagens/classificarUrgencia/(:num)
     */
    public function classificarUrgencia($id)
    {
        if (!$this->verificaPermissao('mensagens', 'access')) {
            return $this->failForbidden('Sem permissão para acessar mensagens.');
        }

        try {
            $userId = user_id();
            if (!$userId) return $this->failUnauthorized();

            $analise = $this->service->classificarUrgencia((int) $id, $userId);

            return $this->respond([
                'success' => true,
                'data' => $analise
            ]);
        } catch (Exception $e) {
            log_message('error', '[ApiV2\Mensagens::classificarUrgencia] ' . $e->getMessage());
            return $this->failServerError('Erro ao classificar urgência da mensagem: ' . $e->getMessage());
        }
    }

    /**
     * Gera uma sugestão de resposta via IA para a mensagem, editável pelo advogado.
     * GET /api/v2/mensagens/sugerirResposta/(:num)
     */
    public function sugerirResposta($id)
    {
        if (!$this->verificaPermissao('mensagens', 'access')) {
            return $this->failForbidden('Sem permissão para acessar mensagens.');
        }

        try {
            $userId = user_id();
            if (!$userId) return $this->failUnauthorized();

            $sugestao = $this->service->sugerirResposta((int) $id, $userId);

            return $this->respond([
                'success' => true,
                'data' => $sugestao
            ]);
        } catch (Exception $e) {
            log_message('error', '[ApiV2\Mensagens::sugerirResposta] ' . $e->getMessage());
            return $this->failServerError('Erro ao gerar sugestão de resposta: ' . $e->getMessage());
        }
    }

    /**
     * Excluir mensagem (apenas se o serviço/repositorio suportar).
     * DELETE /api/v2/mensagens/excluir/(:num)
     */
    public function excluir($id)
    {
        if (!$this->verificaPermissao('mensagens', 'delete')) {
            return $this->failForbidden('Sem permissão para excluir mensagens.');
        }

        try {
            $userId = user_id();
            if (!$userId) return $this->failUnauthorized();

            $this->service->excluir((int) $id, $userId);

            return $this->respondDeleted([
                'success' => true,
                'message' => 'Mensagem excluída com sucesso.'
            ]);
        } catch (Exception $e) {
            return $this->failServerError('Erro ao excluir mensagem: ' . $e->getMessage());
        }
    }
}
