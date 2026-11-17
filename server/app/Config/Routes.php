<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Incluir rotas consolidadas

require APPPATH . 'Routes/Routes.php'; 



// Catch-all OPTIONS route to ensure CORS preflight always returns 200 OK even for 404s
$routes->options('(:any)', static function () {});

