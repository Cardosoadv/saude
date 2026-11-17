<?php /** @var array $dados */
// Identificação do controlador e do encarregado (LGPD, art. 41, § 1º), lida das
// configurações do site para ficar igual ao rodapé e ao JSON-LD. Linha sem valor não aparece.
$unidade  = ((array) site_config('contato.enderecos', []))[0] ?? [];
$linhas   = array_filter([
    'razao_social'      => (string) (site_config('identidade.razao_social') ?: site_config('identidade.nome', '')),
    'cnpj'              => \App\Libraries\Site\Cnpj::formatar((string) site_config('identidade.cnpj', '')),
    'endereco'          => (string) ($unidade['resumo'] ?? trim(($unidade['linha1'] ?? '') . ' ' . ($unidade['linha2'] ?? ''))),
    'email'             => (string) site_config('contato.email', ''),
    'encarregado'       => (string) site_config('lgpd.encarregado_nome', ''),
    'encarregado_email' => (string) site_config('lgpd.encarregado_email', ''),
], static fn (string $v): bool => trim($v) !== '');
?>
            <h2 class="sub-title mt-5"><?= esc(($dados['titulo'] ?? '') !== '' ? $dados['titulo'] : site_texto('controlador.titulo')) ?></h2>
            <dl class="dados-controlador">
<?php foreach ($linhas as $chave => $valor): ?>
                <dt><?= esc(site_texto('controlador.' . $chave)) ?></dt>
<?php if (str_ends_with($chave, 'email')): ?>
                <dd><a href="mailto:<?= esc($valor) ?>"><?= esc($valor) ?></a></dd>
<?php else: ?>
                <dd><?= esc($valor) ?></dd>
<?php endif ?>
<?php endforeach ?>
            </dl>
