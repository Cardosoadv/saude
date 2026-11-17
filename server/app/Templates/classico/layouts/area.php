<?php
/**
 * Página de área de atuação: cabeçalho em largura total, texto à esquerda e
 * barra lateral à direita. As seções são distribuídas pelo tipo:
 *   hero_area            → cabeçalho
 *   lateral_outras_areas → coluna da direita
 *   demais               → coluna da esquerda
 */
$topo = $esquerda = $direita = '';
foreach ($partes as $parte) {
    match ($parte['tipo']) {
        'hero_area'            => $topo .= $parte['html'],
        'lateral_outras_areas' => $direita .= $parte['html'],
        default                => $esquerda .= $parte['html'],
    };
}
?>
<?= $this->extend('shell/base') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= esc(site_asset('css/areas.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="practice-area-view">
<?= $topo ?>

  <div class="gold-line"></div>

  <section class="area-content-section py-5">
    <div class="container px-3 px-md-5">
      <div class="row g-5">
        <div class="col-lg-8">
          <div class="rich-text-container">
<?= $esquerda ?>
          </div>
        </div>
        <div class="col-lg-4">
<?= $direita ?>
        </div>
      </div>
    </div>
  </section>
</div>
<?= $this->endSection() ?>
