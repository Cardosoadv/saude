<?php /** @var array $dados */ ?>
<?php if (!empty($dados['titulo'])): ?>
            <h2 class="sub-title mt-5"><?= esc($dados['titulo']) ?></h2>
<?php endif ?>
            <div class="texto-livre">
<?= $dados['corpo'] ?? '' ?>
            </div>
