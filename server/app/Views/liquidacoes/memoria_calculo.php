<?php
/**
 * Memória de cálculo da liquidação (PDF).
 *
 * @var array  $relatorio  RelatorioLiquidacao::montar()
 * @var string $escritorio Nome do escritório (opcional)
 */

use App\Libraries\Liquidacao\RelatorioLiquidacao as R;

$c = $relatorio['cabecalho'];
$statusRotulos = [
    'rascunho' => 'Rascunho', 'em_calculo' => 'Em cálculo', 'apresentada' => 'Apresentada', 'impugnada' => 'Impugnada',
    'homologada' => 'Homologada', 'em_pagamento' => 'Em pagamento', 'quitada' => 'Quitada',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Memória de cálculo - liquidação <?= (int) $c['liquidacao_id'] ?></title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #111; }
        h1 { font-size: 14pt; margin: 0 0 4px; }
        h2 { font-size: 10.5pt; margin: 16px 0 6px; border-bottom: 1px solid #444; padding-bottom: 2px; }
        .meta td { padding: 1px 8px 1px 0; vertical-align: top; }
        .meta td:first-child { color: #555; white-space: nowrap; }
        table.dados { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.dados th { background: #e8e8e8; text-align: left; font-size: 8pt; padding: 3px 4px; border: 1px solid #999; }
        table.dados td { padding: 2px 4px; border: 1px solid #bbb; font-size: 8.5pt; }
        .num { text-align: right; white-space: nowrap; }
        .total td { font-weight: bold; background: #f3f3f3; }
        ul { margin: 2px 0 6px 16px; padding: 0; }
        .aviso { color: #7a4b00; }
        .rodape { margin-top: 18px; font-size: 7.5pt; color: #555; }
    </style>
</head>
<body>
<h1>Memória de cálculo - liquidação #<?= (int) $c['liquidacao_id'] ?></h1>
<?php if (!empty($escritorio)): ?><div><?= esc($escritorio) ?></div><?php endif; ?>

<table class="meta">
    <tr><td>Processo</td><td><?= esc((string) ($c['processo'] ?? '-')) ?></td></tr>
    <?php if (!empty($c['cliente'])): ?><tr><td>Cliente</td><td><?= esc($c['cliente']) ?></td></tr><?php endif; ?>
    <?php if (!empty($c['poloAtivo'])): ?><tr><td>Polo ativo</td><td><?= esc(implode('; ', $c['poloAtivo'])) ?></td></tr><?php endif; ?>
    <?php if (!empty($c['poloPassivo'])): ?><tr><td>Polo passivo</td><td><?= esc(implode('; ', $c['poloPassivo'])) ?></td></tr><?php endif; ?>
    <tr><td>Data-base</td><td><?= esc(R::data($c['data_base'])) ?></td></tr>
    <tr><td>Situação</td><td><?= esc($statusRotulos[$c['status']] ?? (string) $c['status']) ?></td></tr>
    <?php if (!empty($c['calculado_em'])): ?><tr><td>Último cálculo</td><td><?= esc(R::data(substr((string) $c['calculado_em'], 0, 10))) ?></td></tr><?php endif; ?>
</table>

<?php if (!empty($c['criterio_padrao'])): ?>
    <h2>Critério padrão da liquidação</h2>
    <ul><?php foreach ($c['criterio_padrao'] as $f): ?><li><?= esc($f) ?></li><?php endforeach; ?></ul>
<?php endif; ?>

<?php foreach ($relatorio['itens'] as $item): ?>
    <h2><?= esc($item['descricao'] !== '' ? $item['descricao'] : 'Item') ?> - R$ <?= R::reais($item['valor']) ?></h2>
    <?php if (!empty($item['criterio'])): ?>
        <ul><?php foreach ($item['criterio'] as $f): ?><li><?= esc($f) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <table class="dados">
        <thead><tr><th>Data</th><th class="num">Valor original</th><th class="num">Fator</th><th class="num">Corrigido</th><th class="num">Juros</th><th class="num">Subtotal</th><th>Obs.</th></tr></thead>
        <tbody>
        <?php foreach ($item['linhas'] as $l): ?>
            <tr>
                <td><?= esc(R::data($l[2])) ?></td>
                <td class="num"><?= R::reais($l[3]) ?></td>
                <td class="num"><?= esc((string) ($l[4] ?? '')) ?></td>
                <td class="num"><?= R::reais($l[5]) ?></td>
                <td class="num"><?= R::reais($l[6]) ?></td>
                <td class="num"><?= R::reais($l[7]) ?></td>
                <td><?= esc((string) ($l[8] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endforeach; ?>

<?php if (!empty($relatorio['abatimentos'])): ?>
    <h2>Abatimentos considerados</h2>
    <table class="dados">
        <thead><tr><th>Item</th><th>Data</th><th class="num">Valor</th></tr></thead>
        <tbody>
        <?php foreach ($relatorio['abatimentos'] as $a): ?>
            <tr><td><?= esc($a['item']) ?></td><td><?= esc(R::data($a['data'])) ?></td><td class="num"><?= R::reais($a['valor']) ?></td></tr>
        <?php endforeach; ?>
        <tr class="total"><td colspan="2">Total abatido</td><td class="num"><?= R::reais($relatorio['totais']['abatimentos']) ?></td></tr>
        </tbody>
    </table>
<?php endif; ?>

<h2>Total</h2>
<table class="dados">
    <tr class="total"><td>Valor atualizado em <?= esc(R::data($c['data_base'])) ?></td><td class="num">R$ <?= R::reais($relatorio['totais']['valor_total']) ?></td></tr>
</table>

<?php if (!empty($relatorio['avisos'])): ?>
    <h2>Avisos do cálculo</h2>
    <ul class="aviso"><?php foreach ($relatorio['avisos'] as $a): ?><li><?= esc($a) ?></li><?php endforeach; ?></ul>
<?php endif; ?>

<div class="rodape">Documento gerado em <?= date('d/m/Y H:i') ?>. Valores em reais; índices conforme cadastro do sistema, sem arredondamentos intermediários além dos centavos de cada lançamento.</div>
</body>
</html>
