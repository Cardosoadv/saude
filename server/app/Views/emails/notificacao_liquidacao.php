<?php
/**
 * Corpo do e-mail de aviso de parcela de liquidação (a vencer ou em atraso).
 *
 * @var bool        $vencida
 * @var string      $parcela     "de entrada" ou o número da parcela.
 * @var int         $liquidacao  Id da liquidação.
 * @var string      $valor       Valor formatado (R$).
 * @var string      $vencimento  dd/mm/aaaa.
 * @var array|null  $processo    Resumo do processo (ver NotificacaoRepository::resumoProcesso).
 * @var string      $urlSistema
 */
?>
<p style="margin:0 0 6px;">Olá,</p>
<p style="margin:0 0 22px;">
    <?= $vencida
        ? 'Uma parcela de liquidação sob sua responsabilidade está em atraso.'
        : 'Uma parcela de liquidação sob sua responsabilidade vence em breve.' ?>
</p>

<?= email_detalhes([
    'Liquidação' => '#' . $liquidacao,
    'Parcela'    => $parcela,
    'Valor'      => $valor,
    'Vencimento' => $vencimento,
]) ?>

<?php if (!empty($processo)): ?>
    <?= email_cartao_processo($processo) ?>
<?php endif; ?>

<?= email_botao($processo['url'] ?? $urlSistema, !empty($processo) ? 'Abrir processo' : 'Acessar o sistema') ?>
