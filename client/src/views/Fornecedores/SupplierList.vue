<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'list')">Saúde</a>
      <i class="bi bi-chevron-right"></i>
      <span>Lista de Fornecedores</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Fornecedores</h1>
        <p>Gerenciamento de parceiros e fornecedores da Clínica Saúde</p>
      </div>
      <button class="btn btn-primary" @click="$emit('open-detail', null)">
        <i class="bi bi-plus-circle"></i> Novo Fornecedor
      </button>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
      <div class="stat-chip">
        <i class="bi bi-check-circle" style="color:var(--success);"></i>
        <span>Ativos: <strong>{{ stats.ativos }}</strong></span>
      </div>
      <div class="stat-chip">
        <i class="bi bi-tags" style="color:var(--accent);"></i>
        <span>Categorias: <strong>{{ stats.categorias }}</strong></span>
      </div>
      <div class="stat-chip">
        <i class="bi bi-truck"></i>
        <span>Total: <strong>{{ stats.total }}</strong></span>
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

      <div class="toolbar-right">
        <!-- Filter Category -->
        <select v-model="filters.categoria" @change="fetchData" class="form-control form-select"
          style="min-width:160px; height:38px;">
          <option value="">Todas Categorias</option>
          <option v-for="cat in categoriasDisponiveis" :key="cat" :value="cat">{{ cat }}</option>
        </select>
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
        <div v-else-if="!listFornecedores.length" class="empty-state card">
          <i class="bi bi-truck" style="font-size:3rem;color:var(--accent);"></i>
          <h4>Nenhum fornecedor localizado</h4>
          <p>Tente alterar os termos de busca ou filtros.</p>
        </div>

        <!-- Suppliers Card List -->
        <div v-else class="process-list">
          <div v-for="fornecedor in listFornecedores" :key="fornecedor.id_fornecedor"
            :class="['process-card', { encerrado: fornecedor.ativo == 0 }]"
            @click.self="$emit('open-detail', fornecedor.id_fornecedor)">
            <!-- Card Body contents -->
            <div class="process-main" @click="$emit('open-detail', fornecedor.id_fornecedor)">
              <a href="#" class="process-cnj" @click.prevent="$emit('open-detail', fornecedor.id_fornecedor)">
                {{ fornecedor.nome }}
              </a>
              <span v-if="fornecedor.eh_cliente" class="badge badge-secondary" style="margin-left:6px;"
                title="Mesmo cadastro em Clientes/CRM">
                <i class="bi bi-person-check"></i> Também é cliente
              </span>
              <div class="process-title" v-if="fornecedor.razao_social && fornecedor.razao_social !== fornecedor.nome">
                {{ fornecedor.razao_social }}
              </div>
              <div class="process-meta">
                <span><i class="bi bi-file-earmark-person"></i> CNPJ/CPF: <strong>{{
                  formatDocument(fornecedor.documento) }}</strong></span>
                <span><i class="bi bi-envelope"></i> E-mail: {{ fornecedor.email || '—' }}</span>
                <span><i class="bi bi-telephone"></i> Telefone: {{ formatPhone(fornecedor.telefone) }}</span>
                <span><i class="bi bi-tag"></i> Categoria: {{ fornecedor.categoria || 'Não definida' }}</span>
              </div>
            </div>

            <!-- Card Right actions/status -->
            <div class="process-actions" @click.stop>
              <span :class="['badge', fornecedor.ativo == 1 ? 'badge-success' : 'badge-muted']"
                style="margin-bottom:8px;">
                <i :class="fornecedor.ativo == 1 ? 'bi bi-check-circle-fill' : 'bi bi-x-circle-fill'"></i>
                {{ fornecedor.ativo == 1 ? 'Ativo' : 'Inativo' }}
              </span>

              <div class="action-buttons-wrapper" style="display:flex;gap:4px;margin-top:6px;">
                <button class="btn btn-ghost btn-sm" style="padding:4px 8px;"
                  @click="abrirTransformar(fornecedor)" title="Transformar em Cliente" aria-label="Transformar em Cliente">
                  <i class="bi bi-arrow-left-right" style="color:var(--accent);"></i>
                </button>
                <button class="btn btn-ghost btn-sm" style="padding:4px 8px;"
                  @click="$emit('open-detail', fornecedor.id_fornecedor)" title="Editar">
                  <i class="bi bi-pencil-square" style="color:var(--accent);"></i>
                </button>
                <button class="btn btn-ghost btn-sm" style="padding:4px 8px;" @click="confirmDelete(fornecedor)"
                  title="Excluir">
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
            <button v-else :class="['page-btn', { active: pagination.page === pg }]" @click="changePage(pg)">
              {{ pg }}
            </button>
          </template>

          <button class="page-btn" :disabled="pagination.page === pagination.total_pages"
            @click="changePage(pagination.page + 1)">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>

      <!-- Right Column Sidebar Widgets -->
      <div class="sidebar-widgets">
        <!-- Recent Additions widget -->
        <div class="card">
          <div class="card-header">
            <h3><i class="bi bi-clock-history"></i> Adições Recentes</h3>
          </div>
          <ul class="widget-list">
            <li v-if="!recentFornecedores.length" style="color:var(--ink-muted);font-size:.82rem;">Nenhum fornecedor
              cadastrado recentemente.</li>
            <li v-for="f in recentFornecedores" :key="f.id_fornecedor" role="button" tabindex="0"
              @click="$emit('open-detail', f.id_fornecedor)" @keydown.enter="$emit('open-detail', f.id_fornecedor)"
              @keydown.space.prevent="$emit('open-detail', f.id_fornecedor)">
              <span class="process-num">{{ f.nome }}</span>
              <span class="process-date">{{ f.categoria }} • Cadastrado em {{ formatDate(f.created_at) }}</span>
            </li>
          </ul>
        </div>

        <!-- Help Widget -->
        <div class="card">
          <div class="card-header">
            <h3><i class="bi bi-info-circle"></i> Gestão de Fornecedores</h3>
          </div>
          <div style="padding:16px 18px; color:var(--ink-muted); font-size:0.82rem; line-height:1.5;">
            <p>Mantenha os dados de seus fornecedores atualizados para facilitar a emissão de notas fiscais e o controle
              financeiro de despesas da clínica.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Transformar em Cliente -->
    <ModalTransformarEmCliente
      v-if="selectedFornecedorParaTransformar"
      :show="showTransformModal"
      :fornecedor="selectedFornecedorParaTransformar"
      @close="fecharModalTransformar"
      @toast="$emit('toast', $event)"
      @success="onTransformSuccess"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue'
import { apiFetch, formatDate, formatDocument, formatPhone, debounce } from '../../services/api'
import ModalTransformarEmCliente from '../../components/modals/Fornecedores/ModalTransformarEmCliente.vue'

const props = defineProps({
  searchQuery: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['open-detail', 'toast', 'set-view', 'open-client-detail'])

// Local States
const listFornecedores = ref([])
const recentFornecedores = ref([])
const loading = ref(true)
const activeTab = ref('ativos')
const showTransformModal = ref(false)
const selectedFornecedorParaTransformar = ref<any>(null)

function abrirTransformar(fornecedor: any) {
  selectedFornecedorParaTransformar.value = fornecedor
  showTransformModal.value = true
}

function fecharModalTransformar() {
  showTransformModal.value = false
  selectedFornecedorParaTransformar.value = null
}

function onTransformSuccess(payload: { clienteId: number; message: string }) {
  fetchData()
  if (payload.clienteId) {
    emit('open-client-detail', payload.clienteId)
  }
}

const categoriasDisponiveis = ['Tecnologia', 'Papelaria', 'Serviços Gerais', 'Consultoria', 'Marketing', 'Aluguel', 'Energia', 'Telecomunicações']

const stats = reactive({
  total: 0,
  ativos: 0,
  categorias: 0
})

const filters = reactive({
  categoria: ''
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
function setTab(tab: string) {
  activeTab.value = tab
  pagination.page = 1
  fetchData()

  nextTick(() => {
    const activeEl = document.getElementById('tab-' + tab)
    if (activeEl) activeEl.focus()
  })
}

function onTabKeydown(e: KeyboardEvent) {
  const tabs = ['ativos', 'inativos', 'todos']
  const currentIdx = tabs.indexOf(activeTab.value)
  let nextIdx: number

  switch (e.key) {
    case 'ArrowRight':
    case 'ArrowDown':
      e.preventDefault()
      nextIdx = (currentIdx + 1) % tabs.length
      setTab(tabs[nextIdx])
      break
    case 'ArrowLeft':
    case 'ArrowUp':
      e.preventDefault()
      nextIdx = (currentIdx - 1 + tabs.length) % tabs.length
      setTab(tabs[nextIdx])
      break
  }
}

// Fetch list data
async function fetchData() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.append('page', String(pagination.page))
    params.append('perPage', String(pagination.limit))
    if (props.searchQuery) params.append('s', props.searchQuery)
    if (filters.categoria) params.append('categoria', filters.categoria)

    if (activeTab.value === 'ativos') {
      params.append('status', 'ativo')
    } else if (activeTab.value === 'inativos') {
      params.append('status', 'inativo')
    }

    const res = await apiFetch('fornecedores?' + params.toString())
    if (res && res.success) {
      listFornecedores.value = res.data || []
      pagination.total_items = res.pagination?.total_items || res.data.length
      pagination.total_pages = res.pagination?.total_pages || 1
    }
  } catch (err) {
    console.error(err)
    emit('toast', { msg: 'Não foi possível carregar a lista de fornecedores.', type: 'danger' })
  } finally {
    loading.value = false
  }
}

async function fetchWidgets() {
  try {
    const res = await apiFetch('fornecedores/estatisticas')
    if (res && res.success) Object.assign(stats, res.data)

    const recentRes = await apiFetch('fornecedores/recentes')
    if (recentRes && recentRes.success) recentFornecedores.value = recentRes.data || []
  } catch (err) {
    console.error('Erro ao sincronizar widgets:', err)
  }
}

function changePage(p) {
  if (p === '...' || p < 1 || p > pagination.total_pages) return
  pagination.page = p
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
    if (Math.abs(i - current) <= delta) range.push(i)
  }
  if (total > 1) range.push(total)
  const result = []
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

async function confirmDelete(fornecedor) {
  if (confirm(`Deseja realmente excluir o fornecedor "${fornecedor.nome}"?`)) {
    try {
      const res = await apiFetch(`fornecedores/excluir/${fornecedor.id_fornecedor}`, { method: 'POST' })
      if (res && res.success) {
        emit('toast', { msg: 'Fornecedor excluído com sucesso.', type: 'success' })
        fetchData()
        fetchWidgets()
      }
    } catch (err) {
      emit('toast', { msg: 'Não foi possível excluir o fornecedor.', type: 'danger' })
    }
  }
}

watch(() => pagination.page, fetchData)

onMounted(() => {
  fetchData()
  fetchWidgets()
})
</script>

<style scoped>
.widget-empty {
  padding: 1rem 0;
  text-align: center;
  color: var(--ink-muted);
  font-size: 0.85rem;
}
</style>
