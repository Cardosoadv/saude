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
$routes->options('api/v2/processos', static function () {});
$routes->options('api/v2/processos/(:any)', static function () {});
$routes->options('api/v2/tarefas', static function () {});
$routes->options('api/v2/tarefas/(:any)', static function () {});
$routes->options('api/v2/usuarios', static function () {});
$routes->options('api/v2/usuarios/(:any)', static function () {});
$routes->options('api/v2/configuracoes', static function () {});
$routes->options('api/v2/configuracoes/(:any)', static function () {});
$routes->options('api/v2/integracao-ia', static function () {});
$routes->options('api/v2/integracao-ia/(:any)', static function () {});
$routes->options('api/v2/argumentos', static function () {});
$routes->options('api/v2/argumentos/(:any)', static function () {});
$routes->options('api/v2/template', static function () {});
$routes->options('api/v2/template/(:any)', static function () {});
$routes->options('api/v2/checklist', static function () {});
$routes->options('api/v2/checklist/(:any)', static function () {});
$routes->options('api/v2/checklist-templates', static function () {});
$routes->options('api/v2/checklist-templates/(:any)', static function () {});
$routes->options('api/v2/procedimentos', static function () {});
$routes->options('api/v2/procedimentos/(:any)', static function () {});
$routes->options('api/v2/clientes', static function () {});
$routes->options('api/v2/clientes/(:any)', static function () {});
$routes->options('api/v2/financeiro', static function () {});
$routes->options('api/v2/financeiro/(:any)', static function () {});
$routes->options('api/v2/contratos', static function () {});
$routes->options('api/v2/contratos/(:any)', static function () {});
$routes->options('api/v2/acessos/(:any)', static function () {});
$routes->options('api/v2/casos', static function () {});
$routes->options('api/v2/casos/(:any)', static function () {});
$routes->options('api/v2/fornecedores', static function () {});
$routes->options('api/v2/fornecedores/(:any)', static function () {});
$routes->options('api/v2/liquidacoes', static function () {});
$routes->options('api/v2/liquidacoes/(:any)', static function () {});
$routes->options('api/v2/indices', static function () {});
$routes->options('api/v2/indices/(:any)', static function () {});
$routes->options('api/v2/objetos', static function () {});
$routes->options('api/v2/objetos/(:any)', static function () {});
$routes->options('api/v2/etiquetas', static function () {});
$routes->options('api/v2/etiquetas/(:any)', static function () {});
$routes->options('api/v2/mensagens', static function () {});
$routes->options('api/v2/mensagens/(:any)', static function () {});
$routes->options('api/v2/anotacoes', static function () {});
$routes->options('api/v2/anotacoes/(:any)', static function () {});
$routes->options('api/v2/calendario-forense', static function () {});
$routes->options('api/v2/calendario-forense/(:any)', static function () {});
$routes->options('api/v2/intimacoes', static function () {});
$routes->options('api/v2/intimacoes/(:any)', static function () {});
$routes->options('api/v2/login', static function () {});
$routes->options('api/v2/esqueci-senha', static function () {});
$routes->options('api/v2/redefinir-senha', static function () {});
$routes->options('api/v2/perfil', static function () {});
$routes->options('api/v2/perfil/(:any)', static function () {});
$routes->options('api/v2/push', static function () {});
$routes->options('api/v2/push/(:any)', static function () {});
$routes->options('api/v2/user/info', static function () {});
$routes->options('api/v2/site', static function () {});
$routes->options('api/v2/site/(:any)', static function () {});
$routes->options('api/v2/contabil', static function () {});
$routes->options('api/v2/contabil/(:any)', static function () {});
$routes->options('api/v2/legal-ops', static function () {});
$routes->options('api/v2/legal-ops/(:any)', static function () {});
$routes->options('api/v2/consultas', static function () {});
$routes->options('api/v2/consultas/(:any)', static function () {});
$routes->options('api/v2/modulos', static function () {});
$routes->options('api/v2/modulos/(:any)', static function () {});

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
// ROTAS API V2 - PROCESSOS
// ============================================================================
$routes->group('api/v2/processos', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:processos.access,processos.exclusive', 'modulo:processos']], function ($routes) {
    $routes->get('/', 'Processos::listarJson');
    $routes->get('listarJson', 'Processos::listarJson');
    $routes->get('ids', 'Processos::listarIdsJson');

    // Importação em lote com conferência (DJEN + DataJud + carteira)
    $routes->post('importacao/ler', 'ProcessosImportacao::ler', ['filter' => 'permission:processos.create']);
    $routes->post('importacao/consultar', 'ProcessosImportacao::consultar', ['filter' => 'permission:processos.create']);
    $routes->post('importacao/pesquisar-parte', 'ProcessosImportacao::pesquisarParte', ['filter' => 'permission:processos.create']);
    $routes->post('importacao/confirmar', 'ProcessosImportacao::confirmar', ['filter' => 'permission:processos.create']);
    $routes->get('importacao/modelo', 'ProcessosImportacao::modelo', ['filter' => 'permission:processos.create']);

    $routes->get('detalhes/(:num)', 'Processos::detalhesJson/$1');
    $routes->post('(:num)/sincronizar', 'Processos::sincronizarJson/$1');
    $routes->get('(:num)/arquivos-drive', 'Processos::arquivosDrive/$1');
    $routes->get('buscar', 'Processos::buscar');
    $routes->get('buscar/(:any)', 'Processos::buscar/$1');
    $routes->get('stats', 'Processos::statsJson');
    $routes->get('widgets-sidebar', 'Processos::widgetsSidebar');
    $routes->get('etiquetas', 'Processos::etiquetas');
    $routes->get('ramos', 'Processos::ramos');
    $routes->get('clientes-busca', 'Processos::clientesBusca');
    $routes->get('contratos-busca', 'Processos::contratosBusca');
    $routes->get('processosdocliente/(:num)', 'Processos::processosDoCliente/$1');
    $routes->get('processosDoObjeto/(:num)', 'Processos::processosDoObjeto/$1', ['filter' => ['modulo:objetos', 'permission:objetos.access']]);
    $routes->get('processosmovimentados/(:num)', 'Processos::processosMovimentados/$1');
    $routes->get('gerarPeticao/(:num)', 'Processos::gerarPeticao/$1');
    $routes->get('exportarPDF/(:num)', 'Processos::exportarPDF/$1');
    $routes->get('exportarCSV', 'Processos::exportarCSV');
    $routes->get('relatorioCPC25', 'Processos::relatorioCPC25');
    $routes->get('relatorioValorCausa', 'Processos::relatorioValorCausa');
    $routes->get('subestabelecimento/(:num)', 'Processos::subestabelecimento/$1');
    $routes->get('subestabelecimentoLote', 'Processos::subestabelecimentoLote');

    $routes->post('salvar', 'Processos::salvar');
    $routes->post('adicionarEtiqueta', 'Processos::adicionarEtiqueta');
    $routes->post('removerEtiqueta', 'Processos::removerEtiqueta');
    $routes->post('adicionarParte', 'Processos::adicionarParte');
    $routes->post('removerParte', 'Processos::removerParte');
    $routes->post('adicionarAnotacao', 'Processos::adicionarAnotacao');
    $routes->post('removerAnotacao', 'Processos::removerAnotacao');
    $routes->post('salvarMovimento', 'Processos::salvarMovimento');
    $routes->post('atualizarParteRepresentada', 'Processos::atualizarParteRepresentada');
    $routes->post('salvarVinculo', 'Processos::salvarVinculo');
    $routes->post('removerVinculo/(:num)', 'Processos::removerVinculo/$1');
    $routes->post('excluir/(:num)', 'Processos::excluir/$1');
    $routes->post('excluirEmLote', 'Processos::excluirEmLote');
    $routes->post('arquivarEmLote', 'Processos::arquivarEmLote');
    $routes->post('desarquivarEmLote', 'Processos::desarquivarEmLote');
    $routes->post('arquivar/(:num)', 'Processos::arquivar/$1');
    $routes->post('desarquivar/(:num)', 'Processos::desarquivar/$1');
    $routes->post('encerrar/(:num)', 'Processos::encerrar/$1');
    $routes->post('reabrir/(:num)', 'Processos::reabrir/$1');
    $routes->post('edicaorapida/(:num)', 'Processos::edicaorapida/$1');
    $routes->post('uploadDrive/(:num)', 'Processos::uploadDrive/$1');
    $routes->post('etiquetaemlote', 'Processos::etiquetaEmLote');
    $routes->post('vincularcontrato', 'Processos::vincularContrato');
    $routes->post('desvincularcontrato', 'Processos::desvincularContrato');
    $routes->patch('atribuir/(:num)', 'Processos::atribuir/$1');


    $routes->post('vincularObjeto', 'Processos::vincularObjeto', ['filter' => ['modulo:objetos', 'permission:objetos.edit']]);
    $routes->post('salvarObjeto', 'Processos::salvarObjeto', ['filter' => ['modulo:objetos', 'permission:objetos.edit']]);
    $routes->post('desvincularObjeto/(:num)/(:num)', 'Processos::desvincularObjeto/$1/$2', ['filter' => ['modulo:objetos', 'permission:objetos.edit']]);

    // Davinci - IA & Discovery
    $routes->get('analiseIA/(:num)', 'Processos::analiseIA/$1');
    $routes->get('sugerirArgumentos/(:num)', 'Processos::sugerirArgumentos/$1');
    $routes->get('interpretarMovimento', 'Processos::interpretarMovimento');
    $routes->get('analisarSentimentoMovimento/(:num)', 'Processos::analisarSentimentoMovimento/$1');
    $routes->get('verificarConflito', 'Processos::verificarConflito');

    // Relatório Final de Encerramento (IA)
    $routes->get('relatorioFinal/(:num)', 'ProcessoRelatorioController::get/$1');
    $routes->post('relatorioFinal/(:num)/save', 'ProcessoRelatorioController::save/$1');
    $routes->post('relatorioFinal/(:num)/regenerate', 'ProcessoRelatorioController::regenerate/$1');

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
// ROTAS API V2 - CALENDÁRIO FORENSE (contagem de prazos em dias úteis)
// ============================================================================
$routes->group('api/v2/calendario-forense', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'auth'], function ($routes) {
    $routes->get('calcular', 'CalendarioForense::calcular');
    $routes->get('memoria/tarefa/(:num)', 'CalendarioForense::memoriaTarefa/$1', ['filter' => ['permission:tarefas.access', 'modulo:tarefas']]);
    $routes->get('memoria/intimacao/(:num)', 'CalendarioForense::memoriaIntimacao/$1', ['filter' => ['permission:intimacoes.access', 'modulo:intimacoes']]);

    $routes->group('', ['filter' => 'permission:admin.settings'], function ($routes) {
        $routes->get('feriados', 'CalendarioForense::listarFeriados');
        $routes->post('feriados', 'CalendarioForense::salvarFeriado');
        $routes->put('feriados/(:num)', 'CalendarioForense::salvarFeriado/$1');
        $routes->delete('feriados/(:num)', 'CalendarioForense::excluirFeriado/$1');
        $routes->get('suspensoes', 'CalendarioForense::listarSuspensoes');
        $routes->post('suspensoes', 'CalendarioForense::salvarSuspensao');
        $routes->put('suspensoes/(:num)', 'CalendarioForense::salvarSuspensao/$1');
        $routes->delete('suspensoes/(:num)', 'CalendarioForense::excluirSuspensao/$1');
    });
});

// ============================================================================
// ROTAS API V2 - ARGUMENTOS SUGERIDOS
// ============================================================================
$routes->group('api/v2/argumentos', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:processos.access', 'modulo:processos']], function ($routes) {
    $routes->get('/', 'Argumentos::index');
    $routes->get('ramos-options', 'Argumentos::ramosOptions');
    $routes->get('processo/(:num)', 'Argumentos::porProcesso/$1');
    $routes->get('caso/(:num)', 'Argumentos::porCaso/$1');
    $routes->post('lote', 'Argumentos::salvarLote');
    $routes->post('/', 'Argumentos::criar');
    $routes->put('(:num)', 'Argumentos::atualizar/$1');
    $routes->patch('(:num)/avaliar', 'Argumentos::avaliar/$1');
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
    $routes->get('getAdvogados', 'Usuarios::getAdvogados');
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
// ROTAS API V2 - TEMPLATE
// ============================================================================
$routes->group('api/v2/template', ['namespace' => 'App\Controllers\ApiV2'], function ($routes) {
    $routes->get('index', 'TemplateData::index');
    $routes->get('messages', 'TemplateData::messages');
    $routes->get('tasks', 'TemplateData::tasks');
    $routes->get('expenses', 'TemplateData::expenses');
    $routes->get('notifications', 'TemplateData::notifications');
});

// ============================================================================
// ROTAS API V2 - CHECKLISTS
// ============================================================================
$routes->group('api/v2/checklist', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['auth', 'modulo:checklist']], function ($routes) {
    $routes->get('getByEntidade/(:any)/(:num)', 'Checklist::getByEntidade/$1/$2');
    $routes->get('entidade/(:any)/(:num)', 'Checklist::getByEntidade/$1/$2');
    $routes->post('aplicarTemplate', 'Checklist::aplicarTemplate');
    $routes->post('aplicar', 'Checklist::aplicarTemplate');
    $routes->post('marcarItem', 'Checklist::marcarItem');
    $routes->post('marcar-item', 'Checklist::marcarItem');
    $routes->post('adicionarItemAvulso', 'Checklist::adicionarItemAvulso');
    $routes->post('adicionar-item-avulso', 'Checklist::adicionarItemAvulso');
    $routes->post('concluir/(:num)', 'Checklist::concluir/$1');
    $routes->get('getTemplatesDisponiveis/(:any)/(:num)', 'Checklist::getTemplatesDisponiveis/$1/$2');
    $routes->get('templates/(:any)/(:num)', 'Checklist::getTemplatesDisponiveis/$1/$2');
    $routes->post('excluir/(:num)', 'Checklist::excluir/$1');
    $routes->delete('excluir/(:num)', 'Checklist::excluir/$1');
    $routes->get('sugerirIA', 'Checklist::sugerirIA');
    $routes->get('sugerirComIA/(:any)/(:num)', 'Checklist::sugerirIA/$1/$2');
    $routes->post('toggleItem/(:num)', 'Checklist::toggleItem/$1');
    $routes->post('salvarSugestoesIA', 'Checklist::salvarSugestoesIA');
    $routes->post('remover/(:num)', 'Checklist::excluir/$1');

    // Edição Inline
    $routes->post('criarAvulso', 'Checklist::criarAvulso');
    $routes->post('atualizar/(:num)', 'Checklist::atualizar/$1');
    $routes->post('atualizarItem/(:num)', 'Checklist::atualizarItem/$1');
    $routes->delete('excluirItem/(:num)', 'Checklist::excluirItem/$1');
});

// ============================================================================
// ROTAS API V2 - CHECKLIST TEMPLATES
// ============================================================================
$routes->group('api/v2/checklist-templates', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['auth', 'modulo:checklist']], function ($routes) {
    $routes->get('/', 'ChecklistTemplates::listar');
    $routes->get('detalhes/(:num)', 'ChecklistTemplates::detalhes/$1');
    $routes->post('salvar', 'ChecklistTemplates::salvar');
    $routes->delete('excluir/(:num)', 'ChecklistTemplates::excluir/$1');
    $routes->post('excluir/(:num)', 'ChecklistTemplates::excluir/$1');
    $routes->post('duplicar/(:num)', 'ChecklistTemplates::duplicar/$1');

    $routes->post('adicionarSecao/(:num)', 'ChecklistTemplates::adicionarSecao/$1');
    $routes->post('adicionarItem/(:num)', 'ChecklistTemplates::adicionarItem/$1');

    $routes->post('reordenarSecoes', 'ChecklistTemplates::reordenarSecoes');
    $routes->post('reordenarItens', 'ChecklistTemplates::reordenarItens');

    $routes->get('procedimentos', 'ChecklistTemplates::getProcedimentos');
});

// ============================================================================
// ROTAS API V2 - PROCEDIMENTOS (CHECKLIST)
// ============================================================================
$routes->group('api/v2/procedimentos', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['auth', 'modulo:checklist']], function ($routes) {
    $routes->get('/', 'Procedimentos::listar');
    $routes->get('detalhes/(:num)', 'Procedimentos::detalhes/$1');
    $routes->post('salvar', 'Procedimentos::salvar');
    $routes->delete('excluir/(:num)', 'Procedimentos::excluir/$1');
    $routes->post('excluir/(:num)', 'Procedimentos::excluir/$1');
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
    $routes->get('anotacoes/(:num)', 'Clientes::anotacoes/$1');
    $routes->get('movimentacoes/(:num)', 'Clientes::movimentacoes/$1', ['filter' => ['modulo:financeiro', 'permission:financeiro.access']]);
    $routes->get('intimacoes/(:num)', 'Clientes::intimacoes/$1', ['filter' => ['modulo:intimacoes', 'permission:intimacoes.access']]);
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
    $routes->get('leads/ramos', 'CrmLeads::ramos');
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
    $routes->get('ramos', 'Contratos::ramos');
    $routes->get('estatisticas', 'Contratos::estatisticas');
    $routes->get('rentabilidade', 'Contratos::rentabilidade');
    $routes->get('proximosAVencer', 'Contratos::proximosAVencer');
    $routes->get('proximosAVencer/(:num)', 'Contratos::proximosAVencer/$1');
    $routes->get('movimentacoes/(:num)', 'Contratos::movimentacoes/$1');
    $routes->post('salvar', 'Contratos::salvar', ['filter' => 'permission:contratos.create']);
    $routes->post('atualizar/(:num)', 'Contratos::atualizar/$1', ['filter' => 'permission:contratos.edit']);
    $routes->delete('excluir/(:num)', 'Contratos::excluir/$1', ['filter' => 'permission:contratos.delete']);
    $routes->post('excluir/(:num)', 'Contratos::excluir/$1', ['filter' => 'permission:contratos.delete']);
    $routes->post('vincularProcesso', 'Contratos::vincularProcesso', ['filter' => 'permission:contratos.edit']);
    $routes->post('desvincularProcesso', 'Contratos::desvincularProcesso', ['filter' => 'permission:contratos.edit']);
    // Arquivos do contrato no Google Drive (subpasta Contratos da pasta do cliente)
    $routes->get('(:num)/arquivos', 'ContratosArquivos::listar/$1');
    $routes->post('(:num)/arquivos', 'ContratosArquivos::enviar/$1', ['filter' => 'permission:contratos.edit']);
    $routes->delete('arquivos/(:num)', 'ContratosArquivos::excluir/$1', ['filter' => 'permission:contratos.delete']);
    $routes->get('exportar', 'Contratos::exportar');
    $routes->get('relatorio', 'Contratos::relatorio');
});

// ============================================================================
// ROTAS API V2 - PRIVACIDADE GRANULAR (acessos a processos, clientes e contratos)
// ============================================================================
$routes->group('api/v2/acessos', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'permission:processos.access,clientes.access,contratos.access'], function ($routes) {
    $routes->get('advogados', 'Acessos::advogados');
    $routes->post('cliente/(:num)/propagar', 'Acessos::propagar/$1');
    $routes->get('(:segment)/(:num)', 'Acessos::listar/$1/$2');
    $routes->post('(:segment)/(:num)/visibilidade', 'Acessos::visibilidade/$1/$2');
    $routes->post('(:segment)/(:num)/usuarios', 'Acessos::conceder/$1/$2');
    $routes->delete('(:segment)/(:num)/usuarios/(:num)', 'Acessos::revogar/$1/$2/$3');
});

// ============================================================================
// ROTAS API V2 - CASOS
// ============================================================================
$routes->group('api/v2/casos', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:casos.access', 'modulo:casos']], function ($routes) {
    $routes->get('/', 'Casos::listar');
    $routes->get('detalhes/(:num)', 'Casos::detalhes/$1');
    $routes->get('recentes', 'Casos::recentes');
    $routes->get('estatisticas', 'Casos::estatisticas');
    $routes->get('porCliente/(:num)', 'Casos::porCliente/$1');
    $routes->get('timeline/(:num)', 'Casos::timeline/$1');
    $routes->get('buscar', 'Casos::buscar');
    $routes->post('salvar', 'Casos::salvar', ['filter' => 'permission:casos.create']);
    $routes->post('atualizar/(:num)', 'Casos::atualizar/$1', ['filter' => 'permission:casos.edit']);
    $routes->delete('excluir/(:num)', 'Casos::excluir/$1', ['filter' => 'permission:casos.delete']);
    $routes->post('excluirEmLote', 'Casos::excluirEmLote', ['filter' => 'permission:casos.delete']);
    $routes->post('acaoEmLote', 'Casos::acaoEmLote', ['filter' => 'permission:casos.edit']);
    $routes->post('atualizarStatus', 'Casos::atualizarStatus', ['filter' => 'permission:casos.edit']);
    $routes->post('duplicar/(:num)', 'Casos::duplicar/$1', ['filter' => 'permission:casos.create']);
    $routes->post('transformar/(:num)', 'Casos::transformar/$1', ['filter' => 'permission:casos.edit']);
    $routes->post('vincularProcessoExistente/(:num)', 'Casos::vincularProcessoExistente/$1', ['filter' => 'permission:casos.edit']);
    $routes->post('gerarEstudoCaso/(:num)', 'Casos::gerarEstudoCaso/$1');
    $routes->post('salvarEstudoCaso/(:num)', 'Casos::salvarEstudoCaso/$1', ['filter' => 'permission:casos.edit']);
    $routes->post('validarNumeroProcesso', 'Casos::validarNumeroProcesso');
    $routes->get('exportarCsv', 'Casos::exportarCsv');
    $routes->post('uploadDrive/(:num)', 'Casos::uploadDrive/$1', ['filter' => 'permission:casos.edit']);
    $routes->post('vincular-contrato/(:num)', 'Casos::vincularContrato/$1', ['filter' => 'permission:casos.edit']);
    $routes->delete('vincular-contrato/(:num)', 'Casos::desvincularContrato/$1', ['filter' => 'permission:casos.edit']);
    $routes->post('vincular-processo/(:num)', 'Casos::vincularProcesso/$1', ['filter' => 'permission:casos.edit']);
    $routes->post('historico/(:num)', 'Casos::adicionarHistorico/$1', ['filter' => 'permission:casos.edit']);
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
// ROTAS API V2 - LIQUIDAÇÕES
// ============================================================================
$routes->group('api/v2/liquidacoes', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:liquidacoes.access,liquidacoes.exclusive', 'modulo:processos']], function ($routes) {
    $routes->get('/', 'Liquidacoes::index');
    $routes->get('processo/(:num)', 'Liquidacoes::porProcesso/$1');
    $routes->get('show/(:num)', 'Liquidacoes::show/$1');
    $routes->get('historico/(:num)', 'Liquidacoes::historico/$1');
    $routes->get('memoria/(:num)', 'Liquidacoes::memoria/$1');
    $routes->get('pdf/(:num)', 'Liquidacoes::pdf/$1');
    $routes->get('exportar/(:num)', 'Liquidacoes::exportar/$1');
    $routes->post('sugerirCriterio/(:num)', 'Liquidacoes::sugerirCriterio/$1', ['filter' => 'permission:liquidacoes.edit']);
    $routes->post('simular', 'Liquidacoes::simular');
    $routes->post('recalcular/(:num)', 'Liquidacoes::recalcular/$1', ['filter' => 'permission:liquidacoes.edit']);
    $routes->post('store', 'Liquidacoes::store', ['filter' => 'permission:liquidacoes.create']);
    $routes->put('update/(:num)', 'Liquidacoes::update/$1', ['filter' => 'permission:liquidacoes.edit']);
    $routes->delete('delete/(:num)', 'Liquidacoes::delete/$1', ['filter' => 'permission:liquidacoes.delete']);

    // Items
    $routes->post('storeItem', 'Liquidacoes::storeItem', ['filter' => 'permission:liquidacoes.edit']);
    $routes->put('updateItem/(:num)', 'Liquidacoes::updateItem/$1', ['filter' => 'permission:liquidacoes.edit']);
    $routes->delete('deleteItem/(:num)', 'Liquidacoes::deleteItem/$1', ['filter' => 'permission:liquidacoes.edit']);

    // Pagamentos
    $routes->post('storePagamento', 'Liquidacoes::storePagamento', ['filter' => 'permission:liquidacoes.edit']);
    $routes->put('updatePagamento/(:num)', 'Liquidacoes::updatePagamento/$1', ['filter' => 'permission:liquidacoes.edit']);
    $routes->delete('deletePagamento/(:num)', 'Liquidacoes::deletePagamento/$1', ['filter' => 'permission:liquidacoes.edit']);
    $routes->post('gerarParcelas/(:num)', 'Liquidacoes::gerarParcelas/$1', ['filter' => 'permission:liquidacoes.edit']);
    $routes->post('baixarPagamento/(:num)', 'Liquidacoes::baixarPagamento/$1', ['filter' => 'permission:liquidacoes.edit']);
    $routes->post('cancelarPagamento/(:num)', 'Liquidacoes::cancelarPagamento/$1', ['filter' => 'permission:liquidacoes.edit']);

    // Documentos
    $routes->post('storeDocumento', 'Liquidacoes::storeDocumento', ['filter' => 'permission:liquidacoes.edit']);
    $routes->post('uploadDocumento/(:num)', 'Liquidacoes::uploadDocumento/$1', ['filter' => 'permission:liquidacoes.edit']);
    $routes->delete('deleteDocumento/(:num)', 'Liquidacoes::deleteDocumento/$1', ['filter' => 'permission:liquidacoes.edit']);
});

// ============================================================================
// ROTAS API V2 - ÍNDICES ECONÔMICOS (liquidação)
// ============================================================================
$routes->group('api/v2/indices', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:liquidacoes.access,liquidacoes.exclusive', 'modulo:processos']], function ($routes) {
    $routes->get('/', 'Indices::index');
    $routes->get('fator', 'Indices::fator');
    $routes->get('(:segment)/valores', 'Indices::valores/$1');
    $routes->get('(:segment)/lacunas', 'Indices::lacunas/$1');

    $routes->group('', ['filter' => 'permission:admin.settings'], function ($routes) {
        $routes->post('/', 'Indices::salvar');
        $routes->put('(:num)', 'Indices::salvar/$1');
        $routes->post('atualizar', 'Indices::atualizar');
        $routes->post('(:segment)/valores', 'Indices::lancarValor/$1');
        $routes->post('(:segment)/csv', 'Indices::importarCsv/$1');
    });
});

// ============================================================================
// ROTAS API V2 - OBJETOS
// ============================================================================
$routes->group('api/v2/objetos', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:objetos.access', 'modulo:objetos']], function ($routes) {
    $routes->get('/', 'Objetos::index');
    $routes->get('detalhes/(:num)', 'Objetos::detalhes/$1');
    $routes->get('buscar', 'Objetos::buscar');
    $routes->get('existeObjetoCodInterno/(:any)', 'Objetos::existeObjetoCodInterno/$1');
    $routes->get('existeObjetoInscricao/(:any)', 'Objetos::existeObjetoInscricao/$1');
    $routes->post('salvar', 'Objetos::salvar', ['filter' => 'permission:objetos.create,objetos.edit']);
    $routes->put('atualizar/(:num)', 'Objetos::atualizar/$1', ['filter' => 'permission:objetos.edit']);
    $routes->delete('excluir/(:num)', 'Objetos::excluir/$1', ['filter' => 'permission:objetos.delete']);
    $routes->get('exportar', 'Objetos::exportar');
    $routes->get('relatorioProcessosVinculados', 'Objetos::relatorioProcessosVinculados');
});

// ============================================================================
// ROTAS API V2 - ETIQUETAS
// ============================================================================
$routes->group('api/v2/etiquetas', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'permission:etiquetas.access'], function ($routes) {
    $routes->get('/', 'Etiquetas::index');
    $routes->get('listar', 'Etiquetas::listar');
    $routes->get('detalhes/(:num)', 'Etiquetas::detalhes/$1');
    $routes->post('salvar', 'Etiquetas::store', ['filter' => 'permission:etiquetas.create']);
    $routes->put('atualizar/(:num)', 'Etiquetas::update/$1', ['filter' => 'permission:etiquetas.edit']);
    $routes->delete('excluir/(:num)', 'Etiquetas::delete/$1', ['filter' => 'permission:etiquetas.delete']);
    $routes->post('adicionar', 'Etiquetas::adicionar', ['filter' => 'permission:etiquetas.create']);
    $routes->post('adicionarEmLote', 'Etiquetas::adicionarEmLote', ['filter' => 'permission:etiquetas.edit']);
    $routes->post('removerEmLote', 'Etiquetas::removerEmLote', ['filter' => 'permission:etiquetas.edit']);
});

// ============================================================================
// ROTAS API V2 - MENSAGENS / NOTIFICAÇÕES
// ============================================================================
$routes->group('api/v2/mensagens', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'permission:mensagens.access'], function ($routes) {
    $routes->get('/', 'Mensagens::index');
    $routes->get('naoLidas', 'Mensagens::naoLidas');
    $routes->get('detalhes/(:num)', 'Mensagens::detalhes/$1');
    $routes->get('classificarUrgencia/(:num)', 'Mensagens::classificarUrgencia/$1');
    $routes->get('sugerirResposta/(:num)', 'Mensagens::sugerirResposta/$1');
    $routes->post('salvar', 'Mensagens::salvar', ['filter' => 'permission:mensagens.create']);
    $routes->post('marcarLida/(:num)', 'Mensagens::marcarLida/$1', ['filter' => 'permission:mensagens.edit']);
    $routes->post('marcarTodasLidas', 'Mensagens::marcarTodasLidas', ['filter' => 'permission:mensagens.edit']);
    $routes->delete('excluir/(:num)', 'Mensagens::excluir/$1', ['filter' => 'permission:mensagens.delete']);
});

// ============================================================================
// ROTAS API V2 - ANOTAÇÕES
// ============================================================================
$routes->group('api/v2/anotacoes', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'permission:anotacoes.access'], function ($routes) {
    $routes->get('/', 'Anotacoes::index');
    $routes->get('listar', 'Anotacoes::listar');
    $routes->get('listar/(:any)', 'Anotacoes::listar/$1');
    $routes->get('detalhes/(:num)', 'Anotacoes::detalhes/$1');
    $routes->post('salvar', 'Anotacoes::salvar', ['filter' => 'permission:anotacoes.create']);
    $routes->put('atualizar/(:num)', 'Anotacoes::atualizar/$1', ['filter' => 'permission:anotacoes.edit']);
    $routes->delete('excluir/(:num)', 'Anotacoes::excluir/$1', ['filter' => 'permission:anotacoes.delete']);
});

// ============================================================================
// ROTAS API V2 - DASHBOARD
// ============================================================================
$routes->group('api/v2/dashboard', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Dashboard::index');
    $routes->get('sidebar-counts', 'Dashboard::getSidebarCounts');
});

// ============================================================================
// ROTAS API V2 - LEGAL OPS
// ============================================================================
$routes->group('api/v2/legal-ops', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['auth', 'modulo:gestao']], function ($routes) {
    $routes->get('dashboard', 'LegalOps::dashboard');
    $routes->get('export', 'LegalOps::export');
});

// ============================================================================
// ROTAS API V2 - BUSINESS CANVAS
// ============================================================================
$routes->group('api/v2/business-canvas', ['namespace' => 'App\Controllers\ApiV2', 'filter' => 'modulo:gestao'], function ($routes) {
    $routes->get('/', 'BusinessCanvas::index');
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
// ROTAS API V2 - CONSULTAS (CPF / ENDEREÇO - API BRASIL)
// ============================================================================
$routes->group('api/v2/consultas', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:consultas.access', 'modulo:gestao']], function ($routes) {
    $routes->post('cpf', 'Consultas::cpf');
    $routes->get('cpf/(:any)', 'Consultas::cpf/$1');
});

// ============================================================================
// ROTAS API V2 - EXPORTAÇÃO / SINCRONIZAÇÃO
// ============================================================================
$routes->group('api/v2/exportacao', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:exportacao.access', 'modulo:exportacao']], function ($routes) {
    $routes->get('/', 'Exportacao::index');
    $routes->post('sincronizarProcessos', 'Exportacao::sincronizarProcessos', ['filter' => 'permission:exportacao.create']);
    $routes->post('sincronizarIntimacoes', 'Exportacao::sincronizarIntimacoes', ['filter' => 'permission:exportacao.create']);
    $routes->post('sincronizarMovimentos', 'Exportacao::sincronizarMovimentos', ['filter' => 'permission:exportacao.create']);
    $routes->get('exportarListaDeProcessos', 'Exportacao::exportarListaDeProcessos');
});

// ============================================================================
// ROTAS API V2 - INTIMAÇÕES
// ============================================================================
$routes->group('api/v2/intimacoes', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:intimacoes.access', 'modulo:intimacoes']], function ($routes) {
    $routes->get('/', 'Intimacoes::listar');
    $routes->get('processo/(:any)', 'Intimacoes::processo/$1');
    $routes->get('detalhes/(:num)', 'Intimacoes::detalhes/$1');
    $routes->get('navegacao/(:num)', 'Intimacoes::navegacao/$1');
    $routes->get('tipos-tratamento', 'Intimacoes::tiposTratamento');
    $routes->post('tratar', 'Intimacoes::tratar', ['filter' => 'permission:intimacoes.edit']);
    $routes->post('tratarEmLote', 'Intimacoes::tratarEmLote', ['filter' => 'permission:intimacoes.edit']);
    $routes->post('adicionar', 'Intimacoes::adicionar', ['filter' => 'permission:intimacoes.create']);
    $routes->post('sincronizar', 'Intimacoes::sincronizar', ['filter' => 'permission:intimacoes.create']);
    $routes->get('urlsSincronizacao', 'Intimacoes::urlsSincronizacao', ['filter' => 'permission:intimacoes.create']);
    $routes->post('processarIntimacoes', 'Intimacoes::processarIntimacoes', ['filter' => 'permission:intimacoes.create']);
    $routes->get('receberIntimacoes', 'Intimacoes::receberIntimacoes', ['filter' => 'permission:intimacoes.create']);
    $routes->get('intimacoesporperiodo/(:num)', 'Intimacoes::intimacoesPorPeriodo/$1');
});

// ============================================================================
// ROTAS API V2 - MOVIMENTOS
// ============================================================================
$routes->group('api/v2/movimentos', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:movimentos.access', 'modulo:processos']], function ($routes) {
    $routes->post('receberMovimentos/(:any)', 'Movimentos::receberMovimentos/$1', ['filter' => 'permission:movimentos.create']);
    $routes->post('adicionar', 'Movimentos::adicionar', ['filter' => 'permission:movimentos.create']);
    $routes->post('processarMovimentosDatajud', 'Movimentos::processarMovimentosDatajud', ['filter' => 'permission:movimentos.create']);
    $routes->post('receberLocal', 'Movimentos::receberLocal', ['filter' => 'permission:movimentos.create']);
});

// ============================================================================
// ROTAS API V2 - MONITORAR
// ============================================================================
$routes->group('api/v2/monitorar', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:monitorar.access', 'modulo:processos']], function ($routes) {
    $routes->get('/', 'Monitorar::index');
    $routes->post('receberMovimentos', 'Monitorar::ReceberMovimentos', ['filter' => 'permission:monitorar.create']);
    $routes->post('monitorarProcessos', 'Monitorar::MonitorarProcessos', ['filter' => 'permission:monitorar.create']);
    $routes->post('incluirProcessos', 'Monitorar::incluirProcessos', ['filter' => 'permission:monitorar.create']);
});

// ============================================================================
// ROTAS API V2 - BUSCA AUTOMÁTICA
// ============================================================================
$routes->group('api/v2/buscarProcessos', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:processos.access', 'modulo:processos']], function ($routes) {
    $routes->get('/', 'BuscarProcessos::index');
    $routes->post('salvar', 'BuscarProcessos::salvar', ['filter' => 'permission:processos.create,processos.edit']);
    $routes->post('receberLocal', 'BuscarProcessos::receberLocal', ['filter' => 'permission:processos.create']);
    $routes->post('importar', 'BuscarProcessos::importar', ['filter' => 'permission:processos.create']);
});

// ============================================================================
// ROTAS API V2 - CONHECIMENTO (KNOWLEDGE HUB)
// ============================================================================
$routes->group('api/v2/conhecimento', ['namespace' => 'App\Controllers\ApiV2', 'filter' => ['permission:conhecimento.access', 'modulo:conhecimento']], function ($routes) {
    $routes->get('/', 'Conhecimento::index');
    $routes->get('buscar', 'Conhecimento::buscar');

    // Banco de Argumentos IA (mesmo controller usado por api/v2/argumentos, exposto aqui
    // para que o hub de Conhecimento funcione inteiramente com conhecimento.access,
    // sem depender de processos.access - ver issue #603)
    $routes->get('argumentos', 'Argumentos::index');

    // Favoritos
    $routes->get('favoritos', 'Conhecimento::favoritos');
    $routes->get('favoritos/status', 'Conhecimento::favoritoStatus');
    $routes->post('favoritos', 'Conhecimento::favoritar');
    $routes->put('favoritos/(:num)', 'Conhecimento::atualizarFavorito/$1');
    $routes->delete('favoritos/(:num)', 'Conhecimento::desfavoritar/$1');

    // Ramos do Direito
    $routes->get('ramos', 'RamosDireito::index');
    $routes->post('ramos/salvar', 'RamosDireito::salvar', ['filter' => 'permission:conhecimento.edit']);
    $routes->put('ramos/atualizar/(:num)', 'RamosDireito::atualizar/$1', ['filter' => 'permission:conhecimento.edit']);
    $routes->delete('ramos/deletar/(:num)', 'RamosDireito::deletar/$1', ['filter' => 'permission:conhecimento.delete']);

    // Modelos de Documentos
    $routes->get('modelos', 'Modelos::index');
    $routes->get('modelos/placeholders', 'Modelos::placeholders');
    $routes->get('modelos/ramos-options', 'Modelos::ramosOptions');
    $routes->get('modelos/(:num)', 'Modelos::exibir/$1');
    $routes->post('modelos/salvar', 'Modelos::salvar', ['filter' => 'permission:conhecimento.edit']);
    $routes->put('modelos/atualizar/(:num)', 'Modelos::atualizar/$1', ['filter' => 'permission:conhecimento.edit']);
    $routes->delete('modelos/deletar/(:num)', 'Modelos::deletar/$1', ['filter' => 'permission:conhecimento.delete']);

    // Documentos Gerados a partir de Modelos
    $routes->group('documentos-gerados', function ($routes) {
        $routes->get('/', 'DocumentosGerados::index');
        $routes->get('entidade/(processo|caso)/(:num)', 'DocumentosGerados::porEntidade/$1/$2');
        $routes->get('(:num)', 'DocumentosGerados::exibir/$1');
        $routes->post('iniciar', 'DocumentosGerados::iniciar', ['filter' => 'permission:conhecimento.modelos.gerar']);
        $routes->put('(:num)', 'DocumentosGerados::salvar/$1', ['filter' => 'permission:conhecimento.modelos.gerar']);
        $routes->post('(:num)/finalizar', 'DocumentosGerados::finalizar/$1', ['filter' => 'permission:conhecimento.modelos.gerar']);
        $routes->delete('(:num)', 'DocumentosGerados::deletar/$1', ['filter' => 'permission:conhecimento.modelos.gerar']);
    });
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
