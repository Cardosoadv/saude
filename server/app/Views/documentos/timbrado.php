<?php
// Dados do escritório vêm das configurações do site (identidade.*, contato.*); ver DadosEscritorio.
$escritorio     = \App\Libraries\Site\DadosEscritorio::atual();
$nomeEscritorio = $escritorio->nome();
$corDocumento   = $escritorio->cor();
$corSvg         = rawurlencode($corDocumento);
$telefones      = $escritorio->telefones();
$siteEscritorio = $escritorio->site();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title><?= esc($titulo ?? trim('Papel Timbrado - ' . $nomeEscritorio, ' -')) ?></title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Times New Roman', Georgia, serif;
            font-size: 11pt; /* Tamanho levemente menor para comportar relatórios densos */
            color: #222;
        }

        /* Oculta as bordas da tabela estrutural (layout mestre) */
        table.layout-mestre {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }
        table.layout-mestre > thead > tr > td,
        table.layout-mestre > tbody > tr > td,
        table.layout-mestre > tfoot > tr > td {
            border: none;
            padding: 0;
            background: transparent;
        }

        .faixa-dourada {
            position: fixed;
            top: 0;
            left: 0;
            width: 0.5cm;
            height: 100vh; /* Ocupa 100% da altura da página */
            background-color: #c9a96e;
            z-index: 999;
        }

        .cabecalho {
            position: fixed;
            top: 0.5cm;
            left: 2cm;
            right: 2cm;
            height: 3cm; /* Altura exata reservada para o cabeçalho */
            z-index: 999;
        }

        .rodape {
            position: fixed;
            bottom: 0.5cm;
            left: 1.5cm;
            right: 1.5cm;
            height: 2.5cm; /* Altura exata reservada para o rodapé */
            z-index: 999;
        }

        .traco-cabecalho {
            border-top: 1px solid #333;
            width: 100%;
            margin-top: 8px;
        }
        .traco-rodape {
            border-top: 1px solid #333;
            width: 100%;
            margin-bottom: 6px;
        }
        
        .contatos {
            text-align: right;
            font-size: 10pt;
            color: <?= $corDocumento ?>;
            line-height: 1.4;
            font-family: Arial, Helvetica, sans-serif;
        }
        .contatos img {
            vertical-align: middle;
            margin-right: 4px;
        }

        /* Estes elementos garantem que o conteúdo não passe por cima do cabeçalho/rodapé fixo */
        .espaco-cabecalho {
            height: 3.5cm; /* Compensa o top (0.5) + height do cabeçalho (3.0) */
        }
        .espaco-rodape {
            height: 3cm; /* Compensa o bottom (0.5) + height do rodapé (2.5) */
        }

        .conteudo-wrapper {
            padding-left: 1.5cm;
            padding-right: 1.5cm;
        }

        /* Ajustes essenciais caso o $conteudo seja uma tabela (Ex: Relatório CPC 25) */
        .conteudo-wrapper table {
            width: 100%;
            border-collapse: collapse;
        }
        .conteudo-wrapper tr {
            page-break-inside: avoid; /* Impede que uma linha seja cortada ao meio na virada da página */
        }

        @media print {
            @page {
                size: A4;
                margin: 0; /* Remove margens automáticas do navegador */
            }
            body {
                background-color: transparent;
            }
        }

        @media screen {
            body {
                background-color: #525659; /* Fundo cinza escuro estilo leitor de PDF */
                display: flex;
                justify-content: center;
                padding: 40px 0;
            }
            .documento-container {
                background-color: #ffffff;
                width: 21cm; /* Largura A4 */
                min-height: 29.7cm; /* Altura Mínima A4 */
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
                position: relative;
            }
            /* Na tela, para não bugar o scroll, posicionamos absoluto ao invés de fixed */
            .faixa-dourada { position: absolute; height: 100%; }
            .cabecalho { position: absolute; }
            .rodape { position: absolute; bottom: 0.5cm; top: auto; }
        }
    </style>
</head>

<body>
    <div class="documento-container">
        
        <!-- ELEMENTOS FIXOS QUE REPETEM NA IMPRESSÃO -->
        <div class="faixa-dourada"></div>

        <div class="cabecalho">
            <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">
                <tr>
                    <td style="width: 1%; white-space: nowrap; vertical-align: middle; padding-right: 15px; border: none;">
                        <?php if (!empty($logoBase64)): ?>
                            <img src="<?= $logoBase64 ?>" alt="<?= esc($nomeEscritorio, 'attr') ?>" style="max-width: 6cm; max-height: 2.2cm; display: block;">
                        <?php else: ?>
                            <!-- Fallback caso não tenha logo -->
                            <div style="font-family: Arial; font-size: 16pt; font-weight: bold; color: <?= $corDocumento ?>;"><?= esc($escritorio->nomeCurto()) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="vertical-align: middle; border: none; padding: 0;">
                        <div class="traco-cabecalho"></div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="rodape">
            <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">
                <tr>
                    <td style="vertical-align: middle; border: none; padding-right: 15px;">
                        <div class="traco-rodape"></div>
                    </td>
                    <td style="width: 1%; white-space: nowrap; vertical-align: middle; text-align: right; border: none; padding: 0;">
                        <div class="contatos">
<?php foreach ($telefones as $telefone): ?>
                            <div style="margin-bottom: 2px;">
                                <img src='data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="%2325D366"><path d="M12.031 0C5.396 0 .02 5.376.02 12.012c0 2.119.554 4.185 1.609 6.007L0 24l6.147-1.612a11.968 11.968 0 0 0 5.884 1.533h.005c6.634 0 12.01-5.376 12.01-12.012A11.94 11.94 0 0 0 12.031 0zm0 22.014h-.004a9.98 9.98 0 0 1-5.089-1.395l-.365-.217-3.782.992 1.01-3.687-.238-.379a9.96 9.96 0 0 1-1.528-5.316c0-5.524 4.495-10.018 10.02-10.018 2.674 0 5.187 1.042 7.077 2.934 1.89 1.892 2.929 4.406 2.928 7.08 0 5.525-4.495 10.02-10.024 10.02z"/></svg>' width="14" height="14" alt="WhatsApp">
                                <span><?= esc($telefone) ?></span>
                            </div>
<?php endforeach; ?>
                            <div>
                                <img src='data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="%23<?= substr($corSvg, 3) ?>"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm0 2.182c1.867 0 3.6.495 5.09 1.364H6.91A9.78 9.78 0 0 1 12 2.182zM4.195 5.455h15.61c.883 1.258 1.488 2.718 1.745 4.364H2.45c.257-1.646.862-3.106 1.745-4.364zM2.182 12c0-.704.067-1.392.193-2.057h19.25c.126.665.193 1.353.193 2.057s-.067 1.392-.193 2.057H2.375A9.75 9.75 0 0 1 2.182 12zm2.013 4.364h15.61c-.883 1.258-1.488 2.718-1.745 4.364H5.94c-.257-1.646-.862-3.106-1.745-4.364zm2.715 6.454h10.18a9.78 9.78 0 0 1-5.09 1.364 9.78 9.78 0 0 1-5.09-1.364z"/></svg>' width="14" height="14" alt="Website">
                                <span><?= esc($siteEscritorio) ?></span>
                            </div>
<?php $registrosDoc = array_filter([$escritorio->cnpj() !== '' ? 'CNPJ ' . $escritorio->cnpj() : '', $escritorio->registroOab()]); ?>
<?php if ($registrosDoc !== []): ?>
                            <div style="margin-top: 2px;"><span><?= esc($escritorio->razaoSocial()) ?> &middot; <?= esc(implode(' · ', $registrosDoc)) ?></span></div>
<?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ESTA É A TABELA QUE PERMITE A QUEBRA PERFEITA DE PÁGINAS -->
        <table class="layout-mestre">
            <thead>
                <tr>
                    <td>
                        <!-- Ocupa o espaço do cabeçalho em todas as páginas para não sobrepor o conteúdo -->
                        <div class="espaco-cabecalho"></div>
                    </td>
                </tr>
            </thead>
            
            <tbody>
                <tr>
                    <td>
                        <div class="conteudo-wrapper">
                            <!-- O CONTEÚDO (RELATÓRIO, TEXTO, ETC) ENTRA AQUI -->
                            <?= $conteudo ?? '' ?>
                        </div>
                    </td>
                </tr>
            </tbody>

            <tfoot>
                <tr>
                    <td>
                        <!-- Ocupa o espaço do rodapé em todas as páginas para não sobrepor o conteúdo -->
                        <div class="espaco-rodape"></div>
                    </td>
                </tr>
            </tfoot>
        </table>

    </div>
</body>

</html>