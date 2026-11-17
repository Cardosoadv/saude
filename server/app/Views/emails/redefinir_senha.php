<?php
/**
 * Corpo do e-mail de redefinição de senha do sistema.
 *
 * @var string $nome
 * @var string $link
 * @var int    $expiracaoH Validade do link, em horas.
 */
?>
<p style="margin:0 0 6px;">Olá, <strong style="color:#0f1923;"><?= esc($nome) ?></strong>,</p>
<p style="margin:0;">
    Recebemos um pedido para criar uma nova senha de acesso ao sistema. Clique no botão abaixo para escolher a nova senha.
</p>

<?= email_botao($link, 'Criar nova senha') ?>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:26px 0 0;">
    <tr>
        <td style="background:#f8f7f4;border:1px solid #e5e2db;border-radius:8px;padding:12px 16px;">
            <div style="font-size:12px;color:#6b7280;margin-bottom:4px;">Se o botão não funcionar, copie e cole este link no navegador:</div>
            <div style="font-family:'Courier New',Courier,monospace;font-size:11px;line-height:1.5;color:#374151;word-break:break-all;"><?= esc($link) ?></div>
        </td>
    </tr>
</table>

<?= email_aviso(
    'Este link vale por ' . (int) $expiracaoH . ' hora' . ($expiracaoH > 1 ? 's' : '') . ' e só pode ser usado uma vez.',
    'warning',
    'Importante:'
) ?>

<?= email_aviso(
    'Se você não pediu a redefinição, ignore este e-mail: sua senha continua a mesma.',
    'danger',
    'Atenção:'
) ?>
