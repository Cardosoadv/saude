<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#/dashboard" @click.exact.prevent="$emit('set-view', 'dashboard')">Saúde</a>
      <i class="bi bi-chevron-right"></i>
      <span>Lista de Clientes</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Clientes & CRM</h1>
        <p>Gerenciamento da carteira de clientes e temperatura comercial da Clínica Saúde</p>
      </div>
      <a href="#/clientes/novo" class="btn btn-primary" @click.exact.prevent="$emit('open-detail', null)">
        <i class="bi bi-plus-circle"></i> Novo Cliente
      </a>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
      <div class="stat-chip">
        <i class="bi bi-people"></i>
        <span>Total: <strong>{{ stats.total }}</strong></span>
      </div>
      <div class="stat-chip">
        <i class="bi bi-check-circle" style="color:var(--success);"></i>
        <span>Ativos: <strong>{{ stats.ativos }}</strong></span>
      </div>
      <div class="stat-chip">
        <i class="bi bi-person-fill" style="color:var(--accent);"></i>
        <span>Pessoa Física: <strong>{{ stats.pessoa_fisica }}</strong></span>
      </div>
      <div class="stat-chip">
        <i class="bi bi-building" style="color:var(--warning);"></i>
        <span>Pessoa Jurídica: <strong>{{ stats.pessoa_juridica }}</strong></span>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left">
        <!-- Filter Tabs -->
        <div class="filter-tabs" role="tablist" aria-label="Filtros por status" @keydown="onTabKeydown">
          <button v-for="tab in ['ativos', 'inativos', 'todos']" :key="tab"
            :id="'tab-' + tab"
            :class="['filter-tab', { active: activeTab === tab }]"
            role="tab"
            :aria-selected="activeTab === tab"
            :tabindex="activeTab === tab ? 0 : -1"
            @click="setTab(tab)">
            {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
          </button>
        </div>
      </div>

      <div class="toolbar-right" style="gap:10px;">
        <!-- Filter Type -->
        <select v-model="filters.tipo" @change="fetchData" class="form-control form-select"
          style="min-width:140px; height:38px;">
          <option value="">Todos os Tipos</option>
          <option value="pessoa_fisica">Pessoa Física</option>
          <option value="pessoa_juridica">Pessoa Jurídica</option>
        </select>
        <ResponsavelSelect id="filtro_responsavel_cliente" v-model="filters.responsavel_id" placeholder="Todos os responsáveis"
          aria-label="Filtrar por responsável" style="min-width:180px; height:38px;" @update:model-value="fetchData" />
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
        <div v-else-if="!listClientes.length" class="empty-state card">
          <i class="bi bi-people" style="font-size:3rem;color:var(--accent);"></i>
          <h4>Nenhum cliente localizado</h4>
          <p>Tente alterar os termos de busca ou filtros de status.</p>
        </div>

        <!-- Clients Card List -->
        <div v-else class="process-list">
          <div v-for="cliente in listClientes" :key="cliente.id_cliente"
            :class="['process-card', { encerrado: cliente.ativo == 0 }]"
            @click.self="$emit('open-detail', cliente.id_cliente)"
            role="button"
            tabindex="0"
            @keydown.enter="$emit('open-detail', cliente.id_cliente)"
            @keydown.space.prevent="$emit('open-detail', cliente.id_cliente)"
            :aria-label="'Ver detalhes do cliente: ' + cliente.nome"
          >
            <!-- Card Body contents -->
            <div class="process-main" @click="$emit('open-detail', cliente.id_cliente)">
              <a :href="'#/clientes/editar/' + cliente.id_cliente" class="process-cnj" @click.exact.prevent.stop="$emit('open-detail', cliente.id_cliente)">
                <i v-if="cliente.visibilidade === 'publico'" class="bi bi-globe2" style="font-size:.8em; color:var(--ink-muted); margin-right:4px;"
                  title="Público: visível para todos os profissionais" aria-label="Público"></i>
                {{ cliente.nome }}
              </a>
              <div class="process-title" v-if="cliente.razao_social && cliente.razao_social !== cliente.nome">
                {{ cliente.razao_social }}
              </div>
              <div class="process-meta">
                <span><i class="bi bi-file-earmark-person"></i> Documento: <strong>{{ formatDocument(cliente.documento)
                    }}</strong></span>
                <span><i class="bi bi-envelope"></i> E-mail: {{ cliente.email || '—' }}</span>
                <span><i class="bi bi-telephone"></i> Telefone: {{ formatPhone(cliente.celular || cliente.telefone)
                  }}</span>
                <span><i class="bi bi-person-badge"></i> Tipo: {{ cliente.tipo_cliente === 'J' ? 'Pessoa Jurídica' :
                  'Pessoa Física' }}</span>
                <span v-if="cliente.responsavel_nome"><i class="bi bi-person-check"></i> Responsável: {{ cliente.responsavel_nome }}</span>
              </div>

              <!-- CRM tags -->
              <div class="process-tags" style="margin-top:8px;" v-if="cliente.temperatura || cliente.status_crm || Number(cliente.eh_fornecedor) === 1">
                <span v-if="cliente.status_crm" class="badge badge-secondary badge-tag">
                  CRM: {{ getCrmLabel(cliente.status_crm) }}
                </span>
                <span v-if="Number(cliente.eh_fornecedor) === 1" class="badge badge-secondary badge-tag"
                  title="Mesmo cadastro em Fornecedores">
                  <i class="bi bi-truck"></i> Fornecedor
                </span>
                <span v-if="cliente.temperatura" class="badge badge-tag"
                  :style="{ background: getTemperatureColor(cliente.temperatura), color: '#fff' }">
                  {{ getTemperatureLabel(cliente.temperatura) }}
                </span>
              </div>
            </div>

            <!-- Card Right actions/status -->
            <div class="process-actions" @click.stop>
              <span :class="['badge', cliente.ativo == 1 ? 'badge-success' : 'badge-muted']" style="margin-bottom:8px;">
                <i :class="cliente.ativo == 1 ? 'bi bi-check-circle-fill' : 'bi bi-x-circle-fill'"></i>
                {{ cliente.ativo == 1 ? 'Ativo' : 'Inativo' }}
              </span>

              <div class="action-buttons-wrapper" style="display:flex;gap:4px;margin-top:6px;">
                <button v-if="Number(cliente.eh_fornecedor) !== 1" class="btn btn-ghost btn-sm" style="padding:4px 8px;"
                  @click="abrirTransformar(cliente)" title="Marcar como Fornecedor" aria-label="Marcar como Fornecedor">
                  <i class="bi bi-truck" style="color:var(--accent);"></i>
                </button>
                <a :href="'#/clientes/editar/' + cliente.id_cliente" class="btn btn-ghost btn-sm" style="padding:4px 8px;"
                  @click.exact.prevent="$emit('open-detail', cliente.id_cliente)" title="Editar" aria-label="Editar cliente">
                  <i class="bi bi-pencil-square" style="color:var(--accent);"></i>
                </a>
                <button class="btn btn-ghost btn-sm" style="padding:4px 8px;" @click="confirmDelete(cliente)"
                  title="Excluir" aria-label="Excluir cliente">
                  <i class="bi bi-trash" style="color:var(--danger);"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div class="pagination" v-if="!loading && pagination.total_pages > 1" role="navigation" aria-label="Navegação de página">
          <button class="page-btn" :disabled="pagination.page === 1" @click="changePage(pagination.page - 1)" aria-label="Página anterior">
            <i class="bi bi-chevron-left"></i>
          </button>

          <template v-for="(pg, idx) in displayedPages" :key="idx">
            <span v-if="pg === '...'" class="page-ellipsis">...</span>
            <button v-else :class="['page-btn', { active: pagination.page === pg }]" @click="changePage(pg)" :aria-label="'Ir para a página ' + pg" :aria-current="pagination.page === pg ? 'page' : undefined">
              {{ pg }}
            </button>
          </template>

          <button class="page-btn" :disabled="pagination.page === pagination.total_pages"
            @click="changePage(pagination.page + 1)" aria-label="Próxima página">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>

      <!-- Right Column Sidebar Widgets -->
      <div class="sidebar-widgets">
        <!-- Recent Additions widget -->
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h3><i class="bi bi-clock-history"></i> Adições Recentes</h3>
          </div>
          <ul class="widget-list">
            <li v-if="!recentClientes.length" style="color:var(--ink-muted);font-size:.82rem;">Nenhum cliente cadastrado
              recentemente.</li>
            <li v-for="c in recentClientes" :key="c.id_cliente" style="padding:0;">
              <a :href="'#/clientes/editar/' + c.id_cliente" class="widget-item-link"
                @click.exact.prevent="$emit('open-detail', c.id_cliente)">
                <span class="process-num">{{ c.nome }}</span>
                <span class="process-date">Cadastrado em {{ formatDate(c.created_at) }}</span>
              </a>
            </li>
          </ul>
        </div>

        <!-- Birthdays widget -->
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h3><i class="bi bi-gift"></i> Aniversariantes do Mês</h3>
          </div>
          <ul class="widget-list">
            <li v-if="!birthdays.length" style="color:var(--ink-muted);font-size:.82rem;">Nenhum aniversariante neste
              mês.
            </li>
            <li v-for="c in birthdays" :key="c.id_cliente" style="padding:0;">
              <a :href="'#/clientes/editar/' + c.id_cliente" class="widget-item-link birthday-item"
                @click.exact.prevent="$emit('open-detail', c.id_cliente)">
                <div>
                  <span class="process-num">{{ c.nome }}</span>
                  <span class="process-date"><i class="bi bi-calendar-event"></i> Dia {{ getBirthdayDay(c.data_nascimento)
                    }}</span>
                </div>
                <span class="badge badge-success"
                  style="font-size:.65rem; white-space:nowrap; display:inline-flex; align-items:center; gap:2px;">
                  <i class="bi bi-balloon-fill"></i> Parabéns
                </span>
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Modal Transformar em Fornecedor -->
    <ModalTransformarEmFornecedor
      v-if="selectedClienteParaTransformar"
      :show="showTransformModal"
      :cliente="selectedClienteParaTransformar"
      @close="fecharModalTransformar"
      @toast="$emit('toast', $event)"
      @success="onTransformSuccess"
    />
  </div>
</template>

<script setup lang="ts">
/**
 * View de Listagem de Clientes
 * @version 1.2.0
 */
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue'
import { apiFetch, formatDate, formatDocument, formatPhone, debounce } from '../../services/api'
import ResponsavelSelect from '../../components/widgets/Privacidade/ResponsavelSelect.vue'
import ModalTransformarEmFornecedor from '../../components/modals/Clientes/ModalTransformarEmFornecedor.vue'

const props = defineProps({
  searchQuery: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['open-detail', 'toast', 'set-view', 'open-supplier-detail'])

// Local States
const listClientes = ref([])
const recentClientes = ref([])
const birthdays = ref([])
const loading = ref(true)
const activeTab = ref('ativos')
const showTransformModal = ref(false)
const selectedClienteParaTransformar = ref<any>(null)

function abrirTransformar(cliente: any) {
  selectedClienteParaTransformar.value = cliente
  showTransformModal.value = true
}

function fecharModalTransformar() {
  showTransformModal.value = false
  selectedClienteParaTransformar.value = null
}

function onTransformSuccess(payload: { fornecedorId: number; message: string }) {
  fetchData()
  if (payload.fornecedorId) {
    emit('open-supplier-detail', payload.fornecedorId)
  }
}

const stats = reactive({
  total: 0,
  ativos: 0,
  pessoa_fisica: 0,
  pessoa_juridica: 0
})

const filters = reactive({
  tipo: '',
  responsavel_id: ''
})

const pagination = reactive({
  page: 1,
  limit: 15,
  total_items: 0,
  total_pages: 0
})

// Search debouncer
const debouncedFetch = debounce(() => {
  fetchData()
}, 300)

watch(
  () => props.searchQuery,
  () => {
    pagination.page = 1
    debouncedFetch()
  }
)

// Switch filter tabs
function setTab(tab) {
  activeTab.value = tab
  pagination.page = 1
  fetchData()

  nextTick(() => {
    const activeEl = document.getElementById('tab-' + tab)
    if (activeEl) activeEl.focus()
  })
}

function onTabKeydown(e) {
  const tabs = ['ativos', 'inativos', 'todos']
  const currentIdx = tabs.indexOf(activeTab.value)
  let nextIdx

  switch (e.key) {
    case 'ArrowRight':
      e.preventDefault()
      nextIdx = (currentIdx + 1) % tabs.length
      setTab(tabs[nextIdx])
      break
    case 'ArrowLeft':
      e.preventDefault()
      nextIdx = (currentIdx - 1 + tabs.length) % tabs.length
      setTab(tabs[nextIdx])
      break
    case 'Home':
      e.preventDefault()
      setTab(tabs[0])
      break
    case 'End':
      e.preventDefault()
      setTab(tabs[tabs.length - 1])
      break
  }
}

// Fetch list data targeting API V2 Clientes Controller
async function fetchData() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.append('page', String(pagination.page))
    params.append('perPage', String(pagination.limit))
    if (props.searchQuery) params.append('s', props.searchQuery)
    if (filters.tipo) params.append('tipo', filters.tipo)
    if (filters.responsavel_id) params.append('responsavel_id', filters.responsavel_id)

    // Status tab filters mapping
    if (activeTab.value === 'ativos') {
      params.append('status', 'ativo')
    } else if (activeTab.value === 'inativos') {
      params.append('status', 'inativo')
    }

    const res = await apiFetch('clientes?' + params.toString())
    if (res && res.success) {
      listClientes.value = res.data || []
      if (res.pagination) {
        pagination.total_items = res.pagination.total_items
        pagination.total_pages = res.pagination.total_pages
      }
    } else {
      throw new Error(res.message || 'Erro ao carregar lista.')
    }
  } catch (err) {
    console.error(err)
    emit('toast', { msg: 'Não foi possível carregar a lista de clientes.', type: 'danger' })
  } finally {
    loading.value = false
  }
}

// Fetch general stats and widgets
async function fetchWidgets() {
  try {
    const [statsRes, recentRes, birthdaysRes] = await Promise.all([
      apiFetch('clientes/estatisticas'),
      apiFetch('clientes/recentes'),
      apiFetch('clientes/aniversariantes')
    ])

    if (statsRes && statsRes.success) {
      Object.assign(stats, statsRes.data)
    }
    if (recentRes && recentRes.success) {
      recentClientes.value = recentRes.data || []
    }
    if (birthdaysRes && birthdaysRes.success) {
      birthdays.value = birthdaysRes.data || []
    }
  } catch (err) {
    console.error('Erro ao sincronizar widgets:', err)
  }
}

// Pagination action
function changePage(p) {
  if (p === '...' || p < 1 || p > pagination.total_pages) return
  pagination.page = p
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// Displayed pages in compact pagination (same as Processos list)
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

// Format Helpers
function getBirthdayDay(dateStr) {
  if (!dateStr) return ''
  const parts = dateStr.split('-')
  if (parts.length === 3) return parseInt(parts[2])
  return ''
}

function getTemperatureLabel(temp) {
  if (temp === 'hot') return '🔥 Quente'
  if (temp === 'warm') return '⚖️ Morno'
  if (temp === 'cold') return '❄️ Frio'
  return ''
}

function getTemperatureColor(temp) {
  if (temp === 'hot') return 'var(--danger)'
  if (temp === 'warm') return 'var(--warning)'
  if (temp === 'cold') return 'var(--info)'
  return 'rgba(255,255,255,0.08)'
}

function getCrmLabel(status) {
  if (status === 'lead') return 'Lead'
  if (status === 'prospect' || status === 'opportunity') return 'Oportunidade'
  if (status === 'client') return 'Cliente'
  if (status === 'inactive') return 'Inativo'
  return status || '—'
}

// Delete Client
async function confirmDelete(cliente) {
  if (confirm(`Deseja realmente excluir o cliente "${cliente.nome}"? Esta ação não poderá ser desfeita.`)) {
    try {
      const res = await apiFetch(`clientes/excluir/${cliente.id_cliente}`, { method: 'POST' })
      if (res && res.success) {
        emit('toast', { msg: res.message || 'Cliente excluído com sucesso.', type: 'success' })
        fetchData()
        fetchWidgets()
      } else {
        throw new Error(res.message || 'Erro ao excluir.')
      }
    } catch (err) {
      console.error(err)
      emit('toast', { msg: err.message || 'Não foi possível excluir o cliente.', type: 'danger' })
    }
  }
}

// Watchers
watch(
  () => pagination.page,
  () => {
    fetchData()
  }
)

onMounted(() => {
  // Parallelize independent data fetching
  Promise.all([
    fetchData(),
    fetchWidgets()
  ])
})
</script>

<style scoped>
.widget-item-link {
  display: block;
  padding: 11px 16px;
  text-decoration: none;
  color: inherit;
}

.birthday-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
