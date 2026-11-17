<?php /** @var array $dados @var array $pagina */
$atual = rtrim(site_url_publica('pagina/' . $pagina['slug']), '/');
?>
          <aside class="sidebar-box p-4">
            <h3 class="sidebar-title"><?= esc($dados['titulo'] ?? site_texto('area.outras_areas', 'Outras Áreas')) ?></h3>
            <ul class="sidebar-menu">
<?php foreach (site_menu($dados['menu'] ?? 'lateral_areas') as $item): ?>
<?php if (rtrim($item['href'], '/') === $atual) { continue; } ?>
              <li>
                <a href="<?= esc($item['href']) ?>" class="sidebar-link">
                  <i class="bi bi-chevron-right"></i> <?= esc($item['rotulo']) ?>
                </a>
              </li>
<?php endforeach ?>
            </ul>
          </aside>
