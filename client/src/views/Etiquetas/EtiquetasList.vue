<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'list')">Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <span>Gerenciamento de Etiquetas</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Etiquetas</h1>
        <p>Crie, edite e organize as etiquetas usadas nos processos.</p>
      </div>
      <button class="btn btn-primary" @click="openModal()">
        <i class="bi bi-plus-circle"></i> Nova Etiqueta
      </button>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
      <div class="stat-chip">
        <i class="bi bi-tags"></i>
        <span>Total: <strong>{{ etiquetas.length }}</strong></span>
      </div>
      <div class="stat-chip">
        <i class="bi bi-palette" style="color:var(--accent);"></i>
        <span>Cores únicas: <strong>{{ uniqueColors }}</strong></span>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left">
        <div class="filter-tabs" role="tablist" aria-label="Filtro de etiquetas" @keydown="handleTabNav">
          <button
            v-for="tab in ['todas', 'recentes']"
            :key="tab"
            role="tab"
            :aria-selected="activeTab === tab"
            :tabindex="activeTab === tab ? 0 : -1"
            :class="['filter-tab', { active: activeTab === tab }]"
            @click="setTab(tab)"
            :ref="el => { if (tab === 'todas') tabTodas = el; if (tab === 'recentes') tabRecentes = el; }"
          >
            {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
          </button>
        </div>
      </div>

      <div class="toolbar-right">
      </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid" style="margin-top:1.5rem;">
      <!-- Left Column – Main List -->
      <div>
        <!-- Skeleton Loading -->
        <div v-if="loading" class="process-list">
          <div class="skeleton-card" v-for="i in 4" :key="i">
            <div class="skeleton-line" style="width: 40%; height: 16px;"></div>
            <div class="skeleton-line" style="width: 60%; height: 12px;"></div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!filteredEtiquetas.length" class="empty-state card">
          <i class="bi bi-tags" style="font-size:3rem;color:var(--accent);"></i>
          <h4>Nenhuma etiqueta encontrada</h4>
          <p>Tente alterar os termos de busca ou crie uma nova etiqueta.</p>
          <button class="btn btn-primary mt-3" @click="openModal()">
            <i class="bi bi-plus-circle"></i> Criar Nova Etiqueta
          </button>
        </div>

        <!-- Etiquetas List -->
        <TransitionGroup v-else tag="div" name="fade" class="process-list">
          <div
            v-for="etiqueta in paginatedEtiquetas"
            :key="etiqueta.id_etiqueta || etiqueta.id"
            class="process-card"
            role="button"
            tabindex="0"
            :aria-label="'Etiqueta: ' + etiqueta.nome"
            @click="openModal(etiqueta)"
            @keydown.enter="openModal(etiqueta)"
            @keydown.space.prevent="openModal(etiqueta)"
          >
            <div class="process-main" style="cursor:pointer;">
              <a href="#" class="process-cnj" @click.prevent="openModal(etiqueta)">
                <span class="badge rounded-pill" :style="getBadgeStyle(etiqueta.cor)">
                  {{ etiqueta.nome }}
                </span>
              </a>
              <div class="process-meta" style="margin-top:6px;">
                <span>
                  <i class="bi bi-hash"></i>
                  ID: <strong>{{ etiqueta.id_etiqueta || etiqueta.id }}</strong>
                </span>
                <span>
                  <i class="bi bi-palette"></i>
                  Cor:
                  <span class="color-preview-inline" :style="{ backgroundColor: '#' + (etiqueta.cor || '6c757d') }"></span>
                  <strong>#{{ etiqueta.cor || '6c757d' }}</strong>
                </span>
              </div>
            </div>

            <div class="process-actions" @click.stop>
              <div class="action-buttons-wrapper" style="display:flex;gap:4px;">
                <button class="btn btn-ghost btn-sm" style="padding:4px 8px;" @click="openModal(etiqueta)" title="Editar" aria-label="Editar etiqueta">
                  <i class="bi bi-pencil-square" style="color:var(--accent);"></i>
                </button>
                <button class="btn btn-ghost btn-sm" style="padding:4px 8px;" @click="confirmarExclusao(etiqueta.id_etiqueta || etiqueta.id)" title="Excluir" aria-label="Excluir etiqueta">
                  <i class="bi bi-trash" style="color:var(--danger);"></i>
                </button>
              </div>
            </div>
          </div>
        </TransitionGroup>

        <!-- Pagination -->
        <div class="pagination" v-if="!loading && totalPages > 1" role="navigation" aria-label="Navegação de página">
          <button class="page-btn" :disabled="currentPage === 1" @click="changePage(currentPage - 1)" aria-label="Página anterior">
            <i class="bi bi-chevron-left"></i>
          </button>

          <template v-for="(pg, idx) in displayedPages" :key="idx">
            <span v-if="pg === '...'" class="page-ellipsis">...</span>
            <button
              v-else
              :class="['page-btn', { active: currentPage === pg }]"
              @click="changePage(pg)"
              :aria-label="pg === currentPage ? 'Página atual, Página ' + pg : 'Ir para página ' + pg"
              :aria-current="pg === currentPage ? 'page' : undefined"
            >
              {{ pg }}
            </button>
          </template>

          <button class="page-btn" :disabled="currentPage === totalPages" @click="changePage(currentPage + 1)" aria-label="Próxima página">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>

      <!-- Right Column – Sidebar -->
      <div class="sidebar-widgets">
        <!-- Últimas etiquetas criadas -->
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h3><i class="bi bi-clock-history"></i> Criadas Recentemente</h3>
          </div>
          <TransitionGroup name="fade" tag="ul" class="widget-list">
            <li v-if="!recentEtiquetas.length" key="empty" style="color:var(--ink-muted);font-size:.82rem;">
              Nenhuma etiqueta recente.
            </li>
            <li
              v-for="e in recentEtiquetas"
              :key="e.id_etiqueta || e.id"
              role="button"
              tabindex="0"
              :aria-label="'Etiqueta recente: ' + e.nome"
              @click="openModal(e)"
              @keydown.enter="openModal(e)"
              @keydown.space.prevent="openModal(e)"
            >
              <span class="process-num">
                <span class="badge rounded-pill" :style="getBadgeStyle(e.cor)" style="font-size:.75rem;">
                  {{ e.nome }}
                </span>
              </span>
              <span class="process-date">#{{ e.cor || '6c757d' }}</span>
            </li>
          </TransitionGroup>
        </div>

        <!-- Paleta de cores em uso -->
        <div class="card">
          <div class="card-header">
            <h3><i class="bi bi-palette2"></i> Paleta em Uso</h3>
          </div>
          <div class="palette-grid">
            <div
              v-for="(cor, idx) in colorPalette"
              :key="idx"
              class="palette-chip"
              :style="{ backgroundColor: '#' + cor }"
              :title="'#' + cor"
            ></div>
            <div v-if="!colorPalette.length" style="color:var(--ink-muted);font-size:.82rem;padding:8px;">
              Nenhuma cor cadastrada.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Backdrop -->
    <div v-if="showModal" class="modal-backdrop fade show"></div>

    <!-- Modal Criação / Edição -->
    <div v-if="showModal" class="modal fade show" style="display: block;" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-panel">
          <div class="modal-header border-bottom-0 pb-0">
            <h5 class="modal-title fw-bold">
              <i class="bi bi-tag-fill me-2 text-primary"></i>
              {{ isEditing ? 'Editar Etiqueta' : 'Nova Etiqueta' }}
            </h5>
            <button type="button" class="btn-close" @click="closeModal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label required-field">Nome da Etiqueta</label>
              <input
                type="text"
                class="form-control"
                v-model="formData.nome"
                placeholder="Ex: Urgente, Revisar..."
                ref="nomeInput"
              />
            </div>
            <div class="mb-3">
              <label class="form-label">Cor Visual</label>
              <div class="d-flex gap-2 align-items-center">
                <input
                  type="color"
                  class="form-control form-control-color"
                  style="max-width: 60px;"
                  v-model="formData.cor"
                  title="Escolha a cor"
                />
                <input
                  type="text"
                  class="form-control text-uppercase"
                  v-model="formData.cor"
                  maxlength="7"
                  placeholder="#000000"
                />
              </div>
              <div class="mt-3 p-3 bg-light rounded text-center">
                <small class="text-muted d-block mb-2">Pré-visualização:</small>
                <span class="badge rounded-pill" :style="getBadgeStyle(formData.cor.replace('#', ''))">
                  {{ formData.nome || 'Nome da Etiqueta' }}
                </span>
              </div>
            </div>
          </div>
          <div class="modal-footer border-top-0 pt-0">
            <button type="button" class="btn btn-light" @click="closeModal">Cancelar</button>
            <button type="button" class="btn btn-primary px-4" @click="salvarEtiqueta" :disabled="saving">
              <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
              {{ saving ? 'Salvando...' : 'Salvar' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * @version 1.5.48
 */
import { ref, computed, onMounted, nextTick } from 'vue'
import { apiFetch } from '../../services/api'

const props = defineProps({
  searchQuery: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['toast', 'set-view'])

// ── State ────────────────────────────────────────────────
const etiquetas   = ref([])
const loading     = ref(true)
const activeTab   = ref('todas')
const currentPage = ref(1)
const perPage     = 10

const showModal  = ref(false)
const isEditing  = ref(false)
const saving     = ref(false)
const nomeInput  = ref(null)
const tabTodas = ref(null)
const tabRecentes = ref(null)

const formData = ref({ id: null, nome: '', cor: '#0d6efd' })

// ── Computed ─────────────────────────────────────────────
const filteredEtiquetas = computed(() => {
  let list = etiquetas.value

  if (activeTab.value === 'recentes') {
    list = [...list].slice(0, 10)
  }

  if (props.searchQuery && props.searchQuery.trim()) {
    const q = props.searchQuery.trim().toLowerCase()
    list = list.filter(e => e.nome.toLowerCase().includes(q))
  }

  return list
})

const totalPages = computed(() => Math.ceil(filteredEtiquetas.value.length / perPage))

const paginatedEtiquetas = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredEtiquetas.value.slice(start, start + perPage)
})

const recentEtiquetas = computed(() => etiquetas.value.slice(0, 5))

const uniqueColors = computed(() => new Set(etiquetas.value.map(e => e.cor)).size)

const colorPalette = computed(() => [...new Set(etiquetas.value.map(e => e.cor || '6c757d'))].slice(0, 16))

const displayedPages = computed(() => {
  const current = currentPage.value
  const total   = totalPages.value
  const delta   = 2
  const range   = [1]

  for (let i = 2; i < total; i++) {
    if (Math.abs(i - current) <= delta) range.push(i)
  }
  if (total > 1) range.push(total)

  const result = []
  let l
  for (let i = 0; i < range.length; i++) {
    if (l !== undefined) {
      if (range[i] - l === 2) result.push(l + 1)
      else if (range[i] - l > 2) result.push('...')
    }
    result.push(range[i])
    l = range[i]
  }
  return result
})

// ── Methods ───────────────────────────────────────────────
function setTab(tab) {
  activeTab.value = tab
  currentPage.value = 1
  nextTick(() => {
    const el = document.getElementById('tab-' + tab)
    if (el) el.focus()
  })
}

function onTabKeydown(e) {
  const tabs = ['todas', 'recentes']
  const idx = tabs.indexOf(activeTab.value)
  let nextIdx

  if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
    e.preventDefault()
    nextIdx = (idx + 1) % tabs.length
    setTab(tabs[nextIdx])
  } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
    e.preventDefault()
    nextIdx = (idx - 1 + tabs.length) % tabs.length
    setTab(tabs[nextIdx])
  } else if (e.key === 'Home') {
    e.preventDefault()
    setTab(tabs[0])
  } else if (e.key === 'End') {
    e.preventDefault()
    setTab(tabs[tabs.length - 1])
  }
}

function handleTabNav(e) {
  const tabs = ['todas', 'recentes']
  const currentIndex = tabs.indexOf(activeTab.value)

  if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) {
    e.preventDefault()
    let nextIndex = currentIndex

    if (e.key === 'ArrowRight') nextIndex = (currentIndex + 1) % tabs.length
    else if (e.key === 'ArrowLeft') nextIndex = (currentIndex - 1 + tabs.length) % tabs.length
    else if (e.key === 'Home') nextIndex = 0
    else if (e.key === 'End') nextIndex = tabs.length - 1

    setTab(tabs[nextIndex])

    nextTick(() => {
      if (activeTab.value === 'todas') tabTodas.value?.focus()
      else if (activeTab.value === 'recentes') tabRecentes.value?.focus()
    })
  }
}

function changePage(p) {
  if (p === '...' || p < 1 || p > totalPages.value) return
  currentPage.value = p
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function fetchEtiquetas() {
  loading.value = true
  try {
    const res = await apiFetch('etiquetas')
    if (res && res.data) {
      etiquetas.value = res.data
    } else if (res && res.SugestaoEtiquetas) {
      etiquetas.value = res.SugestaoEtiquetas
    } else {
      etiquetas.value = Array.isArray(res) ? res : []
    }
  } catch (err) {
    emit('toast', { msg: 'Erro ao carregar etiquetas', type: 'error' })
  } finally {
    loading.value = false
  }
}

function getBadgeStyle(corHex) {
  const hex = (corHex || '6c757d').replace('#', '')
  return {
    backgroundColor: '#' + hex,
    color: getContrastYIQ(hex),
    fontSize: '0.9rem',
    padding: '0.4em 0.8em'
  }
}

function getContrastYIQ(hexcolor) {
  hexcolor = hexcolor.replace('#', '')
  if (hexcolor.length === 3) hexcolor = hexcolor.split('').map(c => c + c).join('')
  const r = parseInt(hexcolor.substr(0, 2), 16)
  const g = parseInt(hexcolor.substr(2, 2), 16)
  const b = parseInt(hexcolor.substr(4, 2), 16)
  return ((r * 299 + g * 587 + b * 114) / 1000) >= 128 ? '#000000' : '#ffffff'
}

function openModal(etiqueta = null) {
  if (etiqueta) {
    isEditing.value = true
    formData.value = {
      id:   etiqueta.id_etiqueta || etiqueta.id,
      nome: etiqueta.nome,
      cor:  '#' + (etiqueta.cor || '6c757d')
    }
  } else {
    isEditing.value = false
    formData.value = { id: null, nome: '', cor: '#0d6efd' }
  }
  showModal.value = true
  nextTick(() => { if (nomeInput.value) nomeInput.value.focus() })
}

function closeModal() {
  showModal.value = false
}

async function salvarEtiqueta() {
  if (!formData.value.nome.trim()) {
    emit('toast', { msg: 'O nome da etiqueta é obrigatório', type: 'warning' })
    return
  }

  saving.value = true
  try {
    const payload = JSON.stringify({ nome: formData.value.nome, cor: formData.value.cor })
    const res = isEditing.value
      ? await apiFetch(`etiquetas/atualizar/${formData.value.id}`, { method: 'PUT',  body: payload })
      : await apiFetch('etiquetas/salvar',                         { method: 'POST', body: payload })

    if (res && res.success) {
      emit('toast', { msg: isEditing.value ? 'Etiqueta atualizada!' : 'Etiqueta criada!', type: 'success' })
      closeModal()
      fetchEtiquetas()
    } else {
      emit('toast', { msg: res?.message || 'Erro ao salvar etiqueta', type: 'error' })
    }
  } catch (err) {
    emit('toast', { msg: 'Falha na comunicação com o servidor', type: 'error' })
  } finally {
    saving.value = false
  }
}

async function confirmarExclusao(id) {
  if (!confirm('Tem certeza que deseja excluir esta etiqueta? Ela poderá ser removida de todos os processos vinculados.')) return

  try {
    const res = await apiFetch(`etiquetas/excluir/${id}`, { method: 'DELETE' })
    if (res && res.success) {
      emit('toast', { msg: 'Etiqueta excluída com sucesso', type: 'success' })
      etiquetas.value = etiquetas.value.filter(e => (e.id_etiqueta || e.id) !== id)
    } else {
      emit('toast', { msg: res?.message || 'Erro ao excluir', type: 'error' })
    }
  } catch (err) {
    emit('toast', { msg: 'Erro de comunicação ao excluir', type: 'error' })
  }
}

onMounted(() => fetchEtiquetas())
</script>

<style scoped>
.color-preview-inline {
  display: inline-block;
  width: 14px;
  height: 14px;
  border-radius: 3px;
  border: 1px solid rgba(0,0,0,0.15);
  vertical-align: middle;
  margin: 0 4px;
}

.palette-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 12px;
}

.palette-chip {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: 1px solid rgba(0,0,0,0.1);
  box-shadow: inset 0 1px 2px rgba(0,0,0,0.08);
  cursor: default;
  transition: transform 0.15s ease;
}

.palette-chip:hover {
  transform: scale(1.2);
}
</style>