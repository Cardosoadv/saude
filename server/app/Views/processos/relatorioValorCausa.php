<?php
/**
 * Conteúdo do Relatório de Valor da Causa, renderizado dentro do papel
 * timbrado institucional (view documentos/timbrado). Sem <html>/<head>
 * próprios: os totais abaixo são calculados em PHP no próprio loop.
 */

$processos = $processos ?? [];
$filtrosAplicados = $filtrosAplicados ?? [];

$totalCausa = 0.0;
foreach ($processos as $p) {
    $totalCausa += (float) ($p['valorCausa'] ?? 0);
}
?>
<h2 style="text-align:center;font-family:Arial,Helvetica,sans-serif;color:#1b4965;margin-bottom:4px;">
    <?= esc($titulo ?? 'Relatório de Valor da Causa') ?>
</h2>
<p style="text-align:center;font-family:Arial,Helvetica,sans-serif;font-size:10pt;color:#555;margin-top:0;margin-bottom:16px;">
    Emitido em <?= date('d/m/Y \à\s H:i:s') ?>
</p>

<?php if (!empty($filtrosAplicados)): ?>
    <div style="border:1px solid #c9a96e;background-color:#f9f7f2;padding:6px 10px;margin-bottom:14px;font-family:Arial,Helvetica,sans-serif;font-size:8pt;color:#333;">
        <strong style="color:#1b4965;">Filtros aplicados:</strong>
        <?= esc(implode('  •  ', $filtrosAplicados)) ?>
    </div>
<?php endif; ?>

<table cellpadding="0" cellspacing="0" style="width:100%;table-layout:fixed;border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;font-size:7pt;">
    <colgroup>
        <col style="width:18%;">
        <col style="width:30%;">
        <col style="width:12%;">
        <col style="width:22%;">
        <col style="width:9%;">
        <col style="width:9%;">
    </colgroup>
    <thead>
        <tr style="background-color:#1b4965;color:#ffffff;">
            <th style="border:1px solid #c9a96e;padding:4px 3px;text-align:left;white-space:nowrap;">Nº do Processo</th>
            <th style="border:1px solid #c9a96e;padding:4px 3px;text-align:left;">Título/Assunto</th>
            <th style="border:1px solid #c9a96e;padding:4px 3px;text-align:center;">Data Distrib.</th>
            <th style="border:1px solid #c9a96e;padding:4px 3px;text-align:left;">Cliente</th>
            <th style="border:1px solid #c9a96e;padding:4px 3px;text-align:center;">Status</th>
            <th style="border:1px solid #c9a96e;padding:4px 3px;text-align:right;">Valor da Causa</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($processos)): ?>
            <tr>
                <td colspan="6" style="border:1px solid #ddd;padding:10px;text-align:center;color:#777;">
                    Nenhum processo encontrado para os filtros selecionados.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($processos as $i => $p): ?>
                <?php $bgLinha = $i % 2 === 0 ? '#ffffff' : '#f5f7fa'; ?>
                <tr style="background-color:<?= $bgLinha ?>;">
                    <td style="border:1px solid #ddd;padding:3px;white-space:nowrap;"><?= esc($p['numeroprocessocommascara'] ?? $p['numero_processo'] ?? '') ?></td>
                    <td style="border:1px solid #ddd;padding:3px;word-wrap:break-word;"><?= esc($p['titulo_processo'] ?? '') ?></td>
                    <td style="border:1px solid #ddd;padding:3px;word-wrap:break-word;text-align:center;"><?= !empty($p['dataDistribuicao']) ? date('d/m/Y', strtotime($p['dataDistribuicao'])) : '-' ?></td>
                    <td style="border:1px solid #ddd;padding:3px;word-wrap:break-word;"><?= esc($p['cliente_nome'] ?? '') ?></td>
                    <td style="border:1px solid #ddd;padding:3px;word-wrap:break-word;text-align:center;">
                        <?php if ((int) ($p['encerrado'] ?? 0) === 1): ?>
                            <span style="color:#c0392b;font-weight:bold;">ENCERRADO</span>
                        <?php else: ?>
                            <span style="color:#1e7e34;font-weight:bold;">ATIVO</span>
                        <?php endif; ?>
                    </td>
                    <td style="border:1px solid #ddd;padding:3px;word-wrap:break-word;text-align:right;">R$ <?= number_format((float) ($p['valorCausa'] ?? 0), 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (!empty($processos)): ?>
        <tfoot>
            <tr style="background-color:#e8ecef;font-weight:bold;">
                <td colspan="5" style="border:1px solid #ddd;padding:5px 4px;text-align:right;">TOTAL:</td>
                <td style="border:1px solid #ddd;padding:5px 4px;text-align:right;">R$ <?= number_format($totalCausa, 2, ',', '.') ?></td>
            </tr>
        </tfoot>
    <?php endif; ?>
</table>

<table cellpadding="0" cellspacing="0" style="width:100%;margin-top:18px;font-family:Arial,Helvetica,sans-serif;font-size:8pt;border-collapse:collapse;">
    <tr>
        <td style="width:50%;border:1px solid #ddd;padding:6px;text-align:center;">
            Total de Processos<br><strong style="font-size:11pt;"><?= count($processos) ?></strong>
        </td>
        <td style="width:50%;border:1px solid #ddd;padding:6px;text-align:center;">
            Valor Total das Causas<br><strong style="font-size:11pt;">R$ <?= number_format($totalCausa, 2, ',', '.') ?></strong>
        </td>
    </tr>
</table>
