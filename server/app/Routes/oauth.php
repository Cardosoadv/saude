<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ============================================================================
// OAuth 2.1 — Authorization Server mínimo para o /mcp funcionar como
// "conector personalizado" no Claude.ai (e outros clientes MCP compatíveis
// com OAuth). O SDK MCP (mcp/sdk) atua apenas como Resource Server; estas
// rotas implementam o restante do fluxo (RFC 6749/7636/7591/8414/9728)
// reaproveitando a própria sessão Shield do usuário para login/consentimento.
//
// O token emitido em /oauth/token é, na prática, o mesmo Personal Access
// Token (escopo `mcp`) que App\Controllers\ApiV2\McpTokens já gera
// manualmente — McpAuthFilter não precisa de nenhuma lógica nova para
// aceitá-lo.
// ============================================================================
//
// Quando o site roda em subcaminho (ex.: issuer https://dominio/clinica),
// as RFCs 8414 §3.1 e 9728 §3.1 inserem o caminho DEPOIS do well-known:
// /.well-known/oauth-authorization-server/clinica e
// /.well-known/oauth-protected-resource/clinica/mcp. O Claude.ai segue o
// `resource_metadata` do WWW-Authenticate e nunca precisou disso; o Gemini
// monta a URL pela RFC e, sem resposta ali, recusa o servidor como "sem
// OAuth padrão". Por isso o sufixo é aceito em qualquer forma.
$routes->get('.well-known/oauth-protected-resource', 'Mcp\OAuthController::protectedResourceMetadata');
$routes->get('.well-known/oauth-protected-resource/(:any)', 'Mcp\OAuthController::protectedResourceMetadata');
$routes->get('.well-known/oauth-authorization-server', 'Mcp\OAuthController::authorizationServerMetadata');
$routes->get('.well-known/oauth-authorization-server/(:any)', 'Mcp\OAuthController::authorizationServerMetadata');

$routes->post('oauth/register', 'Mcp\OAuthController::register');

$routes->get('oauth/authorize', 'Mcp\OAuthController::authorize');
$routes->post('oauth/authorize', 'Mcp\OAuthController::consent');

$routes->post('oauth/token', 'Mcp\OAuthController::token');
