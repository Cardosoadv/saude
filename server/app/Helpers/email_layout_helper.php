<?php

/**
 * Componentes HTML para e-mails transacionais.
 *
 * Clientes de e-mail (Outlook, Gmail) ignoram boa parte do CSS moderno, então tudo aqui
 * usa tabelas e estilos inline. As cores seguem o design system do frontend
 * (sistema/src/style.css): azul-petróleo #013B51 e dourado #c8922a.
 */

if (!function_exists('email_tom')) {
    /**
     * Paleta de cada tom de destaque: cor forte, fundo suave e cor do texto sobre o fundo.
     *
     * @return array{cor: string, fundo: string, texto: string}
     */
    function email_tom(string $tom = 'primary'): array
    {
        return match ($tom) {
            'warning' => ['cor' => '#d97706', 'fundo' => '#fef6e7', 'texto' => '#92400e'],
            'danger'  => ['cor' => '#dc2626', 'fundo' => '#fef2f2', 'texto' => '#991b1b'],
            'success' => ['cor' => '#16a34a', 'fundo' => '#f0fdf4', 'texto' => '#166534'],
            'accent'  => ['cor' => '#c8922a', 'fundo' => '#fbf5e8', 'texto' => '#7a5a14'],
            default   => ['cor' => '#013B51', 'fundo' => '#e8f1f5', 'texto' => '#013B51'],
        };
    }
}

if (!function_exists('email_botao')) {
    /** Botão de chamada para ação (compatível com Outlook). */
    function email_botao(string $url, string $rotulo): string
    {
        $url    = esc($url, 'attr');
        $rotulo = esc($rotulo);

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 4px;">'
            . '<tr><td align="center" bgcolor="#013B51" style="border-radius:8px;">'
            . '<a href="' . $url . '" target="_blank" style="display:inline-block;padding:14px 32px;font-family:\'DM Sans\',Arial,Helvetica,sans-serif;'
            . 'font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;letter-spacing:.2px;">'
            . $rotulo . ' &rarr;</a></td></tr></table>';
    }
}

if (!function_exists('email_detalhes')) {
    /**
     * Lista de pares rótulo/valor. Valores nulos ou vazios são omitidos.
     *
     * @param array<string, string|null> $linhas Rótulo => valor já em texto puro (será escapado).
     */
    function email_detalhes(array $linhas): string
    {
        $html = '';
        foreach ($linhas as $rotulo => $valor) {
            if ($valor === null || trim((string) $valor) === '') {
                continue;
            }
            $html .= '<tr>'
                . '<td style="padding:10px 0;border-bottom:1px solid #eeebe4;font-family:\'DM Sans\',Arial,Helvetica,sans-serif;'
                . 'font-size:12px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:#6b7280;width:130px;vertical-align:top;">'
                . esc($rotulo) . '</td>'
                . '<td style="padding:10px 0;border-bottom:1px solid #eeebe4;font-family:\'DM Sans\',Arial,Helvetica,sans-serif;'
                . 'font-size:15px;color:#0f1923;line-height:1.5;">' . nl2br(esc((string) $valor)) . '</td>'
                . '</tr>';
        }

        if ($html === '') {
            return '';
        }

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:8px 0 0;">' . $html . '</table>';
    }
}

if (!function_exists('email_aviso')) {
    /** Faixa de destaque com barra lateral colorida. O texto é escapado. */
    function email_aviso(string $texto, string $tom = 'warning', ?string $titulo = null): string
    {
        $t = email_tom($tom);

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:20px 0 0;">'
            . '<tr><td style="background:' . $t['fundo'] . ';border-left:4px solid ' . $t['cor'] . ';border-radius:6px;padding:14px 16px;'
            . 'font-family:\'DM Sans\',Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:' . $t['texto'] . ';">'
            . ($titulo !== null ? '<strong>' . esc($titulo) . '</strong> ' : '')
            . esc($texto) . '</td></tr></table>';
    }
}
