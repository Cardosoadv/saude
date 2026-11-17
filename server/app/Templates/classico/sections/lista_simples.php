<?php /** @var array $dados */
$itens  = $dados['itens'] ?? [];
$colunas = ($dados['colunas'] ?? '1') === '2' ? array_chunk($itens, max(1, (int) ceil(count($itens) / 2))) : [$itens];
?>
            <h2 class="sub-title mt-5"><?= esc($dados['titulo'] ?? '') ?></h2>
<?php if (count($colunas) > 1): ?>
            <div class="row mt-3">
<?php foreach ($colunas as $coluna): ?>
              <div class="col-md-6">
                <ul class="styled-list simple">
<?php foreach ($coluna as $item): ?>
                  <li><?= esc($item['texto'] ?? '') ?></li>
<?php endforeach ?>
                </ul>
              </div>
<?php endforeach ?>
            </div>
<?php else: ?>
            <ul class="styled-list simple">
<?php foreach ($colunas[0] ?? [] as $item): ?>
              <li><?= esc($item['texto'] ?? '') ?></li>
<?php endforeach ?>
            </ul>
<?php endif ?>
