<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'list')">Saúde</a>
      <i class="bi bi-chevron-right"></i>
      <span>Lista de Contratos</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Contratos</h1>
        <p>Gestão de contratos e prestação de serviços</p>
      </div>
      <button class="btn btn-primary" @click="$emit('open-detail', null)">
        <i class="bi bi-plus-circle"></i> Novo Contrato
      </button>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
      <div class="stat-chip">
        <i class="bi bi-file-earmark-text"></i>
        <span>Total: <strong>{{ stats.total }}</strong></span>
      </div>
      <div class="stat-chip">
        <i class="bi bi-check-circle" style="color:var(--success);"></i>
        <span>Ativos: <strong>{{ stats.ativos }}</strong></span>
      </div>
      <div class="stat-chip">
        <i class="bi bi-exclamation-circle" style="color:var(--warning);"></i>
        <span>Vencendo: <strong>{{ stats.vencendo }}</strong></span>
      </div>
      <div class="stat-chip">
        <i class="bi bi-archive" style="color:var(--ink-muted);"></i>
        <span>Encerrados: <strong>{{ stats.encerrados }}</strong></span>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left">
        <div class="filter-tabs" role="tablist" aria-label="Filtros por status" @keydown="onTabKeydown">
          <button
            v-for="tab in ['ativos', 'vencendo', 'encerrados', 'todos']"
            :key="tab"
            :id="'tab-' + tab"
            :class="['filter-tab', { active: activeTab === tab }]"
            role="tab"
            :aria-selected="activeTab === tab"
            :tabindex="activeTab === tab ? 0 : -1"
            @click="setTab(tab)"
          >
            {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
          </button>
        </div>
      </div>
      <div class="toolbar-right" style="display:flex;gap:8px;align-items:center;">
        <input type="search" class="form-control" v-model.trim="selectedRamo" @change="onRamoChange" style="max-width:220px;" placeholder="Área / especialidade" aria-label="Filtrar por área ou especialidade" />
        <ResponsavelSelect id="filtro_responsavel_contrato" v-model="selectedResponsavel" placeholder="Todos os responsáveis"
          aria-label="Filtrar por responsável" style="max-width:200px;" @update:model-value="onRamoChange" />
      </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid" style="margin-top:1.5rem;">
      <div>
        <div v-if="loading" class="process-list">
          <div class="skeleton-card" v-for="i in 4" :key="i">
            <div class="skeleton-line" style="width: 40%; height: 16px;"></div>
            <div class="skeleton-line" style="width: 80%; height: 12px;"></div>
          </div>
        </div>

        <div v-else-if="!listContratos.length" class="empty-state card">
          <i class="bi bi-file-earmark-text" style="font-size:3rem;color:var(--accent);"></i>
          <h4>Nenhum contrato localizado</h4>
          <p>Tente alterar os termos de busca ou filtros.</p>
        </div>

        <div v-else class="process-list">
          <div
            v-for="contrato in listContratos"
            :key="contrato.id_contrato"
            :class="['process-card', { encerrado: contrato.status === 'encerrado' }]"
            @click.self="$emit('open-detail', contrato.id_contrato)"
            role="button"
            tabindex="0"
            @keydown.enter="$emit('open-detail', contrato.id_contrato)"
            @keydown.space.prevent="$emit('open-detail', contrato.id_contrato)"
            :aria-label="'Ver detalhes do contrato: ' + contrato.numero"
          >
            <div class="process-main" @click="$emit('open-detail', contrato.id_contrato)">
              <a href="#" class="process-cnj" @click.prevent="$emit('open-detail', contrato.id_contrato)">
                <i v-if="contrato.visibilidade === 'publico'" class="bi bi-globe2" style="font-size:.8em; color:var(--ink-muted); margin-right:4px;"
                  title="Público: visível para todos os profissionais" aria-label="Público"></i>
                #{{ contrato.numero_contrato }} - {{ contrato.objeto }}
              </a>
              <div class="process-meta">
                <span><i class="bi bi-person"></i> Cliente: <strong>{{ contrato.cliente_nome }}</strong></span>
                <span><i class="bi bi-diagram-3"></i> Área: <strong>{{ contrato.ramo_direito || contrato.ramo_nome || contrato.area_atuacao || 'Não informado' }}</strong></span>
                <span><i class="bi bi-calendar3"></i> Início: {{ formatDate(contrato.data_inicio) }}</span>
                <span v-if="contrato.valor"><i class="bi bi-cash-coin"></i> Valor: {{ formatCurrency(contrato.valor) }}</span>
                <span v-if="contrato.responsavel_nome"><i class="bi bi-person-check"></i> Responsável: {{ contrato.responsavel_nome }}</span>
              </div>
            </div>

            <div class="process-actions" @click.stop>
              <span :class="['badge', getStatusClass(contrato.status)]" style="margin-bottom:8px;">
                <i :class="getStatusIcon(contrato.status)"></i>
                {{ getStatusLabel(contrato.status) }}
              </span>

              <div class="action-buttons-wrapper" style="display:flex;gap:4px;margin-top:6px;">
                <button class="btn btn-ghost btn-sm" @click="$emit('open-detail', contrato.id_contrato)" title="Editar" aria-label="Editar contrato">
                  <i class="bi bi-pencil-square" style="color:var(--accent);"></i>
                </button>
                <button class="btn btn-ghost btn-sm" @click="confirmDelete(contrato)" title="Excluir" aria-label="Excluir contrato">
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
          <button
            v-for="pg in pagination.total_pages"
            :key="pg"
            :class="['page-btn', { active: pagination.page === pg }]"
            @click="changePage(pg)"
            :aria-label="'Ir para a página ' + pg"
            :aria-current="pagination.page === pg ? 'page' : undefined"
          >
            {{ pg }}
          </button>
          <button class="page-btn" :disabled="pagination.page === pagination.total_pages" @click="changePage(pagination.page + 1)" aria-label="Próxima página">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>

      <div class="sidebar-widgets">
        <div class="card">
          <div class="card-header">
            <h3><i class="bi bi-hourglass-split"></i> Contratos a Vencer</h3>
          </div>
          <ul class="widget-list">
            <li v-for="c in contratosVencendo" :key="c.id_contrato" @click="$emit('open-detail', c.id_contrato)" role="button" tabindex="0">
              <span class="process-num">#{{ c.numero || c.id_contrato }} - {{ c.cliente_nome }}</span>
              <span class="process-date">Vencimento: {{ formatDate(c.data_fim) }}</span>
            </li>
            <li v-if="!contratosVencendo.length" class="text-muted" style="padding:1rem;text-align:center;">
              Nenhum contrato próximo do vencimento.
            </li>
          </ul>
        </div>

        <div class="card" style="margin-top:1.5rem;">
          <div class="card-header">
            <h3 style="color:var(--success);"><i class="bi bi-graph-up-arrow"></i> Mais Rentáveis</h3>
          </div>
          <ul class="widget-list">
            <li v-for="c in contratosRentaveis" :key="c.id_contrato" @click="$emit('open-detail', c.id_contrato)" role="button" tabindex="0">
              <span class="process-num">#{{ c.numero_contrato }} - {{ c.cliente_nome }}</span>
              <span class="process-date" style="color:var(--success);font-weight:bold;">{{ c.saldo_formatado }}</span>
            </li>
            <li v-if="!contratosRentaveis.length" class="text-muted" style="padding:1rem;text-align:center;">
              Nenhum dado disponível.
            </li>
          </ul>
        </div>

        <div class="card" style="margin-top:1.5rem;">
          <div class="card-header">
            <h3 style="color:var(--danger);"><i class="bi bi-graph-down-arrow"></i> Maiores Prejuízos</h3>
          </div>
          <ul class="widget-list">
            <li v-for="c in contratosPrejuizos" :key="c.id_contrato" @click="$emit('open-detail', c.id_contrato)" role="button" tabindex="0">
              <span class="process-num">#{{ c.numero_contrato }} - {{ c.cliente_nome }}</span>
              <span class="process-date" style="color:var(--danger);font-weight:bold;">{{ c.saldo_formatado }}</span>
            </li>
            <li v-if="!contratosPrejuizos.length" class="text-muted" style="padding:1rem;text-align:center;">
              Nenhum dado disponível.
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch, nextTick } from 'vue'
import { apiFetch, formatDate, formatCurrency, debounce } from '../../services/api'
import ResponsavelSelect from '../../components/widgets/Privacidade/ResponsavelSelect.vue'

const props = defineProps({
  searchQuery: { type: String, default: '' }
})

const emit = defineEmits(['open-detail', 'toast', 'set-view'])

const listContratos = ref([])
const contratosVencendo = ref([])
const contratosRentaveis = ref([])
const contratosPrejuizos = ref([])
const loading = ref(true)
const activeTab = ref('ativos')
const selectedRamo = ref('')
const selectedResponsavel = ref('')

const stats = reactive({
  total: 0,
  ativos: 0,
  vencendo: 0,
  encerrados: 0
})

const pagination = reactive({
  page: 1,
  limit: 15,
  total_pages: 0
})

const fetchData = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: String(pagination.page),
      perPage: String(pagination.limit),
      s: props.searchQuery,
      status: activeTab.value !== 'todos' ? activeTab.value : '',
      ramo_id: selectedRamo.value,
      responsavel_id: selectedResponsavel.value
    })
    const res = await apiFetch('contratos?' + params.toString())
    if (res && res.success) {
      listContratos.value = res.data || []
      pagination.total_pages = res.pagination?.total_pages || 0
    }
  } catch (err) {
    emit('toast', { msg: 'Erro ao carregar contratos.', type: 'danger' })
  } finally {
    loading.value = false
  }
}

const onRamoChange = () => {
  pagination.page = 1
  fetchData()
}

const fetchWidgets = async () => {
  try {
    const sRes = await apiFetch('contratos/estatisticas')
    if (sRes.success) Object.assign(stats, sRes.data)

    const vRes = await apiFetch('contratos/proximosAVencer/5')
    if (vRes.success) contratosVencendo.value = vRes.data

    const rRes = await apiFetch('contratos/rentabilidade')
    if (rRes.success) {
      contratosRentaveis.value = rRes.data.mais_rentaveis || []
      contratosPrejuizos.value = rRes.data.maiores_prejuizos || []
    }
  } catch (err) {
    console.error('Erro ao carregar estatísticas de contratos:', err)
  }
}

const setTab = (tab) => {
  activeTab.value = tab
  pagination.page = 1
  fetchData()

  nextTick(() => {
    const activeEl = document.getElementById('tab-' + tab)
    if (activeEl) activeEl.focus()
  })
}

const onTabKeydown = (e) => {
  const tabs = ['ativos', 'vencendo', 'encerrados', 'todos']
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

const changePage = (p) => {
  if (p < 1 || p > pagination.total_pages) return
  pagination.page = p
  fetchData()
}

const getStatusLabel = (s) => ({
  'ativo': 'Ativo',
  'vencendo': 'Vencendo',
  'encerrado': 'Encerrado'
}[s] || s)

const getStatusClass = (s) => ({
  'ativo': 'badge-success',
  'vencendo': 'badge-warning',
  'encerrado': 'badge-muted'
}[s] || 'badge-info')

const getStatusIcon = (s) => ({
  'ativo': 'bi bi-check-circle-fill',
  'vencendo': 'bi bi-exclamation-triangle-fill',
  'encerrado': 'bi bi-archive-fill'
}[s] || 'bi bi-info-circle-fill')

const confirmDelete = async (c) => {
  if (confirm(`Excluir contrato ${c.numero}?`)) {
    try {
      const res = await apiFetch(`contratos/excluir/${c.id_contrato}`, { method: 'POST' })
      if (res.success) {
        emit('toast', { msg: 'Contrato excluído.' })
        fetchData()
        fetchWidgets()
      }
    } catch (err) {
      emit('toast', { msg: 'Erro ao excluir.', type: 'danger' })
    }
  }
}

watch(() => props.searchQuery, debounce(() => {
  pagination.page = 1
  fetchData()
}, 300))

onMounted(() => {
  // Parallelize independent data fetching
  Promise.all([
    fetchData(),
    fetchWidgets(),
  ])
})
</script>
