<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="author" content="<?= esc(\App\Libraries\Site\DadosEscritorio::atual()->nomeCurto(), 'attr') ?>">
<meta name="description" content="Ordo - Sistema de Administração de Escritórios de Advocacia">
<meta name="keywords" content="<?= esc(implode(', ', array_filter([\App\Libraries\Site\DadosEscritorio::atual()->nomeCurto(), 'Advogados', 'Processos'])), 'attr') ?>">

<!-- CSRF Token para AJAX -->
<meta name="csrf-token" content="<?= csrf_hash() ?>">
<meta name="csrf-token-name" content="<?= csrf_token() ?>">
<meta name="csrf-header" content="X-CSRF-TOKEN">

<!-- Cache Control para recursos estáticos -->
<meta http-equiv="Cache-Control" content="public, max-age=31536000">
 
<!-- Favicon com versioning para cache -->
<link rel="icon" href="<?= tenant_asset('img/favicon.ico', true) ?>" sizes="32x32" />
 
<meta name="msapplication-TileImage" content="<?= tenant_asset('img/favicon.ico', true) ?>" />

<!-- Preconnect e DNS Prefetch para melhorar performance de conexões externas -->
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">

<!-- CSS Core e Bibliotecas de Terceiros (CDNs) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" integrity="sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q=" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/styles/overlayscrollbars.min.css" integrity="sha256-dSokZseQNT08wYEWiz5iLI8QPlKxG+TswNRD8k35cpg=" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css" integrity="sha256-Qsx5lrStHZyR9REqhUF8iQt73X06c8LGIUPzpOhwRrI=" crossorigin="anonymous">

<!-- Assets do Framework UI (AdminLTE) - Gerenciado via Helper -->
<link rel="stylesheet" href="<?= shared_asset('dist/css/adminlte-min.css') ?>">

<!-- Bibliotecas Visuais Adicionais -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" xintegrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css" integrity="sha256-4MX+61mt9NVvvuPjUWdUdyfZfxSB1/Rf9WtqRHgG5S0=" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/css/jsvectormap.min.css" integrity="sha256-+uGLJmmTKOqBr+2E6KDYs/NRsHxSkONXFHUL0fy2O/4=" crossorigin="anonymous">

<!-- Estilos Principais com Fallback (Customização Global vs Padrão) -->
<link rel="stylesheet" href="<?= asset_fallback('custom/style.css', 'dist/css/style-min.css') ?>">

<!-- Notificações e Inputs Avançados -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" crossorigin>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- CSS Customizado Específico do Tenant (Isolamento de Estilo) -->
<?php if (defined('TENANT_ASSETS_PATH') && file_exists(TENANT_ASSETS_PATH . 'custom/tenant.css')): ?>
    <link rel="stylesheet" href="<?= tenant_asset('custom/tenant.css', true) ?>">
<?php endif; ?>

<!-- Service Worker para cache avançado -->
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            // Utiliza base_url() padrão aqui pois o SW geralmente reside na raiz pública
            navigator.serviceWorker.register('<?= shared_asset('dist/js/service-worker-min.js', true) ?>')
                .then(function(registration) {
                    console.log('ServiceWorker registrado com sucesso:', registration.scope);
                })
                .catch(function(error) {
                    console.log('Falha ao registrar ServiceWorker:', error);
                });
        });
    }
</script>

<!-- Sistema de Cache Local (JS Injetado) -->
<script>
    /**
     * Cache Manager - Gerenciador de cache do navegador
     * Responsável por armazenar dados de requisições JSON para reduzir carga no servidor
     * e melhorar a responsividade da interface.
     */
    const CacheManager = {
        version: '2.6.4', // Incremento de versão força a invalidação do cache antigo
        prefix: 'ordo_system_',
        defaultExpiration: 30, // Minutos

        // Salva dados no SessionStorage com timestamp
        set: function(key, data, expirationMinutes = null) {
            try {
                const expiration = expirationMinutes || this.defaultExpiration;
                const item = {
                    data: data,
                    timestamp: Date.now(),
                    expiration: expiration * 60 * 1000,
                    version: this.version
                };
                sessionStorage.setItem(this.prefix + key, JSON.stringify(item));
                return true;
            } catch (e) {
                console.warn('Erro ao salvar no cache:', e);
                return false;
            }
        },
 
        // Recupera dados validando expiração e versão
        get: function(key) {
            try {
                const itemStr = sessionStorage.getItem(this.prefix + key);
                if (!itemStr) return null;

                const item = JSON.parse(itemStr);

                if (item.version !== this.version) {
                    this.remove(key);
                    return null;
                }

                const now = Date.now();
                if (now - item.timestamp > item.expiration) {
                    this.remove(key);
                    return null;
                }

                return item.data;
            } catch (e) {
                console.warn('Erro ao recuperar do cache:', e);
                return null;
            }
        },

        remove: function(key) {
            sessionStorage.removeItem(this.prefix + key);
        },

        // Rotina de limpeza automática
        clearExpired: function() {
            const keys = Object.keys(sessionStorage);
            keys.forEach(key => {
                if (key.startsWith(this.prefix)) {
                    const itemStr = sessionStorage.getItem(key);
                    try {
                        const item = JSON.parse(itemStr);
                        const now = Date.now();
                        if (now - item.timestamp > item.expiration || item.version !== this.version) {
                            sessionStorage.removeItem(key);
                        }
                    } catch (e) {
                        sessionStorage.removeItem(key);
                    }
                }
            });
        }
    };

    CacheManager.clearExpired();

    // Wrapper para fetch nativo com suporte a cache transparente
    async function fetchWithCache(url, options = {}, cacheKey = null, expirationMinutes = 30) {
        const key = cacheKey || url;

        const cachedData = CacheManager.get(key);
        if (cachedData) {
            console.log('Dados recuperados do cache:', key);
            return cachedData;
        }

        try {
            const response = await fetch(url, options);
            const data = await response.json();
            CacheManager.set(key, data, expirationMinutes);
            return data;
        } catch (error) {
            console.error('Erro na requisição:', error);
            throw error;
        }
    }

    window.CacheManager = CacheManager;
    window.fetchWithCache = fetchWithCache;

    // Detecção dinâmica de Tenant para consistência de API e Sessão
    const base = '<?= base_url() ?>'.replace(/\/$/, '') + '/';
    const pathSegments = window.location.pathname.split('/').filter(s => s);
    const knownTenants = ['crbio04', 'cb'];
    let tenantSegment = '';

    // Tenta encontrar o tenant no path (ex: /processos/cb/...)
    for (const segment of pathSegments) {
        if (knownTenants.includes(segment)) {
            // Evita duplicar se a base_url já contém o tenant (comum em alguns ambientes)
            if (!base.includes('/' + segment + '/') && !base.endsWith('/' + segment)) {
                tenantSegment = segment + '/';
            }
            break;
        }
    }

    window.AppConfig = {
        baseUrl: base + tenantSegment,
        rootUrl: base,
        apiUrl: base + tenantSegment,
        userId: '<?= user_id() ?>',
        csrfTokenName: '<?= csrf_token() ?>',
        csrfHash: '<?= csrf_hash() ?>',
        environment: '<?= ENVIRONMENT ?>',
        cacheEnabled: true,
        cacheVersion: '2.6.7'
    };
    window.baseUrl = AppConfig.baseUrl;
    window.apiUrl = AppConfig.apiUrl;
</script>