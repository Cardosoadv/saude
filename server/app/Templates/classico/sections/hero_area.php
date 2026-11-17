<?php /** @var array $dados */ ?>
  <!-- HERO DA ÁREA -->
  <header class="area-hero">
    <div class="area-hero-overlay"></div>
    <div class="container px-3 px-md-5 area-hero-container">
<?php if (!empty($dados['sobretitulo'])): ?>
      <span class="section-eyebrow"><?= esc($dados['sobretitulo']) ?></span>
<?php endif ?>
      <h1 class="hero-title"><?= $dados['titulo'] ?? '' ?></h1>
    </div>
  </header>
