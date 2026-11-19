<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'list')">Saúde</a>
      <i class="bi bi-chevron-right"></i>
      <span>Lista de Receitas</span>
    </div>

    <div class="page-header">
      <div>
        <h1>Receitas Recebidas</h1>
        <p>Histórico de receitas e honorários já recebidos</p>
      </div>
      <button class="btn btn-success" @click="$emit('open-detail', null)">
        <i class="bi bi-plus-circle"></i> Nova Receita
      </button>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
      <div class="stat-chip">
        <i class="bi bi-exclamation-triangle" style="color:var(--danger);"></i>
        <span>Vencidas: <strong>{{ formatCurrency(stats.valor_vencidas) }} ({{ stats.total_vencidas }})</strong></span>
      </div>
      <div class="stat-chip">
        <i class="bi bi-calendar-check" style="color:var(--warning);"></i>
        <span>Recebe Hoje: <strong>{{ stats.recebe_hoje }}</strong></span>
      </div>
      <div class="stat-chip">
        <i class="bi bi-calendar-week" style="color:var(--info);"></i>
        <span>Na Semana: <strong>{{ stats.recebe_semana }}</strong></span>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left" style="display:flex; align-items:center; gap: 15px;">
        <!-- Removed Tabs -->
        <h5 style="margin: 0; color: var(--text-main);"><i class="bi bi-check2-circle"></i> Histórico de Recebimentos</h5>
        <select class="form-control form-select" v-model="categoriaFiltro" @change="debouncedFetch" style="min-width: 180px; height: 38px;">
          <option value="">Todas as Categorias</option>
          <option v-for="cat in categorias" :key="cat.id_categoria" :value="cat.id_categoria">{{ cat.categoria }}</option>
        </select>
        <label class="filtro-pendentes" title="Mostrar só os pagamentos que ainda não têm comprovante">
          <input type="checkbox" v-model="semComprovante" @change="debouncedFetch"> Sem comprovante
        </label>
      </div>

      <div class="toolbar-right" style="gap:10px;">
        <div class="action-buttons-wrapper d-flex align-items-center" style="gap:10px;">
          <select class="form-control form-select" v-model="batchAction" style="min-width:160px; height:38px;">
            <option value="">Ações em lote...</option>
            <option value="exportar">Exportar CSV</option>
            <option v-if="podeAnexar" value="comprovante">Anexar comprovante</option>
            <option value="excluir">Excluir Permanente</option>
          </select>
          <button class="btn btn-outline-primary btn-sm" @click="applyBatchAction" :disabled="!selectedRows.length || !batchAction" style="height:38px;">
            Aplicar
          </button>
        </div>
      </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid" style="margin-top:1.5rem;">
      <!-- Left Column Main Card List -->
      <div>
        <!-- Skeletons Loading -->
        <div v-if="loading" class="process-list">
          <div class="skeleton-card" v-for="i in 4" :key="i">
            <div class="skeleton-line" style="width: 40%; height: 16px;"></div>
            <div class="skeleton-line" style="width: 80%; height: 12px;"></div>
            <div class="skeleton-line" style="width: 30%; height: 10px;"></div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!listReceitas.length" class="empty-state card">
          <i class="bi bi-cash-coin" style="font-size:3rem;color:var(--accent);"></i>
          <h4>Nenhuma receita recebida</h4>
          <p>Tente alterar os termos de busca.</p>
        </div>

        <!-- Expense Card List -->
        <div v-else class="process-list">
          <div 
            v-for="receita in listReceitas" 
            :key="receita.id_pgto_receita || receita.id_receita"
            class="process-card"
            @click.self="$emit('open-detail', receita.id_pgto_receita || receita.id_receita)"
          >
            <!-- Checkbox for Batch Actions -->
            <div class="process-checkbox" style="padding-right: 15px; display:flex; align-items:center;">
              <input type="checkbox" :value="receita.id_pgto_receita || receita.id_receita" v-model="selectedRows" @click.stop>
            </div>

            <!-- Card Body contents -->
            <div class="process-main" @click="$emit('open-detail', receita.id_pgto_receita || receita.id_receita)">
              <a href="#" class="process-cnj" @click.prevent="$emit('open-detail', receita.id_pgto_receita || receita.id_receita)">
                {{ receita.receita }}
              </a>
              <div class="process-title" v-if="receita.cliente_pagador">
                Pagador: {{ receita.cliente_pagador }}
              </div>
              <div class="process-meta">
                <span><i class="bi bi-calendar-check"></i> Recebimento: <strong>{{ formatDate(receita.pagamento_receita_dt) }}</strong></span>
                <span>
                  <i class="bi bi-cash-stack"></i> Valor: <strong>{{ formatCurrency(receita.valor) }}</strong>
                </span>
                <span v-if="receita.nome_categoria"><i class="bi bi-folder"></i> Categoria: {{ receita.nome_categoria }}</span>
                <span v-if="Number(receita.total_comprovantes) > 0" title="Comprovantes anexados"><i class="bi bi-paperclip"></i> {{ receita.total_comprovantes }}</span>
              </div>
            </div>

            <!-- Card Right actions/status -->
            <div class="process-actions" @click.stop>
              <span class="badge badge-success" style="margin-bottom:8px;">
                <i class="bi bi-check-circle"></i> Recebida
              </span>

              <div class="action-buttons-wrapper" style="display:flex;gap:4px;margin-top:6px;">
                <button class="btn btn-ghost btn-sm" style="padding:4px 8px;" @click="$emit('open-detail', receita.id_pgto_receita || receita.id_receita)" title="Ver Detalhes">
                  <i class="bi bi-eye" style="color:var(--accent);"></i>
                </button>
                <button class="btn btn-ghost btn-sm" style="padding:4px 8px;" @click="confirmDelete(receita)" title="Excluir">
                  <i class="bi bi-trash" style="color:var(--danger);"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div class="pagination" v-if="!loading && pagination.total_pages > 1">
          <button class="page-btn" :disabled="pagination.page === 1" @click="changePage(pagination.page - 1)">
            <i class="bi bi-chevron-left"></i>
          </button>
          
          <template v-for="(pg, idx) in displayedPages" :key="idx">
            <span v-if="pg === '...'" class="page-ellipsis">...</span>
            <button 
              v-else
              :class="['page-btn', { active: pagination.page === pg }]"
              @click="changePage(pg)"
            >
              {{ pg }}
            </button>
          </template>

          <button class="page-btn" :disabled="pagination.page === pagination.total_pages" @click="changePage(pagination.page + 1)">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>

      <!-- Right Column Sidebar Widgets -->
      <div class="sidebar-widgets">
        <!-- Resumo Financeiro Widget -->
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h3><i class="bi bi-pie-chart"></i> Resumo</h3>
          </div>
          <div class="card-body" style="padding: 12px;">
             <p style="margin-bottom: 5px; font-size: 0.9rem;">Total Vencidas: <strong>{{ formatCurrency(stats.valor_vencidas) }}</strong></p>
             <p style="margin-bottom: 5px; font-size: 0.9rem;">Qtd. Recebe Hoje: <strong>{{ stats.recebe_hoje }}</strong></p>
             <p style="margin-bottom: 5px; font-size: 0.9rem;">Qtd. Recebe Semana: <strong>{{ stats.recebe_semana }}</strong></p>
             <button class="btn btn-outline-secondary btn-sm" style="width: 100%; margin-top:10px;" @click="exportCSV">
                <i class="bi bi-download"></i> Exportar Tudo CSV
             </button>
          </div>
        </div>

        <!-- Últimas Recebidas widget -->
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h3><i class="bi bi-check2-circle"></i> Últimas Recebidas</h3>
          </div>
          <ul class="widget-list">
            <li v-if="!ultimasRecebidas.length" style="color:var(--ink-muted);font-size:.82rem;">Nenhuma receita registrada recentemente.</li>
            <li 
              v-for="(p, idx) in ultimasRecebidas" 
              :key="idx"
            >
              <div style="display:flex; justify-content:space-between; width:100%;">
                <span class="process-num" style="max-width: 60%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" :title="p.receita">{{ p.receita }}</span>
                <span class="process-date" style="color:var(--success); font-weight:bold;">{{ formatCurrency(p.valor) }}</span>
              </div>
              <span class="process-date">Data Recbto: {{ formatDate(p.pagamento_receita_dt) }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <ComprovanteLoteModal
      v-if="loteAberto"
      :pagamentos="selectedRows.map((id) => ({ tipo: 'receita', id }))"
      @close="loteAberto = false"
      @enviado="fetchData"
      @toast="(t) => $emit('toast', t)"
    />
  </div>
</template>

<script setup lang="ts">
import ComprovanteLoteModal from '../../components/Financeiro/ComprovanteLoteModal.vue'
import { pode } from '../../composables/useAcesso'
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { apiFetch, baixarArquivo, toFormData, formatDate, formatCurrency, debounce } from '../../services/api'

const props = defineProps({
  searchQuery: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['open-detail', 'toast', 'set-view'])

// Local States
const listReceitas = ref([])
const ultimasRecebidas = ref([])
const loading = ref(true)
const selectedRows = ref([])
const batchAction = ref('')
const loteAberto = ref(false)
const podeAnexar = computed(() => pode('financeiro.edit', 'financeiro.create'))
const categorias = ref([])
const categoriaFiltro = ref('')
const semComprovante = ref(false)

const stats = reactive({
  total_vencidas: 0,
  valor_vencidas: 0,
  recebe_hoje: 0,
  recebe_semana: 0
})

const pagination = reactive({
  page: 1,
  limit: 15,
  total_items: 0,
  total_pages: 0
})

// Search debouncer
const debouncedFetch = debounce(() => {
  pagination.page = 1
  fetchData()
}, 300)

watch(() => props.searchQuery, debouncedFetch)



// Data fetching
async function fetchData() {
  loading.value = true
  selectedRows.value = []
  try {
    const params = new URLSearchParams()
    params.append('page', String(pagination.page))
    params.append('perPage', String(pagination.limit))
    if (props.searchQuery) params.append('search', props.searchQuery)
    if (categoriaFiltro.value) params.append('categoria', categoriaFiltro.value)
    if (semComprovante.value) params.append('sem_comprovante', 'true')

    const res = await apiFetch('financeiro/pagamentoReceitas/listar?' + params.toString())
    if (res && res.success) {
      listReceitas.value = res.data || []
      if (res.pager) {
        pagination.total_items = res.pager.total
        pagination.total_pages = res.pager.last_page
      }
    } else {
      throw new Error(res.message || 'Erro ao carregar receitas.')
    }
  } catch (err) {
    console.error(err)
    emit('toast', { msg: 'Não foi possível carregar a lista de receitas.', type: 'danger' })
  } finally {
    loading.value = false
  }
}

async function fetchCategorias() {
  try {
    const res = await apiFetch('financeiro/categorias/listar')
    if (res && res.success) categorias.value = res.data || []
  } catch(e) { console.error('Erro categorias:', e) }
}

async function fetchWidgets() {
  try {
    const statsRes = await apiFetch('financeiro/pagamentoReceitas/estatisticas')
    if (statsRes && statsRes.success) {
      Object.assign(stats, statsRes)
    }

    const recRes = await apiFetch('financeiro/pagamentoReceitas/listar?perPage=5')
    if (recRes && recRes.success && recRes.data) {
      ultimasRecebidas.value = recRes.data
    }
  } catch (err) {
    console.error('Erro ao sincronizar widgets de receitas:', err)
  }
}

// Util helpers
function isHoje(data) {
  if (!data) return false
  const d = new Date(data + 'T00:00:00')
  const hj = new Date()
  hj.setHours(0,0,0,0)
  return d.getTime() === hj.getTime()
}

// Pagination action
function changePage(p) {
  if (p === '...' || p < 1 || p > pagination.total_pages) return
  pagination.page = p
  fetchData()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const displayedPages = computed(() => {
  const current = pagination.page
  const total = pagination.total_pages
  const delta = 2
  const range = []

  range.push(1)

  let l
  for (let i = 2; i < total; i++) {
    if (Math.abs(i - current) <= delta) {
      range.push(i)
    }
  }

  if (total > 1) {
    range.push(total)
  }

  const result = []
  for (let i = 0; i < range.length; i++) {
    if (l !== undefined) {
      if (range[i] - l === 2) {
        result.push(l + 1)
      } else if (range[i] - l > 2) {
        result.push('...')
      }
    }
    result.push(range[i])
    l = range[i]
  }
  return result
})

async function confirmDelete(receita) {
  if (confirm(`Deseja realmente estornar a receita "${receita.receita}"? Esta ação não poderá ser desfeita.`)) {
    try {
      const res = await apiFetch(`financeiro/pagamentoReceitas/estornar/${receita.id_pgto_receita || receita.id_receita}`, { method: 'POST' })
      if (res && res.success) {
        emit('toast', { msg: res.message || 'Receita excluída com sucesso.', type: 'success' })
        fetchData()
        fetchWidgets()
        window.dispatchEvent(new Event('sync-global-stats'))
      } else {
        throw new Error(res.message || 'Erro ao excluir.')
      }
    } catch (err) {
      console.error(err)
      emit('toast', { msg: err.message || 'Não foi possível excluir a receita.', type: 'danger' })
    }
  }
}

async function applyBatchAction() {
  if (!selectedRows.value.length || !batchAction.value) return

  if (batchAction.value === 'comprovante') {
    // A seleção fica até o envio terminar; a listagem recarrega e limpa.
    loteAberto.value = true
    batchAction.value = ''
    return
  }

  if (batchAction.value === 'excluir') {
    if (!confirm(`Deseja excluir ${selectedRows.value.length} receita(s)?`)) return
    try {
      const res = await apiFetch(`financeiro/pagamentoReceitas/excluirEmLote`, { 
        method: 'POST',
        body: { ids: selectedRows.value }
      })
      if (res && res.success) {
        emit('toast', { msg: res.message || 'Receitas excluídas.', type: 'success' })
        fetchData()
        fetchWidgets()
        window.dispatchEvent(new Event('sync-global-stats'))
      }
    } catch(e) {
      emit('toast', { msg: 'Erro ao excluir em lote.', type: 'danger' })
    }
  } else if (batchAction.value === 'receber') {
    // Retirado
  } else if (batchAction.value === 'exportar') {
    emit('toast', { msg: 'Exportando receitas selecionadas...', type: 'info' })
    await baixar('financeiro/pagamentoReceitas/acaoEmLote', toFormData({ acao: 'exportar', recebimentos: selectedRows.value }))
  }

  batchAction.value = ''
  selectedRows.value = []
}

async function baixar(endpoint, corpo = undefined) {
  try {
    await baixarArquivo(endpoint, { method: corpo ? 'POST' : 'GET', body: corpo, nomePadrao: 'recebimentos.csv' })
  } catch (e) {
    emit('toast', { msg: e?.message || 'Não foi possível exportar.', type: 'danger' })
  }
}

function exportCSV() {
  emit('toast', { msg: 'Iniciando download...', type: 'info' })
  void baixar('financeiro/pagamentoReceitas/exportar')
}



onMounted(() => {
  fetchCategorias()
  fetchData()
  fetchWidgets()
})
</script>

<style scoped>
.filtro-pendentes { display: inline-flex; align-items: center; gap: 6px; font-size: .85rem; white-space: nowrap; cursor: pointer; }
</style>
