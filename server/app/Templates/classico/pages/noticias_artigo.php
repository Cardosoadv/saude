<?= $this->extend('shell/base') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= esc(site_asset('css/noticias-artigo.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="noticias-detail-view">
  <div class="animate-fade-in">
    <div class="news-detail-hero">
      <div class="hero-overlay"></div>
      <div class="container px-3 px-md-5 hero-inner">
        <a href="<?= site_url_publica('noticias') ?>" class="btn-back">
          <i class="bi bi-arrow-left"></i> <?= esc(site_texto('noticias.voltar')) ?>
        </a>
      </div>
    </div>

    <article class="article-container container py-5 px-3 px-md-5">
      <header class="article-header mb-5">
        <?php if (!empty($article['category_name'])): ?>
          <span class="article-category">
            #<?= esc($article['category_name']) ?>
          </span>
        <?php endif; ?>
        <h1 class="article-title"><?= esc($article['title']) ?></h1>
        
        <div class="article-meta">
          <span class="meta-item">
            <i class="bi bi-calendar3" aria-hidden="true"></i>
            <?= esc(site_texto('noticias.publicado_em')) ?> <?= date('d/m/Y', strtotime($article['published_at'] ?? $article['created_at'])) ?>
          </span>
        </div>
      </header>

      <!-- Corpo do Texto Renderizado em HTML -->
      <div class="article-content">
        <?= $article['content'] ?>
      </div>

      <!-- Rodapé do Artigo: Compartilhamento e Tags -->
      <footer class="article-footer mt-5 pt-4 border-top">
        <div class="row align-items-center justify-content-between g-3">
          <div class="col-md-6 d-flex align-items-center gap-3">
            <span class="share-title"><?= esc(site_texto('noticias.compartilhar')) ?></span>
            <?php 
              $currentUrl = $seo['url'] ?? current_url(); // canônica do artigo, sem parâmetros da requisição
              $linkedinShareUrl = 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($currentUrl);
              $whatsappShareUrl = 'https://api.whatsapp.com/send?text=' . urlencode($article['title'] . ' - ' . $currentUrl);
            ?>
            <a 
              href="<?= $linkedinShareUrl ?>" 
              target="_blank" 
              rel="noopener" 
              class="btn-share" 
              title="<?= esc(site_texto('noticias.compartilhar_linkedin')) ?>"
              aria-label="<?= esc(site_texto('noticias.compartilhar_linkedin_aria')) ?>"
            >
              <i class="bi bi-linkedin"></i>
            </a>
            <a 
              href="<?= $whatsappShareUrl ?>" 
              target="_blank" 
              rel="noopener" 
              class="btn-share" 
              title="<?= esc(site_texto('noticias.compartilhar_whatsapp')) ?>"
              aria-label="<?= esc(site_texto('noticias.compartilhar_whatsapp_aria')) ?>"
            >
              <i class="bi bi-whatsapp"></i>
            </a>
          </div>
          
          <?php if (!empty($article['category_name'])): ?>
            <div class="col-md-6 text-md-end">
              <span class="badge-category">#<?= esc($article['category_name']) ?></span>
            </div>
          <?php endif; ?>
        </div>
      </footer>
    </article>

    <div class="gold-line"></div>

    <!-- SEÇÃO LEIA TAMBÉM (ARTIGOS RELACIONADOS) -->
    <?php if (!empty($related)): ?>
      <section class="related-section py-5">
        <div class="container px-3 px-md-5">
          <h2 class="related-title mb-4"><?= esc(site_texto('noticias.leia_tambem')) ?></h2>
          
          <div class="row g-4">
            <?php foreach ($related as $rel): ?>
              <div class="col-md-4">
                <div class="news-card">
                  <div class="news-card-content">
                    <span class="news-date">
                      <?= date('d/m/Y', strtotime($rel['published_at'] ?? $rel['created_at'])) ?>
                    </span>
                    <h3 class="news-card-title"><?= esc($rel['title']) ?></h3>
                    <a href="<?= site_url_publica('noticias/' . $rel['slug']) ?>" class="news-card-link">
                      <?= esc(site_texto('noticias.ler_artigo')) ?> <i class="bi bi-arrow-right"></i><span class="visually-hidden">: <?= esc($rel['title']) ?></span>
                    </a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>
  </div>
</div>
<?= $this->endSection() ?>
