<?php /** @var array $dados @var array $secao */
$artigos = $dados['artigos'] ?? [];
?>

    <div class="gold-line"<?= $secao['ancora'] ? ' id="' . esc($secao['ancora'], 'attr') . '"' : '' ?>></div>

    <!-- ÚLTIMAS NOTÍCIAS (Destaques) -->
    <section class="highlights-section py-5">
        <div class="container px-3 px-md-5">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-5">
                <div>
<?php if (!empty($dados['sobretitulo'])): ?>
                    <span class="section-eyebrow"><?= esc($dados['sobretitulo']) ?></span>
<?php endif ?>
                    <h2 class="section-title mb-0"><?= esc($dados['titulo'] ?? '') ?></h2>
                </div>
                <a href="<?= site_url_publica('noticias') ?>" class="btn-outline-gold mt-3 mt-md-0">
                    <?= esc(site_texto('noticias.ver_todas')) ?> <i class="bi bi-chevron-right"></i>
                </a>
            </div>

            <div id="newsCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner" id="news-container">
<?php if (empty($artigos)): ?>
                <div class="carousel-item active">
                    <div class="col-12 text-center py-5">
                        <p class="text-muted"><?= esc(site_texto('noticias.vazio')) ?></p>
                    </div>
                </div>
<?php else: ?>
<?php foreach (array_chunk($artigos, 3) as $indice => $grupo): ?>
                    <div class="carousel-item <?= $indice === 0 ? 'active' : '' ?>">
                        <div class="row g-4">
<?php foreach ($grupo as $artigo): ?>
                            <div class="col-md-4">
                                <article class="news-card">
                                    <div class="news-card-content">
                                        <span class="news-date"><?= $artigo['data'] ? esc(date('d/m/Y', strtotime($artigo['data']))) : '' ?></span>
                                        <h3 class="news-card-title"><?= esc($artigo['titulo']) ?></h3>
                                        <p class="news-card-excerpt"><?= esc($artigo['resumo']) ?></p>
                                        <a href="<?= esc($artigo['href']) ?>" class="news-card-link">
                                            <?= esc(site_texto('noticias.ler_artigo')) ?> <i class="bi bi-arrow-right"></i><span class="visually-hidden">: <?= esc($artigo['titulo']) ?></span>
                                        </a>
                                    </div>
                                </article>
                            </div>
<?php endforeach ?>
                        </div>
                    </div>
<?php endforeach ?>
<?php endif ?>
                </div>

<?php if (count($artigos) > 3): ?>
                <button class="carousel-control-prev" type="button" data-bs-target="#newsCarousel" data-bs-slide="prev" style="width: 5%; justify-content: flex-start; margin-left: -5%;">
                    <span class="carousel-control-prev-icon" aria-hidden="true" style="filter: invert(1);"></span>
                    <span class="visually-hidden"><?= esc(site_texto('noticias.anterior')) ?></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#newsCarousel" data-bs-slide="next" style="width: 5%; justify-content: flex-end; margin-right: -5%;">
                    <span class="carousel-control-next-icon" aria-hidden="true" style="filter: invert(1);"></span>
                    <span class="visually-hidden"><?= esc(site_texto('noticias.proximo')) ?></span>
                </button>
<?php endif ?>
            </div>
        </div>
    </section>
