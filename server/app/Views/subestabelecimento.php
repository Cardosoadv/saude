<?php
// Dados do escritório (configurações do site) e advogado que assina; ver DadosEscritorio.
$escritorio     = \App\Libraries\Site\DadosEscritorio::atual();
$nomeEscritorio = $escritorio->nome();
$outorgante     = \App\Libraries\Site\DadosEscritorio::outorgante($outorgante ?? null);
$cidade         = $escritorio->cidade();
$logoEscritorio = $escritorio->logoDataUri();
$telefones      = $escritorio->telefones();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Substabelecimento<?= $nomeEscritorio !== '' ? ' - ' . esc($nomeEscritorio) : '' ?></title>
    <!-- Incluindo apenas o necessário para ícones sem o template pesado -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css">

    <style>
        /* Configurações globais de página e impressão */
        @page {
            size: A4;
            margin: 20mm;
            /* Requisito: Margens de 20mm */
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            background: white !important;
            color: #000;
            width: 100%;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        /* Estrutura de Tabela para garantir repetição de Header/Footer em todas as paginas */
        table.print-container {
            width: 100%;
            border-collapse: collapse;
        }

        thead.print-header {
            display: table-header-group;
        }

        tfoot.print-footer {
            display: table-footer-group;
        }

        /* Conteúdo do Cabeçalho */
        .header-wrapper {
            margin-bottom: 12mm;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }

        .logo {
            width: 55mm;
            height: auto;
        }

        .header-line-spacer {
            flex-grow: 1;
            border-top: 1.5px solid #333;
            margin-left: 15mm;
            margin-top: 6mm;
            /* Alinha com o topo do texto do logo */
        }

        /* Conteúdo do Rodapé */
        .footer-wrapper {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 8.5pt;
            color: #333;
            background: white;
            z-index: 1000;
        }

        .footer-line-left {
            width: 65%;
            border-top: 1px solid #ccc;
            margin-bottom: 4mm;
        }

        .footer-spacer {
            height: 15mm;
            /* Reservar espaço no final da tabela para o rodapé fixo */
        }

        .footer-contacts {
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .footer-contacts span {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .footer-contacts i {
            font-size: 10pt;
            color: #777;
        }

        /* Estilização do Documento */
        .document-body {
            padding: 5mm 0;
        }

        .title {
            text-align: center;
            font-weight: bold;
            font-size: 14pt;
            margin: 15mm 0 20mm 0;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .content {
            font-size: 12pt;
            line-height: 1.8;
            text-align: justify;
        }

        .content p {
            margin-bottom: 10mm;
        }

        .assinatura {
            margin-top: 30mm;
            text-align: right;
            font-size: 12pt;
            line-height: 1.8;
        }

        /* Ocultar elementos do template que não devem sair na impressão */
        @media print {

            .no-print,
            nav,
            aside,
            .main-header,
            .main-footer,
            .breadcrumb {
                display: none !important;
            }

            .content-wrapper {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
            }
        }

        /* Estilo para visualização em tela simulando papel */
        @media screen {
            body {
                background: #e0e0e0 !important;
                padding: 0;
                margin: 0;
            }

            .print-container {
                background: white;
                width: 210mm;
                min-height: 297mm;
                margin: 20mm auto;
                padding: 20mm;
                box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
                border-radius: 2px;
            }
        }
    </style>
</head>

<body>
    <table class="print-container">
        <!-- Cabeçalho (Repete em todas as páginas) -->
        <thead class="print-header">
            <div class="header-wrapper">
                <tr>
                    <td>
                        <?php if ($logoEscritorio): ?><img src="<?= $logoEscritorio ?>" alt="<?= esc($nomeEscritorio, 'attr') ?>" class="logo"><?php endif; ?>
                    </td>
                    <td>
                        <div class="header-line-spacer"></div>
                    </td>
                </tr>
            </div>
        </thead>

        <!-- Corpo do Documento -->
        <tbody>
            <tr>
                <td>
                    <div class="document-body">
                        <h1 class="title">SUBSTABELECIMENTO</h1>

                        <div class="content">
                            <p>
                                Pelo presente instrumento, <strong><?= esc($outorgante['nome']) ?></strong>,
                                <?= esc($outorgante['oab']) ?>, <em><u>sem reservas</u></em>, os poderes que me foram
                                conferidos por <strong><?= $representados ?></strong> nos autos
                                do processo nº <strong><?= $numero_processo ?></strong>.
                            </p>

                            <p>
                                <?= $cidade !== '' ? esc($cidade) . ', ' : '' ?><?= $dataHoje ?? date('d/m/Y') ?>.
                            </p>

                            <div class="assinatura">
                                <em>(Assinado Eletronicamente)</em><br>
                                <strong><?= esc($outorgante['nome']) ?></strong><br>
                                <?= esc($outorgante['oab']) ?>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        </tbody>

        <!-- Rodapé (Espaçador para garantir que o conteúdo não sobreponha o rodapé fixo) -->
        <tfoot class="print-footer">
            <tr>
                <td>
                    <div class="footer-spacer"></div>
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- Rodapé Fixo (Aparece em todas as páginas no final) -->
    <div class="footer-wrapper">
        <div class="footer-line-left"></div>
        <div class="footer-contacts">
            <?php foreach ($telefones as $telefone): ?><span><i class="bi bi-whatsapp"></i> <?= esc($telefone) ?> </span><?php endforeach; ?>
            <span><i class="bi bi-laptop"></i> <?= esc($escritorio->site()) ?> </span>
        </div>
    </div>

    <script>
        // Imprime automaticamente se for desejado
        window.onload = function() {
            window.print();
        };
    </script>
</body>

</html>