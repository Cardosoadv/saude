<?php

declare(strict_types=1);

namespace App\Filters;

use App\Mcp\Support\AtorMcp;
use App\Mcp\Support\McpContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\AccessTokens;
use Config\Mcp as McpConfig;

/**
 * Protege o endpoint /mcp, aceitando dois tipos de credencial no header
 * `Authorization: Bearer <token>`:
 *
 *  1. O token de serviço interno (mcp.internalToken) — usado pela IA
 *     interna do sistema (GeminiService/Davinci). Nenhum usuário é
 *     autenticado; cada Tool de escrita recebe `usuarioId` explícito e
 *     confia no chamador (ver App\Mcp\Support\AtorMcp).
 *  2. Um Personal Access Token do Shield com escopo `mcp`. Chega aqui de
 *     duas formas equivalentes: gerado manualmente pelo usuário em suas
 *     configurações (App\Controllers\ApiV2\McpTokens::gerar(), para
 *     Claude Desktop/ChatGPT/outros clientes sem suporte a OAuth) ou
 *     emitido automaticamente pelo fluxo OAuth 2.1 de
 *     App\Controllers\Mcp\OAuthController (usado pelo "conector
 *     personalizado" do Claude.ai). Em ambos os casos autentica o usuário
 *     normalmente (auth('tokens')) e passa a exigir que toda ação seja
 *     feita em nome dele — nunca de um usuarioId inventado pelo modelo
 *     (ver App\Mcp\Support\McpContext).
 *
 * Nos erros 401/403, o header `WWW-Authenticate` aponta para o Protected
 * Resource Metadata (RFC 9728) em /.well-known/oauth-protected-resource,
 * que por sua vez leva a este mesmo servidor como Authorization Server
 * (App\Controllers\Mcp\OAuthController) — é assim que um cliente OAuth
 * como o conector do Claude.ai descobre para onde ir quando ainda não tem
 * um token.
 */
class McpAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        /** @var McpConfig $config */
        $config = config(McpConfig::class);

        if (empty($config->internalToken)) {
            return $this->responderErro(500, 'Token interno do MCP não configurado (mcp.internalToken no .env).');
        }

        $header = (string) $request->getHeaderLine('Authorization');

        log_message('debug', '[MCP DEBUG] {method} {path} — Authorization presente: {presente}, tamanho: {tam}', [
            'method'   => $request->getMethod(),
            'path'     => $request->getPath(),
            'presente' => $header === '' ? 'não' : 'sim',
            'tam'      => strlen($header),
        ]);

        if ($header === '') {
            return $this->responderErro(401, 'Token de acesso ao MCP ausente.');
        }

        if (hash_equals('Bearer ' . $config->internalToken, $header)) {
            McpContext::iniciarChamadaInterna();

            // O agente interno informa em nome de quem pergunta (só confiável junto
            // com o token interno), para que as leituras sigam a privacidade granular
            // desse usuário em vez de enxergarem tudo como contexto de sistema.
            $usuarioInterno = $request->getHeaderLine('X-Mcp-Usuario');
            if (ctype_digit($usuarioInterno) && (int) $usuarioInterno > 0) {
                AtorMcp::assumir((int) $usuarioInterno);
            }

            return;
        }

        return $this->autenticarTokenExterno($header, $config);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Sem ação pós-requisição necessária.
    }

    private function autenticarTokenExterno(string $header, McpConfig $config): ?ResponseInterface
    {
        /** @var AccessTokens $authenticator */
        $authenticator = auth('tokens')->getAuthenticator();

        $resultado = $authenticator->attempt(['token' => $header]);

        log_message('debug', '[MCP DEBUG] attempt() isOK: {ok}, motivo: {motivo}', [
            'ok'     => $resultado->isOK() ? 'sim' : 'não',
            'motivo' => $resultado->reason() ?: '(nenhum)',
        ]);

        if (!$resultado->isOK()) {
            return $this->responderErro(401, 'Token de acesso ao MCP inválido, expirado ou revogado.');
        }

        $usuario = $authenticator->getUser();

        if ($usuario === null || $usuario->tokenCant($config->tokenScope)) {
            log_message('debug', '[MCP DEBUG] usuario null: {nulo}, escopos do token: {escopos}', [
                'nulo'    => $usuario === null ? 'sim' : 'não',
                'escopos' => $usuario === null ? '?' : json_encode($usuario->currentAccessToken()?->scopes ?? []),
            ]);

            return $this->responderErro(403, "Este token não possui o escopo '{$config->tokenScope}' necessário para uso com IA.");
        }

        // Assume a sessão Shield do usuário aqui, não só nas Tools de
        // escrita: várias Services/Repositories usados por Tools de leitura
        // (ex.: tarefas de um cliente) dependem de auth()->id() para
        // filtrar o que pertence a esse usuário. Sem isso, um chat de IA
        // externo via token pessoal via `obter_cliente`
        // enxergaria menos dados do que o próprio usuário veria logado no
        // sistema.
        AtorMcp::assumir((int) $usuario->id);

        McpContext::iniciarChamadaExterna((int) $usuario->id);

        return null;
    }

    private function responderErro(int $status, string $mensagem): ResponseInterface
    {
        $response = service('response')
            ->setStatusCode($status)
            ->setContentType('application/json')
            ->setBody(json_encode(['success' => false, 'error' => $mensagem]));

        if ($status === 401 || $status === 403) {
            $response = $response->setHeader('WWW-Authenticate', $this->wwwAuthenticateHeader());
        }

        return $response;
    }

    /**
     * Aponta sempre para a URL pública do site (nunca para `mcp.baseUrl`,
     * que é só um atalho de loopback para chamadas internas) — é essa a
     * URL que um cliente OAuth externo (o conector do Claude.ai, rodando
     * no navegador/servidor do usuário) precisa conseguir alcançar.
     */
    private function wwwAuthenticateHeader(): string
    {
        $baseUrl = rtrim(site_url(), '/');

        return 'Bearer resource_metadata="' . $baseUrl . '/.well-known/oauth-protected-resource"';
    }
}
