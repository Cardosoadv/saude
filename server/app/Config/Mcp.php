<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuração do servidor e do cliente MCP do OrdoJuris.
 *
 * O endpoint /mcp serve dois tipos de chamador, ambos autenticados via
 * `Authorization: Bearer <token>`, diferenciados em McpAuthFilter:
 *
 *  - IA interna do sistema (GeminiService/Davinci): usa o token de serviço
 *    fixo `internalToken`, definido em .env. Não é um usuário Shield —
 *    identifica apenas "o próprio backend chamando a si mesmo".
 *  - Chats de IA externos (Claude Desktop, ChatGPT, o conector nativo do
 *    Claude.ai, etc.): cada usuário acaba com um Personal Access Token do
 *    Shield com escopo `tokenScope`, seja colado manualmente (gerado em
 *    App\Controllers\ApiV2\McpTokens::gerar(), para Claude Desktop/outros
 *    clientes sem suporte a OAuth) ou emitido automaticamente pelo fluxo
 *    OAuth 2.1 de App\Controllers\Mcp\OAuthController (usado pelo conector
 *    personalizado do Claude.ai). Em ambos os casos a IA passa a agir
 *    estritamente como aquele usuário (McpContext).
 */
class Mcp extends BaseConfig
{
    /**
     * Token de serviço exigido no header `Authorization: Bearer <token>`
     * para chamadas internas ao endpoint /mcp (IA interna do sistema).
     * Não é um token de usuário Shield — identifica apenas "este é o
     * próprio backend chamando a si mesmo", nunca um cliente externo.
     *
     * Definido em .env como: mcp.internalToken = "..."
     */
    public string $internalToken;

    /**
     * Escopo exigido nos Personal Access Tokens (Shield) usados por chats
     * de IA externos para autenticar no endpoint /mcp. Gerado via
     * App\Controllers\ApiV2\McpTokens::gerar() (fluxo manual) ou via
     * App\Controllers\Mcp\OAuthController::token() (fluxo OAuth do
     * conector do Claude.ai) — nunca com escopo `*`.
     */
    public string $tokenScope = 'mcp';

    /**
     * Tempo de vida (em segundos) de um código de autorização OAuth emitido
     * em /oauth/authorize antes de expirar, caso não seja trocado por um
     * token em /oauth/token. Curto de propósito (RFC 6749 recomenda poucos
     * minutos) — o código é de uso único e descartado após a troca.
     */
    public int $authorizationCodeTtl = 300;

    /**
     * URL base usada pelo cliente MCP interno (GeminiMcpAgentService) para
     * chegar ao próprio endpoint /mcp. Vazia por padrão — nesse caso o
     * chamador usa `site_url()` em tempo de execução. Pode ser sobrescrita
     * em .env (ex.: mcp.baseUrl = "http://localhost") para evitar sair via
     * proxy/CDN público num loopback interno.
     *
     * Não resolvida aqui via site_url()/base_url(): o helper de URL pode
     * ainda não estar carregado quando as Config classes são instanciadas.
     */
    public string $baseUrl;

    /**
     * Diretórios (relativos a APPPATH) escaneados pelo SDK para descobrir
     * Tools/Resources via atributos (#[McpTool], #[McpResourceTemplate]).
     *
     * @var list<string>
     */
    public array $discoveryPaths = ['Mcp/Tools', 'Mcp/Resources'];

    public function __construct()
    {
        parent::__construct();
        $this->internalToken = env('mcp.internalToken', '');
        $this->baseUrl       = rtrim((string) env('mcp.baseUrl', ''), '/');
    }
}
