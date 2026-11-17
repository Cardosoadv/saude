<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Models\Mcp\OAuthClientModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Mcp as McpConfig;
use Exception;

/**
 * Cadastro manual de clientes OAuth para o endpoint /mcp — para conectores
 * que pedem Client ID/Secret prontos em vez de se autorregistrarem em
 * /oauth/register (Dynamic Client Registration), como o conector
 * personalizado do Gemini.
 *
 * O cliente é confidencial (sempre tem client_secret), então pode dispensar
 * PKCE — ver App\Controllers\Mcp\OAuthController::validarPkce(). O fluxo de
 * login/consentimento e o token emitido são os mesmos do Claude.ai: quem
 * autoriza é o usuário logado na tela de consentimento, não quem cadastrou o
 * cliente.
 *
 * Cada usuário só vê/remove os clientes que ele mesmo cadastrou.
 */
class McpClientesOAuth extends BaseController
{
    use ResponseTrait;

    public function listar(): ResponseInterface
    {
        $clientes = model(OAuthClientModel::class)
            ->where('usuario_id', (int) auth()->id())
            ->orderBy('created_at', 'DESC')
            ->findAll();

        $data = array_map(fn (array $c) => [
            'id'           => (int) $c['id'],
            'nome'         => $c['client_name'],
            'client_id'    => $c['client_id'],
            'redirect_uri' => (json_decode((string) $c['redirect_uris'], true) ?: [''])[0],
            'criado_em'    => $c['created_at'],
        ], $clientes);

        return $this->respond(['success' => true, 'data' => $data, 'endpoints' => $this->endpoints()]);
    }

    /**
     * Cria o cliente e devolve o client_secret bruto — só nesta resposta;
     * o banco guarda apenas o hash.
     */
    public function criar(): ResponseInterface
    {
        $dados       = $this->request->getJSON(true) ?? [];
        $nome        = substr(trim((string) ($dados['nome'] ?? '')), 0, 255);
        $redirectUri = trim((string) ($dados['redirect_uri'] ?? ''));

        if ($nome === '') {
            return $this->respond(['success' => false, 'message' => 'Informe um nome para o conector (ex.: "Gemini").'], 400);
        }

        if (strlen($redirectUri) > 500 || !OAuthClientModel::redirectUriPermitida($redirectUri)) {
            return $this->respond([
                'success' => false,
                'message' => 'Informe a URL de redirecionamento (callback) exibida pelo conector. Ela precisa usar https.',
            ], 400);
        }

        $clientId     = bin2hex(random_bytes(16));
        $clientSecret = bin2hex(random_bytes(32));

        try {
            model(OAuthClientModel::class)->insert([
                'client_id'                  => $clientId,
                'client_secret_hash'         => hash('sha256', $clientSecret),
                'client_name'                => $nome,
                'redirect_uris'              => json_encode([$redirectUri]),
                'token_endpoint_auth_method' => 'client_secret_basic',
                'usuario_id'                 => (int) auth()->id(),
                'created_at'                 => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            log_message('error', 'Erro ao cadastrar cliente OAuth (MCP): ' . $e->getMessage());

            return $this->respond(['success' => false, 'message' => 'Erro ao cadastrar o conector.'], 500);
        }

        return $this->respond([
            'success' => true,
            'data'    => [
                'nome'          => $nome,
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri'  => $redirectUri,
            ] + $this->endpoints(),
            'message' => 'Copie o Client Secret agora — ele não será exibido novamente.',
        ], 201);
    }

    /**
     * Remove o cliente (e, por cascata, os códigos pendentes). Tokens já
     * emitidos por ele são Personal Access Tokens comuns e continuam
     * aparecendo — e podendo ser revogados — na lista de tokens de IA.
     */
    public function excluir(int $id): ResponseInterface
    {
        $model   = model(OAuthClientModel::class);
        $cliente = $model->where('id', $id)->where('usuario_id', (int) auth()->id())->first();

        if ($cliente === null) {
            return $this->respond(['success' => false, 'message' => 'Conector não encontrado.'], 404);
        }

        $model->delete($id);

        return $this->respond(['success' => true, 'message' => 'Conector removido.']);
    }

    /**
     * URLs e escopo que o usuário cola na tela do conector.
     */
    private function endpoints(): array
    {
        $baseUrl = rtrim(site_url(), '/');

        return [
            'mcp_url'           => $baseUrl . '/mcp',
            'authorization_url' => $baseUrl . '/oauth/authorize',
            'token_url'         => $baseUrl . '/oauth/token',
            'scope'             => config(McpConfig::class)->tokenScope,
        ];
    }
}
