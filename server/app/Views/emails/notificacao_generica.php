<?php
/**
 * Corpo do e-mail de notificação genérica.
 *
 * @var string $urlSistema
 */
?>
<p style="margin:0 0 6px;">Olá,</p>
<p style="margin:0;">Você tem uma nova notificação. Acesse o sistema para verificar e tomar as ações necessárias.</p>

<?= email_botao($urlSistema, 'Acessar o sistema') ?>
