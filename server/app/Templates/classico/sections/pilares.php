<?php /** @var array $dados */ ?>

    <!-- PILARES DE EXCELÊNCIA (Valores) -->
    <section class="pillars-section py-5">
        <div class="container px-3 px-md-5">
            <div class="row g-4">
<?php foreach ($dados['itens'] ?? [] as $item): ?>
                <div class="col-md-4">
                    <div class="pillar-card">
                        <div class="pillar-icon" aria-hidden="true"><i class="bi <?= esc($item['icone'] ?? 'bi-award', 'attr') ?>"></i></div>
                        <h3 class="pillar-title"><?= esc($item['titulo'] ?? '') ?></h3>
                        <p class="pillar-text">
                            <?= esc($item['texto'] ?? '') ?>
                        </p>
                    </div>
                </div>
<?php endforeach ?>
            </div>
        </div>
    </section>
