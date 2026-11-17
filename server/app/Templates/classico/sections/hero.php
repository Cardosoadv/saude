<?php /** @var array $dados */ ?>
    <!-- HERO SECTION PREMIUM -->
    <header class="hero-section">
        <div class="hero-bg-overlay"></div>
        <div class="container px-3 px-md-5 hero-container">
            <div class="row align-items-center">
                <div class="col-lg-8 hero-content">
<?php if (!empty($dados['sobretitulo'])): ?>
                    <span class="section-eyebrow animate-fade-in"><?= esc($dados['sobretitulo']) ?></span>
<?php endif ?>
                    <h1 class="hero-title animate-slide-up">
                        <?= $dados['titulo'] ?? '' ?>
                    </h1>
<?php if (!empty($dados['subtitulo'])): ?>
                    <p class="hero-subtitle animate-slide-up-delay">
                        <?= esc($dados['subtitulo']) ?>
                    </p>
<?php endif ?>
                    <div class="hero-actions animate-slide-up-delay-more">
<?php foreach ($dados['botoes'] ?? [] as $botao): ?>
<?php if (!empty($botao['link']['href'])): ?>
                        <a href="<?= esc($botao['link']['href']) ?>" class="<?= ($botao['estilo'] ?? 'primario') === 'contorno' ? 'btn-outline' : 'btn-gold' ?>"><?= esc($botao['rotulo'] ?? '') ?></a>
<?php endif ?>
<?php endforeach ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="gold-line position-absolute bottom-0 w-100"></div>
    </header>
