<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ============================================================================
// MCP (Model Context Protocol) — endpoint interno para a IA do sistema.
// Todos os métodos HTTP (POST/GET/DELETE/OPTIONS) apontam para a mesma ação;
// o transporte Streamable HTTP do SDK decide o que fazer com cada um.
// ============================================================================
$routes->match(
    ['GET', 'POST', 'DELETE', 'OPTIONS'],
    'mcp',
    'Mcp\McpController::handle',
    ['filter' => 'mcpAuth']
);
