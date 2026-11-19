<?php
// API Pública
$routes->group('api/v2/site/', ['namespace' => 'App\Controllers\ApiV2\Site'], function ($routes) {
    $routes->get('noticias', 'ApiController::noticias');
    $routes->get('noticias/(:segment)', 'ApiController::noticiaBySlug/$1');
    $routes->post('contact', 'ApiController::submitContact');

    // Sitemap (Mantido dinâmico para SEO)
    $routes->get('sitemap.xml', 'Sitemap::index');
    $routes->get('sitemap/generate', 'Sitemap::generate');
});

// API Admin (Requer autenticação Shield e o módulo Site ativo)
$routes->group('api/v2/site/admin', ['namespace' => 'App\Controllers\ApiV2\Site\admin', 'filter' => ['auth', 'modulo:site']], function ($routes) {
    // Permissões (Shield) por rota: site.conteudo, site.publicar, site.configuracoes, site.templates (#654).
    $routes->group('', ['filter' => 'permission:site.conteudo'], function ($routes) {
        $routes->get('noticias/categorias', 'NoticiaCategorias::index');
        $routes->post('noticias/categorias', 'NoticiaCategorias::store');
        $routes->get('noticias/categorias/(:num)', 'NoticiaCategorias::show/$1');
        $routes->put('noticias/categorias/(:num)', 'NoticiaCategorias::update/$1');
        $routes->patch('noticias/categorias/(:num)', 'NoticiaCategorias::update/$1');
        $routes->delete('noticias/categorias/(:num)', 'NoticiaCategorias::destroy/$1');
        $routes->get('noticias', 'Noticias::index');
        $routes->get('noticias/(:num)', 'Noticias::show/$1');
        $routes->post('noticias', 'Noticias::store');
        $routes->put('noticias/(:num)', 'Noticias::update/$1');
        $routes->patch('noticias/(:num)', 'Noticias::update/$1');
        $routes->delete('noticias/(:num)', 'Noticias::destroy/$1');
        $routes->get('paginas', 'Paginas::index');
        $routes->post('paginas', 'Paginas::store');
        $routes->get('paginas/(:num)', 'Paginas::show/$1');
        $routes->put('paginas/(:num)', 'Paginas::update/$1');
        $routes->patch('paginas/(:num)', 'Paginas::update/$1');
        $routes->delete('paginas/(:num)', 'Paginas::destroy/$1');
        $routes->post('paginas/(:num)/duplicar', 'Paginas::duplicar/$1');
        $routes->get('paginas/(:num)/preview-token', 'Paginas::previewToken/$1');
        $routes->post('paginas/(:num)/secoes', 'Secoes::store/$1');
        $routes->put('paginas/(:num)/secoes/ordem', 'Secoes::ordem/$1');
        $routes->put('secoes/(:num)', 'Secoes::update/$1');
        $routes->patch('secoes/(:num)', 'Secoes::update/$1');
        $routes->delete('secoes/(:num)', 'Secoes::destroy/$1');
        $routes->get('midias', 'Midias::index');
        $routes->get('midias/(:num)', 'Midias::show/$1');
        $routes->post('midias', 'Midias::store');
        $routes->put('midias/(:num)', 'Midias::update/$1');
        $routes->patch('midias/(:num)', 'Midias::update/$1');
        $routes->get('midias/(:num)/usos', 'Midias::usos/$1');
        $routes->delete('midias/(:num)', 'Midias::destroy/$1');
        $routes->get('menus', 'Menus::index');
        $routes->put('menus/(:segment)', 'Menus::update/$1');
    });

    $routes->group('', ['filter' => 'permission:site.publicar'], function ($routes) {
        $routes->post('paginas/(:num)/publicar', 'Paginas::publicar/$1');
        $routes->post('paginas/(:num)/despublicar', 'Paginas::despublicar/$1');
    });

    $routes->group('', ['filter' => 'permission:site.configuracoes'], function ($routes) {
        $routes->get('configuracoes', 'Configuracoes::index');
        $routes->put('configuracoes', 'Configuracoes::update');
        $routes->patch('configuracoes', 'Configuracoes::update');
        $routes->get('template/tema', 'TemplateAtivo::tema');
        $routes->put('template/tema', 'TemplateAtivo::salvarTema');
        $routes->patch('template/tema', 'TemplateAtivo::salvarTema');
        $routes->delete('template/tema', 'TemplateAtivo::restaurarTema');
    });

    $routes->group('', ['filter' => 'permission:site.conteudo,site.configuracoes'], function ($routes) {
        $routes->get('template/schema', 'TemplateAtivo::schema');
    });

    $routes->group('', ['filter' => 'permission:site.templates'], function ($routes) {
        $routes->get('templates', 'Templates::index');
        $routes->post('templates', 'Templates::store');
        $routes->post('templates/(:segment)/ativar', 'Templates::ativar/$1');
        $routes->get('templates/(:segment)/paginas-exemplo', 'Templates::paginasExemploPrevia/$1');
        $routes->post('templates/(:segment)/paginas-exemplo', 'Templates::paginasExemplo/$1');
        $routes->get('templates/(:segment)/exportar', 'Templates::exportar/$1');
        $routes->delete('templates/(:segment)', 'Templates::destroy/$1');
    });
});

// ============================================================
// ÁREA DO CLIENTE — Auth pública (sem filtro de autenticação)
// ============================================================
$routes->group('api/v2/site/cliente/auth', ['namespace' => 'App\Controllers\ApiV2'], function ($routes) {
    $routes->post('solicitar', 'ClienteAuth::solicitarLink');
    $routes->post('verificar', 'ClienteAuth::verificarToken');
    $routes->post('logout',    'ClienteAuth::logout');
});
// ============================================================
// ÁREA DO CLIENTE — Dados protegidos (requer clienteAuth)
// ============================================================
$routes->group('api/v2/site/cliente', [
    'namespace' => 'App\Controllers\ApiV2',
    'filter'    => 'clienteAuth',
], function ($routes) {
    $routes->get('perfil',                          'ClienteArea::perfil');
});

// ============================================================
// FRONTEND SITE SSR
// ============================================================
// Todas as rotas públicas passam pelo filtro `hostcanonico`: protocolo, host (www × sem www) e
// subpasta /clinica/ são normalizados com um 301 para a `publicURL` (issue #684).
$routes->group('', ['filter' => 'hostcanonico'], function ($routes) {
    $routes->get('/', 'Site::index', ['namespace' => 'App\Controllers']);
    $routes->get('pagina/(:segment)', 'Site::pagina/$1', ['namespace' => 'App\Controllers']);
    $routes->get('noticias', 'Site::noticias', ['namespace' => 'App\Controllers']);
    $routes->get('noticias/(:segment)', 'Site::noticiaDetail/$1', ['namespace' => 'App\Controllers']);
    $routes->get('area-cliente', 'Site::areaCliente', ['namespace' => 'App\Controllers']);
    $routes->get('painel-cliente', 'Site::painelCliente', ['namespace' => 'App\Controllers']);
    $routes->get('cliente', 'Site::painelCliente', ['namespace' => 'App\Controllers']);
    $routes->get('equipe/foto/(:num)', 'Site::fotoEquipe/$1', ['namespace' => 'App\Controllers']);

    // Sitemap na raiz do site para SEO
    $routes->get('sitemap.xml', 'Sitemap::index', ['namespace' => 'App\Controllers\ApiV2\Site']);

    // robots.txt na raiz do site (issue #685)
    $routes->get('robots.txt', 'Robots::index', ['namespace' => 'App\Controllers\ApiV2\Site']);

    // Contexto da clínica para IAs (issues #686 e #687)
    $routes->get('llms.txt', 'LlmsTxt::index', ['namespace' => 'App\Controllers\ApiV2\Site']);
    $routes->get('llms-full.txt', 'LlmsFullTxt::index', ['namespace' => 'App\Controllers\ApiV2\Site']);
});

// Remover fallback catch-all anterior para o CI4 lidar com 404 nativo
// $routes->get('(:any)', 'Site::index/$1', ['namespace' => 'App\Controllers', 'priority' => 999]);
