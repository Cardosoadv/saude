<?php

declare(strict_types=1);

namespace App\Controllers\Mcp;

use App\Controllers\BaseController;
use App\Models\Mcp\OAuthClientModel;
use App\Models\Mcp\OAuthCodeModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Models\UserModel;
use Config\Mcp as McpConfig;
use Mcp\Server\Transport\Http\OAuth\ProtectedResourceMetadata;

/**
 * Authorization Server mínimo (OAuth 2.1 + PKCE + Dynamic Client
 * Registration) que permite ao endpoint /mcp funcionar como "conector
 * personalizado" no Claude.ai e em outros clientes MCP compatíveis com
 * OAuth.
 *
 * O SDK MCP (mcp/sdk) é deliberadamente apenas um Resource Server — ver
 * adr/0001-oauth-authorization-server-out-of-scope.md no próprio pacote.
 * Este controlador implementa o restante do fluxo reaproveitando a sessão
 * Shield já existente para login/consentimento, em vez de depender de um
 * IdP externo (Keycloak/Auth0/Entra):
 *
 *   1. /.well-known/oauth-protected-resource  (RFC 9728) e
 *      /.well-known/oauth-authorization-server (RFC 8414) — descoberta.
 *   2. /oauth/register (RFC 7591) — o cliente (Claude) se autorregistra na
 *      primeira conexão, sem o usuário precisar colar Client ID/Secret na
 *      tela "Adicionar conector personalizado".
 *   3. /oauth/authorize (RFC 6749 + PKCE/RFC 7636) — exige sessão Shield
 *      ativa e a confirmação explícita do usuário (tela de consentimento).
 *   4. /oauth/token — troca o código por um Personal Access Token comum do
 *      Shield, escopo `mcp`: o mesmo tipo de token que
 *      App\Controllers\ApiV2\McpTokens já gera manualmente. Por isso
 *      McpAuthFilter não precisa de nenhuma lógica nova para aceitá-lo.
 */
class OAuthController extends BaseController
{
    protected $helpers = ['security'];

    /**
     * RFC 9728 — indica onde está o Authorization Server responsável pelo
     * recurso /mcp. É para cá que aponta o `WWW-Authenticate` que
     * McpAuthFilter devolve quando o cliente ainda não tem um token válido.
     */
    public function protectedResourceMetadata(): ResponseInterface
    {
        $baseUrl = rtrim(site_url(), '/');

        $metadata = new ProtectedResourceMetadata(
            authorizationServers: [$baseUrl],
            scopesSupported: [config(McpConfig::class)->tokenScope],
            resource: $baseUrl . '/mcp',
            resourceName: 'Saúde',
        );

        return $this->response
            ->setStatusCode(200)
            ->setContentType('application/json')
            ->setHeader('Cache-Control', 'max-age=3600')
            ->setBody(json_encode($metadata, JSON_UNESCAPED_SLASHES));
    }

    /**
     * RFC 8414 — anuncia os próprios endpoints /oauth/* deste controlador.
     */
    public function authorizationServerMetadata(): ResponseInterface
    {
        $baseUrl = rtrim(site_url(), '/');

        $metadata = [
            'issuer'                                 => $baseUrl,
            'authorization_endpoint'                 => $baseUrl . '/oauth/authorize',
            'token_endpoint'                         => $baseUrl . '/oauth/token',
            'registration_endpoint'                  => $baseUrl . '/oauth/register',
            'scopes_supported'                       => [config(McpConfig::class)->tokenScope],
            'response_types_supported'               => ['code'],
            'grant_types_supported'                  => ['authorization_code'],
            'code_challenge_methods_supported'       => ['S256'],
            'token_endpoint_auth_methods_supported'  => ['client_secret_basic', 'client_secret_post', 'none'],
        ];

        return $this->response
            ->setStatusCode(200)
            ->setContentType('application/json')
            ->setHeader('Cache-Control', 'max-age=3600')
            ->setBody(json_encode($metadata, JSON_UNESCAPED_SLASHES));
    }

    /**
     * RFC 7591 — Dynamic Client Registration. O Claude chama isto sozinho
     * na primeira conexão, sem o usuário precisar preencher Client
     * ID/Secret na tela "Adicionar conector personalizado".
     */
    public function register(): ResponseInterface
    {
        $contentType = $this->request->getHeaderLine('Content-Type');
        if (stripos($contentType, 'application/json') !== 0) {
            return $this->erroJson(400, 'invalid_client_metadata', 'Content-Type deve ser application/json.');
        }

        $dados = json_decode((string) $this->request->getBody(), true);
        if (!is_array($dados)) {
            return $this->erroJson(400, 'invalid_client_metadata', 'Corpo da requisição deve ser um JSON válido.');
        }

        $redirectUris = $dados['redirect_uris'] ?? null;
        if (!is_array($redirectUris) || $redirectUris === []) {
            return $this->erroJson(400, 'invalid_redirect_uri', 'É necessário informar ao menos um redirect_uri.');
        }

        foreach ($redirectUris as $uri) {
            if (!is_string($uri) || !OAuthClientModel::redirectUriPermitida($uri)) {
                return $this->erroJson(400, 'invalid_redirect_uri', 'redirect_uri inválido (exige https, exceto em localhost).');
            }
        }

        $authMethod = $dados['token_endpoint_auth_method'] ?? 'client_secret_post';
        if (!in_array($authMethod, ['client_secret_basic', 'client_secret_post', 'none'], true)) {
            $authMethod = 'client_secret_post';
        }

        $clientId         = bin2hex(random_bytes(16));
        $clientSecret     = null;
        $clientSecretHash = null;

        if ($authMethod !== 'none') {
            $clientSecret     = bin2hex(random_bytes(32));
            $clientSecretHash = hash('sha256', $clientSecret);
        }

        $clientName = isset($dados['client_name']) && is_string($dados['client_name'])
            ? substr(trim($dados['client_name']), 0, 255)
            : null;

        model(OAuthClientModel::class)->insert([
            'client_id'                  => $clientId,
            'client_secret_hash'         => $clientSecretHash,
            'client_name'                => $clientName,
            'redirect_uris'              => json_encode(array_values($redirectUris)),
            'token_endpoint_auth_method' => $authMethod,
            'created_at'                 => date('Y-m-d H:i:s'),
        ]);

        $resposta = [
            'client_id'                  => $clientId,
            'client_id_issued_at'        => time(),
            'client_name'                => $clientName,
            'redirect_uris'              => array_values($redirectUris),
            'token_endpoint_auth_method' => $authMethod,
            'grant_types'                => ['authorization_code'],
            'response_types'             => ['code'],
        ];

        if ($clientSecret !== null) {
            $resposta['client_secret'] = $clientSecret;
        }

        return $this->response
            ->setStatusCode(201)
            ->setContentType('application/json')
            ->setHeader('Cache-Control', 'no-store')
            ->setBody(json_encode($resposta, JSON_UNESCAPED_SLASHES));
    }

    /**
     * GET /oauth/authorize — exige login Shield (preservando a URL de
     * retorno, do mesmo jeito que o próprio Shield faz) e então mostra a
     * tela de consentimento.
     */
    public function authorize()
    {
        $clientId            = (string) $this->request->getGet('client_id');
        $redirectUri         = (string) $this->request->getGet('redirect_uri');
        $responseType        = (string) $this->request->getGet('response_type');
        $codeChallenge       = (string) $this->request->getGet('code_challenge');
        $codeChallengeMethod = (string) ($this->request->getGet('code_challenge_method') ?? 'S256');
        $state               = $this->request->getGet('state');

        $client = model(OAuthClientModel::class)->where('client_id', $clientId)->first();

        log_message('debug', '[MCP DEBUG] GET /oauth/authorize — client_id: {cid}, encontrado: {found}, redirect_uri: {ru}, response_type: {rt}', [
            'cid'   => $clientId,
            'found' => $client === null ? 'não' : 'sim',
            'ru'    => $redirectUri,
            'rt'    => $responseType,
        ]);

        if ($client === null) {
            return $this->paginaErro('Cliente OAuth desconhecido. Peça para reconectar o conector a partir do Claude.');
        }

        $redirectUris = json_decode((string) $client['redirect_uris'], true) ?: [];
        if (!in_array($redirectUri, $redirectUris, true)) {
            return $this->paginaErro('O redirect_uri informado não corresponde ao cadastrado para este cliente.');
        }

        if ($responseType !== 'code') {
            return $this->redirecionarComErro($redirectUri, $state, 'unsupported_response_type', 'Apenas response_type=code é suportado.');
        }

        $erroPkce = $this->validarPkce($client, $codeChallenge, $codeChallengeMethod);
        if ($erroPkce !== null) {
            return $this->redirecionarComErro($redirectUri, $state, 'invalid_request', $erroPkce);
        }

        if (!auth()->loggedIn()) {
            session()->setTempdata('beforeLoginUrl', current_url(true), 300);

            return redirect()->route('login');
        }

        return view('Mcp/authorize', [
            'client'              => $client,
            'clientId'            => $clientId,
            'redirectUri'         => $redirectUri,
            'state'               => $state,
            'codeChallenge'       => $codeChallenge,
            'codeChallengeMethod' => $codeChallengeMethod,
            'usuario'             => auth()->user(),
            'escopo'              => config(McpConfig::class)->tokenScope,
        ]);
    }

    /**
     * POST /oauth/authorize — processa a decisão (permitir/negar) da tela
     * de consentimento e, se aprovado, emite um código de autorização de
     * uso único.
     */
    public function consent()
    {
        if (!auth()->loggedIn()) {
            return $this->paginaErro('Sessão expirada. Reinicie o processo de autorização a partir do Claude.');
        }

        $clientId            = (string) $this->request->getPost('client_id');
        $redirectUri         = (string) $this->request->getPost('redirect_uri');
        $state               = $this->request->getPost('state');
        $codeChallenge       = (string) $this->request->getPost('code_challenge');
        $codeChallengeMethod = (string) ($this->request->getPost('code_challenge_method') ?: 'S256');
        $decisao             = (string) $this->request->getPost('decisao');

        $client = model(OAuthClientModel::class)->where('client_id', $clientId)->first();

        if ($client === null) {
            return $this->paginaErro('Cliente OAuth desconhecido.');
        }

        $redirectUris = json_decode((string) $client['redirect_uris'], true) ?: [];
        if (!in_array($redirectUri, $redirectUris, true)) {
            return $this->paginaErro('O redirect_uri informado não corresponde ao cadastrado para este cliente.');
        }

        if ($decisao !== 'permitir') {
            return $this->redirecionarComErro($redirectUri, $state, 'access_denied', 'O usuário negou a autorização.');
        }

        // Revalida aqui: o formulário de consentimento pode ter sido adulterado.
        $erroPkce = $this->validarPkce($client, $codeChallenge, $codeChallengeMethod);
        if ($erroPkce !== null) {
            return $this->redirecionarComErro($redirectUri, $state, 'invalid_request', $erroPkce);
        }

        $config      = config(McpConfig::class);
        $codigoBruto = bin2hex(random_bytes(32));

        model(OAuthCodeModel::class)->insert([
            'code_hash'             => hash('sha256', $codigoBruto),
            'client_id'             => $clientId,
            'usuario_id'            => (int) auth()->user()->id,
            'redirect_uri'          => $redirectUri,
            'code_challenge'        => $codeChallenge !== '' ? $codeChallenge : null,
            'code_challenge_method' => 'S256',
            'scope'                 => $config->tokenScope,
            'expires_at'            => date('Y-m-d H:i:s', time() + $config->authorizationCodeTtl),
            'created_at'            => date('Y-m-d H:i:s'),
        ]);

        $query = ['code' => $codigoBruto];
        if ($state !== null) {
            $query['state'] = $state;
        }

        return redirect()->to($redirectUri . (str_contains($redirectUri, '?') ? '&' : '?') . http_build_query($query));
    }

    /**
     * POST /oauth/token — troca o código de autorização (validando PKCE)
     * por um Personal Access Token do Shield, escopo `mcp`.
     */
    public function token(): ResponseInterface
    {
        $params = $this->request->getPost();
        if ($params === []) {
            $decodedJson = json_decode((string) $this->request->getBody(), true);
            $params      = is_array($decodedJson) ? $decodedJson : [];
        }

        if (($params['grant_type'] ?? null) !== 'authorization_code') {
            return $this->erroJson(400, 'unsupported_grant_type', 'Apenas grant_type=authorization_code é suportado.');
        }

        $code         = (string) ($params['code'] ?? '');
        $redirectUri  = (string) ($params['redirect_uri'] ?? '');
        $codeVerifier = (string) ($params['code_verifier'] ?? '');
        $clientId     = (string) ($params['client_id'] ?? '');
        $clientSecret = $params['client_secret'] ?? null;

        // client_secret_basic: clientes como o Gemini mandam as credenciais no
        // header e podem repetir o client_id no corpo.
        $basic = $this->extrairBasicAuth();
        if ($basic !== null && ($clientId === '' || $clientId === $basic[0])) {
            [$clientId, $clientSecret] = $basic;
        }

        log_message('debug', '[MCP DEBUG] POST /oauth/token — client_id: {cid}, tem_secret: {secret}, code: {code}, redirect_uri: {ru}', [
            'cid'    => $clientId ?: '(vazio)',
            'secret' => $clientSecret !== null ? 'sim' : 'não',
            'code'   => $code === '' ? '(vazio)' : substr($code, 0, 8) . '…',
            'ru'     => $redirectUri ?: '(vazio)',
        ]);

        if ($code === '' || $redirectUri === '' || $clientId === '') {
            return $this->erroJson(400, 'invalid_request', 'Parâmetros obrigatórios ausentes (code, redirect_uri, client_id).');
        }

        $client = model(OAuthClientModel::class)->where('client_id', $clientId)->first();
        if ($client === null) {
            return $this->erroJson(401, 'invalid_client', 'Cliente OAuth desconhecido.');
        }

        if ($client['client_secret_hash'] !== null) {
            if (!is_string($clientSecret) || !hash_equals($client['client_secret_hash'], hash('sha256', $clientSecret))) {
                return $this->erroJson(401, 'invalid_client', 'client_secret inválido.');
            }
        }

        $codeModel = model(OAuthCodeModel::class);
        $registro  = $codeModel->where('code_hash', hash('sha256', $code))->first();

        log_message('debug', '[MCP DEBUG] lookup do code — encontrado: {found}, used_at: {used}, client_id_bate: {bate}', [
            'found' => $registro === null ? 'não' : 'sim',
            'used'  => $registro['used_at'] ?? '(n/a)',
            'bate'  => $registro !== null ? ($registro['client_id'] === $clientId ? 'sim' : 'não') : '(n/a)',
        ]);

        if ($registro === null || $registro['used_at'] !== null || $registro['client_id'] !== $clientId) {
            return $this->erroJson(400, 'invalid_grant', 'Código de autorização inválido, expirado ou já utilizado.');
        }

        if (strtotime($registro['expires_at']) < time()) {
            return $this->erroJson(400, 'invalid_grant', 'Código de autorização expirado.');
        }

        if (!hash_equals($registro['redirect_uri'], $redirectUri)) {
            return $this->erroJson(400, 'invalid_grant', 'redirect_uri não corresponde ao usado em /oauth/authorize.');
        }

        // Sem code_challenge só chega aqui cliente confidencial, já autenticado acima.
        if ($registro['code_challenge'] !== null) {
            $desafioCalculado = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
            if ($codeVerifier === '' || !hash_equals($registro['code_challenge'], $desafioCalculado)) {
                return $this->erroJson(400, 'invalid_grant', 'code_verifier não corresponde ao code_challenge original (PKCE).');
            }
        }

        // Uso único: marca o código como usado antes de emitir qualquer token.
        $codeModel->update($registro['id'], ['used_at' => date('Y-m-d H:i:s')]);

        $usuario = model(UserModel::class)->find((int) $registro['usuario_id']);
        if ($usuario === null) {
            return $this->erroJson(400, 'invalid_grant', 'Usuário associado ao código não existe mais.');
        }

        $config      = config(McpConfig::class);
        $nomeToken   = ($client['client_name'] ?: 'Conector') . ' (OAuth)';
        $accessToken = $usuario->generateAccessToken($nomeToken, [$config->tokenScope]);

        return $this->response
            ->setStatusCode(200)
            ->setContentType('application/json')
            ->setHeader('Cache-Control', 'no-store')
            ->setBody(json_encode([
                'access_token' => $accessToken->raw_token,
                'token_type'   => 'Bearer',
                'scope'        => $config->tokenScope,
            ]));
    }

    /**
     * PKCE (S256) é obrigatório para clientes públicos (sem client_secret,
     * caso do Claude.ai via /oauth/register). Clientes confidenciais
     * cadastrados manualmente — o Gemini, que trata PKCE como opcional —
     * podem dispensá-lo, já que se autenticam com o segredo em /oauth/token.
     *
     * @return string|null Mensagem de erro, ou null se estiver tudo certo.
     */
    private function validarPkce(array $client, string $codeChallenge, string $codeChallengeMethod): ?string
    {
        if ($codeChallenge === '') {
            return $client['client_secret_hash'] === null
                ? 'PKCE (code_challenge com method S256) é obrigatório para clientes públicos.'
                : null;
        }

        return strtoupper($codeChallengeMethod) === 'S256'
            ? null
            : 'Apenas code_challenge_method=S256 é suportado.';
    }

    private function erroJson(int $status, string $erro, string $descricao): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setContentType('application/json')
            ->setHeader('Cache-Control', 'no-store')
            ->setBody(json_encode(['error' => $erro, 'error_description' => $descricao]));
    }

    private function paginaErro(string $mensagem)
    {
        return $this->response
            ->setStatusCode(400)
            ->setContentType('text/html')
            ->setBody('<h1>Erro de autorização</h1><p>' . esc($mensagem) . '</p>');
    }

    private function redirecionarComErro(string $redirectUri, ?string $state, string $erro, string $descricao)
    {
        $query = ['error' => $erro, 'error_description' => $descricao];
        if ($state !== null) {
            $query['state'] = $state;
        }

        return redirect()->to($redirectUri . (str_contains($redirectUri, '?') ? '&' : '?') . http_build_query($query));
    }

    /**
     * client_secret_basic (RFC 6749 §2.3.1) — ID e segredo vêm
     * form-urlencoded antes do Base64.
     */
    private function extrairBasicAuth(): ?array
    {
        $header = (string) $this->request->getHeaderLine('Authorization');
        if (stripos($header, 'Basic ') !== 0) {
            return null;
        }

        $decoded = base64_decode(trim(substr($header, 6)), true);
        if ($decoded === false || !str_contains($decoded, ':')) {
            return null;
        }

        return array_map('urldecode', explode(':', $decoded, 2));
    }
}
