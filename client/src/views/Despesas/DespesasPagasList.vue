<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'list')">Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <span>Lista de Despesas</span>
    </div>

    <div class="page-header">
      <div>
        <h1>Despesas Pagas</h1>
        <p>Histórico de despesas e contas já liquidadas</p>
      </div>
      <button class="btn btn-success" @click="$emit('open-detail', null)">
        <i class="bi bi-plus-circle"></i> Nova Despesa
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
        <span>Vence Hoje: <strong>{{ stats.vence_hoje }}</strong></span>
      </div>
      <div class="stat-chip">
        <i class="bi bi-calendar-week" style="color:var(--info);"></i>
        <span>Vence na Semana: <strong>{{ stats.vence_semana }}</strong></span>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left" style="display:flex; align-items:center; gap: 15px;">
        <!-- Removed Tabs -->
        <h5 style="margin: 0; color: var(--text-main);"><i class="bi bi-check2-circle"></i> Histórico de Pagamentos</h5>
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
        <div v-else-if="!listDespesas.length" class="empty-state card">
          <i class="bi bi-receipt" style="font-size:3rem;color:var(--accent);"></i>
          <h4>Nenhuma despesa paga</h4>
          <p>Tente alterar os termos de busca.</p>
        </div>

        <!-- Expense Card List -->
        <div v-else class="process-list">
          <div 
            v-for="despesa in listDespesas" 
            :key="despesa.id_pgto_despesa || despesa.id_despesa"
            class="process-card"
            @click.self="$emit('open-detail', despesa.id_pgto_despesa || despesa.id_despesa)"
          >
            <!-- Checkbox for Batch Actions -->
            <div class="process-checkbox" style="padding-right: 15px; display:flex; align-items:center;">
              <input type="checkbox" :value="despesa.id_pgto_despesa || despesa.id_despesa" v-model="selectedRows" @click.stop>
            </div>

            <!-- Card Body contents -->
            <div class="process-main" @click="$emit('open-detail', despesa.id_pgto_despesa || despesa.id_despesa)">
              <a href="#" class="process-cnj" @click.prevent="$emit('open-detail', despesa.id_pgto_despesa || despesa.id_despesa)">
                {{ despesa.despesa }}
              </a>
              <div class="process-title" v-if="despesa.cliente_fornecedor">
                Fornecedor: {{ despesa.cliente_fornecedor }}
              </div>
              <div class="process-meta">
                <span><i class="bi bi-calendar-check"></i> Pagamento: <strong>{{ formatDate(despesa.pagamento_despesa_dt) }}</strong></span>
                <span>
                  <i class="bi bi-cash-stack"></i> Valor: <strong>{{ formatCurrency(despesa.valor) }}</strong>
                </span>
                <span v-if="despesa.nome_categoria"><i class="bi bi-folder"></i> Categoria: {{ despesa.nome_categoria }}</span>
                <span v-if="Number(despesa.total_comprovantes) > 0" title="Comprovantes anexados"><i class="bi bi-paperclip"></i> {{ despesa.total_comprovantes }}</span>
              </div>
            </div>

            <!-- Card Right actions/status -->
            <div class="process-actions" @click.stop>
              <span class="badge badge-success" style="margin-bottom:8px;">
                <i class="bi bi-check-circle"></i> Paga
              </span>

              <div class="action-buttons-wrapper" style="display:flex;gap:4px;margin-top:6px;">
                <button class="btn btn-ghost btn-sm" style="padding:4px 8px;" @click="$emit('open-detail', despesa.id_pgto_despesa || despesa.id_despesa)" title="Ver Detalhes">
                  <i class="bi bi-eye" style="color:var(--accent);"></i>
                </button>
                <button class="btn btn-ghost btn-sm" style="padding:4px 8px;" @click="confirmDelete(despesa)" title="Excluir">
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
             <p style="margin-bottom: 5px; font-size: 0.9rem;">Qtd. Vence Hoje: <strong>{{ stats.vence_hoje }}</strong></p>
             <p style="margin-bottom: 5px; font-size: 0.9rem;">Qtd. Vence na Semana: <strong>{{ stats.vence_semana }}</strong></p>
             <button class="btn btn-outline-secondary btn-sm" style="width: 100%; margin-top:10px;" @click="exportCSV">
                <i class="bi bi-download"></i> Exportar Tudo CSV
             </button>
          </div>
        </div>

        <!-- Últimas Pagas widget -->
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h3><i class="bi bi-check2-circle"></i> Últimas Pagas</h3>
          </div>
          <ul class="widget-list">
            <li v-if="!ultimasPagas.length" style="color:var(--ink-muted);font-size:.82rem;">Nenhuma despesa paga recentemente.</li>
            <li 
              v-for="(p, idx) in ultimasPagas" 
              :key="idx"
            >
              <div style="display:flex; justify-content:space-between; width:100%;">
                <span class="process-num" style="max-width: 60%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" :title="p.despesa">{{ p.despesa }}</span>
                <span class="process-date" style="color:var(--success); font-weight:bold;">{{ formatCurrency(p.valor) }}</span>
              </div>
              <span class="process-date">Data Pagto: {{ formatDate(p.pagamento_despesa_dt) }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <ComprovanteLoteModal
      v-if="loteAberto"
      :pagamentos="selectedRows.map((id) => ({ tipo: 'despesa', id }))"
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
const listDespesas = ref([])
const ultimasPagas = ref([])
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
  vence_hoje: 0,
  vence_semana: 0
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

    const res = await apiFetch('financeiro/pagamentoDespesas/listar?' + params.toString())
    if (res && res.success) {
      listDespesas.value = res.data || []
      if (res.pager) {
        pagination.total_items = res.pager.total
        pagination.total_pages = res.pager.last_page
      }
    } else {
      throw new Error(res.message || 'Erro ao carregar despesas.')
    }
  } catch (err) {
    console.error(err)
    emit('toast', { msg: 'Não foi possível carregar a lista de despesas.', type: 'danger' })
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
    const statsRes = await apiFetch('financeiro/pagamentoDespesas/estatisticas')
    if (statsRes && statsRes.success) {
      Object.assign(stats, statsRes)
    }

    const pagasRes = await apiFetch('financeiro/pagamentoDespesas/listar?perPage=5')
    if (pagasRes && pagasRes.success && pagasRes.data) {
      ultimasPagas.value = pagasRes.data
    }
  } catch (err) {
    console.error('Erro ao sincronizar widgets despesas:', err)
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

async function confirmDelete(despesa) {
  if (confirm(`Deseja realmente estornar a despesa "${despesa.despesa}"? Esta ação não poderá ser desfeita.`)) {
    try {
      const res = await apiFetch(`financeiro/pagamentoDespesas/estornar/${despesa.id_pgto_despesa || despesa.id_despesa}`, { method: 'POST' })
      if (res && res.success) {
        emit('toast', { msg: res.message || 'Despesa excluída com sucesso.', type: 'success' })
        fetchData()
        fetchWidgets()
        // globalStats must be synced but that's handled at App.vue via interval
        window.dispatchEvent(new Event('sync-global-stats'))
      } else {
        throw new Error(res.message || 'Erro ao excluir.')
      }
    } catch (err) {
      console.error(err)
      emit('toast', { msg: err.message || 'Não foi possível excluir a despesa.', type: 'danger' })
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
    if (!confirm(`Deseja excluir ${selectedRows.value.length} despesa(s)?`)) return
    try {
      const res = await apiFetch(`financeiro/pagamentoDespesas/excluirEmLote`, { 
        method: 'POST',
        body: { ids: selectedRows.value }
      })
      if (res && res.success) {
        emit('toast', { msg: res.message || 'Despesas excluídas.', type: 'success' })
        fetchData()
        fetchWidgets()
        window.dispatchEvent(new Event('sync-global-stats'))
      }
    } catch(e) {
      emit('toast', { msg: 'Erro ao excluir em lote.', type: 'danger' })
    }
  } else if (batchAction.value === 'pagar') {
    // Retirado
  } else if (batchAction.value === 'exportar') {
    emit('toast', { msg: 'Exportando despesas selecionadas...', type: 'info' })
    await baixar('financeiro/pagamentoDespesas/acaoEmLote', toFormData({ acao: 'exportar', pagamentos: selectedRows.value }))
  }

  batchAction.value = ''
  selectedRows.value = []
}

async function baixar(endpoint, corpo = undefined) {
  try {
    await baixarArquivo(endpoint, { method: corpo ? 'POST' : 'GET', body: corpo, nomePadrao: 'pagamentos.csv' })
  } catch (e) {
    emit('toast', { msg: e?.message || 'Não foi possível exportar.', type: 'danger' })
  }
}

function exportCSV() {
  emit('toast', { msg: 'Iniciando download...', type: 'info' })
  void baixar('financeiro/pagamentoDespesas/exportar')
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
