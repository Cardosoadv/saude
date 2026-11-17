<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'conhecimento')">Base de Conhecimento</a>
      <i class="bi bi-chevron-right"></i>
      <span>Modelos de Documentos</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Modelos de Documentos</h1>
        <p>Gerencie os modelos usados para gerar documentos a partir de processos e casos</p>
      </div>
      <button class="btn btn-primary" @click="$emit('open-modelo-detail', null)">
        <i class="bi bi-plus-circle"></i> Novo Modelo
      </button>
    </div>

    <!-- Filtros -->
    <div class="filters-bar">
      <div class="search-input" style="max-width: 320px;">
        <i class="bi bi-search"></i>
        <input
          type="text"
          class="form-control"
          v-model="filters.q"
          @input="onFilterChange"
          placeholder="Buscar por título ou conteúdo..."
        >
      </div>

      <select class="form-select" v-model="filters.ramo_id" @change="onFilterChange" style="max-width: 200px;">
        <option value="">Todos os ramos</option>
        <option v-for="ramo in ramos" :key="ramo.id" :value="ramo.id">{{ ramo.nome }}</option>
      </select>

      <select class="form-select" v-model="filters.status" @change="onFilterChange" style="max-width: 160px;">
        <option value="">Todos os status</option>
        <option value="Rascunho">Rascunho</option>
        <option value="Revisão">Revisão</option>
        <option value="Publicado">Publicado</option>
      </select>

      <select class="form-select" v-model="filters.tipo_alvo" @change="onFilterChange" style="max-width: 160px;">
        <option value="">Processo ou Caso</option>
        <option value="processo">Processo</option>
        <option value="caso">Caso</option>
        <option value="ambos">Ambos</option>
      </select>

      <select class="form-select" v-model="filters.ativo" @change="onFilterChange" style="max-width: 140px;">
        <option value="">Ativos e inativos</option>
        <option value="1">Somente ativos</option>
        <option value="0">Somente inativos</option>
      </select>

      <div class="tag-filter" ref="tagFilterEl">
        <button type="button" class="btn btn-ghost btn-sm" @click="tagFilterOpen = !tagFilterOpen">
          <i class="bi bi-tags"></i> Etiquetas
          <span v-if="filters.etiqueta_id" class="badge badge-count ms-1">1</span>
        </button>
        <div v-if="tagFilterOpen" class="tag-filter-dropdown">
          <div
            v-for="tag in availableTags" :key="tag.id"
            class="tag-filter-item"
            :class="{ active: filters.etiqueta_id === tag.id }"
            @click="toggleTagFilter(tag)"
          >
            <span class="badge" :style="{ background: '#' + tag.cor, color: '#fff' }">{{ tag.nome }}</span>
          </div>
          <div v-if="!availableTags.length" class="text-muted tag-filter-empty p-2">Nenhuma etiqueta cadastrada.</div>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="empty-state card">
      <div class="spinner"></div>
      <p>Carregando modelos...</p>
    </div>

    <!-- Empty -->
    <div v-else-if="!modelos.length" class="empty-state card">
      <i class="bi bi-file-earmark-ruled" style="font-size:3rem;color:var(--accent);"></i>
      <h4>Nenhum modelo encontrado</h4>
      <p>Tente alterar os filtros ou crie um novo modelo.</p>
    </div>

    <!-- Table -->
    <div v-else class="card" style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Título</th>
            <th>Ramo</th>
            <th>Alvo</th>
            <th>Etiquetas</th>
            <th>Status</th>
            <th>Versão</th>
            <th>Última revisão</th>
            <th class="col-end">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="modelo in modelos" :key="modelo.id">
            <td>
              <a href="#" class="fw-bold" @click.prevent="$emit('open-modelo-detail', modelo.id)">{{ modelo.titulo }}</a>
            </td>
            <td>{{ modelo.ramo_nome || 'N/A' }}</td>
            <td><span class="badge badge-muted">{{ tipoAlvoLabel(modelo.tipo_alvo) }}</span></td>
            <td>
              <div class="tag-list">
                <span
                  v-for="tag in modelo.etiquetas" :key="tag.id"
                  class="badge" :style="{ background: '#' + tag.cor, color: '#fff' }"
                >{{ tag.nome }}</span>
              </div>
            </td>
            <td><span class="badge" :class="statusBadgeClass(modelo.status)">{{ modelo.status }}</span></td>
            <td>{{ modelo.versao }}</td>
            <td>{{ modelo.ultima_revisao_em ? formatDate(modelo.ultima_revisao_em) : '-' }}</td>
            <td class="col-end">
              <div class="row-actions">
                <button class="btn btn-ghost btn-sm" title="Usar modelo" @click="usarModelo(modelo)">
                  <i class="bi bi-file-earmark-plus" style="color:var(--primary);"></i>
                </button>
                <button class="btn btn-ghost btn-sm" title="Editar" @click="$emit('open-modelo-detail', modelo.id)">
                  <i class="bi bi-pencil-square" style="color:var(--accent);"></i>
                </button>
                <button class="btn btn-ghost btn-sm" title="Duplicar" @click="duplicar(modelo)">
                  <i class="bi bi-files"></i>
                </button>
                <button class="btn btn-ghost btn-sm" :title="modelo.ativo ? 'Desativar' : 'Ativar'" @click="toggleAtivo(modelo)">
                  <i class="bi" :class="modelo.ativo ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted'"></i>
                </button>
                <button class="btn btn-ghost btn-sm" title="Excluir" @click="excluir(modelo)">
                  <i class="bi bi-trash" style="color:var(--danger);"></i>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="!loading && pagination.total_pages > 1" role="navigation" aria-label="Navegação de página">
      <button class="page-btn" :disabled="pagination.page === 1" @click="changePage(pagination.page - 1)" aria-label="Página anterior">
        <i class="bi bi-chevron-left"></i>
      </button>
      <span class="pagination-info text-muted">Página {{ pagination.page }} de {{ pagination.total_pages }} ({{ pagination.total }} modelos)</span>
      <button class="page-btn" :disabled="pagination.page === pagination.total_pages" @click="changePage(pagination.page + 1)" aria-label="Próxima página">
        <i class="bi bi-chevron-right"></i>
      </button>
    </div>

    <EscolherModeloModal
      v-if="modeloParaUsar"
      :modelo-id="modeloParaUsar.id"
      :saving="iniciandoGeracao"
      @close="modeloParaUsar = null"
      @submit="iniciarGeracao"
      @set-view="v => $emit('set-view', v)"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, onBeforeUnmount } from 'vue'
import { apiFetch, formatDate, debounce } from '../../services/api'
import EscolherModeloModal from '../../components/modals/Documentos/EscolherModeloModal.vue'

const emit = defineEmits(['open-modelo-detail', 'toast', 'set-view', 'open-documento-gerado'])

const loading = ref(true)
const modelos = ref([])
const ramos = ref([])
const availableTags = ref([])
const tagFilterOpen = ref(false)
const tagFilterEl = ref(null)
const modeloParaUsar = ref(null)
const iniciandoGeracao = ref(false)

const filters = reactive({
  q: '',
  ramo_id: '',
  status: '',
  tipo_alvo: '',
  ativo: '',
  etiqueta_id: ''
})

const pagination = reactive({
  page: 1,
  per_page: 20,
  total: 0,
  total_pages: 0
})

const debouncedFetch = debounce(() => {
  pagination.page = 1
  fetchData()
}, 300)

function onFilterChange() {
  debouncedFetch()
}

function toggleTagFilter(tag) {
  filters.etiqueta_id = filters.etiqueta_id === tag.id ? '' : tag.id
  tagFilterOpen.value = false
  onFilterChange()
}

function onDocumentClick(e) {
  if (tagFilterEl.value && !tagFilterEl.value.contains(e.target)) {
    tagFilterOpen.value = false
  }
}

function tipoAlvoLabel(tipo) {
  const labels = { processo: 'Processo', caso: 'Caso', ambos: 'Ambos' }
  return labels[tipo] || 'Ambos'
}

function statusBadgeClass(status) {
  if (status === 'Publicado') return 'badge-success'
  if (status === 'Revisão') return 'badge-warning'
  return 'badge-muted'
}

async function fetchData() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.append('page', String(pagination.page))
    params.append('per_page', String(pagination.per_page))
    if (filters.q) params.append('q', filters.q)
    if (filters.ramo_id) params.append('ramo_id', filters.ramo_id)
    if (filters.status) params.append('status', filters.status)
    if (filters.tipo_alvo) params.append('tipo_alvo', filters.tipo_alvo)
    if (filters.ativo !== '') params.append('ativo', filters.ativo)
    if (filters.etiqueta_id) params.append('etiqueta_id', filters.etiqueta_id)

    const res = await apiFetch('conhecimento/modelos?' + params.toString())
    modelos.value = res?.data || []
    if (res?.pagination) {
      Object.assign(pagination, res.pagination)
    }
  } catch (err) {
    console.error('Erro ao carregar modelos:', err)
    emit('toast', { msg: 'Não foi possível carregar os modelos.', type: 'danger' })
  } finally {
    loading.value = false
  }
}

async function fetchRamos() {
  try {
    ramos.value = await apiFetch('conhecimento/modelos/ramos-options') || []
  } catch (err) {
    console.error('Erro ao carregar ramos:', err)
  }
}

async function fetchTags() {
  try {
    const res = await apiFetch('etiquetas')
    const list = (res && res.data) || (Array.isArray(res) ? res : [])
    availableTags.value = list.map(t => ({ id: t.id_etiqueta || t.id, nome: t.nome, cor: t.cor }))
  } catch (err) {
    console.error('Erro ao carregar etiquetas:', err)
  }
}

function changePage(p) {
  if (p < 1 || p > pagination.total_pages) return
  pagination.page = p
  fetchData()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function duplicar(modelo) {
  try {
    const original = await apiFetch(`conhecimento/modelos/${modelo.id}`)
    const res = await apiFetch('conhecimento/modelos/salvar', {
      method: 'POST',
      body: {
        titulo: `${original.titulo} (cópia)`,
        ramo_id: original.ramo_id,
        tipo_alvo: original.tipo_alvo,
        conteudo: original.conteudo,
        status: 'Rascunho',
        versao: '1.0.0',
        alerta_vencimento: original.alerta_vencimento
      }
    })
    if (res && res.success !== false) {
      emit('toast', { msg: 'Modelo duplicado com sucesso.', type: 'success' })
      fetchData()
    }
  } catch (err) {
    console.error('Erro ao duplicar modelo:', err)
    emit('toast', { msg: 'Não foi possível duplicar o modelo.', type: 'danger' })
  }
}

async function toggleAtivo(modelo) {
  try {
    await apiFetch(`conhecimento/modelos/atualizar/${modelo.id}`, {
      method: 'PUT',
      body: {
        titulo: modelo.titulo,
        conteudo: modelo.conteudo,
        ativo: modelo.ativo ? 0 : 1
      }
    })
    emit('toast', { msg: modelo.ativo ? 'Modelo desativado.' : 'Modelo ativado.', type: 'success' })
    fetchData()
  } catch (err) {
    console.error('Erro ao atualizar modelo:', err)
    emit('toast', { msg: 'Não foi possível atualizar o modelo.', type: 'danger' })
  }
}

function usarModelo(modelo) {
  modeloParaUsar.value = modelo
}

async function iniciarGeracao(payload) {
  iniciandoGeracao.value = true
  try {
    const documento = await apiFetch('conhecimento/documentos-gerados/iniciar', { method: 'POST', body: payload })
    modeloParaUsar.value = null
    emit('open-documento-gerado', documento.id)
  } catch (err) {
    console.error('Erro ao iniciar geração do documento:', err)
    emit('toast', { msg: err.message || 'Não foi possível gerar o documento a partir do modelo.', type: 'danger' })
  } finally {
    iniciandoGeracao.value = false
  }
}

async function excluir(modelo) {
  if (!confirm(`Deseja realmente excluir o modelo "${modelo.titulo}"? Esta ação não poderá ser desfeita.`)) return
  try {
    await apiFetch(`conhecimento/modelos/deletar/${modelo.id}`, { method: 'DELETE' })
    emit('toast', { msg: 'Modelo excluído com sucesso.', type: 'success' })
    fetchData()
  } catch (err) {
    console.error('Erro ao excluir modelo:', err)
    emit('toast', { msg: 'Não foi possível excluir o modelo.', type: 'danger' })
  }
}

onMounted(() => {
  fetchData()
  fetchRamos()
  fetchTags()
  document.addEventListener('click', onDocumentClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
})
</script>

<style scoped>
.filters-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
  margin-bottom: 1.25rem;
}

.search-input {
  position: relative;
  display: flex;
  align-items: center;
}

.search-input i {
  position: absolute;
  left: 12px;
  color: var(--ink-muted);
  font-size: .85rem;
  pointer-events: none;
}

.search-input .form-control {
  padding-left: 34px;
}

.badge-count {
  background: var(--primary);
  color: #fff;
}

.tag-filter-empty {
  font-size: .8rem;
}

.tag-filter {
  position: relative;
}

.tag-filter-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  margin-top: 4px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 8px;
  box-shadow: var(--shadow);
  padding: 6px;
  min-width: 200px;
  max-height: 260px;
  overflow-y: auto;
  z-index: 20;
}

.tag-filter-item {
  padding: 6px 8px;
  border-radius: 6px;
  cursor: pointer;
}

.tag-filter-item:hover,
.tag-filter-item.active {
  background: var(--surface);
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th {
  text-align: left;
  padding: 12px 20px;
  font-size: .78rem;
  font-weight: 600;
  color: var(--ink-muted);
  text-transform: uppercase;
  letter-spacing: .02em;
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}

.data-table td {
  padding: 12px 20px;
  font-size: .85rem;
  color: var(--ink-dim);
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}

.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: var(--surface); }

.data-table .col-end { text-align: right; }

.tag-list {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.row-actions {
  display: flex;
  gap: 4px;
  justify-content: flex-end;
}

.pagination-info {
  font-size: .82rem;
  margin: 0 8px;
}
</style>
