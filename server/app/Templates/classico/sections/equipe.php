<?php /** @var array $dados */
// Equipe da clínica. Ordem de preferência: membros cadastrados na seção; usuários do sistema
// marcados com "Exibir em Quem Somos"; por fim a lista `identidade.profissionais` (nome, cargo, registro),
// a mesma que alimenta o JSON-LD.
$membros = array_values(array_filter(
    (array) ($dados['membros'] ?? []),
    static fn ($m): bool => is_array($m) && trim((string) ($m['nome'] ?? '')) !== ''
));
if ($membros === []) {
    $membros = site_equipe_usuarios();
}
if ($membros === []) {
    $membros = array_values(array_filter(
        (array) site_config('identidade.profissionais', []),
        static fn ($m): bool => is_array($m) && trim((string) ($m['nome'] ?? '')) !== ''
    ));
}
if ($membros === []) {
    return;
}
$rotulosLinks = ['lattes' => 'Currículo Lattes', 'linkedin' => 'LinkedIn'];
?>
            <section class="equipe-section" aria-labelledby="equipe-titulo">
                <h2 class="sub-title mt-5" id="equipe-titulo"><?= esc(($dados['titulo'] ?? '') !== '' ? $dados['titulo'] : site_texto('equipe.titulo', 'Nossa equipe')) ?></h2>
<?php if (! empty($dados['intro'])): ?>
                <p class="equipe-intro"><?= esc($dados['intro']) ?></p>
<?php endif ?>
                <div class="row g-4">
<?php foreach ($membros as $i => $m): ?>
<?php $foto = is_array($m['foto'] ?? null) ? $m['foto'] : null; ?>
                    <div class="col-md-6">
                        <article class="equipe-card" id="profissional-<?= $i + 1 ?>">
<?php if (! empty($foto['url'])): ?>
                            <?= site_imagem($foto, ['alt' => ($foto['alt'] ?? '') !== '' ? $foto['alt'] : $m['nome'], 'class' => 'equipe-foto', 'sizes' => '220px']) ?>
<?php endif ?>
                            <h3 class="equipe-nome"><?= esc($m['nome']) ?></h3>
<?php if (($m['cargo'] ?? '') !== ''): ?>
                            <p class="equipe-cargo"><?= esc($m['cargo']) ?></p>
<?php endif ?>
<?php if (($m['registro'] ?? '') !== ''): ?>
                            <p class="equipe-registro"><?= esc(site_texto('equipe.registro', 'Registro')) ?> <?= esc($m['registro']) ?></p>
<?php endif ?>
<?php if (($m['curriculo'] ?? '') !== ''): ?>
                            <p class="equipe-bio"><?= esc($m['curriculo']) ?></p>
<?php endif ?>
<?php foreach (['formacao' => 'equipe.formacao', 'especializacoes' => 'equipe.especializacoes', 'areas' => 'equipe.areas'] as $campo => $chave): ?>
<?php if (($m[$campo] ?? '') !== ''): ?>
                            <p class="equipe-detalhe"><strong><?= esc(site_texto($chave)) ?>:</strong> <?= esc($m[$campo]) ?></p>
<?php endif ?>
<?php endforeach ?>
<?php $links = []; foreach (array_keys($rotulosLinks) as $k) { if (! empty($m[$k]['href'])) { $links[$k] = $m[$k]['href']; } } ?>
<?php if ($links !== []): ?>
                            <p class="equipe-links">
<?php foreach ($links as $k => $href): ?>
                                <a href="<?= esc($href, 'attr') ?>" rel="noopener me" target="_blank"><?= esc($rotulosLinks[$k]) ?></a>
<?php endforeach ?>
                            </p>
<?php endif ?>
                        </article>
                    </div>
<?php endforeach ?>
                </div>
            </section>
