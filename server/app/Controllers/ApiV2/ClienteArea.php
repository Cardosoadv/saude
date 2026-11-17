<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\ClienteAreaService;
use CodeIgniter\API\ResponseTrait;
use Exception;

/**
 * Controller de Dados da Área do Cliente.
 *
 * Todos os endpoints exigem sessão Shield com grupo 'cliente'
 * (protegidos pelo ClienteAuthFilter com alias 'clienteAuth').
 *
 * Escopo de autorização:
 *   - O cliente_id é lido da sessão (definido pelo ClienteAreaService::criarSessaoShield)
 *   - Todos os dados são filtrados por esse cliente_id
 *   - Mensagens são sempre enviadas para processos.responsavel_id
 *
 * @version 1.0.0
 * @since   2026-05-31
 */
class ClienteArea extends BaseController
{
    use ResponseTrait;

    protected ClienteAreaService $clienteAreaService;

    /**
     * Construtor com injeção de dependência.
     *
     * @param ClienteAreaService|null $clienteAreaService
     */
    public function __construct(?ClienteAreaService $clienteAreaService = null)
    {
        $this->clienteAreaService = $clienteAreaService ?? new ClienteAreaService();
    }

    // =========================================================================
    // PERFIL
    // =========================================================================

    /**
     * Retorna os dados do perfil do cliente logado.
     *
     * GET /api/v2/cliente/perfil
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function perfil(): \CodeIgniter\HTTP\ResponseInterface
    {
        $clienteId = (int) session()->get('cliente_id');
        $cliente   = $this->clienteAreaService->getById($clienteId);

        if (!$cliente) {
            return $this->respond([
                'success' => false,
                'message' => 'Perfil não encontrado.',
            ], 404);
        }

        return $this->respond([
            'success' => true,
            'data'    => [
                'id_cliente'   => $cliente['id_cliente'],
                'nome'         => $cliente['nome'] ?? $cliente['razao_social'] ?? '',
                'email'        => $cliente['email'],
                'telefone'     => $cliente['telefone'] ?? null,
                'tipo_cliente' => $cliente['tipo_cliente'] ?? null,
                'documento'    => $cliente['documento'] ?? null,
                'cidade'       => $cliente['cidade'] ?? null,
                'uf'           => $cliente['uf'] ?? null,
            ],
        ]);
    }

    // =========================================================================
    // PROCESSOS
    // =========================================================================

    /**
     * Lista todos os processos do cliente logado.
     *
     * GET /api/v2/cliente/processos
     * Escopo: processos.cliente_id = cliente_id da sessão
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function processos(): \CodeIgniter\HTTP\ResponseInterface
    {
        $clienteId = (int) session()->get('cliente_id');

        try {
            $processos = $this->clienteAreaService->listarProcessos($clienteId);

            return $this->respond([
                'success' => true,
                'total'   => count($processos),
                'data'    => $processos,
            ]);
        } catch (Exception $e) {
            log_message('error', '[ClienteArea::processos] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar processos.',
            ], 500);
        }
    }

    /**
     * Retorna os detalhes de um processo específico do cliente.
     *
     * GET /api/v2/cliente/processos/(:num)
     * Valida que processos.cliente_id = cliente_id da sessão (403 se diferente).
     *
     * @param int $processoId ID do processo.
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function processo(int $processoId): \CodeIgniter\HTTP\ResponseInterface
    {
        $clienteId = (int) session()->get('cliente_id');

        try {
            $detalhes = $this->clienteAreaService->detalharProcesso($clienteId, $processoId);

            if (!$detalhes) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Processo não encontrado ou sem permissão de acesso.',
                ], 404);
            }

            return $this->respond([
                'success' => true,
                'data'    => $detalhes,
            ]);
        } catch (Exception $e) {
            log_message('error', '[ClienteArea::processo] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar detalhes do processo.',
            ], 500);
        }
    }

    // =========================================================================
    // TIMELINE
    // =========================================================================

    /**
     * Retorna a linha do tempo simplificada de um processo do cliente.
     *
     * GET /api/v2/site/cliente/processos/(:num)/timeline
     *
     * @param int $processoId ID do processo.
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function timeline(int $processoId): \CodeIgniter\HTTP\ResponseInterface
    {
        $clienteId = (int) session()->get('cliente_id');

        try {
            $timeline = $this->clienteAreaService->timelineProcesso($clienteId, $processoId);

            if (!$timeline) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Processo não encontrado ou sem permissão de acesso.',
                ], 404);
            }

            return $this->respond([
                'success' => true,
                'data'    => $timeline,
            ]);
        } catch (Exception $e) {
            log_message('error', '[ClienteArea::timeline] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar a linha do tempo do processo.',
            ], 500);
        }
    }

    // =========================================================================
    // MENSAGENS
    // =========================================================================

    /**
     * Lista mensagens trocadas com o advogado responsável do processo.
     *
     * GET /api/v2/cliente/processos/(:num)/mensagens
     *
     * @param int $processoId ID do processo.
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function mensagens(int $processoId): \CodeIgniter\HTTP\ResponseInterface
    {
        $clienteId = (int) session()->get('cliente_id');

        try {
            $mensagens = $this->clienteAreaService->listarMensagens($clienteId, $processoId);

            return $this->respond([
                'success' => true,
                'total'   => count($mensagens),
                'data'    => $mensagens,
            ]);
        } catch (Exception $e) {
            log_message('error', '[ClienteArea::mensagens] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao carregar mensagens.',
            ], 500);
        }
    }

    /**
     * Envia uma mensagem ao advogado responsável do processo.
     *
     * POST /api/v2/cliente/processos/(:num)/mensagens
     * Body: { assunto: string, mensagem: string }
     * Destinatário: sempre processos.responsavel_id
     *
     * @param int $processoId ID do processo.
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function enviarMensagem(int $processoId): \CodeIgniter\HTTP\ResponseInterface
    {
        $clienteId = (int) session()->get('cliente_id');
        $data      = $this->request->getJSON(true) ?? $this->request->getPost();

        $rules = [
            'assunto'  => 'required|max_length[255]',
            'mensagem' => 'required|max_length[5000]',
        ];

        $validation = \Config\Services::validation();
        $validation->setRules($rules);

        if (!$validation->run((array) $data)) {
            return $this->respond([
                'success' => false,
                'message' => 'Dados inválidos.',
                'errors'  => $validation->getErrors(),
            ], 400);
        }

        try {
            $enviada = $this->clienteAreaService->enviarMensagem(
                $clienteId,
                $processoId,
                (string) ($data['assunto'] ?? ''),
                (string) ($data['mensagem'] ?? ''),
            );

            if ($enviada) {
                return $this->respond([
                    'success' => true,
                    'message' => 'Mensagem enviada com sucesso. O advogado responsável foi notificado.',
                ]);
            }

            return $this->respond([
                'success' => false,
                'message' => 'Não foi possível enviar a mensagem. Tente novamente.',
            ], 500);
        } catch (Exception $e) {
            log_message('error', '[ClienteArea::enviarMensagem] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
