<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\ClienteAreaService;
use CodeIgniter\API\ResponseTrait;
use Exception;

/**
 * Controller de Autenticação da Área do Cliente (Magic Link).
 *
 * Endpoints públicos (sem filtro de auth):
 *   POST /api/v2/cliente/auth/solicitar → Envia magic link por email
 *   POST /api/v2/cliente/auth/verificar → Valida token e cria sessão Shield
 *   POST /api/v2/cliente/auth/logout    → Encerra sessão Shield
 *
 * @version 1.0.1
 * @since   2026-05-31
 */
class ClienteAuth extends BaseController
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

    /**
     * Solicita o envio de um magic link para o email informado.
     *
     * Aceita JSON ou form-data. Sempre retorna 200 genérico para
     * não revelar se o email está ou não cadastrado (evita user enumeration).
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function solicitarLink(): \CodeIgniter\HTTP\ResponseInterface
    {
        $throttler = service('throttler');
        $ipAddress = $this->request->getIPAddress();

        if ($throttler->check('cliente-magiclink-ip-' . md5($ipAddress), 10, MINUTE) === false) {
            return $this->respond([
                'success' => false,
                'message' => 'Muitas solicitações. Tente novamente em instantes.',
            ], 429);
        }

        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        $rules = [
            'email' => 'required|valid_email|max_length[150]',
        ];

        $validation = \Config\Services::validation();
        $validation->setRules($rules);

        if (!$validation->run((array) $data)) {
            return $this->respond([
                'success' => false,
                'message' => 'Informe um endereço de email válido.',
            ], 400);
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if ($throttler->check('cliente-magiclink-email-' . md5($email), 5, MINUTE) === false) {
            return $this->respond([
                'success' => true,
                'message' => 'Se o email informado estiver cadastrado, você receberá um link de acesso em instantes.',
            ]);
        }

        // O service sempre retorna true (resposta genérica de segurança)
        $this->clienteAreaService->solicitarAcesso($email);

        return $this->respond([
            'success' => true,
            'message' => 'Se o email informado estiver cadastrado, você receberá um link de acesso em instantes.',
        ]);
    }

    /**
     * Verifica o token recebido via URL e cria sessão Shield com grupo 'cliente'.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function verificarToken(): \CodeIgniter\HTTP\ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        $token = trim((string) ($data['token'] ?? ''));

        if (empty($token)) {
            return $this->respond([
                'success' => false,
                'message' => 'Token de acesso não informado.',
            ], 400);
        }

        try {
            $cliente = $this->clienteAreaService->autenticarToken($token);

            if (!$cliente) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Link de acesso inválido ou expirado. Solicite um novo link.',
                ], 401);
            }

            return $this->respond([
                'success' => true,
                'message' => 'Acesso autorizado.',
                'cliente' => [
                    'nome'  => $cliente['nome'] ?? ($cliente['razao_social'] ?? 'Cliente'),
                    'email' => $cliente['email'] ?? '',
                ],
            ]);
        } catch (Exception $e) {
            log_message('error', '[ClienteAuth::verificarToken] ' . $e->getMessage());
            return $this->respond([
                'success' => false,
                'message' => 'Erro ao processar autenticação. Tente novamente.',
            ], 500);
        }
    }

    /**
     * Encerra a sessão Shield do cliente.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function logout(): \CodeIgniter\HTTP\ResponseInterface
    {
        if (auth()->loggedIn()) {
            session()->remove('cliente_id');
            auth()->logout();
        }

        return $this->respond([
            'success' => true,
            'message' => 'Sessão encerrada com sucesso.',
        ]);
    }
}
