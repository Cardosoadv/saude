<?php
/**
 * Corpo do e-mail de tarefa (prazo vencendo ou nova atribuição).
 *
 * @var string      $modo           'prazo' | 'atribuida'
 * @var string      $tarefa         Título da tarefa.
 * @var string|null $detalhes       Descrição da tarefa.
 * @var string      $prazoFormatado dd/mm/aaaa.
 * @var string|null $situacaoPrazo  Ex.: "vence em 2 dias".
 * @var array|null  $processo       Resumo do processo vinculado (ver NotificacaoRepository::resumoProcesso).
 * @var array|null  $vinculo        Link para cliente/caso vinculado: rotulo, url, info.
 * @var string      $urlSistema
 */
?>
<p style="margin:0 0 6px;">Olá,</p>
<p style="margin:0 0 22px;">
    <?= $modo === 'prazo'
        ? 'Uma tarefa sob sua responsabilidade está com o prazo se aproximando.'
        : 'Uma nova tarefa foi atribuída a você.' ?>
</p>

<div style="font-family:Georgia,'Playfair Display',serif;font-size:20px;font-weight:700;line-height:1.35;color:#0f1923;margin:0 0 14px;">
    <?= esc($tarefa) ?>
</div>

<?= email_detalhes([
    'Prazo'    => $prazoFormatado . ($situacaoPrazo ? ' — ' . $situacaoPrazo : ''),
    'Detalhes' => $detalhes,
]) ?>

<?php if (!empty($processo)): ?>
    <?= email_cartao_processo($processo) ?>
<?php endif; ?>

<?php if (!empty($vinculo)): ?>
    <?= email_detalhes([$vinculo['rotulo'] => $vinculo['info']]) ?>
<?php endif; ?>

<?= email_botao($processo['url'] ?? $vinculo['url'] ?? $urlSistema, !empty($processo) ? 'Abrir processo' : (!empty($vinculo) ? $vinculo['acao'] : 'Acessar o sistema')) ?>

<?php if ($modo === 'prazo'): ?>
    <p style="margin:22px 0 0;font-size:13px;color:#6b7280;">Verifique a tarefa e tome as providências necessárias antes do vencimento.</p>
<?php endif; ?>
