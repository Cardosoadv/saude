<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var \CodeIgniter\Router\RouteCollection $routes
 */

// Rotas de Autenticação (Shield)
service('auth')->routes($routes);

// ============================================================================
// OPTIONS preflight – rota catch-all para CORS (sem filtro de permissão, pois preflight não envia cookies/auth)
// ============================================================================

// ============================================================================
// ROTAS API V2 - AUTH
// ============================================================================
$routes->group('api/v2', ['namespace' => 'App\Controllers\ApiV2'], function ($routes) {
    $routes->post('login', 'Auth::login');
    $routes->post('esqueci-senha', 'Auth::esqueciSenha');
    $routes->post('redefinir-senha', 'Auth::redefinirSenha');
    $routes->get('user/info', 'Auth::userInfo', ['filter' => 'auth']);
});

// ============================================================================
// ROTAS API V2 - PERFIL DO USUÁRIO LOGADO
// ============================================================================
$routes->group('api/v2/perfil', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Perfil::detalhes');
    $routes->post('atualizar', 'Perfil::atualizar');
    $routes->post('senha', 'Perfil::alterarSenha');
});

// ============================================================================
// ROTAS API V2 - NOTIFICAÇÕES PUSH (PWA) DO USUÁRIO LOGADO
// ============================================================================
$routes->group('api/v2/push', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'auth'], function ($routes) {
    $routes->get('config', 'Push::config');
    $routes->post('inscricao', 'Push::inscrever');
    $routes->post('inscricao/remover', 'Push::remover');
    $routes->post('teste', 'Push::teste');
});


// ============================================================================
// ROTAS API V2 - TAREFAS
// ============================================================================
$routes->group('api/v2/tarefas', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:tarefas.access', 'modulo:tarefas']], function ($routes) {
    $routes->get('listar', 'Tarefas::listarJson');
    $routes->get('/', 'Tarefas::listarJson');
    $routes->post('salvar', 'Tarefas::salvar');
    $routes->post('mudar-status', 'Tarefas::mudarStatus');
    $routes->post('mudar-responsavel', 'Tarefas::mudarResponsavel');
    $routes->get('estatisticas', 'Tarefas::estatisticas');
    $routes->get('urgentes', 'Tarefas::urgentes');
    $routes->get('responsaveis', 'Tarefas::responsaveis');
    $routes->get('calendario', 'Tarefas::calendario');
    $routes->delete('excluir/(:num)', 'Tarefas::excluir/$1', ['filter' => 'permission:tarefas.delete']);
});


// ============================================================================
// ROTAS API V2 - CONTADORES GLOBAIS (MENU / BARRA SUPERIOR)
// ============================================================================
$routes->group('api/v2/template', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'auth'], function ($routes) {
    $routes->get('notifications', 'TemplateData::notifications');
});

// ============================================================================
// ROTAS API V2 - USUÁRIOS
// ============================================================================
$routes->group('api/v2/usuarios', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Usuarios::listar');
    $routes->get('listar', 'Usuarios::listar');
    $routes->get('detalhes/(:num)', 'Usuarios::detalhes/$1');
    $routes->post('salvar', 'Usuarios::salvar');
    $routes->post('atualizar/(:num)', 'Usuarios::atualizar/$1');
    $routes->delete('excluir/(:num)', 'Usuarios::excluir/$1');
    $routes->post('excluir/(:num)', 'Usuarios::excluir/$1');
    $routes->get('grupos', 'Usuarios::grupos');

    $routes->get('buscar', 'Usuarios::buscar');
    $routes->get('buscarUsuario', 'Usuarios::buscar');
    $routes->get('buscarUsuario/(:any)', 'Usuarios::buscar/$1');
    $routes->get('exibirFoto/(:num)', 'Usuarios::exibirFoto/$1');
    $routes->get('participa-rateio', 'Usuarios::participaRateio');
    $routes->get('getProfissionais', 'Usuarios::getProfissionais');
});

// ============================================================================
// ROTAS API V2 - CONFIGURAÇÕES
// ============================================================================
$routes->group('api/v2/configuracoes', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Configuracoes::listar');
    $routes->post('salvar', 'Configuracoes::salvar');
    $routes->post('limpar-cache', 'Configuracoes::limparCache');
    $routes->get('migracoes', 'Migracoes::status');
    $routes->post('migracoes/executar', 'Migracoes::executar');
});

// ============================================================================
// ROTAS API V2 - TOKENS DE INTEGRAÇÃO COM IA (MCP)
// Cada usuário gera/revoga seus próprios tokens para plugar um chat de IA
// externo (Claude Desktop, ChatGPT etc.) no endpoint /mcp.
// ============================================================================
$routes->group('api/v2/integracao-ia', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'auth'], function ($routes) {
    $routes->get('tokens', 'McpTokens::listar');
    $routes->post('tokens', 'McpTokens::gerar');
    $routes->delete('tokens/(:num)', 'McpTokens::revogar/$1');

    // Conectores OAuth com Client ID/Secret cadastrados à mão (ex.: Gemini).
    $routes->get('clientes-oauth', 'McpClientesOAuth::listar');
    $routes->post('clientes-oauth', 'McpClientesOAuth::criar');
    $routes->delete('clientes-oauth/(:num)', 'McpClientesOAuth::excluir/$1');
});



// ============================================================================
// ROTAS API V2 - CLIENTES / CRM
// ============================================================================
$routes->group('api/v2/clientes', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:clientes.access', 'modulo:clientes']], function ($routes) {
    $routes->get('/', 'Clientes::listar');
    $routes->get('detalhes/(:num)', 'Clientes::detalhes/$1');
    $routes->get('estatisticas', 'Clientes::estatisticas');
    $routes->get('recentes', 'Clientes::recentes');
    $routes->get('aniversariantes', 'Clientes::aniversariantes');
    $routes->get('estatisticasCliente/(:num)', 'Clientes::estatisticasCliente/$1');
    $routes->get('movimentacoes/(:num)', 'Clientes::movimentacoes/$1', ['filter' => ['modulo:financeiro', 'permission:financeiro.access']]);
    $routes->get('buscar', 'Clientes::buscar');
    $routes->post('salvar', 'Clientes::salvar');
    $routes->post('transformar-em-fornecedor/(:num)', 'Clientes::transformarEmFornecedor/$1', ['filter' => ['permission:fornecedores.create', 'modulo:fornecedores']]);
    $routes->post('excluir/(:num)', 'Clientes::excluir/$1');
    $routes->delete('excluir/(:num)', 'Clientes::excluir/$1');

    // CRM Pipeline
    $routes->get('dashboard', 'Crm::dashboard', ['filter' => 'modulo:crm']);
    $routes->get('pipeline', 'Crm::pipeline', ['filter' => 'modulo:crm']);
    $routes->post('mover', 'Crm::mover', ['filter' => 'modulo:crm']);
    $routes->get('interacoes/(:num)', 'Crm::interacoes/$1', ['filter' => 'modulo:crm']);
    $routes->post('salvarInteracao', 'Crm::salvarInteracao', ['filter' => 'modulo:crm']);
    $routes->post('interacao/salvar', 'Crm::salvarInteracao', ['filter' => 'modulo:crm']);
    $routes->delete('interacao/excluir/(:num)', 'Crm::excluirInteracao/$1', ['filter' => 'modulo:crm']);
});

$routes->group('api/v2/crm', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:clientes.access', 'modulo:crm']], function ($routes) {
    // Oportunidades
    $routes->get('oportunidades', 'Crm::listarOportunidades');
    $routes->get('oportunidade/(:num)', 'Crm::obterOportunidade/$1');
    $routes->get('oportunidadesPorCliente/(:num)', 'Crm::listarOportunidadesPorCliente/$1');
    $routes->post('salvarOportunidade', 'Crm::salvarOportunidade');
    $routes->post('excluirOportunidade/(:num)', 'Crm::excluirOportunidade/$1');

    // Pipeline
    $routes->get('pipeline', 'Crm::pipeline');
    $routes->post('mover', 'Crm::mover');
    $routes->post('moverOportunidade', 'Crm::mover');
    $routes->post('salvar', 'Crm::salvar');

    // Interações
    $routes->get('interacoes/(:num)', 'Crm::interacoes/$1');
    $routes->get('obterInteracoes/(:num)', 'Crm::interacoes/$1');
    $routes->post('registrarInteracao', 'Crm::salvarInteracao');
    $routes->post('salvarInteracao', 'Crm::salvarInteracao');

    // Dashboard
    $routes->get('dashboard', 'Crm::dashboard');

    // Leads (Contatos do Site)
    $routes->get('leads', 'CrmLeads::listar');
    $routes->post('leads/converter/(:num)', 'CrmLeads::converter/$1');
    $routes->put('leads/status/(:num)', 'CrmLeads::atualizarStatus/$1');
});

// ============================================================================
// ROTAS API V2 - FINANCEIRO
// ============================================================================
$routes->group('api/v2/financeiro', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:financeiro.access', 'modulo:financeiro']], function ($routes) {
    // Dashboard / Extratos
    $routes->get('balanco', 'Financeiro::balanco');
    $routes->get('extrato', 'Financeiro::extrato');
    $routes->get('relatorio-categorias', 'Financeiro::relatorioCategorias');
    $routes->get('dre', 'Financeiro::dre');
    $routes->get('movimentacoes/(:num)', 'Financeiro::movimentacoes/$1');
});
$routes->group('api/v2/financeiro', ['namespace' => 'App\Controllers\ApiV2\Financeiro', 'filter' => ['permission:financeiro.access', 'modulo:financeiro']], function ($routes) {

    // Despesas
    $routes->get('despesas/listar', 'Despesas::listar');
    $routes->get('despesas/detalhes/(:num)', 'Despesas::detalhar/$1');
    $routes->post('despesas/salvar', 'Despesas::salvar');
    $routes->get('despesas/resumo', 'Despesas::resumo');
    $routes->get('despesas/ultimas-pagas', 'Despesas::ultimasPagas');
    $routes->post('despesas/excluirEmLote', 'Despesas::excluirEmLote', ['filter' => 'permission:financeiro.delete']);
    $routes->get('despesas/exportar', 'Despesas::exportar');
    $routes->post('despesas/acaoEmLote', 'Despesas::acaoEmLote', ['filter' => 'permission:financeiro.edit']);

    // Receitas
    $routes->get('receitas/listar', 'Receitas::listar');
    $routes->get('receitas/detalhes/(:num)', 'Receitas::detalhar/$1');
    $routes->post('receitas/salvar', 'Receitas::salvar');
    $routes->get('receitas/resumo', 'Receitas::resumo');
    $routes->get('receitas/ultimas-recebidas', 'Receitas::ultimasRecebidas');
    $routes->post('receitas/excluirEmLote', 'Receitas::excluirEmLote', ['filter' => 'permission:financeiro.delete']);
    $routes->get('receitas/exportar', 'Receitas::exportar');
    $routes->post('receitas/acaoEmLote', 'Receitas::acaoEmLote', ['filter' => 'permission:financeiro.edit']);

    // Pagamento Despesas
    $routes->get('pagamentoDespesas/listar', 'PagamentoDespesas::listar');
    $routes->get('pagamentoDespesas/detalhes/(:num)', 'PagamentoDespesas::detalhar/$1');
    $routes->post('pagamentoDespesas/salvar', 'PagamentoDespesas::salvar');
    $routes->get('pagamentoDespesas/estatisticas', 'PagamentoDespesas::estatisticas');
    $routes->get('pagamentoDespesas/resumo-por-conta', 'PagamentoDespesas::resumoPorConta');
    $routes->post('pagamentoDespesas/estornar/(:num)', 'PagamentoDespesas::estornar/$1', ['filter' => 'permission:financeiro.delete']);
    $routes->post('pagamentoDespesas/excluirEmLote', 'PagamentoDespesas::excluirEmLote', ['filter' => 'permission:financeiro.delete']);
    $routes->post('pagamentoDespesas/acaoEmLote', 'PagamentoDespesas::acaoEmLote', ['filter' => 'permission:financeiro.edit']);
    $routes->get('pagamentoDespesas/exportar', 'PagamentoDespesas::exportar');

    // Pagamento Receitas
    $routes->get('pagamentoReceitas/listar', 'PagamentoReceitas::listar');
    $routes->get('pagamentoReceitas/detalhes/(:num)', 'PagamentoReceitas::detalhar/$1');
    $routes->post('pagamentoReceitas/salvar', 'PagamentoReceitas::salvar');
    $routes->get('pagamentoReceitas/estatisticas', 'PagamentoReceitas::estatisticas');
    $routes->get('pagamentoReceitas/resumo-por-conta', 'PagamentoReceitas::resumoPorConta');
    $routes->post('pagamentoReceitas/estornar/(:num)', 'PagamentoReceitas::estornar/$1', ['filter' => 'permission:financeiro.delete']);
    $routes->post('pagamentoReceitas/excluirEmLote', 'PagamentoReceitas::excluirEmLote', ['filter' => 'permission:financeiro.delete']);
    $routes->post('pagamentoReceitas/acaoEmLote', 'PagamentoReceitas::acaoEmLote', ['filter' => 'permission:financeiro.edit']);
    $routes->get('pagamentoReceitas/exportar', 'PagamentoReceitas::exportar');

    // Comprovantes de pagamento (Google Drive)
    $routes->post('comprovantes/testar-pasta', 'Comprovantes::testarPasta', ['filter' => 'permission:financeiro.edit']);
    $routes->get('pagamentoDespesas/(:num)/comprovantes', 'Comprovantes::listar/despesa/$1');
    $routes->post('pagamentoDespesas/(:num)/comprovantes', 'Comprovantes::enviar/despesa/$1', ['filter' => 'permission:financeiro.edit']);
    $routes->get('pagamentoReceitas/(:num)/comprovantes', 'Comprovantes::listar/receita/$1');
    $routes->post('pagamentoReceitas/(:num)/comprovantes', 'Comprovantes::enviar/receita/$1', ['filter' => 'permission:financeiro.edit']);
    $routes->get('comprovantes/pendencias', 'Comprovantes::pendencias');
    $routes->post('comprovantes/lote', 'Comprovantes::enviarLote', ['filter' => 'permission:financeiro.edit']);
    $routes->delete('comprovantes/(:num)', 'Comprovantes::excluir/$1', ['filter' => 'permission:financeiro.delete']);

    // Contas e Categorias
    $routes->get('contas/listar', 'Contas::listar');
    $routes->post('contas/salvar', 'Contas::salvar', ['filter' => 'permission:financeiro.edit,financeiro.create']);
    $routes->delete('contas/excluir/(:num)', 'Contas::excluir/$1', ['filter' => 'permission:financeiro.delete']);

    $routes->get('categorias/listar', 'Categorias::listar');
    $routes->post('categorias/salvar', 'Categorias::salvar', ['filter' => 'permission:financeiro.edit,financeiro.create']);
    $routes->delete('categorias/excluir/(:num)', 'Categorias::excluir/$1', ['filter' => 'permission:financeiro.delete']);

    // Transferências
    $routes->get('transferencias/listar', 'Transferencias::listar');
    $routes->get('transferencias/detalhes/(:num)', 'Transferencias::detalhes/$1');
    $routes->post('transferencias/salvar', 'Transferencias::salvar', ['filter' => 'permission:financeiro.create,financeiro.edit']);
    $routes->delete('transferencias/excluir/(:num)', 'Transferencias::excluir/$1', ['filter' => 'permission:financeiro.delete']);

    // Previsão de Fluxo de Caixa & Anomalias
    $routes->get('previsao-fluxo-caixa/dashboard', 'CashFlowForecast::dashboard');
    $routes->get('previsao-fluxo-caixa/anomalias', 'CashFlowForecast::anomalias');

    // Importação de extratos bancários (OFX)
    $routes->post('extratos/previa', 'Extratos::previa', ['filter' => 'permission:financeiro.extratos_importar']);
    $routes->post('extratos/importar', 'Extratos::importar', ['filter' => 'permission:financeiro.extratos_importar']);
    $routes->get('extratos/importacoes', 'Extratos::importacoes');
    $routes->get('extratos/importacoes/(:num)', 'Extratos::detalharImportacao/$1');
    $routes->get('extratos/movimentos', 'Extratos::movimentos');

    // Conciliação de extratos com pagamentos já lançados
    $routes->get('extratos/movimentos/(:num)/sugestoes', 'ConciliacaoExtratos::sugestoes/$1');
    $routes->post('extratos/movimentos/(:num)/conciliar', 'ConciliacaoExtratos::conciliar/$1', ['filter' => 'permission:financeiro.extratos_conciliar']);
    $routes->post('extratos/movimentos/(:num)/desconciliar', 'ConciliacaoExtratos::desconciliar/$1', ['filter' => 'permission:financeiro.extratos_conciliar']);
    $routes->post('extratos/movimentos/(:num)/ignorar', 'ConciliacaoExtratos::ignorar/$1', ['filter' => 'permission:financeiro.extratos_conciliar']);
    $routes->get('extratos/conciliacao/lote', 'ConciliacaoExtratos::previaLote');
    $routes->post('extratos/conciliacao/lote', 'ConciliacaoExtratos::conciliarLote', ['filter' => 'permission:financeiro.extratos_conciliar']);

    // Lançamento a partir do extrato (despesa/receita, quitar em aberto, transferência) e regras de classificação
    $routes->get('extratos/movimentos/(:num)/sugestao-lancamento', 'LancamentoExtratos::sugestao/$1');
    $routes->post('extratos/movimentos/(:num)/lancar', 'LancamentoExtratos::lancar/$1', ['filter' => 'permission:financeiro.extratos_lancar']);
    $routes->post('extratos/movimentos/lancar-lote', 'LancamentoExtratos::lancarLote', ['filter' => 'permission:financeiro.extratos_lancar']);
    // Conferência de saldo (informativa) e comprovantes dos pagamentos conciliados
    $routes->get('extratos/conferencia', 'ConferenciaExtratos::conferir');
    $routes->get('extratos/resumo-mes', 'ConferenciaExtratos::resumoMes');
    $routes->post('extratos/movimentos/(:num)/comprovantes', 'Comprovantes::enviarDoMovimento/$1', ['filter' => 'permission:financeiro.edit']);
    $routes->get('extratos/regras', 'RegrasExtratos::listar');
    $routes->get('extratos/regras/(:num)', 'RegrasExtratos::detalhar/$1');
    $routes->post('extratos/regras', 'RegrasExtratos::criar', ['filter' => 'permission:financeiro.extratos_lancar']);
    $routes->put('extratos/regras/(:num)', 'RegrasExtratos::atualizar/$1', ['filter' => 'permission:financeiro.extratos_lancar']);
    $routes->delete('extratos/regras/(:num)', 'RegrasExtratos::excluir/$1', ['filter' => 'permission:financeiro.extratos_lancar']);

    // Fechamento de Mês
    $routes->get('fechamento/listar', 'FechamentoMes::listar');
    $routes->post('fechamento/fechar', 'FechamentoMes::fechar', ['filter' => 'permission:financeiro.edit']);
    $routes->post('fechamento/reabrir', 'FechamentoMes::reabrir', ['filter' => 'permission:financeiro.delete']);
    $routes->get('fechamento/verificar', 'FechamentoMes::verificar');
    $routes->get('fechamento/sugerir', 'FechamentoMes::sugerirProximo');

    // Favoritos
    $routes->get('favoritos', 'Favoritos::index');
    $routes->post('favoritos', 'Favoritos::create');
    $routes->put('favoritos/(:num)', 'Favoritos::update/$1');
    $routes->delete('favoritos/(:num)', 'Favoritos::delete/$1');
});

// ============================================================================
// ROTAS API V2 - CONTÁBIL (INTEGRAÇÃO FINANCEIRA E PACOTE CONTÁBIL)
// ============================================================================
$routes->group('api/v2/contabil', ['namespace' => 'App\Controllers\ApiV2\Contabil', 'filter' => ['permission:contabil.access', 'modulo:contabil']], function ($routes) {
    $routes->get('integracao/transacoes', 'IntegracaoController::transacoes');
    $routes->get('integracao/contas', 'IntegracaoController::contas');
    $routes->get('integracao/categorias-contas', 'IntegracaoController::dicionarioCategorias');
    $routes->post('integracao/categorias-contas', 'IntegracaoController::salvarDicionarioCategorias', ['filter' => 'permission:contabil.create,contabil.edit']);
    $routes->post('integracao/importar', 'IntegracaoController::importar', ['filter' => 'permission:contabil.create,contabil.edit']);
    $routes->get('integracao/planos-modelo', 'IntegracaoController::planosModelo');
    $routes->post('integracao/abertura', 'IntegracaoController::abertura', ['filter' => 'permission:contabil.create,contabil.edit']);
    $routes->post('integracao/plano-padrao', 'IntegracaoController::importarPlanoPadrao', ['filter' => 'permission:contabil.create,contabil.edit']);
});

$routes->group('api/v2/contabil', ['namespace' => 'CognitioTechny\Contabil\Infrastructure\CodeIgniter\Controllers', 'filter' => ['permission:contabil.access', 'modulo:contabil']], function ($routes) {
    // Contas
    $routes->get('accounts', 'AccountsController::index');
    $routes->post('accounts', 'AccountsController::create');
    $routes->get('accounts/tree', 'AccountsController::tree');
    $routes->get('accounts/export', 'AccountsController::export');
    $routes->post('accounts/import', 'AccountsController::import');
    $routes->get('accounts/(:num)', 'AccountsController::show/$1');
    $routes->put('accounts/(:num)', 'AccountsController::update/$1');
    $routes->patch('accounts/(:num)', 'AccountsController::update/$1');
    $routes->delete('accounts/(:num)', 'AccountsController::delete/$1');
    $routes->post('accounts/(:num)/move', 'AccountsController::move/$1');
    $routes->post('accounts/(:num)/deactivate', 'AccountsController::deactivate/$1');
    $routes->post('accounts/(:num)/reactivate', 'AccountsController::reactivate/$1');

    // Exercícios e Períodos Fiscais
    $routes->get('fiscal-years', 'FiscalYearsController::index');
    $routes->post('fiscal-years', 'FiscalYearsController::create');
    $routes->get('fiscal-years/(:num)/periods', 'FiscalYearsController::periods/$1');
    $routes->post('fiscal-years/(:num)/close', 'FiscalYearsController::close/$1');
    $routes->post('fiscal-years/(:num)/reopen', 'FiscalYearsController::reopen/$1');
    $routes->post('periods/(:num)/close', 'PeriodsController::close/$1');
    $routes->post('periods/(:num)/reopen', 'PeriodsController::reopen/$1');

    // Lançamentos
    $routes->get('entries', 'EntriesController::index');
    $routes->post('entries', 'EntriesController::create');
    $routes->post('entries/batch', 'EntriesController::batch');
    $routes->get('entries/(:num)', 'EntriesController::show/$1');
    $routes->put('entries/(:num)', 'EntriesController::update/$1');
    $routes->patch('entries/(:num)', 'EntriesController::update/$1');
    $routes->delete('entries/(:num)', 'EntriesController::delete/$1');
    $routes->post('entries/(:num)/post', 'EntriesController::post/$1');
    $routes->post('entries/(:num)/reverse', 'EntriesController::reverse/$1');
    $routes->post('entries/(:num)/settle', 'EntriesController::settle/$1');

    // Livros e Demonstrações Contábeis
    $routes->get('reports/ledger', 'ReportsController::ledger');
    $routes->get('reports/journal', 'ReportsController::journal');
    $routes->get('reports/trial-balance', 'ReportsController::trialBalance');
    $routes->get('reports/income-statement', 'ReportsController::incomeStatement');
    $routes->get('reports/balance-sheet', 'ReportsController::balanceSheet');
    $routes->get('reports/by-source', 'ReportsController::bySource');
});

// ============================================================================
// ROTAS API V2 - CONTRATOS
// ============================================================================
$routes->group('api/v2/contratos', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:contratos.access', 'modulo:contratos']], function ($routes) {
    $routes->get('/', 'Contratos::listar');
    $routes->get('listar', 'Contratos::listar');
    $routes->get('listarJson', 'Contratos::listarJson');
    $routes->get('detalhes/(:num)', 'Contratos::detalhes/$1');
    $routes->get('buscar', 'Contratos::buscar');
    $routes->get('estatisticas', 'Contratos::estatisticas');
    $routes->get('rentabilidade', 'Contratos::rentabilidade');
    $routes->get('proximosAVencer', 'Contratos::proximosAVencer');
    $routes->get('proximosAVencer/(:num)', 'Contratos::proximosAVencer/$1');
    $routes->get('movimentacoes/(:num)', 'Contratos::movimentacoes/$1');
    $routes->post('salvar', 'Contratos::salvar', ['filter' => 'permission:contratos.create']);
    $routes->post('atualizar/(:num)', 'Contratos::atualizar/$1', ['filter' => 'permission:contratos.edit']);
    $routes->delete('excluir/(:num)', 'Contratos::excluir/$1', ['filter' => 'permission:contratos.delete']);
    $routes->post('excluir/(:num)', 'Contratos::excluir/$1', ['filter' => 'permission:contratos.delete']);
    // Arquivos do contrato no Google Drive (subpasta Contratos da pasta do cliente)
    $routes->get('(:num)/arquivos', 'ContratosArquivos::listar/$1');
    $routes->post('(:num)/arquivos', 'ContratosArquivos::enviar/$1', ['filter' => 'permission:contratos.edit']);
    $routes->delete('arquivos/(:num)', 'ContratosArquivos::excluir/$1', ['filter' => 'permission:contratos.delete']);
    $routes->get('exportar', 'Contratos::exportar');
    $routes->get('relatorio', 'Contratos::relatorio');
});

// ============================================================================
// ROTAS API V2 - PRIVACIDADE GRANULAR (acessos a clientes e contratos)
// ============================================================================
$routes->group('api/v2/acessos', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'permission:clientes.access,contratos.access'], function ($routes) {
    $routes->get('profissionais', 'Acessos::profissionais');
    $routes->post('cliente/(:num)/propagar', 'Acessos::propagar/$1');
    $routes->get('(:segment)/(:num)', 'Acessos::listar/$1/$2');
    $routes->post('(:segment)/(:num)/visibilidade', 'Acessos::visibilidade/$1/$2');
    $routes->post('(:segment)/(:num)/usuarios', 'Acessos::conceder/$1/$2');
    $routes->delete('(:segment)/(:num)/usuarios/(:num)', 'Acessos::revogar/$1/$2/$3');
});


// ============================================================================
// ROTAS API V2 - FORNECEDORES
// ============================================================================
$routes->group('api/v2/fornecedores', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:fornecedores.access', 'modulo:fornecedores']], function ($routes) {
    $routes->get('/', 'Fornecedores::index');
    $routes->get('detalhes/(:num)', 'Fornecedores::detalhes/$1');
    $routes->get('estatisticas', 'Fornecedores::estatisticas');
    $routes->get('recentes', 'Fornecedores::recentes');
    $routes->get('buscar', 'Fornecedores::buscar');
    $routes->post('salvar', 'Fornecedores::salvar', ['filter' => 'permission:fornecedores.create']);
    $routes->post('transformar-em-cliente/(:num)', 'Fornecedores::transformarEmCliente/$1', ['filter' => ['permission:clientes.create', 'modulo:clientes']]);
    $routes->put('atualizar/(:num)', 'Fornecedores::atualizar/$1', ['filter' => 'permission:fornecedores.edit']);
    $routes->delete('excluir/(:num)', 'Fornecedores::excluir/$1', ['filter' => 'permission:fornecedores.delete']);
});


// ============================================================================
// ROTAS API V2 - DASHBOARD
// ============================================================================
$routes->group('api/v2/dashboard', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Dashboard::index');
    $routes->get('sidebar-counts', 'Dashboard::getSidebarCounts');
});


// ============================================================================
// ROTAS API V2 - BUSINESS CANVAS
// ============================================================================
$routes->group('api/v2/business-canvas', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'modulo:gestao'], function ($routes) {
    $routes->get('get-canvases', 'BusinessCanvas::ajaxGetCanvases');
    $routes->get('get-canvas/(:num)', 'BusinessCanvas::ajaxGetCanvas/$1');
    $routes->post('create-canvas', 'BusinessCanvas::ajaxCreateCanvas');
    $routes->put('update-canvas/(:num)', 'BusinessCanvas::ajaxUpdateCanvas/$1');
    $routes->delete('delete-canvas/(:num)', 'BusinessCanvas::ajaxDeleteCanvas/$1');
    $routes->post('save-block', 'BusinessCanvas::ajaxSaveBlock');
});

// ============================================================================
// ROTAS API V2 - UTILS / CONSULTAS
// ============================================================================
$routes->group('api/v2/utils', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'auth'], function ($routes) {
    $routes->get('consultaCep/(:any)', 'Utils::consultaCep/$1');
});


// ============================================================================
// ROTAS API V2 - MÓDULOS
// ============================================================================
$routes->group('api/v2/modulos', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'permission:module.access'], function ($routes) {
    $routes->get('/', 'Modulos::index');
    $routes->post('toggleStatus', 'Modulos::toggleStatus', ['filter' => 'permission:module.edit']);
});

// ============================================================================
// ROTAS API V2 - GOOGLE AUTH
// ============================================================================
$routes->group('api/v2/google-auth', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['auth', 'modulo:google']], function ($routes) {
    $routes->get('callback', 'GoogleAuth::callback');
    $routes->get('login', 'GoogleAuth::login');
    $routes->get('url', 'GoogleAuth::getAuthUrl');
});

// Alias legacy routes for Google Auth
$routes->group('googleAuth', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['auth', 'modulo:google']], function ($routes) {
    $routes->get('callback', 'GoogleAuth::callback');
    $routes->get('login', 'GoogleAuth::login');
    $routes->get('url', 'GoogleAuth::getAuthUrl');
});

// ============================================================================
// ROTAS API V2 - DRIVE SYNC
// ============================================================================
$routes->group('api/v2/drive', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['auth', 'modulo:google']], function ($routes) {
    $routes->get('resumo', 'SincronizacaoDrive::resumo');
    $routes->get('jobs', 'SincronizacaoDrive::jobs');
    $routes->get('entidades', 'SincronizacaoDrive::entidades');
    $routes->post('sincronizar-retroativo', 'SincronizacaoDrive::retroativo');
    $routes->get('sincronizar-retroativo', 'SincronizacaoDrive::retroativo'); // compatibilidade
    $routes->post('enfileirar', 'SincronizacaoDrive::enfileirar');
    $routes->post('processar', 'SincronizacaoDrive::processar');
    $routes->post('jobs/reprocessar', 'SincronizacaoDrive::reprocessar');
    $routes->post('jobs/excluir', 'SincronizacaoDrive::excluir');
});
