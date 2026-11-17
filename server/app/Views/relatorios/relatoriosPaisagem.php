<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title><?= (isset($titulo) ? esc($titulo) : "Relatório") ?></title>
    <style>
        /* Estilos de impressão */
        @page {
            size: A4 landscape;
            margin: 10mm;
            counter-increment: page;
        }

        /* Rodapé de impressão */
        @page {
            @bottom-right {
                content: "Página " counter(page);
                font-family: Arial, sans-serif;
                font-size: 10px;
                color: #00435A;
            }
        }

            /* Estilos do cabeçalho da tabela para repetir em cada página */
            thead {
                display: table-header-group;
            }

            /* Estilos gerais */
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: Arial, sans-serif;
                font-size: 11px;
                line-height: 1.3;
                color: #00435A;
                background: white;
            }

            /* Container principal */
            .container {
                width: 100%;
                max-width: 100%;
            }

            /* Cabeçalho do relatório */
            .header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 20px;
                border-bottom: 2px solid #00435A;
                padding-bottom: 15px;
            }

            .header-logo {
                flex: 0 0 200px;
                text-align: left;
            }

            .header-logo img {
                max-width: 200px;
                max-height: 60px;
                object-fit: contain;
            }

            .header-content {
                flex: 1;
                text-align: center;
                padding: 0 20px;
            }

            .header-content h1 {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 5px;
                color: #00435A;
            }

            .header-content h2 {
                font-size: 14px;
                color: #00435A;
                margin-bottom: 5px;
            }

            .header-info {
                flex: 0 0 200px;
                text-align: right;
                font-size: 10px;
                color: #00435A;
            }

            /* Fallback para quando não há logo */
            .header.no-logo {
                justify-content: center;
            }

            .header.no-logo .header-content {
                flex: none;
                padding: 0;
            }

            /* Informações do relatório */
            .report-info {
                display: flex;
                justify-content: space-between;
                margin-bottom: 15px;
                padding: 8px;
                background-color: #e9ecef;
                border: 1px solid #00435A;
            }

            .info-left,
            .info-right {
                font-size: 10px;
            }

            /* Tabela */
            .table-container {
                width: 100%;
                margin-bottom: 20px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 10px;
                table-layout: fixed;
            }

            th,
            td {
                padding: 6px;
                border: 1px solid #a3843e;
                vertical-align: top;
                word-wrap: break-word;
            }

            th {
                background-color: #00435A;
                color: white;
                text-align: left;
                font-weight: bold;
            }

            /* Zebra striping */
            tbody tr:nth-child(even) {
                background-color: #f8f9fa;
            }

            tbody tr:hover {
                background-color: #e9ecef;
            }

            /* Alinhamento das colunas */
            .text-center {
                text-align: center;
            }

            .text-right {
                text-align: right;
            }

            .text-left {
                text-align: left;
            }

            /* Colunas específicas */
            .col-numero {
                width: 15%;
            }

            .col-titulo {
                width: 25%;
            }

            .col-data {
                width: 10%;
            }

            .col-valor {
                width: 12%;
            }

            .col-status {
                width: 8%;
            }

            .col-cliente {
                width: 20%;
            }

            .col-risco {
                width: 10%;
            }

            /* Rodapé */
            .footer {
                margin-top: 20px;
                padding-top: 10px;
                border-top: 1px solid #00435A;
                font-size: 9px;
                text-align: center;
                color: #00435A;
            }

            /* Resumo */
            .summary {
                margin-top: 15px;
                padding: 10px;
                background-color: #f0f0f0;
                border: 1px solid #00435A;
                display: flex;
                justify-content: space-around;
            }

            .summary-item {
                text-align: center;
                font-size: 11px;
            }

            .summary-item strong {
                display: block;
                font-size: 13px;
                margin-top: 3px;
            }

            /* Status badges */
            .status-ativo {
                background-color: #28a745;
                color: white;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 9px;
                font-weight: bold;
            }

            .status-encerrado {
                background-color: #dc3545;
                color: white;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 9px;
                font-weight: bold;
            }

            .status-suspenso {
                background-color: #ffc107;
                color: #212529;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 9px;
                font-weight: bold;
            }

            /* Risco badges */
            .risco-alto {
                color: #dc3545;
                font-weight: bold;
            }

            .risco-medio {
                color: #ffc107;
                font-weight: bold;
            }

            .risco-baixo {
                color: #28a745;
                font-weight: bold;
            }

        /* Ocultar elementos na impressão */
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

            /* Quebra de página */
            .page-break {
                page-break-before: always;
            }

            /* Mensagem quando não há dados */
            .no-data {
                text-align: center;
                padding: 40px;
                color: #00435A;
                font-style: italic;
                border: 2px dashed #a3843e;
                margin: 20px 0;
            }

            /* Esconde elementos na tela para que só apareçam na impressão */
            .print-only {
                display: none;
            }

        @media print {
            .print-only {
                display: block;
            }

            .screen-only {
                display: none;
            }
        }
    </style>
</head>

<body>

    <!-- Cabeçalho Fixo -->
    <div class="header-fixed">
        <table class="header-table">
            <tr>
                <td style="width: 20%;">
                    <div class="header-logo">
                        <img src="<?= base_url($templateData['logoEmpresa'] ?? env('logoEmpresa') ?? 'public/dist/assets/img/logo.png') ?>" alt="Logo">
                    </div>
                </td>
                <td style="width: 50%; text-align: center;">
                    <div class="header-title">
                        <h1><?= (isset($titulo) ? esc($titulo) : "Relatório") ?></h1>
                        <h2><?= (isset($subtitulo) ? esc($subtitulo) : "") ?></h2>
                    </div>
                </td>
                <td style="width: 30%;">
                    <div class="header-info">
                        <strong><?= $templateData['nomeEmpresa'] ?></strong><br>
                        CNPJ: <?= $templateData['cnpjEmpresa'] ?>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Conteúdo Dinâmico -->
    <div class="main-content">
        <?= $this->renderSection('conteudo') ?>
    </div>
</body>
    <?= $this->renderSection('scripts') ?>
</html>
