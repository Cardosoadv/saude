<?php

declare(strict_types=1);

namespace App\Controllers\Mcp;

use App\Controllers\BaseController;
use Http\Discovery\Psr17Factory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;

/**
 * Endpoint MCP (Streamable HTTP), consumido tanto pela IA interna do sistema
 * (GeminiService/Davinci, via loopback) quanto por clientes MCP externos
 * (Claude Desktop, o conector OAuth do Claude.ai, etc.) — protegido pelo
 * filtro `mcpAuth`.
 *
 * O SDK MCP fala PSR-7 puro; o CodeIgniter não. Em vez de tentar converter
 * o Request/Response do CI4, montamos a requisição PSR-7 direto das
 * superglobais e emitimos a resposta PSR-7 diretamente na saída,
 * encerrando o script — bypassando por completo o ciclo Response do CI4
 * (necessário porque o transporte pode responder via streaming/SSE).
 *
 * @see .agents/api.yaml para o contrato REST tradicional (/api/v2/*),
 *      que continua sendo a via de acesso para o frontend Vue.
 */
class McpController extends BaseController
{
    public function handle(): void
    {
        $psr17    = new Psr17Factory();
        $request  = $psr17->createServerRequestFromGlobals();

        $server = Server::builder()
            ->setServerInfo('Saúde', '1.0.0')
            // Sem logger, o SDK engole em silêncio falhas de descoberta (ex.:
            // symfony/finder ausente, classe de Tool com erro) e o servidor
            // sobe normalmente, só que sem nenhuma tool registrada.
            ->setLogger(service('logger'))
            ->setDiscovery(APPPATH, config('Mcp')->discoveryPaths, excludeDirs: ['vendor', 'tests'])
            ->setSession(new FileSessionStore(WRITEPATH . 'mcp_sessions'))
            ->build();

        // O SDK, sem middleware explícito, aplicaria seus padrões (Cors +
        // DnsRebindingProtection), este último com allowlist de Host/Origin
        // restrito a localhost — o que rejeitaria com 403 qualquer chamador
        // externo ao domínio público (Claude Desktop, e sobretudo o conector
        // OAuth do Claude.ai, cujas requisições chegam com um `Origin` que
        // nunca vai bater com o host deste servidor). Omitimos essa proteção
        // de propósito: ela existe para servidores MCP sem autenticação
        // expostos em localhost, cenário que não é o nosso — aqui toda
        // requisição a /mcp já exige um Bearer token válido (`mcpAuth`,
        // verificado antes mesmo deste transporte rodar), o que já neutraliza
        // o ataque de DNS rebinding que esse middleware endereça.
        $transport = new StreamableHttpTransport($request, middleware: [
            new CorsMiddleware(),
        ]);
        $response  = $server->run($transport);

        (new SapiEmitter())->emit($response);
        exit;
    }
}
