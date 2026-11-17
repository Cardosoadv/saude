<?php
// Caminho para assets compartilhados (CSS, JS frameworks)
define('SHARED_ASSETS_PATH', FCPATH . '../public/');

// Caminho para assets específicos do tenant (logos, uploads)
define('TENANT_ASSETS_PATH', FCPATH . 'assets/');

/**
 * Helper interno para obter a versão do arquivo com cache estático por requisição.
 */
function _get_asset_version(string $filePath): ?int
{
    static $versions = [];

    if (array_key_exists($filePath, $versions)) {
        return $versions[$filePath];
    }

    if (file_exists($filePath)) {
        $versions[$filePath] = filemtime($filePath);
        } else {
        $versions[$filePath] = null;
    }

    return $versions[$filePath];
}

/**
 * Retorna URL para asset compartilhado
 */
/**
 * Retorna URL para asset compartilhado
 */
function shared_asset(string $path, bool $withVersion = true)
{
    // Obtém a base URL atual (ex: http://localhost/processos/crbio04)
    $baseUrl = rtrim(base_url(), '/');

    // Lista de tenants conhecidos para remover da URL
    $tenants = ['crbio04', 'cb'];
    $pattern = '/\/(' . implode('|', $tenants) . ')$/';

    // Remove o tenant do final da URL se existir
    $baseUrl = preg_replace($pattern, '', $baseUrl);

    // Monta a URL para a pasta public
    $url = $baseUrl . '/public/' . ltrim($path, '/');

    if ($withVersion) {
        // CORREÇÃO: Usa ROOTPATH para garantir o caminho físico correto, independente do tenant
        $filePath = ROOTPATH . 'public/' . ltrim($path, '/');
        $version  = _get_asset_version($filePath);

        if ($version !== null) {
            $url .= '?v=' . $version;
        }
    }

    return $url;
}

/**
 * Retorna URL para asset do tenant
 */
function tenant_asset(string $path, bool $withVersion = false)
{
    $url = base_url('assets/' . ltrim($path, '/'));

    if ($withVersion) {
        $filePath = TENANT_ASSETS_PATH . ltrim($path, '/');
        $version  = _get_asset_version($filePath);

        if ($version !== null) {
            $url .= '?v=' . $version;
        }
    }

    return $url;
}

/**
 * Verifica se asset do tenant existe, senão usa o compartilhado
 */
function asset_fallback(string $tenantPath, string $sharedPath, bool $withVersion = true)
{
    $tenantFile = TENANT_ASSETS_PATH . ltrim($tenantPath, '/');

    if (file_exists($tenantFile)) {
        return tenant_asset($tenantPath, $withVersion);
    }

    return shared_asset($sharedPath, $withVersion);
}

/**
 * Modularização do código JavaScript
 */
// ─── Mapeamento: nome do módulo → arquivo(s) JS ───────────────────────────────
// Adicione novos módulos aqui à medida que criar os arquivos correspondentes.

if (!defined('APP_JS_MODULES')) {
    define('APP_JS_MODULES', [
        'Utils'             => [],          // incluído via app.utils.js (global)
        'Clientes'          => ['modules/app.clientes.js'],
        'Contratos'         => ['modules/app.contratos.js'],
        'Casos'             => ['modules/app.casos.js'],
        'Dashboard'         => ['modules/app.dashboard.js'],
        'Jurisprudencia'    => ['modules/app.jurisprudencia.js'],
        'Tarefas'           => ['modules/app.tarefas.js'],
        'Search'            => ['modules/app.search.js'],
        'ViaCep'            => ['modules/app.viaCep.js'],
        'Despesas'          => ['modules/app.despesas.js'],
        'Receitas'          => ['modules/app.receitas.js'],
        'Fornecedores'      => ['modules/app.fornecedores.js'],
        'LegalOps'          => ['modules/app.legalOps.js'],
        'Pagamentos'        => ['modules/app.pagamentos.js'],
        'Processos'         => ['modules/app.processos.js'],
        'ProcessoDetalhes'  => ['modules/app.processosDetalhes.js'],
        'BusinessCanvas'    => ['modules/app.businessCanvas.js'],
        'Financeiro'        => ['modules/app.financeiro.js'],
        'PagamentoDespesas' => ['modules/app.pagamentoDespesas.js'],
        'PagamentoReceitas' => ['modules/app.pagamentoReceitas.js'],
        'FechamentoMes'     => ['modules/app.fechamentoMes.js'],
        'Conhecimento'      => ['modules/app.conhecimento.js'],
        'Crm'               => ['modules/app.crm.js'],
        'CrmLeads'          => ['modules/app.crmLeads.js'],
    ]);
}

if (!function_exists('_app_js_base')) {
    function _app_js_base() {
        return shared_asset('api/app/', false);
    }
}

// ─── Scripts globais (carregados em TODAS as páginas) ─────────────────────────

if (!function_exists('js_global')) {
    /**
     * Retorna as tags <script> dos arquivos JS globais (core + utils).
     * Inclua no layout principal (ex: views/layout/footer.php).
     *
     * @return string HTML com as tags <script>
     */
    function js_global() {
        $base = _app_js_base();
        $v    = config('App')->app_js_version ?? time(); // cache busting

        $scripts = [
            'app.core.js',
            'app.utils.js',
        ];

        $html = '';
        foreach ($scripts as $file) {
            $html .= '<script src="' . $base . $file . '?v=' . $v . '"></script>' . "\n";
        }
        return $html;
    }
}

// ─── Scripts de módulos (carregados sob demanda) ──────────────────────────────

if (!function_exists('js_modules')) {
    /**
     * Retorna as tags <script> dos módulos solicitados.
     * Chame no footer da view, passando os módulos definidos pelo controller.
     *
     * Exemplo de uso no footer:
     *   <?= js_modules(isset($js_modules) ? $js_modules : []) ?>
     *
     * @param  array  $modules  Lista de nomes de módulos (ex: ['Clientes', 'ViaCep'])
     * @return string HTML com as tags <script>
     */
    function js_modules(array $modules = []) {
        if (empty($modules)) return '';

        $base     = _app_js_base();
        $v        = config('App')->app_js_version ?? time();
        $map      = APP_JS_MODULES;
        $loaded   = [];
        $html     = '';

        foreach ($modules as $name) {
            if (!isset($map[$name])) {
                log_message('error', "[Asset Helper] Módulo JS não encontrado: {$name}");
                continue;
            }
            foreach ($map[$name] as $file) {
                if (in_array($file, $loaded)) continue; // evita duplicatas
                $html   .= '<script src="' . $base . $file . '?v=' . $v . '"></script>' . "\n";
                $loaded[] = $file;
            }
        }

        return $html;
    }
}

// ─── Injeção do AppConfig via PHP ─────────────────────────────────────────────

if (!function_exists('js_app_config')) {
    /**
     * Gera o bloco <script> com AppConfig injetado pelo PHP.
     * Deve ser incluído ANTES dos arquivos JS da aplicação.
     *
     * @param  array  $extra  Configurações adicionais a mesclar
     * @return string HTML com o bloco <script>
     */
    function js_app_config(array $extra = []) {
        $config = array_merge([
            'baseUrl'     => base_url(),
            'csrfName'    => csrf_token(),
            'csrfHash'    => csrf_hash(),
            'environment' => ENVIRONMENT,
        ], $extra);

        return '<script>window.AppConfig = Object.assign(window.AppConfig || {}, ' . json_encode($config) . ');</script>' . "\n";
    }
}



