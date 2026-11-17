<?php
/**
 * Estrutura HTML comum do template Clássico (cabeçalho, menu, rodapé).
 * Nada da clínica fica aqui: nome, contatos, menus e textos vêm do banco
 * (site_config, site_menu, site_texto).
 *
 * @var array  $seo
 * @var array  $pagina
 * @var string $conteudo
 */
$nome      = (string) site_config('identidade.nome', '');
// Sem logo cadastrado, usa o do template (PNG com variante WebP).
$logoMidia = site_midia(site_config('identidade.logo')) ?? [
    'url' => site_asset('img/logo.png'), 'alt' => '', 'largura' => 960, 'altura' => 280,
    'srcset' => ['webp' => site_asset('img/logo.webp') . ' 960w'],
];
$logoRodaMidia = site_midia(site_config('identidade.logo_rodape')) ?? $logoMidia;
$logo      = $logoMidia['url'];
$favicon   = site_midia(site_config('identidade.favicon'))['url'] ?? null;
$ogImagem  = $seo['imagem'] ?? $logo;
$unidades  = (array) site_config('contato.enderecos', []);
$telefone  = (string) site_config('contato.telefone', '');
$razaoSocial = trim((string) site_config('identidade.razao_social', ''));
$cnpj      = \App\Libraries\Site\Cnpj::formatar((string) site_config('identidade.cnpj', ''));
$registroProfissional = trim((string) site_config('identidade.registro_profissional', ''));
$registros = array_values(array_filter([
    $cnpj !== '' ? 'CNPJ ' . $cnpj : '',
    $registroProfissional !== '' ? 'Registro ' . $registroProfissional : '',
]));
$gaId      = (string) site_config('integracoes.ga_id', '');
$gaId      = preg_match('/^[A-Za-z0-9-]+$/', $gaId) ? $gaId : '';
// Google Ads: ID da conta (AW-…) e rótulo de cada conversão (formulário enviado, clique no WhatsApp).
$adsId     = (string) site_config('integracoes.ads_id', '');
$adsId     = preg_match('/^AW-\d+$/', $adsId) ? $adsId : '';
$conversoes = [];
foreach (['formulario' => 'integracoes.ads_conversao_formulario', 'whatsapp' => 'integracoes.ads_conversao_whatsapp'] as $nomeConversao => $chaveConversao) {
    $rotulo = (string) site_config($chaveConversao, '');
    if ($adsId !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $rotulo)) {
        $conversoes[$nomeConversao] = $adsId . '/' . $rotulo;
    }
}
$usaGoogle = $gaId !== '' || $adsId !== '';
$tagId     = $gaId !== '' ? $gaId : $adsId;
$politica  = '<a href="' . esc(site_url_publica('pagina/politica-de-privacidade')) . '">' . esc(site_texto('contato.privacidade_link')) . '</a>';
$atual     = rtrim(current_url(), '/');
$ativo     = static function (string $href) use ($atual): bool {
    $href = rtrim($href, '/');
    if ($href === '' || str_contains($href, '#') || $href === rtrim(site_url_publica(), '/')) {
        return false;
    }
    return $atual === $href || str_starts_with($atual, $href . '/');
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($seo['titulo']) ?></title>
    <meta name="description" content="<?= esc($seo['descricao']) ?>">
<?php if (!empty($seo['palavras'])): ?>
    <meta name="keywords" content="<?= esc($seo['palavras']) ?>">
<?php endif ?>
    <meta property="og:title" content="<?= esc($seo['titulo']) ?>">
    <meta property="og:description" content="<?= esc($seo['descricao']) ?>">
    <link rel="canonical" href="<?= esc($seo['url']) ?>">
    <meta property="og:url" content="<?= esc($seo['url']) ?>">
    <meta property="og:image" content="<?= esc($ogImagem) ?>">
    <meta property="og:type" content="website">
<?php if (!empty($rascunho) || !empty($noindex)): ?>
    <meta name="robots" content="noindex, nofollow">
<?php endif ?>

<?php if ($favicon): ?>
    <link rel="icon" href="<?= esc($favicon) ?>">
<?php else: ?>
    <link rel="icon" type="image/x-icon" href="<?= site_url_publica('public/favicon.ico') ?>">
<?php endif ?>

    <!-- Fonte e Icones -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php if (site_fontes_url() !== ''): ?>
    <link href="<?= esc(site_fontes_url()) ?>" rel="stylesheet">
<?php endif ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- CSS do template -->
    <link rel="stylesheet" href="<?= esc(site_asset('css/site.css')) ?>">
    <?= $this->renderSection('styles') ?>
<?php if (site_tema_css() !== ''): ?>
    <style id="tema-do-site"><?= site_tema_css() ?></style>
<?php endif ?>
    <?= site_jsonld($seo, (array) ($pagina ?? []), is_array($article ?? null) ? $article : null) ?>
<?php if ($usaGoogle): ?>

    <!-- Google tag (gtag.js) com Consent Mode v2: nada é gravado antes do aceite (LGPD) -->
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('consent', 'default', {
        analytics_storage: 'denied',
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
        functionality_storage: 'granted',
        security_storage: 'granted',
        wait_for_update: 500
      });
      try {
        if (localStorage.getItem('cookies_consentimento') === 'aceito') {
          gtag('consent', 'update', {
            analytics_storage: 'granted',
            ad_storage: 'granted',
            ad_user_data: 'granted',
            ad_personalization: 'granted'
          });
        }
      } catch (e) { /* armazenamento bloqueado: segue sem consentimento */ }
      gtag('js', new Date());
<?php if ($gaId !== ''): ?>
      gtag('config', <?= json_encode($gaId) ?>);
<?php endif ?>
<?php if ($adsId !== ''): ?>
      gtag('config', <?= json_encode($adsId) ?>);
<?php endif ?>
<?php if ($conversoes !== []): ?>

      // Conversões do Google Ads: window.siteConversao('formulario' | 'whatsapp').
      const CONVERSOES = <?= json_encode($conversoes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      window.siteConversao = function (nome) {
        if (CONVERSOES[nome]) { gtag('event', 'conversion', { send_to: CONVERSOES[nome] }); }
      };
      // Clique em link do WhatsApp (wa.me ou api.whatsapp.com/send?phone=…); links de compartilhar (?text=) ficam de fora.
      document.addEventListener('click', function (e) {
        const link = e.target.closest ? e.target.closest('a[href]') : null;
        if (!link) { return; }
        let url;
        try { url = new URL(link.href, location.href); } catch (err) { return; }
        const ehWhatsapp = url.hostname === 'wa.me'
          || (url.hostname === 'api.whatsapp.com' && url.searchParams.has('phone'));
        if (ehWhatsapp) { window.siteConversao('whatsapp'); }
      });
<?php endif ?>
    </script>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= esc($tagId, 'url') ?>"></script>
<?php endif ?>

</head>
<body>
    <a href="#conteudo-principal" class="skip-link"><?= esc(site_texto('menu.pular', 'Pular para o conteúdo')) ?></a>
    <div class="app-container">
        <!-- Navbar Premium com Efeito Glassmorphism -->
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top main-navbar" aria-label="<?= esc(site_texto('menu.aria', 'Navegação principal'), 'attr') ?>">
            <div class="container px-3 px-md-5">
                <a href="<?= site_url_publica() ?>" class="navbar-brand py-1">
                    <?= site_imagem($logoMidia, ['alt' => 'Logo ' . site_config('identidade.nome_curto', $nome), 'class' => 'nav-logo', 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => '224px']) ?>
                </a>

                <button
                    class="navbar-toggler"
                    type="button"
                    id="navbar-toggler-btn"
                    aria-controls="navbar-collapse-menu"
                    aria-expanded="false"
                    aria-label="<?= esc(site_texto('menu.alternar')) ?>"
                >
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse justify-content-end" id="navbar-collapse-menu">
                    <ul class="navbar-nav align-items-center gap-3 gap-lg-4 my-3 my-lg-0 me-lg-4">
<?php $cta = []; foreach (site_menu('cabecalho') as $item): ?>
<?php if ($item['destaque']) { $cta[] = $item; continue; } ?>
                        <li class="nav-item">
                            <a href="<?= esc($item['href']) ?>" class="nav-link-custom <?= $ativo($item['href']) ? 'active' : '' ?>"<?= $ativo($item['href']) ? ' aria-current="page"' : '' ?>><?= esc($item['rotulo']) ?></a>
                        </li>
<?php endforeach ?>
                    </ul>
<?php foreach ($cta as $item): ?>
                    <div class="d-flex justify-content-center justify-content-lg-start">
                        <a href="<?= esc($item['href']) ?>" class="nav-cta"><?= esc($item['rotulo']) ?></a>
                    </div>
<?php endforeach ?>
                </div>
            </div>
        </nav>

        <!-- Transições de Página Fluidas -->
        <main class="main-content" id="conteudo-principal" tabindex="-1">
            <?= $this->renderSection('content') ?>
        </main>

        <!-- Footer Premium e Acessível -->
        <footer class="main-footer">
            <div class="container px-3 px-md-5">
                <div class="row py-5 g-4">
                    <div class="col-md-5">
                        <?= site_imagem($logoRodaMidia, ['alt' => 'Logo ' . site_config('identidade.nome_curto', $nome), 'class' => 'footer-logo mb-3', 'sizes' => '280px']) ?>
                        <p class="footer-about">
                            <?= esc(site_config('identidade.sobre_rodape', '')) ?>
                        </p>
                    </div>
                    <div class="col-md-3">
                        <h4 class="footer-title"><?= esc(site_texto('rodape.links_titulo')) ?></h4>
                        <nav aria-label="<?= esc(site_texto('rodape.links_titulo'), 'attr') ?>">
                        <ul class="footer-links">
<?php foreach (site_menu('rodape') as $item): ?>
                            <li><a href="<?= esc($item['href']) ?>"><?= esc($item['rotulo']) ?></a></li>
<?php endforeach ?>
                        </ul>
                        </nav>
                    </div>
                    <div class="col-md-4">
                        <h4 class="footer-title"><?= esc(site_texto('rodape.unidades_titulo')) ?></h4>
<?php foreach ($unidades as $i => $unidade): ?>
                        <address class="footer-address">
                            <strong><?= esc($unidade['titulo'] ?? '') ?></strong><br />
                            <?= esc($unidade['linha1'] ?? '') ?><br />
                            <?= esc($unidade['linha2'] ?? '') ?>
<?php if ($i === 0 && $telefone !== ''): ?><br />
                            <abbr title="Telefone"><?= esc(site_texto('rodape.telefone_abrev')) ?></abbr> <?= site_telefone($telefone) ?>
<?php endif ?>
                        </address>
<?php endforeach ?>
                    </div>
                </div>

                <div class="footer-bottom py-3 d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <p class="mb-0 text-center text-md-start">&copy; <?= date('Y') ?> <?= esc($razaoSocial !== '' ? $razaoSocial : $nome) ?><?php foreach ($registros as $registro): ?> &middot; <?= esc($registro) ?><?php endforeach ?>. <?= esc(site_texto('rodape.direitos')) ?></p>
                    <div class="d-flex gap-3 align-items-center mt-3 mt-md-0">
<?php if ($usaGoogle): ?>
                        <button type="button" class="discreet-link btn-link-reset" data-cookies-abrir>
                            <i class="bi bi-sliders" aria-hidden="true"></i> <?= esc(site_texto('rodape.cookies')) ?>
                        </button>
<?php endif ?>
                        <a href="<?= site_url_publica('area-cliente') ?>" class="discreet-link" title="<?= esc(site_texto('rodape.area_cliente_titulo')) ?>">
                            <i class="bi bi-person-badge" aria-hidden="true"></i> <?= esc(site_texto('rodape.area_cliente')) ?>
                        </a>
                        <a href="<?= esc(config('App')->frontURL) ?>" class="admin-login-link" rel="nofollow" title="<?= esc(site_texto('rodape.painel_titulo')) ?>">
                            <i class="bi bi-shield-lock" aria-hidden="true"></i> <?= esc(site_texto('rodape.painel')) ?>
                        </a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
<?php if ($usaGoogle): ?>

    <div class="cookie-banner" id="cookie-banner" role="dialog" aria-live="polite" aria-label="<?= esc(site_texto('cookies.aria'), 'attr') ?>" hidden>
        <div class="cookie-banner-texto">
            <strong><?= esc(site_texto('cookies.titulo')) ?></strong>
            <p class="mb-0"><?= str_replace('{politica}', $politica, esc(site_texto('cookies.texto'))) ?></p>
        </div>
        <div class="cookie-banner-acoes">
            <button type="button" class="cookie-btn cookie-btn-recusar" data-cookies="recusado"><?= esc(site_texto('cookies.recusar')) ?></button>
            <button type="button" class="cookie-btn cookie-btn-aceitar" data-cookies="aceito"><?= esc(site_texto('cookies.aceitar')) ?></button>
        </div>
    </div>
    <script>
        // Consentimento de cookies de estatística e de anúncios (GA4 e Google Ads). A escolha fica no localStorage do visitante.
        (() => {
            const CHAVE = 'cookies_consentimento';
            const banner = document.getElementById('cookie-banner');
            const ler = () => { try { return localStorage.getItem(CHAVE); } catch (e) { return null; } };
            const mostrar = (sim) => { banner.hidden = !sim; };

            // Ao recusar depois de ter aceitado, apaga os cookies do Analytics já gravados (_ga, _ga_*).
            const apagarCookiesGa = () => {
                const partes = location.hostname.split('.');
                document.cookie.split(';').map(c => c.split('=')[0].trim()).filter(n => /^(_ga(_|$)|_gcl_)/.test(n)).forEach(nome => {
                    for (let i = 0; i < partes.length; i++) {
                        const dominio = partes.slice(i).join('.');
                        document.cookie = nome + '=; Max-Age=0; path=/; domain=' + dominio;
                    }
                    document.cookie = nome + '=; Max-Age=0; path=/';
                });
            };

            banner.querySelectorAll('[data-cookies]').forEach(btn => btn.addEventListener('click', () => {
                const escolha = btn.dataset.cookies;
                try { localStorage.setItem(CHAVE, escolha); } catch (e) { /* sem armazenamento: vale só nesta página */ }
                const estado = escolha === 'aceito' ? 'granted' : 'denied';
                gtag('consent', 'update', { analytics_storage: estado, ad_storage: estado, ad_user_data: estado, ad_personalization: estado });
                if (escolha !== 'aceito') { apagarCookiesGa(); }
                mostrar(false);
            }));

            document.querySelectorAll('[data-cookies-abrir]').forEach(el => el.addEventListener('click', () => mostrar(true)));

            if (ler() === null) { mostrar(true); }
        })();
    </script>
<?php endif ?>

    <script>
        // Alterna o menu no mobile e rola até a âncora da URL
        document.addEventListener('DOMContentLoaded', () => {
            const toggler = document.getElementById('navbar-toggler-btn');
            const menu = document.getElementById('navbar-collapse-menu');

            if (toggler && menu) {
                toggler.addEventListener('click', () => {
                    const isExpanded = toggler.getAttribute('aria-expanded') === 'true';
                    toggler.setAttribute('aria-expanded', !isExpanded);
                    menu.classList.toggle('show', !isExpanded);
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && toggler.getAttribute('aria-expanded') === 'true') {
                        toggler.setAttribute('aria-expanded', 'false');
                        menu.classList.remove('show');
                        toggler.focus();
                    }
                });

                menu.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', () => {
                        toggler.setAttribute('aria-expanded', 'false');
                        menu.classList.remove('show');
                    });
                });
            }

            if (window.location.hash) {
                setTimeout(() => {
                    try {
                        const element = document.querySelector(window.location.hash);
                        if (element) {
                            element.scrollIntoView({ behavior: 'smooth' });
                        }
                    } catch (e) { /* hash que não é seletor válido */ }
                }, 300);
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
