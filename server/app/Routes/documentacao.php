<?php

// ============================================================================
// ROTAS API V2 - DOCUMENTAÇÃO INTERNA (épico #699, issue #703)
// Contrato em docs/api-documentacao.md.
// ============================================================================
$routes->options('api/v2/documentacao', static function () {});
$routes->options('api/v2/documentacao/(:any)', static function () {});

$routes->group('api/v2/documentacao', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:documentacao.access', 'modulo:documentacao']], function ($routes) {
    $routes->get('/', 'Documentacao::index');
    $routes->get('busca', 'Documentacao::busca');
    $routes->get('paginas/(:any)', 'Documentacao::pagina/$1');
    $routes->get('tela/(:segment)', 'Documentacao::tela/$1');
    $routes->get('imagens/(:any)', 'Documentacao::imagem/$1');
});

// Edição pela interface (issue #711): só quem tem documentacao.editar.
$routes->group('api/v2/documentacao/admin', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:documentacao.editar', 'modulo:documentacao']], function ($routes) {
    $routes->get('paginas', 'DocumentacaoAdmin::index');
    $routes->post('paginas', 'DocumentacaoAdmin::create');
    $routes->post('paginas/de-arquivo/(:any)', 'DocumentacaoAdmin::deArquivo/$1');
    $routes->get('paginas/(:num)', 'DocumentacaoAdmin::show/$1');
    $routes->put('paginas/(:num)', 'DocumentacaoAdmin::update/$1');
    $routes->post('paginas/(:num)/publicar', 'DocumentacaoAdmin::publicar/$1');
    $routes->post('paginas/(:num)/despublicar', 'DocumentacaoAdmin::despublicar/$1');
    $routes->delete('paginas/(:num)', 'DocumentacaoAdmin::delete/$1');
    $routes->post('preview', 'DocumentacaoAdmin::preview');
    $routes->post('imagens', 'DocumentacaoAdmin::imagem');
});
