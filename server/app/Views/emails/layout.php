<?php
/**
 * Moldura comum dos e-mails: logo, faixa de título, conteúdo e rodapé.
 *
 * @var string      $conteudo        HTML já montado do corpo (não escapado aqui).
 * @var string      $titulo          Título exibido na faixa superior.
 * @var string|null $etiqueta        Rótulo curto acima do título (ex.: "Prazo").
 * @var string      $tom             primary|warning|danger|success|accent — cor da etiqueta.
 * @var string|null $preheader       Texto de pré-visualização exibido pelo cliente de e-mail.
 * @var string|null $logoSrc         URL do logo (normalmente "cid:...").
 * @var string      $nomeClinica  Nome exibido no rodapé e como texto alternativo do logo.
 * @var string|null $rodape          Observação adicional no rodapé.
 */
helper('email_layout');
$tom      = $tom ?? 'primary';
$etiqueta = $etiqueta ?? null;
$cores    = email_tom($tom);
// Sobre o fundo azul-petróleo, o tom primário não tem contraste: a etiqueta usa o dourado.
$corEtiqueta = $tom === 'primary' ? '#e0b45c' : $cores['cor'];
$fonte = "font-family:'DM Sans',Arial,Helvetica,sans-serif;";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <title><?= esc($titulo) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; background: #f8f7f4; }
        a { color: #013B51; }
        @media only screen and (max-width: 620px) {
            .caixa  { width: 100% !important; }
            .pad    { padding-left: 22px !important; padding-right: 22px !important; }
            .titulo { font-size: 22px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#f8f7f4;">
    <?php if (!empty($preheader)): ?>
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:#f8f7f4;">
            <?= esc($preheader) ?>
        </div>
    <?php endif; ?>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f8f7f4" style="background:#f8f7f4;">
        <tr>
            <td align="center" style="padding:32px 12px;">

                <table role="presentation" class="caixa" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;">

                    <!-- Logo -->
                    <tr>
                        <td align="center" bgcolor="#ffffff" style="background:#ffffff;border:1px solid #e5e2db;border-bottom:0;border-radius:12px 12px 0 0;padding:26px 20px 20px;">
                            <?php if (!empty($logoSrc)): ?>
                                <img src="<?= htmlspecialchars($logoSrc, ENT_QUOTES) ?>" alt="<?= esc($nomeClinica, 'attr') ?>" height="64" style="display:block;height:64px;width:auto;max-width:240px;border:0;margin:0 auto;">
                            <?php else: ?>
                                <div style="font-family:Georgia,'Playfair Display',serif;font-size:24px;font-weight:700;color:#013B51;letter-spacing:.5px;"><?= esc($nomeClinica) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Filete dourado -->
                    <tr><td height="3" style="height:3px;font-size:0;line-height:0;background:#c8922a;border-left:1px solid #c8922a;border-right:1px solid #c8922a;">&nbsp;</td></tr>

                    <!-- Faixa de título -->
                    <tr>
                        <td class="pad" bgcolor="#013B51" style="background:#013B51;padding:30px 40px 32px;border-left:1px solid #013B51;border-right:1px solid #013B51;">
                            <?php if ($etiqueta): ?>
                                <div style="<?= $fonte ?>font-size:11px;font-weight:700;letter-spacing:1.6px;text-transform:uppercase;color:<?= $corEtiqueta ?>;margin-bottom:10px;">
                                    <?= esc($etiqueta) ?>
                                </div>
                            <?php endif; ?>
                            <div class="titulo" style="font-family:Georgia,'Playfair Display',serif;font-size:26px;line-height:1.25;font-weight:700;color:#ffffff;">
                                <?= esc($titulo) ?>
                            </div>
                        </td>
                    </tr>

                    <!-- Conteúdo -->
                    <tr>
                        <td class="pad" bgcolor="#ffffff" style="background:#ffffff;padding:34px 40px 36px;border:1px solid #e5e2db;border-top:0;border-bottom:0;<?= $fonte ?>font-size:15px;line-height:1.65;color:#374151;">
                            <?= $conteudo ?>
                        </td>
                    </tr>

                    <!-- Rodapé -->
                    <tr>
                        <td class="pad" align="center" bgcolor="#ffffff" style="background:#ffffff;border:1px solid #e5e2db;border-top:1px solid #eeebe4;border-radius:0 0 12px 12px;padding:22px 40px 26px;<?= $fonte ?>font-size:12px;line-height:1.7;color:#6b7280;">
                            <?php if (!empty($rodape)): ?>
                                <?= esc($rodape) ?><br>
                            <?php endif; ?>
                            Este é um e-mail automático. Por favor, não responda a esta mensagem.<br>
                            <strong style="color:#013B51;"><?= esc($nomeClinica) ?></strong>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
