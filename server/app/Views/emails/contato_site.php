<?php
/**
 * Corpo do e-mail enviado ao escritório quando alguém preenche o formulário do site.
 *
 * @var array  $contact   name, email, phone, message, area_interesse
 * @var string $areaTitle Nome da área de interesse (pode ser vazio).
 */
$email = trim((string) ($contact['email'] ?? ''));
?>
<p style="margin:0 0 6px;">Olá,</p>
<p style="margin:0 0 18px;">Uma pessoa entrou em contato pelo formulário do site. Responda o quanto antes para não perder a oportunidade.</p>

<?= email_detalhes([
    'Nome'     => $contact['name'] ?? '',
    'E-mail'   => $email,
    'Telefone' => $contact['phone'] ?? '',
    'Área'     => $areaTitle ?? '',
]) ?>

<?php if (trim((string) ($contact['message'] ?? '')) !== ''): ?>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:22px 0 0;">
        <tr>
            <td style="background:#f8f7f4;border:1px solid #e5e2db;border-left:4px solid #c8922a;border-radius:8px;padding:16px 20px;">
                <div style="font-size:11px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:#c8922a;margin-bottom:8px;">Mensagem</div>
                <div style="font-size:15px;line-height:1.7;color:#374151;"><?= nl2br(esc($contact['message'])) ?></div>
            </td>
        </tr>
    </table>
<?php endif; ?>

<?php if ($email !== ''): ?>
    <?= email_botao('mailto:' . $email, 'Responder contato') ?>
<?php endif; ?>
