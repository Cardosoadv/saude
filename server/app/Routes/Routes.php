<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var \CodeIgniter\Router\RouteCollection $routes
 */

// ============================================================================
// API ROUTES (V1 / LEGACY API)
// ============================================================================

$routes->group('api/usuarios', ['namespace' => 'App\Controllers\Api', 'filter' => 'auth'], function ($routes) {
    $routes->get('buscar', 'Usuarios::buscar');
});

$routes->group('api/processos', ['namespace' => 'App\Controllers\Api', 'filter' => 'permission:processos.access,processos.exclusive'], function ($routes) {
    $routes->get('listarJson', 'Processos::listarJson');
    $routes->get('detalhes/(:num)', 'Processos::detalhesJson/$1');
    $routes->get('etiquetas', 'Processos::etiquetas');
    $routes->post('adicionarEtiqueta', 'Processos::adicionarEtiqueta');
    $routes->post('removerEtiqueta', 'Processos::removerEtiqueta');
    $routes->post('adicionarParte', 'Processos::adicionarParte');
    $routes->post('removerParte', 'Processos::removerParte');
    $routes->post('adicionarAnotacao', 'Processos::adicionarAnotacao');
    $routes->post('salvarMovimento', 'Processos::salvarMovimento');
    $routes->post('atualizarParteRepresentada', 'Processos::atualizarParteRepresentada');
    $routes->post('salvarVinculo', 'Processos::salvarVinculo');
    $routes->post('removerVinculo/(:num)', 'Processos::removerVinculo/$1');
    $routes->post('excluir/(:num)', 'Processos::excluir/$1');
    $routes->post('excluirEmLote', 'Processos::excluirEmLote');
    $routes->post('arquivarEmLote', 'Processos::arquivarEmLote');
});

$routes->group('api/crm', ['namespace' => 'App\Controllers\Api', 'filter' => 'permission:clientes.access'], function ($routes) {
    $routes->get('dashboard', 'Crm::dashboard');
    $routes->get('pipeline', 'Crm::pipeline');
    $routes->post('mover', 'Crm::mover');
    $routes->post('salvar', 'Crm::salvar');
    $routes->get('interacoes/(:num)', 'Crm::interacoes/$1');
    $routes->post('salvarInteracao', 'Crm::salvarInteracao');
});

$routes->group('api/clientes', ['namespace' => 'App\Controllers\Api', 'filter' => 'permission:clientes.access'], function ($routes) {
    $routes->get('/', 'Clientes::listar');
});

$routes->group('api/financeiro', ['namespace' => 'App\Controllers\Api', 'filter' => 'permission:financeiro.access'], function ($routes) {
    $routes->get('balanco', 'Financeiro::balanco');
    $routes->get('extrato', 'Financeiro::extrato');
    $routes->get('dre', 'Financeiro::dre');
    $routes->get('movimentacoes/(:num)', 'Financeiro::movimentacoes/$1');
    $routes->get('fechamento/listar', 'FechamentoMes::listar');
    $routes->post('fechamento/fechar', 'FechamentoMes::fechar', ['filter' => 'permission:financeiro.edit']);
    $routes->post('fechamento/reabrir', 'FechamentoMes::reabrir', ['filter' => 'permission:financeiro.delete']);
    $routes->get('fechamento/verificar', 'FechamentoMes::verificar');
    $routes->get('fechamento/sugerir', 'FechamentoMes::sugerirProximo');
});

$routes->group('api/tarefas', ['namespace' => 'App\Controllers\Api', 'filter' => 'permission:tarefas.access'], function ($routes) {
    $routes->get('listar', 'Tarefas::listarJson');
    $routes->post('salvar', 'Tarefas::salvar');
    $routes->post('mudar-status', 'Tarefas::mudarStatus');
    $routes->post('mudar-responsavel', 'Tarefas::mudarResponsavel');
    $routes->get('estatisticas', 'Tarefas::estatisticas');
    $routes->get('urgentes', 'Tarefas::urgentes');
    $routes->get('responsaveis', 'Tarefas::responsaveis');
    $routes->get('calendario', 'Tarefas::calendario');
    $routes->delete('excluir/(:num)', 'Tarefas::excluir/$1', ['filter' => 'permission:tarefas.delete']);
});

$routes->group('api/contratos', ['namespace' => 'App\Controllers\Api', 'filter' => 'permission:contratos.access'], function ($routes) {
    $routes->get('listar', 'Contratos::listar');
});

$routes->group('api/checklist', ['namespace' => 'App\Controllers\Api', 'filter' => 'permission:checklist.access,checklist.exclusive'], function($routes) {
    $routes->get('entidade/(:any)/(:num)', 'Checklist::getByEntidade/$1/$2');
    $routes->post('aplicar', 'Checklist::aplicarTemplate');
    $routes->post('marcar-item', 'Checklist::marcarItem');
    $routes->post('adicionar-item-avulso', 'Checklist::adicionarItemAvulso');
    $routes->post('concluir/(:num)', 'Checklist::concluir/$1');
    $routes->delete('excluir/(:num)', 'Checklist::excluir/$1');
    $routes->get('templates/(:any)/(:num)', 'Checklist::getTemplatesDisponiveis/$1/$2');
    $routes->get('sugerirIA', 'Checklist::sugerirIA');
    $routes->get('sugerirComIA/(:any)/(:num)', 'Checklist::sugerirIA/$1/$2');
    $routes->post('salvarSugestoesIA', 'Checklist::salvarSugestoesIA');
    $routes->post('remover/(:num)', 'Checklist::excluir/$1');
    $routes->post('toggleItem/(:num)', 'Checklist::toggleItem/$1');
});


$routes->group('api/fornecedores', ['namespace' => 'App\Controllers\Api', 'filter' => 'permission:fornecedores.access'], function ($routes) {
    $routes->get('/', 'Fornecedores::index');
    $routes->get('detalhes/(:num)', 'Fornecedores::detalhes/$1', ['filter' => 'permission:fornecedores.edit']);
    $routes->post('salvar', 'Fornecedores::salvar', ['filter' => 'permission:fornecedores.create']);
    $routes->delete('excluir/(:num)', 'Fornecedores::excluir/$1', ['filter' => 'permission:fornecedores.delete']);
    $routes->get('estatisticas', 'Fornecedores::estatisticas');
    $routes->get('recentes', 'Fornecedores::recentes');
});

$routes->group('api/legal-ops', ['namespace' => 'App\Controllers\Api'], function ($routes) {
    $routes->get('dashboard', 'LegalOps::dashboard', ['filter' => 'permission:module.processos']);
    $routes->get('export', 'LegalOps::export', ['filter' => 'permission:module.processos']);
});

$routes->group('api', ['namespace' => 'App\Controllers\Api', 'filter' => 'auth'], function ($routes) {
    $routes->get('dashboard', 'Dashboard::index');
});

$routes->group('api/template', ['namespace' => 'App\Controllers\Api'], function ($routes) {
    $routes->get('index', 'TemplateData::index');
    $routes->get('messages', 'TemplateData::messages');
    $routes->get('tasks', 'TemplateData::tasks');
    $routes->get('expenses', 'TemplateData::expenses');
    $routes->get('notifications', 'TemplateData::notifications');
});
