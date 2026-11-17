<div class="modal fade" id="modal-tarefa" tabindex="-1" aria-labelledby="modal-tarefa-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modal-tarefa-label">
                    <i class="bi bi-check-square"></i> Nova Tarefa
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <?= $this->include('componentes/tarefas/tarefas_formulario') ?>
            </div>
        </div>
    </div>
</div>