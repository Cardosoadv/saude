<?= $this->extend('template/layout') ?>

<?= $this->section('conteudo') ?>
<!-- Header Actions -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-inbox inbox-icon text-primary"></i>
        Caixa de Mensagens
    </h4>
    <a href="<?= base_url('mensagens/novo/') ?>" class="btn btn-success" title="Escrever nova mensagem" aria-label="Escrever nova mensagem">
        <i class="bi bi-plus-circle"></i> Nova Mensagem
    </a>
</div>

<!-- Search Form -->
<form action="" method="get" class="mb-3">
    <div class="input-group">
        <span class="input-group-text bg-white">
            <i class="bi bi-search"></i>
        </span>
        <input type="text" name="s" class="form-control"
            placeholder="Pesquisar por assunto ou remetente/destinatário..." aria-label="Pesquisar"
            value="<?= esc($_GET['s'] ?? '') ?>">
        <button class="btn btn-primary" type="submit" title="Executar pesquisa" aria-label="Executar pesquisa">
            Pesquisar
        </button>
        <?php if (!empty($_GET['s'])): ?>
            <a href="<?= base_url('mensagens') ?>" class="btn btn-outline-danger" title="Limpar pesquisa" aria-label="Limpar pesquisa">
                <i class="bi bi-x-circle"></i>
            </a>
        <?php endif; ?>
    </div>
</form>

<!-- Filter Tabs -->
<?php
$currentFilter = $_GET['filter'] ?? 'todas';
?>
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $currentFilter == 'todas' ? 'active' : '' ?>"
            href="<?= base_url('mensagens?filter=todas') ?>"
            role="tab"
            aria-selected="<?= $currentFilter == 'todas' ? 'true' : 'false' ?>"
            aria-label="Ver todas as mensagens">
            <i class="bi bi-inbox"></i> Todas
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $currentFilter == 'nao-lidas' ? 'active' : '' ?>"
            href="<?= base_url('mensagens?filter=nao-lidas') ?>"
            role="tab"
            aria-selected="<?= $currentFilter == 'nao-lidas' ? 'true' : 'false' ?>"
            aria-label="Ver apenas mensagens não lidas">
            <i class="bi bi-envelope-fill"></i> Não Lidas
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $currentFilter == 'lidas' ? 'active' : '' ?>"
            href="<?= base_url('mensagens?filter=lidas') ?>"
            role="tab"
            aria-selected="<?= $currentFilter == 'lidas' ? 'true' : 'false' ?>"
            aria-label="Ver apenas mensagens lidas">
            <i class="bi bi-envelope-open"></i> Lidas
        </a>
    </li>
</ul>

<!-- Notifications -->
<?= $this->include('componentes/notificacaoSessao') ?>

<!-- Messages List -->
<div class="mt-3">
    <?php if (empty($mensagens)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-inbox icon-empty-lg text-muted"></i>
                <h5 class="mt-3 text-muted">Nenhuma mensagem encontrada</h5>
                <p class="text-muted">Sua caixa de entrada está vazia.</p>
            </div>
        </div>
    <?php else: ?>
        <!-- Messages Count -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="alert alert-info mb-0 d-flex align-items-center" role="status" aria-live="polite">
                <i class="bi bi-info-circle me-2"></i>
                <?php if ($pager->getTotal() > 1): ?>
                    <?= $pager->getTotal() ?> mensagens encontradas
                <?php else: ?>
                    <?= $pager->getTotal() ?> mensagem encontrada
                <?php endif; ?>
            </div>

            <!-- Sort Options -->
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-label="Opções de ordenação" title="Ordenar mensagens">
                    <i class="bi bi-sort-down"></i> Ordenar
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="?sort=recente">
                            <i class="bi bi-calendar-minus"></i> Mais Recentes
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="?sort=antiga">
                            <i class="bi bi-calendar-plus"></i> Mais Antigas
                        </a>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <a class="dropdown-item" href="?sort=nao-lidas">
                            <i class="bi bi-envelope-fill"></i> Não Lidas Primeiro
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Messages Cards -->
        <div class="row">
            <?php foreach ($mensagens as $mensagem):
                $isRead = !empty($mensagem['data_leitura']);
                $cardClass = $isRead ? 'message-read' : 'message-unread';
                $icon = $isRead ? 'bi-envelope-open' : 'bi-envelope-fill';
                $badgeClass = $isRead ? 'secondary' : 'primary';
                $borderClass = $isRead ? 'border-secondary' : 'border-primary';
            ?>
                <div class="col-12 mb-3">
                    <div class="card message-card shadow-sm border-start border-4 <?= $borderClass ?> <?= $cardClass ?>"
                        role="button"
                        tabindex="0"
                        onclick="window.location.href='<?= base_url('mensagens/ler/' . $mensagem['id']) ?>'"
                        onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location.href='<?= base_url('mensagens/ler/' . $mensagem['id']) ?>'; }"
                        aria-label="Ler mensagem: <?= esc($mensagem['assunto']) ?>">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <!-- Icon Section -->
                                <div class="col-auto">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle bg-light"
                                        style="width: 50px; height: 50px;">
                                        <i class="bi <?= $icon ?> text-<?= $badgeClass ?>" style="font-size: 1.5rem;"></i>
                                    </div>
                                </div>

                                <!-- Content Section -->
                                <div class="col">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5
                                            class="card-title mb-0 <?= $isRead ? 'text-secondary' : 'text-primary fw-bold' ?>">
                                            <?= esc($mensagem['assunto']) ?>
                                            <?php if (!$isRead): ?>
                                                <span class="badge bg-danger badge-pulse ms-2">Novo</span>
                                            <?php endif; ?>
                                        </h5>
                                        <span class="badge bg-<?= $badgeClass ?> rounded-pill">
                                            <i class="bi bi-calendar-event"></i>
                                            <?= date('d/m/Y H:i', strtotime($mensagem['data_envio'])) ?>
                                        </span>
                                    </div>

                                    <div class="row mb-2">
                                        <div class="col-md-6">
                                            <small class="text-muted">
                                                <i class="bi bi-send"></i>
                                                <strong>De:</strong>
                                                <span
                                                    class="text-dark"><?= esc($mensagem['remetente_nome'] ?? 'Desconhecido') ?></span>
                                            </small>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted">
                                                <i class="bi bi-person-check"></i>
                                                <strong>Para:</strong>
                                                <span
                                                    class="text-dark"><?= esc($mensagem['destinatario_nome'] ?? 'Desconhecido') ?></span>
                                            </small>
                                        </div>
                                    </div>

                                    <?php if (!empty($mensagem['conteudo'])): ?>
                                        <div class="message-preview text-muted small">
                                            <?= esc(strip_tags($mensagem['conteudo'])) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-end gap-2 mt-3">
                                        <button class="btn btn-sm btn-primary" title="Ver detalhes da mensagem" aria-label="Ver detalhes da mensagem"
                                            onclick="event.stopPropagation(); window.location.href='<?= base_url('mensagens/ler/' . $mensagem['id']) ?>'">
                                            <i class="bi bi-eye"></i> Ler Mensagem
                                        </button>
                                        <?php if (!$isRead && ($mensagem['destinatario_id'] == user_id())): ?>
                                            <button class="btn btn-sm btn-outline-success" title="Marcar como lida" aria-label="Marcar como lida"
                                                onclick="event.stopPropagation(); App.get('Mensagens').marcarComoLida(<?= $mensagem['id'] ?>)">
                                                <i class="bi bi-check2-circle"></i> Marcar como Lida
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            <?= $pager->links() ?>
        </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('conteudoSidebar') ?>
<!-- Statistics Card -->
<div class="card mb-3 shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <i class="bi bi-bar-chart"></i> Estatísticas
        </h5>
    </div>
    <div class="card-body">
        <?php
        // Calcular estatísticas corretas
        $recebidas = 0;
        $recebidasNaoLidas = 0;
        $enviadas = 0;

        foreach ($mensagens as $m) {
            if ($m['destinatario_id'] == user_id()) {
                // É uma mensagem RECEBIDA
                $recebidas++;
                if (empty($m['data_leitura'])) {
                    $recebidasNaoLidas++;
                }
            } else {
                // É uma mensagem ENVIADA
                $enviadas++;
            }
        }
        $recebidasLidas = $recebidas - $recebidasNaoLidas;
        ?>

        <!-- Total Geral -->
        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
            <div>
                <small class="text-muted d-block">Total de Mensagens</small>
                <h4 class="mb-0 text-primary"><?= $pager->getTotal() ?></h4>
            </div>
            <i class="bi bi-inbox-fill text-primary" style="font-size: 2rem;"></i>
        </div>

        <!-- Mensagens Recebidas -->
        <div class="mb-3 pb-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted fw-bold">
                    <i class="bi bi-arrow-down-circle"></i> RECEBIDAS
                </small>
                <span class="badge bg-info"><?= $recebidas ?></span>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2 ms-3">
                <small class="text-muted">
                    <i class="bi bi-envelope-fill"></i> Não Lidas
                </small>
                <span class="badge bg-danger"><?= $recebidasNaoLidas ?></span>
            </div>

            <div class="d-flex justify-content-between align-items-center ms-3">
                <small class="text-muted">
                    <i class="bi bi-envelope-open"></i> Lidas
                </small>
                <span class="badge bg-success"><?= $recebidasLidas ?></span>
            </div>
        </div>

        <!-- Mensagens Enviadas -->
        <div>
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted fw-bold">
                    <i class="bi bi-arrow-up-circle"></i> ENVIADAS
                </small>
                <span class="badge bg-secondary"><?= $enviadas ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-3 shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <i class="bi bi-lightning"></i> Ações Rápidas
        </h5>
    </div>
    <div class="card-body">
        <div class="d-grid gap-2">
            <a href="<?= base_url('mensagens/novo') ?>" class="btn btn-success btn-sm">
                <i class="bi bi-plus-circle"></i> Nova Mensagem
            </a>
            <button class="btn btn-outline-primary btn-sm" onclick="App.get('Mensagens').marcarTodasComoLidas()">
                <i class="bi bi-check2-all"></i> Marcar Todas como Lidas
            </button>
            <a href="<?= base_url('mensagens?filter=nao-lidas') ?>" class="btn btn-outline-info btn-sm">
                <i class="bi bi-filter"></i> Ver Não Lidas
            </a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('dist/js/app/modules/app.mensagens.js') ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        App.get('Mensagens').init({
            baseUrl: '<?= base_url() ?>'
        });
    });
</script>
<?= $this->endSection() ?>