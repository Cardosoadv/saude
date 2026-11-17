<?= $this->extend('template/layout') ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= $titulo ?></h1>
        <button class="btn btn-primary" id="btnNewCanvas">
            <i class="fas fa-plus"></i> Novo Canvas
        </button>
    </div>

    <div id="canvasGrid" class="row" role="status" aria-live="polite">
        <!-- Carregado via JS -->
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo Canvas -->
<div class="modal fade" id="modalCanvas" tabindex="-1" aria-labelledby="modalCanvasLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCanvasLabel">Business Model Canvas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formCanvas">
                <?= csrf_field() ?>
                <input type="hidden" id="canvas_id" name="canvas_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label required-field">Título</label>
                        <input type="text" class="form-control" id="title" name="title" required aria-required="true">
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Descrição</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
