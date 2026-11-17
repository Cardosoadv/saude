<?php /** @var array $dados */ ?>
            <p class="lead">
              <?= esc($dados['lead'] ?? '') ?>
            </p>
            <?= $dados['corpo'] ?? '' ?>

