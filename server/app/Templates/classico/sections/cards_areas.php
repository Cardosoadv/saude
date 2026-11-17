<?php /** @var array $dados @var array $secao */ ?>

    <div class="gold-line"<?= $secao['ancora'] ? ' id="' . esc($secao['ancora'], 'attr') . '"' : '' ?>></div>

    <!-- ÁREAS DE ATUAÇÃO (Expertise) -->
    <section class="expertise-section py-5">
        <div class="container px-3 px-md-5">
            <div class="text-center mb-5">
<?php if (!empty($dados['sobretitulo'])): ?>
                <span class="section-eyebrow"><?= esc($dados['sobretitulo']) ?></span>
<?php endif ?>
                <h2 class="section-title"><?= esc($dados['titulo'] ?? '') ?></h2>
            </div>

            <div class="row g-4">
<?php foreach ($dados['itens'] ?? [] as $item): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="expertise-card">
                        <h3 class="expertise-title"><?= esc($item['titulo'] ?? '') ?></h3>
                        <p class="expertise-text"><?= esc($item['texto'] ?? '') ?></p>
<?php if (!empty($item['link']['href'])): ?>
                        <a href="<?= esc($item['link']['href']) ?>" class="expertise-link">
                            <?= esc(str_replace('{area}', (string) ($item['titulo'] ?? ''), site_texto('areas.ver_detalhes'))) ?> <i class="bi bi-arrow-right"></i>
                        </a>
<?php endif ?>
                    </div>
                </div>
<?php endforeach ?>
            </div>
        </div>
    </section>
