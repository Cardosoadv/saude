<?php
/**
 * Conteúdo do Relatório de Objetos com Processos Vinculados, renderizado
 * dentro do papel timbrado institucional (view documentos/timbrado), no
 * mesmo padrão visual do Relatório CPC 25. Sem <html>/<head> próprios e
 * sem JavaScript: o Dompdf não executa script.
 */

$grupos = $grupos ?? [];

$totalObjetos = count($grupos);
$totalProcessos = 0;
foreach ($grupos as $g) {
    $totalProcessos += count($g['processos'] ?? []);
}

$relObjNome = static function (array $objeto): string {
    $partes = array_filter([
        $objeto['bairro'] ?? null,
        !empty($objeto['quadra']) ? 'Quadra ' . $objeto['quadra'] : null,
        !empty($objeto['lote']) ? 'Lote ' . $objeto['lote'] : null,
    ]);

    return $partes ? implode(' - ', $partes) : ($objeto['cod_interno'] ?? ('Objeto #' . $objeto['id_objeto']));
};
?>
<h2 style="text-align:center;font-family:Arial,Helvetica,sans-serif;color:#1b4965;margin-bottom:4px;">
    <?= esc($titulo ?? 'Relatório de Objetos com Processos Vinculados') ?>
</h2>
<p style="text-align:center;font-family:Arial,Helvetica,sans-serif;font-size:10pt;color:#555;margin-top:0;margin-bottom:16px;">
    Emitido em <?= date('d/m/Y \à\s H:i:s') ?>
</p>

<table cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:18px;font-family:Arial,Helvetica,sans-serif;font-size:8pt;border-collapse:collapse;">
    <tr>
        <td style="width:50%;border:1px solid #ddd;padding:6px;text-align:center;">
            Total de Objetos<br><strong style="font-size:11pt;"><?= $totalObjetos ?></strong>
        </td>
        <td style="width:50%;border:1px solid #ddd;padding:6px;text-align:center;">
            Total de Processos Vinculados<br><strong style="font-size:11pt;"><?= $totalProcessos ?></strong>
        </td>
    </tr>
</table>

<?php if (empty($grupos)): ?>
    <p style="text-align:center;color:#777;font-family:Arial,Helvetica,sans-serif;padding:20px 0;">
        Nenhum objeto com processo vinculado foi encontrado.
    </p>
<?php else: ?>
    <?php foreach ($grupos as $g): ?>
        <?php
        $objeto = $g['objeto'];
        $processos = $g['processos'] ?? [];

        $endereco = array_filter([
            $objeto['logradouro'] ?? null,
            !empty($objeto['numero']) ? 'nº ' . $objeto['numero'] : null,
            $objeto['complemento'] ?? null,
        ]);
        $enderecoTexto = implode(', ', $endereco);

        $identificadores = array_filter([
            !empty($objeto['cod_interno']) ? 'Código: ' . $objeto['cod_interno'] : null,
            !empty($objeto['inscricao']) ? 'Inscrição: ' . $objeto['inscricao'] : null,
            !empty($objeto['matricula']) ? 'Matrícula: ' . $objeto['matricula'] : null,
            !empty($objeto['cartorio']) ? 'Cartório: ' . $objeto['cartorio'] : null,
        ]);
        ?>
        <table cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:2px;border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;">
            <tr>
                <td style="background-color:#1b4965;border:1px solid #c9a96e;padding:6px 8px;">
                    <div style="color:#ffffff;font-size:9pt;font-weight:bold;">
                        <i>&#8962;</i> <?= esc($relObjNome($objeto)) ?>
                        <?= !empty($objeto['cidade']) ? ' — ' . esc($objeto['cidade']) : '' ?>
                    </div>
                    <?php if ($enderecoTexto !== ''): ?>
                        <div style="color:#dbe6ee;font-size:7.5pt;margin-top:2px;"><?= esc($enderecoTexto) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($identificadores)): ?>
                        <div style="color:#dbe6ee;font-size:7.5pt;margin-top:2px;"><?= esc(implode('  •  ', $identificadores)) ?></div>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <table cellpadding="0" cellspacing="0" style="width:100%;table-layout:fixed;border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;font-size:7pt;margin-bottom:16px;">
            <colgroup>
                <col style="width:16%;">
                <col style="width:28%;">
                <col style="width:22%;">
                <col style="width:11%;">
                <col style="width:11%;">
                <col style="width:12%;">
            </colgroup>
            <thead>
                <tr style="background-color:#e8ecef;color:#1b4965;">
                    <th style="border:1px solid #c9a96e;padding:4px 3px;text-align:left;white-space:nowrap;">Nº do Processo</th>
                    <th style="border:1px solid #c9a96e;padding:4px 3px;text-align:left;">Título/Assunto</th>
                    <th style="border:1px solid #c9a96e;padding:4px 3px;text-align:left;">Cliente</th>
                    <th style="border:1px solid #c9a96e;padding:4px 3px;text-align:center;">Data Distrib.</th>
                    <th style="border:1px solid #c9a96e;padding:4px 3px;text-align:center;">Status</th>
                    <th style="border:1px solid #c9a96e;padding:4px 3px;text-align:right;">Valor da Causa</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($processos as $i => $p): ?>
                    <?php $bgLinha = $i % 2 === 0 ? '#ffffff' : '#f5f7fa'; ?>
                    <tr style="background-color:<?= $bgLinha ?>;">
                        <td style="border:1px solid #ddd;padding:3px;white-space:nowrap;"><?= esc($p['numeroprocessocommascara'] ?? $p['numero_processo'] ?? '') ?></td>
                        <td style="border:1px solid #ddd;padding:3px;word-wrap:break-word;"><?= esc($p['titulo_processo'] ?? '') ?></td>
                        <td style="border:1px solid #ddd;padding:3px;word-wrap:break-word;"><?= esc($p['cliente_nome'] ?? '') ?></td>
                        <td style="border:1px solid #ddd;padding:3px;text-align:center;"><?= !empty($p['dataDistribuicao']) ? date('d/m/Y', strtotime($p['dataDistribuicao'])) : '-' ?></td>
                        <td style="border:1px solid #ddd;padding:3px;text-align:center;">
                            <?php if ((int) ($p['encerrado'] ?? 0) === 1): ?>
                                <span style="color:#c0392b;font-weight:bold;">ENCERRADO</span>
                            <?php else: ?>
                                <span style="color:#1e7e34;font-weight:bold;">ATIVO</span>
                            <?php endif; ?>
                        </td>
                        <td style="border:1px solid #ddd;padding:3px;text-align:right;">R$ <?= number_format((float) ($p['valorCausa'] ?? 0), 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
<?php endif; ?>
