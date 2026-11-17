<?php
/**
 * Corpo do e-mail de mensagem recebida.
 *
 * @var string      $remetente  Nome de quem enviou (pode ser vazio).
 * @var string      $assunto
 * @var string      $conteudo   Texto da mensagem.
 * @var array|null  $processo   Resumo do processo vinculado.
 * @var string      $url        Link para abrir a mensagem no sistema.
 */
?>
<p style="margin:0 0 6px;">Olá,</p>
<p style="margin:0 0 22px;">
    <?= $remetente !== '' ? '<strong style="color:#0f1923;">' . esc($remetente) . '</strong> enviou' : 'Você recebeu' ?>
    uma mensagem para você.
</p>

<?php if (!empty($processo)): ?>
    <?= email_cartao_processo($processo) ?>
<?php endif; ?>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:22px 0 0;">
    <tr>
        <td style="border:1px solid #e5e2db;border-radius:8px;padding:18px 20px;">
            <div style="font-size:11px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:#6b7280;">Assunto</div>
            <div style="font-family:Georgia,'Playfair Display',serif;font-size:18px;font-weight:700;color:#0f1923;margin:4px 0 14px;line-height:1.35;"><?= esc($assunto) ?></div>
            <div style="border-top:1px solid #eeebe4;padding-top:14px;font-size:15px;line-height:1.7;color:#374151;">
                <?= nl2br(esc($conteudo)) ?>
            </div>
        </td>
    </tr>
</table>

<?= email_botao($url, 'Abrir mensagem') ?>
