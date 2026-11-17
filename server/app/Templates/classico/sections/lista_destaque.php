<?php /** @var array $dados */ ?>
            <h2 class="sub-title mt-5"><?= esc($dados['titulo'] ?? '') ?></h2>
            <ul class="styled-list<?= ($dados['marcador'] ?? 'padrao') === 'dourado' ? ' gold-bullet' : '' ?>">
<?php foreach ($dados['itens'] ?? [] as $item): ?>
              <li><?php if (!empty($item['destaque'])): ?><strong><?= esc($item['destaque']) ?></strong> <?php endif ?><?= esc($item['texto'] ?? '') ?></li>
<?php endforeach ?>
            </ul>
