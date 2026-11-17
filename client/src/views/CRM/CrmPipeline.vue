<template>
  <div class="page-content d-flex flex-column gap-3">
    <!-- Main Kanban Area -->
    <div class="main-column kanban-wrapper" style="flex: 1; min-width: 0;">
      <div class="page-header d-flex justify-content-between align-items-center mb-3">
        <div>
          <h1 class="m-0"><i class="bi bi-funnel text-primary me-2"></i>Pipeline de Negócios</h1>
          <p class="text-muted mt-1 mb-0">Gerencie suas oportunidades comerciais e conversões.</p>
        </div>
        <div>
          <a href="#/crm/leads" class="btn btn-outline-info me-2" aria-label="Ver Leads do Site" title="Leads do Site">
            <i class="bi bi-inbox"></i> Leads do Site
          </a>
          <button class="btn btn-primary" @click="openModal(null)" aria-label="Criar nova oportunidade" title="Criar nova oportunidade">
            <i class="bi bi-plus-lg"></i> Nova Oportunidade
          </button>
        </div>
      </div>

      <div v-if="loading" class="empty-state">
        <div class="spinner"></div>
        <p>Carregando pipeline...</p>
      </div>

      <div v-else class="kanban-board">
        <div 
          v-for="col in pipelineData" 
          :key="col.etapa.id" 
          class="kanban-column"
          :class="{ 'drag-over': dragOverCol === col.etapa.id }"
          @dragover.prevent="dragOverCol = col.etapa.id"
          @dragleave.prevent="dragOverCol = null"
          @drop="onDrop($event, col.etapa.id)"
          :aria-labelledby="'col-title-' + col.etapa.id"
        >
          <div class="kanban-column-header">
            <h3 class="kanban-column-title" :id="'col-title-' + col.etapa.id">
              {{ col.etapa.nome }}
              <span class="badge badge-muted ms-2">{{ col.count }}</span>
            </h3>
            <div class="kanban-column-value" v-if="col.total_valor > 0">
              {{ formatCurrency(col.total_valor) }}
            </div>
          </div>
          
          <TransitionGroup
            tag="div"
            name="fade"
            class="kanban-column-body"
            role="list"
          >
            <div 
              v-for="card in col.oportunidades" 
              :key="card.id" 
              role="listitem"
            >
                <div
                  class="kanban-card"
                  :class="{ 'is-dragging': draggingId === card.id }"
                  draggable="true"
                  @dragstart="onDragStart($event, card.id, col.etapa.id)"
                  @dragend="draggingId = null"
                  @click="openModal(card.id)"
                  role="button"
                  tabindex="0"
                  @keydown.enter="openModal(card.id)"
                  @keydown.space.prevent="openModal(card.id)"
                  :aria-label="'Ver detalhes da oportunidade: ' + card.titulo"
                >
                  <div class="card-title">{{ card.titulo }}</div>
                  <div class="card-client" v-if="card.cliente_nome">
                    <i class="bi bi-person"></i> {{ card.cliente_nome }}
                  </div>
                  <div class="card-footer-info mt-2">
                    <span class="card-value fw-bold text-success">{{ formatCurrency(card.valor_estimado) }}</span>
                  </div>
                </div>
              </div>
          </TransitionGroup>
        </div>
      </div>
    </div>

    <!-- Sidebar Stats -->
    <div class="sidebar-widgets">
      <div class="widget-card section-card">
        <div class="section-header">
          <h4 class="m-0"><i class="bi bi-bar-chart-fill text-accent me-2"></i>Resumo do Funil</h4>
        </div>
        <div class="section-body p-3">
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Total Oportunidades</span>
            <span class="fw-bold">{{ totalOportunidades }}</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Valor em Pipeline</span>
            <span class="fw-bold text-success">{{ formatCurrency(totalPipeline) }}</span>
          </div>
        </div>
      </div>
    </div>
    <!-- Modal Nova Oportunidade -->
    <CrmOportunidadeModal 
      v-if="showModal" 
      :oportunidade-id="selectedOportunidadeId"
      :oportunidade-data="selectedOportunidadeData"
      @close="showModal = false"
      @saved="loadPipeline"
      @toast="toastProxy"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { apiFetch, formatCurrency } from '../../services/api'
import CrmOportunidadeModal from './CrmOportunidadeModal.vue'

const emit = defineEmits(['toast'])

const loading = ref(true)
const pipelineData = ref([])
const dragOverCol = ref(null)
const draggingId = ref(null)

const showModal = ref(false)
const selectedOportunidadeId = ref(null)
const selectedOportunidadeData = ref(null)

function openModal(id) {
  selectedOportunidadeId.value = id
  if (id) {
    let found = null
    for (const col of pipelineData.value) {
      found = col.oportunidades.find(c => c.id == id)
      if (found) break
    }
    selectedOportunidadeData.value = found
  } else {
    selectedOportunidadeData.value = null
  }
  showModal.value = true
}

function toastProxy(payload) {
  emit('toast', payload)
}

const totalOportunidades = computed(() => {
  return pipelineData.value.reduce((acc, col) => acc + (col.count || 0), 0)
})

const totalPipeline = computed(() => {
  return pipelineData.value.reduce((acc, col) => acc + (Number(col.total_valor) || 0), 0)
})

async function loadPipeline() {
  loading.value = true
  try {
    const res = await apiFetch('crm/pipeline')
    if (res && res.status === 'success') {
      pipelineData.value = res.data || []
    } else if (res && Array.isArray(res)) {
      pipelineData.value = res
    } else if (res && res.data) {
      pipelineData.value = res.data
    } else {
      pipelineData.value = []
    }
  } catch (err) {
    console.error('Erro ao carregar pipeline CRM:', err)
    emit('toast', { msg: 'Erro ao carregar pipeline', type: 'danger' })
  } finally {
    loading.value = false
  }
}

function onDragStart(event, cardId, sourceEtapaId) {
  event.dataTransfer.setData('cardId', cardId)
  event.dataTransfer.setData('sourceEtapaId', sourceEtapaId)
  event.dataTransfer.effectAllowed = 'move'
  setTimeout(() => {
    draggingId.value = cardId
  }, 0)
}

async function onDrop(event, targetEtapaId) {
  dragOverCol.value = null
  const cardId = event.dataTransfer.getData('cardId')
  const sourceEtapaId = event.dataTransfer.getData('sourceEtapaId')

  if (!cardId || sourceEtapaId == targetEtapaId) {
    return // Não moveu ou card inválido
  }

  // Otimisticamente mover o card no front
  optimisticMove(cardId, sourceEtapaId, targetEtapaId)

  // Sincronizar com o back
  try {
    const res = await apiFetch('crm/mover', { 
      method: 'POST', 
      body: { 
        oportunidade_id: cardId, 
        etapa_id: targetEtapaId 
      } 
    })
    
    // Se falhar e a API retornar sucesso=false
    if (res && res.success === false) {
      throw new Error(res.message || 'Erro ao mover oportunidade')
    }
  } catch (error) {
    console.error('Erro ao mover oportunidade:', error)
    emit('toast', { msg: 'Erro ao mover oportunidade', type: 'danger' })
    loadPipeline() // Reverte se falhar
  }
}

function optimisticMove(cardId, sourceEtapaId, targetEtapaId) {
  let movedCard = null
  
  // Encontrar o card na coluna original
  const sourceCol = pipelineData.value.find(c => c.etapa.id == sourceEtapaId)
  if (sourceCol) {
    const cardIndex = sourceCol.oportunidades.findIndex(c => c.id == cardId)
    if (cardIndex > -1) {
      movedCard = sourceCol.oportunidades.splice(cardIndex, 1)[0]
      sourceCol.count -= 1
      sourceCol.total_valor -= Number(movedCard.valor_estimado || 0)
    }
  }

  // Colocar na nova coluna
  if (movedCard) {
    movedCard.etapa_id = targetEtapaId
    const targetCol = pipelineData.value.find(c => c.etapa.id == targetEtapaId)
    if (targetCol) {
      targetCol.oportunidades.push(movedCard)
      targetCol.count += 1
      targetCol.total_valor += Number(movedCard.valor_estimado || 0)
    }
  }
}

// Expõe recarregar para poder ser chamado por fora (se o modal salvar algo)
defineExpose({
  loadPipeline
})

onMounted(() => {
  loadPipeline()
})
</script>

<style scoped>
.kanban-wrapper {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.kanban-board {
  display: flex;
  gap: 16px;
  overflow-x: auto;
  padding-bottom: 10px;
  flex: 1;
  align-items: flex-start;
}

.kanban-column {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  width: 320px;
  min-width: 320px;
  display: flex;
  flex-direction: column;
  max-height: 100%;
}

.kanban-column-header {
  padding: 12px 16px;
  border-bottom: 2px solid var(--border);
  background: var(--card);
  border-top-left-radius: var(--radius);
  border-top-right-radius: var(--radius);
}

.kanban-column-title {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--ink);
  margin: 0;
  display: flex;
  align-items: center;
}

.kanban-column-value {
  font-size: 0.8rem;
  color: var(--success);
  font-weight: 600;
  margin-top: 4px;
}

.kanban-column-body {
  padding: 12px;
  overflow-y: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-height: 150px; /* Alvo para drop mesmo se vazio */
}

.kanban-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 14px;
  cursor: grab;
  box-shadow: var(--shadow);
  transition: transform 0.15s, box-shadow 0.15s;
}

.kanban-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
  border-color: var(--primary);
}

.kanban-card.is-dragging {
  opacity: 0.4;
  transform: scale(0.98);
  box-shadow: none;
  border-style: dashed;
}

.kanban-column.drag-over {
  background: rgba(var(--primary-rgb), 0.05);
  box-shadow: inset 0 0 0 2px var(--primary);
}

.kanban-card:active {
  cursor: grabbing;
}

.card-title {
  font-weight: 600;
  font-size: 0.85rem;
  color: var(--ink);
  margin-bottom: 6px;
  line-height: 1.3;
}

.card-client {
  font-size: 0.75rem;
  color: var(--ink-muted);
  display: flex;
  align-items: center;
  gap: 4px;
}

.card-footer-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px dashed var(--border);
  padding-top: 8px;
}

.card-value {
  font-size: 0.85rem;
}
</style>
