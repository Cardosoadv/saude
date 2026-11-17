<?php

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\ConfiguracoesService;
use CodeIgniter\HTTP\RedirectResponse;
use Google\Client;
use Google\Service\Drive;

/**
 * Controlador para autenticação com o Google.
 *
 * Este controlador gerencia o fluxo de autenticação OAuth2 com o Google
 * para obter tokens de acesso e de atualização (refresh token).
 *
 * @version 1.1.0
 */
class GoogleAuth extends BaseController
{
    /**
     * @var ConfiguracoesService
     */
    protected $configService;

    public function __construct(?ConfiguracoesService $configService = null)
    {
        $this->configService = $configService ?? new ConfiguracoesService();
    }

    /**
     * Inicia o processo de login com o Google.
     *
     * @return string|RedirectResponse
     */
    public function login()
    {
        if (!auth()->user()->can('admin.access') && !auth()->user()->can('admin.settings')) {
            return service('response')->setStatusCode(403)->setBody('Você não tem permissão para conectar a integração com o Google.');
        }

        $clientId = $this->configService->getValor('GOOGLE_CLIENT_ID');
        $clientSecret = $this->configService->getValor('GOOGLE_CLIENT_SECRET');

        if (!$clientId || !$clientSecret) {
            return "Please set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in your database configurations.";
        }

        $state = bin2hex(random_bytes(32));
        session()->set('google_auth_state', $state);

        $client = new Client();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri(base_url('api/v2/google-auth/callback'));
        $client->addScope(Drive::DRIVE_FILE);
        $client->addScope(Drive::DRIVE_READONLY); // lista arquivos adicionados direto no Drive
        $client->addScope(\Google\Service\Calendar::CALENDAR);
        $client->setAccessType('offline');
        $client->setPrompt('consent'); // Force approval prompt to ensure refresh token is returned
        $client->setState($state);

        return redirect()->to($client->createAuthUrl());
    }

    /**
     * Callback para receber o código de autorização do Google.
     *
     * @return string
     */
    public function callback()
    {
        if (!auth()->user()->can('admin.access') && !auth()->user()->can('admin.settings')) {
            return service('response')->setStatusCode(403)->setBody('Você não tem permissão para conectar a integração com o Google.');
        }

        $clientId = $this->configService->getValor('GOOGLE_CLIENT_ID');
        $clientSecret = $this->configService->getValor('GOOGLE_CLIENT_SECRET');
        $code = $this->request->getGet('code');
        $state = $this->request->getGet('state');
        $expectedState = session()->get('google_auth_state');
        session()->remove('google_auth_state');

        if (!$state || !$expectedState || !hash_equals($expectedState, (string) $state)) {
            $frontUrl = config('App')->frontURL . 'configuracoes?google_auth=error&error_desc=' . urlencode("Estado de autorização inválido ou expirado.");
            return redirect()->to($frontUrl);
        }

        if (!$code) {
            $frontUrl = config('App')->frontURL . 'configuracoes?google_auth=error&error_desc=' . urlencode("Código de autorização não retornado pelo Google.");
            return redirect()->to($frontUrl);
        }

        $client = new Client();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri(base_url('api/v2/google-auth/callback'));

        try {
            $token = $client->fetchAccessTokenWithAuthCode((string) $code);

            if (isset($token['error'])) {
                $errorMsg = $token['error_description'] ?? $token['error'];
                $frontUrl = config('App')->frontURL . 'configuracoes?google_auth=error&error_desc=' . urlencode("Erro ao obter token: " . $errorMsg);
                return redirect()->to($frontUrl);
            }

            $refreshToken = $token['refresh_token'] ?? null;

            if ($refreshToken) {
                $config = $this->configService->findByNome('GOOGLE_REFRESH_TOKEN');
                if ($config) {
                    if ($this->configService->update($config['id'], ['valor' => $refreshToken])) {
                        $frontUrl = config('App')->frontURL . 'configuracoes?google_auth=success';
                        return redirect()->to($frontUrl);
                    } else {
                        $error = "Falha ao salvar o Refresh Token no banco de dados.";
                    }
                } else {
                    $error = "Configuração GOOGLE_REFRESH_TOKEN não encontrada no banco de dados.";
                }
            } else {
                $error = "Nenhum refresh token foi retornado pelo Google. Remova o acesso do sistema na sua Conta Google e tente novamente.";
            }

            $frontUrl = config('App')->frontURL . 'configuracoes?google_auth=error&error_desc=' . urlencode($error);
            return redirect()->to($frontUrl);
        } catch (\Exception $e) {
            $frontUrl = config('App')->frontURL . 'configuracoes?google_auth=error&error_desc=' . urlencode("Exceção: " . $e->getMessage());
            return redirect()->to($frontUrl);
        }
    }
}
