<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Garante que o preflight de CORS responda 200 mesmo para rotas inexistentes.
// As rotas da API ficam em apiV2.php; as do site público em site.php.
$routes->options('(:any)', static function () {});
