<?php /** @var array $dados */ ?>
            <div class="contact-cta-box mt-5 p-4 text-center">
              <p class="mb-3"><?= esc($dados['texto'] ?? '') ?></p>
<?php if (!empty($dados['botao_link']['href'])): ?>
              <a href="<?= esc($dados['botao_link']['href']) ?>" class="btn-gold"><?= esc($dados['botao_rotulo'] ?? '') ?></a>
<?php endif ?>
            </div>
