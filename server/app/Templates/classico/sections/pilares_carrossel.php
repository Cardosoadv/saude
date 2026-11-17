<?php /** @var array $dados */ /** @var array $secao */
// Cards de texto em faixa deslizante (scroll-snap). Funciona sem JS; o script só liga as setas e o autoplay.
$itens = array_values(array_filter((array) ($dados['itens'] ?? []), static fn ($i): bool => is_array($i) && ($i['titulo'] ?? '') !== ''));
if ($itens === []) {
    return;
}
$id       = 'pc-' . (int) ($secao['id'] ?? 0);
$autoplay = ($dados['autoplay'] ?? false) === true;
$varios   = count($itens) > 1;
?>

    <!-- CARROSSEL DE TEXTOS -->
    <section class="pilares-carrossel-section py-5" id="<?= esc($id, 'attr') ?>">
        <div class="container px-3 px-md-5">
<?php if (! empty($dados['sobretitulo'])): ?>
            <span class="section-eyebrow"><?= esc($dados['sobretitulo']) ?></span>
<?php endif ?>
<?php if (! empty($dados['titulo'])): ?>
            <h2 class="section-title mb-4"><?= esc($dados['titulo']) ?></h2>
<?php endif ?>
            <div class="pc-wrap" role="region" aria-roledescription="carrossel" aria-label="<?= esc(($dados['titulo'] ?? '') !== '' ? $dados['titulo'] : site_texto('pilares_carrossel.rotulo'), 'attr') ?>">
<?php if ($varios): ?>
                <div class="pc-controles">
                    <button type="button" class="pc-btn" data-pc="prev" aria-label="<?= esc(site_texto('pilares_carrossel.anterior'), 'attr') ?>"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                    <button type="button" class="pc-btn" data-pc="next" aria-label="<?= esc(site_texto('pilares_carrossel.proximo'), 'attr') ?>"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                </div>
<?php endif ?>
                <div class="pc-track" tabindex="0">
<?php foreach ($itens as $item): ?>
                    <div class="pc-item">
                        <div class="pillar-card">
                            <div class="pillar-icon" aria-hidden="true"><i class="bi <?= esc($item['icone'] ?? 'bi-award', 'attr') ?>"></i></div>
                            <h3 class="pillar-title"><?= esc($item['titulo']) ?></h3>
                            <p class="pillar-text"><?= esc($item['texto'] ?? '') ?></p>
                        </div>
                    </div>
<?php endforeach ?>
                </div>
            </div>
        </div>
<?php if ($varios): ?>
        <script>
        (function () {
            var raiz = document.getElementById(<?= json_encode($id, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
            if (!raiz) { return; }
            var autoplay = <?= $autoplay ? 'true' : 'false' ?>;
            var faixa = raiz.querySelector('.pc-track');
            var ant = raiz.querySelector('[data-pc="prev"]');
            var prox = raiz.querySelector('[data-pc="next"]');
            var passo = function () { var c = faixa.querySelector('.pc-item'); return c ? c.getBoundingClientRect().width + 24 : faixa.clientWidth; };
            var fim = function () { return faixa.scrollLeft + faixa.clientWidth >= faixa.scrollWidth - 4; };
            var atualizar = function () { ant.disabled = faixa.scrollLeft <= 4; prox.disabled = fim() && !autoplay; };
            var avancar = function () { faixa.scrollTo({ left: fim() ? 0 : faixa.scrollLeft + passo() }); };
            ant.addEventListener('click', function () { faixa.scrollBy({ left: -passo() }); });
            prox.addEventListener('click', avancar);
            faixa.addEventListener('scroll', atualizar, { passive: true });
            window.addEventListener('resize', atualizar);
            atualizar();
            if (autoplay && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                var pausado = false;
                ['mouseenter', 'focusin', 'touchstart'].forEach(function (e) { raiz.addEventListener(e, function () { pausado = true; }, { passive: true }); });
                ['mouseleave', 'focusout'].forEach(function (e) { raiz.addEventListener(e, function () { pausado = false; }); });
                setInterval(function () { if (!pausado && !document.hidden) { avancar(); } }, 6000);
            }
        })();
        </script>
<?php endif ?>
    </section>
