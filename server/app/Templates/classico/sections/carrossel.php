<?php /** @var array $dados */ /** @var array $secao */
// Carrossel de imagens (Bootstrap Carousel). Item sem imagem e sem título é ignorado.
$itens = array_values(array_filter(
    (array) ($dados['itens'] ?? []),
    static fn ($i): bool => is_array($i) && (! empty($i['imagem']['url']) || ($i['titulo'] ?? '') !== '')
));
if ($itens === []) {
    return;
}
$id        = 'carrossel-' . (int) ($secao['id'] ?? 0);
$autoplay  = ($dados['autoplay'] ?? true) !== false;
$intervalo = max(2, (int) ($dados['intervalo'] ?? 6)) * 1000;
$varios    = count($itens) > 1;
?>

    <!-- CARROSSEL -->
    <section class="carousel-section py-5">
        <div class="container px-3 px-md-5">
<?php if (! empty($dados['sobretitulo'])): ?>
            <span class="section-eyebrow"><?= esc($dados['sobretitulo']) ?></span>
<?php endif ?>
<?php if (! empty($dados['titulo'])): ?>
            <h2 class="section-title mb-4"><?= esc($dados['titulo']) ?></h2>
<?php endif ?>
            <div id="<?= esc($id, 'attr') ?>" class="carousel slide site-carousel"
                 data-bs-ride="<?= $autoplay ? 'carousel' : 'false' ?>" data-bs-interval="<?= $autoplay ? $intervalo : 'false' ?>"
                 data-bs-pause="hover" aria-roledescription="carrossel" aria-label="<?= esc($dados['titulo'] ?? site_texto('carrossel.rotulo'), 'attr') ?>">
<?php if ($varios): ?>
                <div class="carousel-indicators">
<?php foreach ($itens as $n => $item): ?>
                    <button type="button" data-bs-target="#<?= esc($id, 'attr') ?>" data-bs-slide-to="<?= $n ?>"<?= $n === 0 ? ' class="active" aria-current="true"' : '' ?> aria-label="<?= esc(sprintf(site_texto('carrossel.slide_n'), $n + 1), 'attr') ?>"></button>
<?php endforeach ?>
                </div>
<?php endif ?>
                <div class="carousel-inner">
<?php foreach ($itens as $n => $item): ?>
<?php $img = $item['imagem'] ?? null; $href = $item['link']['href'] ?? null; ?>
                    <div class="carousel-item<?= $n === 0 ? ' active' : '' ?>">
<?php if (! empty($img['url'])): ?>
                        <?= site_imagem($img, ['alt' => ($img['alt'] ?? '') !== '' ? $img['alt'] : ($item['titulo'] ?? ''), 'class' => 'd-block w-100 site-carousel-img', 'sizes' => '(min-width: 1200px) 1140px, 100vw', 'loading' => $n > 0 ? 'lazy' : 'eager', 'fetchpriority' => $n === 0 ? 'high' : '']) ?>
<?php else: ?>
                        <div class="site-carousel-img site-carousel-vazio" aria-hidden="true"></div>
<?php endif ?>
<?php if (($item['titulo'] ?? '') !== '' || ($item['texto'] ?? '') !== '' || ($href && ($item['link_rotulo'] ?? '') !== '')): ?>
                        <div class="carousel-caption site-carousel-legenda">
<?php if (($item['titulo'] ?? '') !== ''): ?>
                            <h3 class="site-carousel-titulo"><?= esc($item['titulo']) ?></h3>
<?php endif ?>
<?php if (($item['texto'] ?? '') !== ''): ?>
                            <p class="site-carousel-texto"><?= esc($item['texto']) ?></p>
<?php endif ?>
<?php if ($href && ($item['link_rotulo'] ?? '') !== ''): ?>
                            <a href="<?= esc($href, 'attr') ?>" class="btn-gold"><?= esc($item['link_rotulo']) ?></a>
<?php endif ?>
                        </div>
<?php endif ?>
                    </div>
<?php endforeach ?>
                </div>
<?php if ($varios): ?>
                <button class="carousel-control-prev" type="button" data-bs-target="#<?= esc($id, 'attr') ?>" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden"><?= esc(site_texto('carrossel.anterior')) ?></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#<?= esc($id, 'attr') ?>" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden"><?= esc(site_texto('carrossel.proximo')) ?></span>
                </button>
<?php endif ?>
            </div>
        </div>
    </section>
