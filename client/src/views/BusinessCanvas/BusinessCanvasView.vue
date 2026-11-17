<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'dashboard')">Saúde</a>
      <i class="bi bi-chevron-right"></i>
      <span>Business Model Canvas</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Business Model Canvas</h1>
        <p>Desenvolvimento e planejamento estratégico</p>
      </div>
      <button class="btn btn-primary" @click="showNewCanvasModal = true">
        <i class="bi bi-plus-circle"></i> Novo Canvas
      </button>
    </div>

    <div class="content-grid" style="margin-top:1.5rem; display: block;">
      <!-- Seleção de Canvas -->
      <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header" style="display: flex; gap: 1rem; align-items: center;">
          <h3 style="margin: 0; min-width: 150px;">Selecionar Canvas</h3>
          <select class="form-control form-select" v-model="selectedCanvasId" @change="onCanvasSelect" style="max-width: 400px;">
            <option value="">Selecione um canvas...</option>
            <option v-for="c in canvases" :key="c.id" :value="c.id">{{ c.title }}</option>
          </select>
        </div>
      </div>

      <!-- Informações do Canvas Atual -->
      <div v-if="selectedCanvasId && canvasData" class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
          <h3 style="margin: 0;">{{ canvasData.title }}</h3>
          <div style="display: flex; gap: 8px;">
            <button class="btn btn-ghost btn-sm" @click="openEditCanvasModal" title="Editar Canvas">
              <i class="bi bi-pencil-square" style="color:var(--accent);"></i>
            </button>
            <button class="btn btn-ghost btn-sm" @click="confirmDeleteCanvas" title="Excluir Canvas">
              <i class="bi bi-trash" style="color:var(--danger);"></i>
            </button>
          </div>
        </div>
        <div class="card-body">
          <p style="color: var(--ink-dim); margin: 0;">{{ canvasData.description || 'Sem descrição' }}</p>
        </div>
      </div>

      <!-- Skeleton Loading -->
      <div v-if="loadingBlocks" class="process-list" style="margin-top: 1rem;">
        <div class="skeleton-card" v-for="i in 3" :key="i">
          <div class="skeleton-line" style="width: 40%; height: 16px;"></div>
          <div class="skeleton-line" style="width: 80%; height: 12px;"></div>
        </div>
      </div>

      <!-- Grid Canvas -->
      <div v-if="selectedCanvasId && !loadingBlocks" class="canvas-grid-container" style="margin-top: 1.5rem;">
        <div class="canvas-grid">
          <div v-for="block in defaultBlocks" :key="block.key" class="canvas-card" :data-block="block.key" :style="`border-top-color: ${block.color}`">
            <div class="canvas-card-header" :style="`background-color: ${block.color}20`">
              <i :class="block.icon"></i>
              <h5>{{ block.name }}</h5>
              <button class="btn btn-sm btn-ghost edit-block-btn" @click="openEditBlockModal(block.key, block.name)">
                <i class="bi bi-pencil-square"></i>
              </button>
            </div>
            <div class="canvas-card-body">
              <div v-if="blocks[block.key] && blocks[block.key].trim()">
                <div v-for="(item, idx) in blocks[block.key].split('\n').filter(i => i.trim() !== '')" :key="idx" class="canvas-item-card">
                  {{ item.trim() }}
                </div>
              </div>
              <div v-else class="text-muted" style="text-align: center; padding: 2rem 0; opacity: 0.6;">
                <i class="bi bi-plus-circle" style="font-size: 2rem;"></i>
                <p style="font-size: 0.85rem; margin-top: 0.5rem;">{{ block.description }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Novo Canvas -->
    <div v-if="showNewCanvasModal" class="modal-backdrop" @click.self="showNewCanvasModal = false">
      <div class="modal-box">
        <div class="modal-header">
          <h2><i class="bi bi-plus-square"></i> Novo Business Model Canvas</h2>
          <button class="modal-close" @click="showNewCanvasModal = false"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Título *</label>
            <input class="form-control" type="text" v-model="newCanvasForm.title" required>
          </div>
          <div class="form-group">
            <label class="form-label">Descrição</label>
            <textarea class="form-control" v-model="newCanvasForm.description" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" @click="showNewCanvasModal = false">Cancelar</button>
          <button class="btn btn-primary" @click="submitNewCanvas" :disabled="saving">Salvar</button>
        </div>
      </div>
    </div>

    <!-- Modal Editar Canvas -->
    <div v-if="showEditCanvasModal" class="modal-backdrop" @click.self="showEditCanvasModal = false">
      <div class="modal-box">
        <div class="modal-header">
          <h2><i class="bi bi-pencil-square"></i> Editar Canvas</h2>
          <button class="modal-close" @click="showEditCanvasModal = false"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Título *</label>
            <input class="form-control" type="text" v-model="editCanvasForm.title" required>
          </div>
          <div class="form-group">
            <label class="form-label">Descrição</label>
            <textarea class="form-control" v-model="editCanvasForm.description" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" @click="showEditCanvasModal = false">Cancelar</button>
          <button class="btn btn-primary" @click="submitEditCanvas" :disabled="saving">Atualizar</button>
        </div>
      </div>
    </div>

    <!-- Modal Editar Bloco -->
    <div v-if="showEditBlockModal" class="modal-backdrop" @click.self="showEditBlockModal = false">
      <div class="modal-box" style="max-width: 600px;">
        <div class="modal-header">
          <h2>Editar - {{ editBlockForm.blockName }}</h2>
          <button class="modal-close" @click="showEditBlockModal = false"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Conteúdo (um item por linha)</label>
            <textarea class="form-control" v-model="editBlockForm.content" rows="10" style="font-family: inherit;"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" @click="showEditBlockModal = false">Cancelar</button>
          <button class="btn btn-primary" @click="submitEditBlock" :disabled="saving">Salvar Bloco</button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { apiFetch, toFormData } from '../../services/api'

const emit = defineEmits(['toast', 'set-view'])

// Estados
const canvases = ref([])
const selectedCanvasId = ref('')
const canvasData = ref(null)
const blocks = ref({})
const loadingBlocks = ref(false)
const saving = ref(false)

const showNewCanvasModal = ref(false)
const showEditCanvasModal = ref(false)
const showEditBlockModal = ref(false)

const newCanvasForm = reactive({ title: '', description: '' })
const editCanvasForm = reactive({ title: '', description: '' })
const editBlockForm = reactive({ blockType: '', blockName: '', content: '' })

// Estrutura dos Blocos (Semelhante ao Backend Legacy)
const defaultBlocks = [
  { key: 'key_partners', name: 'Parcerias Principais', icon: 'bi bi-link-45deg', color: '#dc3545', description: 'Parceiros e fornecedores' },
  { key: 'key_activities', name: 'Atividades Principais', icon: 'bi bi-check-circle', color: '#fd7e14', description: 'Atividades essenciais' },
  { key: 'key_resources', name: 'Recursos Principais', icon: 'bi bi-box', color: '#ffc107', description: 'Recursos necessários' },
  { key: 'value_proposition', name: 'Proposta de Valor', icon: 'bi bi-gift', color: '#28a745', description: 'Problema e valor entregue' },
  { key: 'customer_relationships', name: 'Relacionamento', icon: 'bi bi-heart', color: '#20c997', description: 'Interação com clientes' },
  { key: 'channels', name: 'Canais', icon: 'bi bi-truck', color: '#17a2b8', description: 'Canais de alcance' },
  { key: 'customer_segments', name: 'Segmento de Clientes', icon: 'bi bi-people', color: '#007bff', description: 'Segmentos-alvo' },
  { key: 'cost_structure', name: 'Estrutura de Custo', icon: 'bi bi-tags', color: '#6610f2', description: 'Principais custos' },
  { key: 'revenue_streams', name: 'Fontes de Renda', icon: 'bi bi-cash', color: '#e83e8c', description: 'Geração de receita' }
]

// Carregar Lista
async function fetchCanvases() {
  try {
    const res = await apiFetch('business-canvas/get-canvases')
    if (res && res.success) {
      canvases.value = res.data || []
    }
  } catch (e) {
    console.error('Erro get-canvases:', e)
    emit('toast', { msg: 'Erro ao carregar os canvases.', type: 'danger' })
  }
}

// Selecionar e carregar dados do Canvas
async function onCanvasSelect() {
  if (!selectedCanvasId.value) {
    canvasData.value = null
    blocks.value = {}
    return
  }

  loadingBlocks.value = true
  try {
    const res = await apiFetch(`business-canvas/get-canvas/${selectedCanvasId.value}`)
    if (res && res.success) {
      canvasData.value = res.data
      blocks.value = {}
      if (res.data.blocks) {
        res.data.blocks.forEach(b => {
          blocks.value[b.block_type] = b.content
        })
      }
    }
  } catch (e) {
    console.error('Erro get-canvas:', e)
    emit('toast', { msg: 'Erro ao carregar detalhes do canvas.', type: 'danger' })
  } finally {
    loadingBlocks.value = false
  }
}

// Criar novo Canvas
async function submitNewCanvas() {
  if (!newCanvasForm.title) {
    emit('toast', { msg: 'O título é obrigatório.', type: 'warning' })
    return
  }
  saving.value = true
  try {
    const fd = toFormData(newCanvasForm)
    const res = await apiFetch('business-canvas/create-canvas', {
      method: 'POST',
      body: fd
    })
    if (res && res.success) {
      emit('toast', { msg: 'Canvas criado com sucesso!', type: 'success' })
      showNewCanvasModal.value = false
      newCanvasForm.title = ''
      newCanvasForm.description = ''
      await fetchCanvases()
      selectedCanvasId.value = res.canvas_id
      onCanvasSelect()
    }
  } catch (e) {
    console.error('Erro create-canvas:', e)
    emit('toast', { msg: 'Erro ao criar canvas.', type: 'danger' })
  } finally {
    saving.value = false
  }
}

// Editar Canvas (Metadados)
function openEditCanvasModal() {
  if (canvasData.value) {
    editCanvasForm.title = canvasData.value.title || ''
    editCanvasForm.description = canvasData.value.description || ''
    showEditCanvasModal.value = true
  }
}

async function submitEditCanvas() {
  if (!editCanvasForm.title) return
  saving.value = true
  try {
    const res = await apiFetch(`business-canvas/update-canvas/${selectedCanvasId.value}`, {
      method: 'PUT',
      body: { title: editCanvasForm.title, description: editCanvasForm.description }
    })
    if (res && res.success) {
      emit('toast', { msg: 'Canvas atualizado!', type: 'success' })
      showEditCanvasModal.value = false
      await fetchCanvases()
      canvasData.value.title = editCanvasForm.title
      canvasData.value.description = editCanvasForm.description
    }
  } catch (e) {
    console.error('Erro update-canvas:', e)
    emit('toast', { msg: 'Erro ao atualizar canvas.', type: 'danger' })
  } finally {
    saving.value = false
  }
}

// Excluir Canvas
async function confirmDeleteCanvas() {
  if (confirm('Tem certeza que deseja excluir este canvas permanentemente?')) {
    try {
      const res = await apiFetch(`business-canvas/delete-canvas/${selectedCanvasId.value}`, {
        method: 'DELETE'
      })
      if (res && res.success) {
        emit('toast', { msg: 'Canvas removido', type: 'success' })
        selectedCanvasId.value = ''
        canvasData.value = null
        blocks.value = {}
        fetchCanvases()
      }
    } catch (e) {
      console.error('Erro delete-canvas:', e)
      emit('toast', { msg: 'Erro ao remover canvas.', type: 'danger' })
    }
  }
}

// Blocos
function openEditBlockModal(key, name) {
  editBlockForm.blockType = key
  editBlockForm.blockName = name
  editBlockForm.content = blocks.value[key] || ''
  showEditBlockModal.value = true
}

async function submitEditBlock() {
  saving.value = true
  try {
    const fd = toFormData({
      canvas_id: selectedCanvasId.value,
      block_type: editBlockForm.blockType,
      content: editBlockForm.content
    })
    const res = await apiFetch('business-canvas/save-block', {
      method: 'POST',
      body: fd
    })
    if (res && res.success) {
      blocks.value[editBlockForm.blockType] = editBlockForm.content
      emit('toast', { msg: 'Bloco atualizado!', type: 'success' })
      showEditBlockModal.value = false
    }
  } catch (e) {
    console.error('Erro save-block:', e)
    emit('toast', { msg: 'Erro ao salvar bloco.', type: 'danger' })
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchCanvases()
})

</script>

<style scoped>
.canvas-grid-container {
  background: var(--bg-surface);
  border-radius: 12px;
  border: 1px solid var(--border);
  padding: 15px;
}

.canvas-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  grid-template-rows: repeat(2, 1fr) auto;
  gap: 15px;
  padding: 15px;
  border-radius: 8px;
  min-height: 600px;
}

/* Layout BMC Clássico */
.canvas-card[data-block="key_partners"] { grid-column: 1; grid-row: 1 / span 2; }
.canvas-card[data-block="key_activities"] { grid-column: 2; grid-row: 1; }
.canvas-card[data-block="key_resources"] { grid-column: 2; grid-row: 2; }
.canvas-card[data-block="value_proposition"] { grid-column: 3; grid-row: 1 / span 2; }
.canvas-card[data-block="customer_relationships"] { grid-column: 4; grid-row: 1; }
.canvas-card[data-block="channels"] { grid-column: 4; grid-row: 2; }
.canvas-card[data-block="customer_segments"] { grid-column: 5; grid-row: 1 / span 2; }
.canvas-card[data-block="cost_structure"] { grid-column: 1 / span 3; grid-row: 3; }
.canvas-card[data-block="revenue_streams"] { grid-column: 4 / span 2; grid-row: 3; }

.canvas-card {
  background: var(--bg-surface);
  border-radius: 8px;
  border-top: 4px solid;
  display: flex;
  flex-direction: column;
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
  position: relative;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.canvas-card:hover {
  box-shadow: 0 8px 15px rgba(0,0,0,0.3);
  transform: translateY(-2px);
}

.canvas-card-header {
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: 8px;
  border-top-left-radius: 4px;
  border-top-right-radius: 4px;
}

.canvas-card-header h5 {
  margin: 0;
  flex: 1;
  font-size: 0.85rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--ink);
}

.canvas-card-header i {
  font-size: 1.1rem;
}

.edit-block-btn {
  opacity: 0.3;
  padding: 4px;
  transition: opacity 0.2s;
}

.canvas-card:hover .edit-block-btn {
  opacity: 1;
}

.canvas-card-body {
  padding: 12px;
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 8px;
  background-color: rgba(255, 255, 255, 0.02);
}

.canvas-item-card {
  background: rgba(212, 175, 55, 0.15); /* Dourado suave */
  padding: 10px;
  border-radius: 4px;
  box-shadow: 2px 2px 5px rgba(0,0,0,0.1);
  font-size: 0.85rem;
  line-height: 1.4;
  color: var(--ink);
  border-left: 3px solid rgba(212, 175, 55, 0.5);
  animation: slideIn 0.3s ease-out;
  word-wrap: break-word;
}

@keyframes slideIn {
  from { opacity: 0; transform: translateX(-10px); }
  to { opacity: 1; transform: translateX(0); }
}

@media (max-width: 1200px) {
  .canvas-grid {
    grid-template-columns: repeat(2, 1fr);
    grid-template-rows: auto;
  }
  .canvas-card {
    grid-column: auto !important;
    grid-row: auto !important;
    min-height: 250px;
  }
}

@media (max-width: 768px) {
  .canvas-grid {
    grid-template-columns: 1fr;
  }
}

.canvas-card-body::-webkit-scrollbar {
  width: 4px;
}
.canvas-card-body::-webkit-scrollbar-thumb {
  background: var(--border);
  border-radius: 10px;
}
</style>
