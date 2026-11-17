<?= $this->extend('shell/base') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= esc(site_asset('css/noticias-lista.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="noticias-list-view">
  <!-- HERO DE NOTÍCIAS -->
  <header class="news-hero">
    <div class="news-hero-overlay"></div>
    <div class="container px-3 px-md-5 news-hero-container">
      <span class="section-eyebrow"><?= esc(site_texto('noticias.hero_sobretitulo')) ?></span>
      <h1 class="hero-title"><?= \App\Libraries\Site\HtmlSanitizer::inline(site_texto('noticias.hero_titulo')) ?></h1>
      <p class="hero-sub mx-auto">
        <?= esc(site_texto('noticias.hero_subtitulo')) ?>
      </p>
    </div>
  </header>

  <div class="gold-line"></div>

  <section class="news-list-section py-5">
    <div class="container px-3 px-md-5">
      
      <!-- BARRA DE BUSCA E FILTROS -->
      <div class="search-filter-container mb-5">
        <form action="<?= site_url_publica('noticias') ?>" method="GET" id="search-form">
          <div class="row g-3 align-items-center justify-content-between">
            <!-- Filtros Rápidos -->
            <div class="col-lg-6 d-flex gap-2 flex-wrap">
              <button 
                type="submit" 
                name="search" 
                value="" 
                class="btn-filter <?= empty($searchQuery) ? 'active' : '' ?>"
              >
                <?= esc(site_texto('noticias.filtro_todos')) ?>
              </button>
<?php foreach ((array) site_config('noticias.filtros', []) as $filtro): ?>
              <button 
                type="submit" 
                name="search" 
                value="<?= esc($filtro) ?>" 
                class="btn-filter <?= ($searchQuery === $filtro) ? 'active' : '' ?>"
              >
                <?= esc($filtro) ?>
              </button>
<?php endforeach ?>
            </div>

            <!-- Busca Textual -->
            <div class="col-lg-4">
              <div class="search-input-group">
                <i class="bi bi-search search-icon" aria-hidden="true"></i>
                <input 
                  type="text" 
                  name="search"
                  value="<?= esc($searchQuery) ?>" 
                  placeholder="<?= esc(site_texto('noticias.busca_placeholder')) ?>" 
                  class="search-field"
                  aria-label="<?= esc(site_texto('noticias.busca_aria')) ?>"
                />
                <?php if (!empty($searchQuery)): ?>
                <a href="<?= site_url_publica('noticias') ?>" class="btn-clear" aria-label="<?= esc(site_texto('noticias.busca_limpar')) ?>">
                  <i class="bi bi-x"></i>
                </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </form>
      </div>

      <!-- GRID DE ARTIGOS -->
      <?php if (empty($articles)): ?>
        <div class="text-center py-5 empty-state animate-fade-in">
          <i class="bi bi-journal-x empty-icon" aria-hidden="true"></i>
          <h3 class="mt-4"><?= esc(site_texto('noticias.vazio_titulo')) ?></h3>
          <p class="text-muted"><?= esc(site_texto('noticias.vazio_texto')) ?></p>
          <a href="<?= site_url_publica('noticias') ?>" class="btn-gold mt-3 text-decoration-none"><?= esc(site_texto('noticias.vazio_botao')) ?></a>
        </div>
      <?php else: ?>
        <div class="animate-fade-in">
          <div class="row g-4 news-grid">
            <?php foreach ($articles as $article): ?>
              <div class="col-md-6 col-lg-4">
                <article class="news-card">
                  <div class="news-card-content">
                    <span class="news-date">
                      <?= date('d/m/Y', strtotime($article['published_at'] ?? $article['created_at'])) ?>
                    </span>
                    <h3 class="news-card-title"><?= esc($article['title']) ?></h3>
                    <p class="news-card-excerpt"><?= esc($article['summary'] ?? '') ?></p>
                    <a href="<?= site_url_publica('noticias/' . $article['slug']) ?>" class="news-card-link" aria-label="<?= esc(site_texto('noticias.ler_artigo_aria')) ?> <?= esc($article['title']) ?>">
                      <?= esc(site_texto('noticias.ler_artigo')) ?> <i class="bi bi-arrow-right"></i><span class="visually-hidden">: <?= esc($article['title']) ?></span>
                    </a>
                  </div>
                </article>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- PAGINAÇÃO -->
          <?php if ($lastPage > 1): ?>
            <nav class="d-flex justify-content-center mt-5" aria-label="<?= esc(site_texto('noticias.pag_nav_aria')) ?>">
              <ul class="pagination-custom">
                <!-- Botão Voltar -->
                <li class="page-item-custom">
                  <a href="<?= site_url_publica('noticias?page=' . max(1, $currentPage - 1) . '&search=' . urlencode($searchQuery)) ?>" 
                     class="page-link-custom <?= $currentPage <= 1 ? 'disabled' : '' ?>"
                     aria-label="<?= esc(site_texto('noticias.pag_anterior')) ?>">
                    <i class="bi bi-chevron-left"></i>
                  </a>
                </li>
                
                <?php
                $delta = 2;
                $pages = [];
                for ($i = 1; $i <= $lastPage; $i++) {
                    if ($i === 1 || $i === $lastPage || ($i >= $currentPage - $delta && $i <= $currentPage + $delta)) {
                        $pages[] = $i;
                    } elseif (end($pages) !== '...') {
                        $pages[] = '...';
                    }
                }
                ?>
                
                <?php foreach ($pages as $p): ?>
                  <li class="page-item-custom">
                    <?php if ($p === '...'): ?>
                      <span class="page-link-custom dots">...</span>
                    <?php else: ?>
                      <a href="<?= site_url_publica('noticias?page=' . $p . '&search=' . urlencode($searchQuery)) ?>" 
                         class="page-link-custom <?= $p == $currentPage ? 'active' : '' ?>"
                         <?= $p == $currentPage ? 'aria-current="page"' : '' ?>>
                        <?= $p ?>
                      </a>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>

                <!-- Botão Próximo -->
                <li class="page-item-custom">
                  <a href="<?= site_url_publica('noticias?page=' . min($lastPage, $currentPage + 1) . '&search=' . urlencode($searchQuery)) ?>" 
                     class="page-link-custom <?= $currentPage >= $lastPage ? 'disabled' : '' ?>"
                     aria-label="<?= esc(site_texto('noticias.pag_proxima')) ?>">
                    <i class="bi bi-chevron-right"></i>
                  </a>
                </li>
              </ul>
            </nav>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    </div>
  </section>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.querySelector('.search-field');
  const searchForm = document.getElementById('search-form');
  let timeout = null;

  if (searchInput && searchForm) {
    searchInput.addEventListener('input', () => {
      clearTimeout(timeout);
      timeout = setTimeout(() => {
        searchForm.submit();
      }, 600); // 600ms debounce
    });
  }
});
</script>
<?= $this->endSection() ?>
