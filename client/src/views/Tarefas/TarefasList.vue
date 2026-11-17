<template>
  <div class="page-content">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent>Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <span>Tarefas</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Gestão de Tarefas</h1>
        <p>Acompanhamento e organização de tarefas operacionais</p>
      </div>
      <button class="btn btn-primary" @click="activeModal = 'tarefa'" aria-label="Criar nova tarefa" title="Criar nova tarefa">
        <i class="bi bi-plus-circle"></i> Nova Tarefa
      </button>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
      <div class="stat-chip">
        <i class="bi bi-list-task"></i>
        <span>Total: <strong>{{ stats.total }}</strong></span>
      </div>
      <div class="stat-chip">
        <i class="bi bi-clock" style="color:var(--warning);"></i>
        <span>A Fazer: <strong>{{ stats.a_fazer }}</strong></span>
      </div>
      <div class="stat-chip">
        <i class="bi bi-activity" style="color:var(--accent);"></i>
        <span>Fazendo: <strong>{{ stats.fazendo }}</strong></span>
      </div>
      <div class="stat-chip">
        <i class="bi bi-check-circle" style="color:var(--success);"></i>
        <span>Feito: <strong>{{ stats.feito }}</strong></span>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar" style="justify-content: space-between;">
      <div class="toolbar-left">
        <!-- View Toggle -->
        <div class="filter-tabs" role="tablist" aria-label="Visualização de tarefas" @keydown="handleTabNav">
          <button 
            role="tab"
            :aria-selected="viewMode === 'kanban'"
            :tabindex="viewMode === 'kanban' ? 0 : -1"
            :class="['filter-tab', { active: viewMode === 'kanban' }]"
            @click="viewMode = 'kanban'"
            ref="tabKanban"
            aria-label="Ver em modo Kanban"
            title="Modo Kanban"
          >
            <i class="bi bi-kanban"></i> Kanban
          </button>
          <button 
            role="tab"
            :aria-selected="viewMode === 'list'"
            :tabindex="viewMode === 'list' ? 0 : -1"
            :class="['filter-tab', { active: viewMode === 'list' }]"
            @click="viewMode = 'list'"
            ref="tabList"
            aria-label="Ver em modo Lista"
            title="Modo Lista"
          >
            <i class="bi bi-list-ul"></i> Lista
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="process-list" style="margin-top: 20px;">
      <div class="skeleton-card" v-for="i in 3" :key="i">
        <div class="skeleton-line" style="width: 40%; height: 16px;"></div>
        <div class="skeleton-line" style="width: 80%; height: 12px;"></div>
      </div>
    </div>

    <template v-else>
      <!-- KANBAN VIEW -->
      <div v-if="viewMode === 'kanban'" class="kanban-board">
        <div class="kanban-column" v-for="col in kanbanColumns" :key="col.id">
          <div class="kanban-column-header">
            <h3><i :class="col.icon" :style="{ color: col.color }"></i> {{ col.title }}</h3>
            <span class="badge badge-muted">{{ getTasksByStatus(col.id).length }}</span>
          </div>
          <TransitionGroup
               tag="div"
               name="fade"
               class="kanban-column-body"
               role="list"
               :aria-label="'Coluna ' + col.title"
               :class="{ 'drag-over': dragOverCol === col.id }"
               @dragover.prevent="dragOverCol = col.id"
               @dragleave.prevent="dragOverCol = null"
               @drop="onDrop($event, col.id)"
          >
              <div
                v-for="t in getTasksByStatus(col.id)"
                :key="t.id || t.id_tarefa"
                role="listitem"
                class="kanban-card-wrapper"
              >
                <div
                  class="kanban-card"
                  :class="[{ 'is-dragging': draggingId === (t.id || t.id_tarefa) }, `kanban-card--${col.id}`]"
                  @click="editTarefa(t)"
                  draggable="true"
                  @dragstart="onDragStart($event, t)"
                  @dragend="draggingId = null"
                  role="button"
                  tabindex="0"
                  @keydown.enter="editTarefa(t)"
                  @keydown.space.prevent="editTarefa(t)"
                  :aria-label="'Tarefa: ' + t.tarefa + '. Prazo: ' + formatDate(t.prazo)"
                >
                  <div class="kanban-card-title">{{ t.tarefa }}</div>
                  <div class="kanban-card-meta">
                    <span><i class="bi bi-calendar-event"></i> {{ formatDate(t.prazo) }}</span>
                    <PrazoAConferirBadge v-if="prazoAConferir(t)" />
                    <span v-if="t.responsavel_nome || t.responsavel_username">
                      <i class="bi bi-person"></i> {{ t.responsavel_nome || t.responsavel_username }}
                    </span>
                    <span v-if="t.prioridade" :style="{ color: priorityColor(t.prioridade) }" title="Prioridade">
                      <i class="bi bi-flag-fill"></i>
                    </span>
                  </div>
                </div>
                <!-- Entidade Vinculada (Sibling to card to avoid nested buttons) -->
                <div v-if="getEntityLink(t)" class="kanban-card-entity" @click.stop="navigateToEntity(t)"
                     role="button" tabindex="0" @keydown.enter.stop="navigateToEntity(t)" @keydown.space.prevent.stop="navigateToEntity(t)"
                     :aria-label="'Ver ' + getEntityLink(t) + ': ' + getEntityLabel(t)">
                  <i :class="getEntityIcon(t)"></i>
                  <span>{{ getEntityLabel(t) }}</span>
                </div>
              </div>
          </TransitionGroup>
          <div v-if="getTasksByStatus(col.id).length === 0" class="empty-column">
            Nenhuma tarefa
          </div>
        </div>
      </div>

      <!-- LIST VIEW -->
      <TransitionGroup
        v-if="viewMode === 'list'"
        tag="div"
        name="fade"
        class="process-list"
        style="margin-top: 16px;"
        role="list"
      >
        <div 
          v-for="t in tarefas" 
          :key="t.id || t.id_tarefa"
          role="listitem"
          class="process-card-wrapper"
        >
          <div
            class="process-card"
            @click="editTarefa(t)"
            role="button"
            tabindex="0"
            @keydown.enter="editTarefa(t)"
            @keydown.space.prevent="editTarefa(t)"
            :aria-label="'Tarefa: ' + t.tarefa + '. Status: ' + getStatusLabel(t.status)"
          >
            <div class="process-main">
              <div class="process-title">{{ t.tarefa }}</div>
              <div class="process-meta">
                <span><i class="bi bi-calendar-event"></i> Prazo: {{ formatDate(t.prazo) }}</span>
                <PrazoAConferirBadge v-if="prazoAConferir(t)" />
                <span v-if="t.responsavel_nome || t.responsavel_username">
                  <i class="bi bi-person"></i> {{ t.responsavel_nome || t.responsavel_username }}
                </span>
              </div>
            </div>
            <div class="process-actions">
              <span class="badge" :style="{ background: getStatusColor(t.status) }">
                {{ getStatusLabel(t.status) }}
              </span>
              <span style="font-size: .75rem; color: var(--ink-muted); display: block; margin-top: 6px;">
                <i class="bi bi-flag-fill" :style="{ color: priorityColor(t.prioridade) }"></i> Prioridade: {{ t.prioridade }}
              </span>
            </div>
          </div>
          <!-- Entity Link as Sibling -->
          <div v-if="getEntityLink(t)" class="task-entity-link" @click.stop="navigateToEntity(t)"
               role="button" tabindex="0" @keydown.enter.stop="navigateToEntity(t)" @keydown.space.prevent.stop="navigateToEntity(t)"
               :aria-label="'Ver ' + getEntityLink(t) + ': ' + getEntityLabel(t)">
              <i :class="getEntityIcon(t)"></i>
              <span>{{ getEntityLabel(t) }}</span>
            </div>
          </div>
      </TransitionGroup>
      <div v-if="viewMode === 'list' && !tarefas.length" class="empty-state card">
        <i class="bi bi-check2-square"></i>
        <h4>Nenhuma tarefa localizada</h4>
      </div>
    </template>

    <!-- Modal Tarefa -->
    <TarefaModal 
      v-if="activeModal === 'tarefa'"
      :task-data="editingTarefa"
      @close="closeTarefaModal"
      @submit="submitTarefa"
      @warn="msg => $emit('toast', { msg, type: 'warning' })"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch, nextTick } from 'vue'
import { apiFetch, formatDate } from '../../services/api'
import TarefaModal from '../../components/modals/Processos/TarefaModal.vue'
import PrazoAConferirBadge from '../../components/widgets/PrazoAConferirBadge.vue'
import { prazoAConferir } from '../../utils/prazos'

const props = defineProps({
  searchQuery: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['toast', 'open-process', 'open-caso', 'open-cliente'])

const viewMode = ref('kanban')
const loading = ref(true)
const tabKanban = ref(null)
const tabList = ref(null)
const tarefas = ref([])
const activeModal = ref(null)
const editingTarefa = ref(null)

const dragOverCol = ref(null)
const draggingId = ref(null)

const stats = reactive({
  total: 0,
  a_fazer: 0,
  fazendo: 0,
  feito: 0
})

const kanbanColumns = [
  { id: 2, title: 'A Fazer', icon: 'bi-clock', color: 'var(--warning)' },
  { id: 3, title: 'Fazendo', icon: 'bi-activity', color: 'var(--accent)' },
  { id: 4, title: 'Feito', icon: 'bi-check-circle', color: 'var(--success)' },
  { id: 1, title: 'Eventos', icon: 'bi-calendar-event', color: '#9A49C3' }
]

function priorityColor(p) {
  const prio = Number(p)
  if (prio === 5) return 'var(--danger)' // Urgente
  if (prio === 4) return '#d97706' // Alta
  if (prio === 3) return 'var(--accent)' // Media
  if (prio === 2) return 'var(--success)' // Baixa
  return 'var(--ink-muted)' // Muito Baixa
}

function getStatusLabel(s) {
  const status = Number(s)
  if (status === 1) return 'Eventos'
  if (status === 2) return 'A Fazer'
  if (status === 3) return 'Fazendo'
  if (status === 4) return 'Feito'
  if (status === 5) return 'Cancelado'
  return 'Desconhecido'
}

function getStatusColor(s) {
  const status = Number(s)
  if (status === 2) return 'var(--warning)'
  if (status === 3) return 'var(--accent)'
  if (status === 4) return 'var(--success)'
  if (status === 1) return '#9A49C3'
  if (status === 5) return 'var(--danger)'
  return 'var(--ink-muted)'
}

function getTasksByStatus(statusId) {
  return tarefas.value.filter(t => Number(t.status) === statusId)
}

async function fetchTarefas() {
  loading.value = true
  try {
    const endpoint = props.searchQuery ? `tarefas?s=${props.searchQuery}` : 'tarefas'
    const res = await apiFetch(endpoint)
    
    if (res && res.success) {
      tarefas.value = res.data || res.results || []
    } else {
      if (Array.isArray(res)) tarefas.value = res
      else tarefas.value = []
    }
    updateStats()
  } catch (err) {
    console.error(err)
    emit('toast', { msg: 'Erro ao carregar tarefas.', type: 'danger' })
  } finally {
    loading.value = false
  }
}

function updateStats() {
  stats.total = tarefas.value.length
  stats.a_fazer = tarefas.value.filter(t => Number(t.status) === 2).length
  stats.fazendo = tarefas.value.filter(t => Number(t.status) === 3).length
  stats.feito = tarefas.value.filter(t => Number(t.status) === 4).length
}

// Entity helper functions
function getEntityLink(t) {
  if (t.processo_id || t.id_processo) return 'processo'
  if (t.caso_id || t.id_caso) return 'caso'
  if (t.cliente_id || t.id_cliente) return 'cliente'
  return null
}

function getEntityIcon(t) {
  const type = getEntityLink(t)
  if (type === 'processo') return 'bi bi-briefcase-fill'
  if (type === 'caso') return 'bi bi-journal-bookmark-fill'
  if (type === 'cliente') return 'bi bi-person-badge-fill'
  return 'bi bi-link-45deg'
}

function getEntityLabel(t) {
  const type = getEntityLink(t)
  if (type === 'processo') {
    return t.processo_numero || t.processo_nome || `Processo #${t.processo_id || t.id_processo}`
  }
  if (type === 'caso') {
    return t.caso_titulo || t.caso_nome || `Caso #${t.caso_id || t.id_caso}`
  }
  if (type === 'cliente') {
    return t.cliente_nome || t.cliente_razao_social || `Cliente #${t.cliente_id || t.id_cliente}`
  }
  return ''
}

function navigateToEntity(t) {
  const type = getEntityLink(t)
  if (type === 'processo') {
    const id = t.processo_id || t.id_processo
    emit('open-process', Number(id))
  } else if (type === 'caso') {
    const id = t.caso_id || t.id_caso
    emit('open-caso', Number(id))
  } else if (type === 'cliente') {
    const id = t.cliente_id || t.id_cliente
    emit('open-cliente', Number(id))
  }
}

watch(() => props.searchQuery, () => {
  fetchTarefas()
})

function setViewMode(mode) {
  viewMode.value = mode
  nextTick(() => {
    const el = document.getElementById('tab-' + mode)
    if (el) el.focus()
  })
}

function onViewModeKeydown(e) {
  const modes = ['kanban', 'list']
  const idx = modes.indexOf(viewMode.value)
  let nextIdx

  if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
    e.preventDefault()
    nextIdx = (idx + 1) % modes.length
    setViewMode(modes[nextIdx])
  } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
    e.preventDefault()
    nextIdx = (idx - 1 + modes.length) % modes.length
    setViewMode(modes[nextIdx])
  } else if (e.key === 'Home') {
    e.preventDefault()
    setViewMode(modes[0])
  } else if (e.key === 'End') {
    e.preventDefault()
    setViewMode(modes[modes.length - 1])
  }
}

function onDragStart(event, tarefa) {
  event.dataTransfer.setData('tarefaId', tarefa.id || tarefa.id_tarefa)
  event.dataTransfer.effectAllowed = 'move'
  setTimeout(() => {
    draggingId.value = tarefa.id || tarefa.id_tarefa
  }, 0)
}

async function onDrop(event, newStatusId) {
  dragOverCol.value = null
  const tarefaId = event.dataTransfer.getData('tarefaId')
  if (!tarefaId) return

  const taskIndex = tarefas.value.findIndex(t => (t.id || t.id_tarefa) == tarefaId)
  if (taskIndex === -1) return
  
  const task = tarefas.value[taskIndex]
  if (Number(task.status) === newStatusId) return

  const oldStatus = task.status
  task.status = newStatusId
  updateStats()

  try {
    const res = await apiFetch('tarefas/mudar-status', {
      method: 'POST',
      body: { id: tarefaId, status: newStatusId }
    })
    
    if (!res || !res.success) {
      throw new Error(res?.message || 'Erro ao atualizar status')
    }
  } catch (err) {
    console.error(err)
    task.status = oldStatus
    updateStats()
    emit('toast', { msg: 'Erro ao mover tarefa.', type: 'danger' })
  }
}

function handleTabNav(e) {
  if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) {
    e.preventDefault()
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
      viewMode.value = viewMode.value === 'kanban' ? 'list' : 'kanban'
    } else if (e.key === 'Home') {
      viewMode.value = 'kanban'
    } else if (e.key === 'End') {
      viewMode.value = 'list'
    }
    nextTick(() => {
      if (viewMode.value === 'kanban') tabKanban.value?.focus()
      else tabList.value?.focus()
    })
  }
}

function editTarefa(t) {
  editingTarefa.value = t
  activeModal.value = 'tarefa'
}

function closeTarefaModal() {
  editingTarefa.value = null
  activeModal.value = null
}

async function submitTarefa(payload) {
  try {
    const res = await apiFetch('tarefas/salvar', {
      method: 'POST',
      body: payload
    })
    if (res && res.success) {
      emit('toast', { msg: 'Tarefa salva com sucesso!', type: 'success' })
      closeTarefaModal()
      fetchTarefas()
    } else {
      throw new Error(res.message || 'Erro ao salvar')
    }
  } catch (err) {
    console.error(err)
    emit('toast', { msg: 'Erro ao salvar tarefa. ' + err.message, type: 'danger' })
  }
}

onMounted(() => {
  fetchTarefas()
})
</script>

<style scoped>

.kanban-board {
  display: flex;
  gap: 20px;
  overflow-x: auto;
  padding-bottom: 20px;
  margin-top: 16px;
  align-items: flex-start;
}

.kanban-column {
  flex: 0 0 320px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - 250px);
  overflow: hidden;
}

/* Borda superior colorida por coluna */
.kanban-column:has(.col-header--2) { border-top: 3px solid var(--warning); }
.kanban-column:has(.col-header--3) { border-top: 3px solid var(--accent); }
.kanban-column:has(.col-header--4) { border-top: 3px solid var(--success); }
.kanban-column:has(.col-header--1) { border-top: 3px solid #9A49C3; }

.kanban-column-header {
  padding: 14px 16px;
  border-bottom: 1px solid var(--border);
  background: var(--card);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.kanban-column-header h3 {
  margin: 0;
  font-size: 0.92rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  color: var(--ink);
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Badges de contagem coloridos por coluna */
.col-badge {
  font-size: 0.78rem;
  font-weight: 700;
  padding: 2px 9px;
  border-radius: 20px;
  min-width: 26px;
  text-align: center;
}

.col-badge--2 { background: rgba(234,179,8,0.18); color: var(--warning); border: 1px solid var(--border); }
.col-badge--3 { background: rgba(59,130,246,0.18); color: var(--accent); border: 1px solid var(--border); }
.col-badge--4 { background: rgba(34,197,94,0.18); color: var(--success); border: 1px solid var(--border); }
.col-badge--1 { background: rgba(154,73,195,0.18); color: #9A49C3; border: 1px solid var(--border); }

.kanban-column-body {
  padding: 14px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.kanban-card-wrapper {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

/* Cards base */
.kanban-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 13px 14px;
  cursor: pointer;
  transition: all 0.2s ease;
  border-left-width: 3px;
  box-shadow: var(--shadow);
}

/* Borda lateral colorida por status */
.kanban-card--2 { border-left-color: var(--warning); }
.kanban-card--3 { border-left-color: var(--accent); }
.kanban-card--4 { border-left-color: var(--success); }
.kanban-card--1 { border-left-color: #9A49C3; }

.kanban-card:hover {
  transform: translateY(-2px);
  border-color: var(--primary);
  box-shadow: var(--shadow-lg);
}

.kanban-card-title {
  font-size: 0.88rem;
  color: var(--ink);
  margin-bottom: 10px;
  line-height: 1.45;
  font-weight: 500;
}

.kanban-card-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 6px;
  font-size: 0.74rem;
  color: var(--ink-muted);
}

.kanban-card-meta i {
  margin-right: 3px;
  font-size: 0.8rem;
}

/* Entity link badge inside kanban card */
.kanban-card-entity {
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.71rem;
  font-weight: 600;
  color: var(--accent);
  background: rgba(212, 175, 55, 0.10);
  border: 1px solid rgba(212, 175, 55, 0.25);
  border-radius: 20px;
  padding: 2px 9px 2px 7px;
  cursor: pointer;
  transition: all 0.18s ease;
  max-width: 100%;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.kanban-card-entity:hover {
  background: rgba(212, 175, 55, 0.22);
  border-color: var(--accent);
  color: var(--accent);
  text-decoration: underline;
}

.kanban-card-entity i {
  font-size: 0.78rem;
  flex-shrink: 0;
}

.process-card-wrapper {
  display: flex;
  flex-direction: column;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 16px 20px;
  position: relative;
  overflow: hidden;
  gap: 8px;
}

.process-card-wrapper::before {
  content: '';
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 3px;
  background: var(--success);
  border-radius: 2px 0 0 2px;
}

/* Entity link inside list card */
.task-entity-link {
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.74rem;
  font-weight: 600;
  color: var(--accent);
  background: rgba(212, 175, 55, 0.10);
  border: 1px solid rgba(212, 175, 55, 0.25);
  border-radius: 20px;
  padding: 2px 10px 2px 8px;
  cursor: pointer;
  transition: all 0.18s ease;
  max-width: 100%;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.task-entity-link:hover {
  background: rgba(212, 175, 55, 0.22);
  border-color: var(--accent);
  text-decoration: underline;
}

.task-entity-link i {
  font-size: 0.78rem;
  flex-shrink: 0;
}

/* Refactor list process-card to not have border when wrapped */
.process-card-wrapper .process-card {
  border: none;
  padding: 0;
  background: transparent;
  box-shadow: none;
  cursor: pointer;
  display: flex;
  align-items: flex-start;
  gap: 14px;
}

.process-card-wrapper .process-card::before {
  display: none;
}

.empty-column {
  text-align: center;
  padding: 24px 12px;
  color: var(--ink-muted);
  font-size: 0.83rem;
  font-style: italic;
  border: 1px dashed var(--border);
  border-radius: 6px;
}

/* Drag and Drop Effects */
.kanban-column-body.drag-over {
  background: rgba(var(--primary-rgb), 0.05);
  border-radius: 8px;
  box-shadow: inset 0 0 0 2px var(--primary);
  transition: all 0.2s ease;
}

.kanban-card.is-dragging {
  opacity: 0.4;
  transform: scale(0.98);
  box-shadow: none;
  border-style: dashed;
}
</style>
