<?php
/**
 * Corpo do e-mail de acesso à Área do Cliente (link mágico).
 *
 * @var string $nomeCliente
 * @var string $linkAcesso
 * @var int    $expiracaoH Validade do link, em horas.
 */
?>
<p style="margin:0 0 6px;">Olá, <strong style="color:#0f1923;"><?= esc($nomeCliente) ?></strong>,</p>
<p style="margin:0;">
    Você solicitou acesso à sua <strong>Área do Cliente</strong>, onde pode acompanhar seus processos,
    visualizar movimentações e enviar mensagens ao advogado responsável.
</p>

<?= email_botao($linkAcesso, 'Acessar Área do Cliente') ?>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:26px 0 0;">
    <tr>
        <td style="background:#f8f7f4;border:1px solid #e5e2db;border-radius:8px;padding:12px 16px;">
            <div style="font-size:12px;color:#6b7280;margin-bottom:4px;">Se o botão não funcionar, copie e cole este link no navegador:</div>
            <div style="font-family:'Courier New',Courier,monospace;font-size:11px;line-height:1.5;color:#374151;word-break:break-all;"><?= esc($linkAcesso) ?></div>
        </td>
    </tr>
</table>

<?= email_aviso(
    'Este link vale por ' . (int) $expiracaoH . ' hora' . ($expiracaoH > 1 ? 's' : '') . ' e só pode ser usado uma vez. Depois de clicar, sua sessão ficará ativa até você sair. Se precisar, solicite um novo link a qualquer momento.',
    'warning',
    'Importante:'
) ?>

<?= email_aviso(
    'Se você não solicitou este acesso, ignore este e-mail com segurança. Nunca compartilhe este link com terceiros.',
    'danger',
    'Atenção:'
) ?>
