<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <title><?= $titulo ?></title>
    <?= $this->include('template/header') ?>
</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
        <?= $this->include('template/nav') ?>
        <?= $this->include('template/sidebar') ?>

        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <?= $this->include('componentes/breadcrumbs') ?>
                </div>
            </div>

            <div class="app-content">
                <div class="container-fluid">
                    <div class="row">
                        <!-- Main Content Column (Largura Total - col-lg-12) -->
                        <div class="col-lg-12">

                            <!-- Action Button and Messages -->
                            <div class="container p-0">
                                <div class="d-flex justify-content-end mb-4">
                                    <a href="<?= base_url('mensagens/') ?>"
                                        class="btn btn-primary shadow-sm" title="Voltar para a lista de mensagens">
                                        <i class="bi bi-arrow-left-circle me-1"></i> Voltar para Mensagens
                                    </a>
                                </div>

                                <!-- Início da Notificação -->
                                <div class="mb-3">
                                    <?= $this->include('componentes/notificacaoSessao') ?>
                                </div>

                                <!-- Message Card -->
                                <div class="mt-4">
                                    <?php if (empty($mensagem)): ?>
                                        <div class="alert alert-info d-flex align-items-center">
                                            <i class="bi bi-info-circle me-2"></i> Nenhuma mensagem encontrada.
                                        </div>
                                    <?php else: ?>
                                        <div class="card shadow-lg">
                                            <div class="card-header bg-primary text-white">
                                                <h4 class="card-title mb-0 d-flex align-items-center">
                                                    <i class="bi bi-envelope-open me-2"></i> Assunto: <?= esc($mensagem['assunto']) ?>
                                                </h4>
                                            </div>
                                            <div class="card-body">
                                                <div class="row mb-4">
                                                    <div class="col-md-6">
                                                        <p class="mb-1 text-muted">
                                                            <i class="bi bi-send-fill me-1 text-primary"></i>
                                                            <strong>Remetente:</strong> <?= esc($mensagem['remetente_nome'] ?? 'Desconhecido') ?>
                                                        </p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p class="mb-1 text-muted">
                                                            <i class="bi bi-calendar-event me-1 text-primary"></i>
                                                            <strong>Data de Envio:</strong> <?= date('d/m/Y H:i:s', strtotime($mensagem['data_envio'])) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <hr>
                                                <div class="message-content">
                                                    <p class="lead"><?= nl2br(esc($mensagem['conteudo'])) ?></p>
                                                </div>
                                            </div>
                                            <div class="card-footer d-flex justify-content-between align-items-center bg-light py-3">
                                                <p class="mb-0 small text-muted">
                                                    <i class="bi bi-check-circle me-1"></i> Lido em:
                                                    <?= $mensagem['data_leitura'] ? date('d/m/Y H:i:s', strtotime($mensagem['data_leitura'])) : 'Ainda não lida' ?>
                                                </p>
                                                <!-- Botão de Resposta/Ação - Exemplo -->
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalResponderMensagem" title="Responder a esta mensagem" aria-label="Responder a esta mensagem">
                                                    <i class="bi bi-reply"></i> Responder
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- A sidebar foi removida daqui -->

                    </div>
                </div>
            </div>
        </main>
        <?= $this->include('componentes/mensagens/modal-reponder') ?>
        <?= $this->include('template/modals/change_user_img.php') ?>

        <?= $this->include('template/footer') ?>
    </div>

    <!-- O Script da Sidebar também foi removido, pois não há mais elementos para buscar/renderizar -->

</body>

</html>