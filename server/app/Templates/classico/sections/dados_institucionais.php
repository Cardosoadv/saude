<?php /** @var array $dados */
// Dados da clínica lidos das configurações do site (nada é digitado aqui).
$unidades = (array) site_config('contato.enderecos', []);
$linhas   = array_filter([
    'razao_social'  => (string) (site_config('identidade.razao_social') ?: site_config('identidade.nome', '')),
    'cnpj'          => \App\Libraries\Site\Cnpj::formatar((string) site_config('identidade.cnpj', '')),
    'registro_profissional'  => (string) site_config('identidade.registro_profissional', ''),
], static fn (string $v): bool => trim($v) !== '');
if ($linhas === [] && $unidades === []) {
    return;
}
?>
            <h2 class="sub-title mt-5"><?= esc(($dados['titulo'] ?? '') !== '' ? $dados['titulo'] : site_texto('institucional.titulo', 'Dados institucionais')) ?></h2>
            <dl class="dados-controlador">
<?php foreach ($linhas as $chave => $valor): ?>
                <dt><?= esc(site_texto('institucional.' . $chave)) ?></dt>
                <dd><?= esc($valor) ?></dd>
<?php endforeach ?>
<?php foreach ($unidades as $unidade): ?>
                <dt><?= esc($unidade['titulo'] ?? site_texto('institucional.endereco')) ?></dt>
                <dd><?= esc(trim(($unidade['linha1'] ?? '') . ' ' . ($unidade['linha2'] ?? ''))) ?></dd>
<?php endforeach ?>
            </dl>
