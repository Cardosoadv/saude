<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'list')">Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <a href="#/crm" @click.prevent="$emit('set-view', 'crm')">CRM</a>
      <i class="bi bi-chevron-right"></i>
      <span>Leads do Site</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Leads do Site</h1>
        <p>Contatos e oportunidades comerciais recebidos através do portal e site institucional</p>
      </div>
      <div class="header-actions">
        <a href="#/crm" class="btn btn-ghost" title="Visualizar Funil de Vendas" @click.prevent="$emit('set-view', 'crm')">
          <i class="bi bi-kanban"></i> Funil de Vendas
        </a>
        <button class="btn btn-ghost" @click="loadLeads(1)" title="Recarregar lista de leads" :disabled="loading">
          <i class="bi bi-arrow-clockwise" :class="{ 'spin-icon': loading }"></i> Atualizar
        </button>
      </div>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
      <div
        class="stat-chip"
        role="button"
        tabindex="0"
        @click="setTab('new')"
        @keydown.enter="setTab('new')"
        :class="{ 'stat-chip-active': activeTab === 'new' }"
        title="Filtrar leads novos"
      >
        <i class="bi bi-envelope-fill" style="color:var(--primary);"></i>
        <span>Novos: <strong>{{ stats.new }}</strong></span>
      </div>
      <div
        class="stat-chip"
        role="button"
        tabindex="0"
        @click="setTab('contacted')"
        @keydown.enter="setTab('contacted')"
        :class="{ 'stat-chip-active': activeTab === 'contacted' }"
        title="Filtrar contatados"
      >
        <i class="bi bi-telephone-outbound-fill" style="color:#2563eb;"></i>
        <span>Contatados: <strong>{{ stats.contacted }}</strong></span>
      </div>
      <div
        class="stat-chip"
        role="button"
        tabindex="0"
        @click="setTab('qualified')"
        @keydown.enter="setTab('qualified')"
        :class="{ 'stat-chip-active': activeTab === 'qualified' }"
        title="Filtrar qualificados"
      >
        <i class="bi bi-star-fill" style="color:var(--accent);"></i>
        <span>Qualificados: <strong>{{ stats.qualified }}</strong></span>
      </div>
      <div
        class="stat-chip"
        role="button"
        tabindex="0"
        @click="setTab('converted')"
        @keydown.enter="setTab('converted')"
        :class="{ 'stat-chip-active': activeTab === 'converted' }"
        title="Filtrar convertidos em oportunidade"
      >
        <i class="bi bi-check-circle-fill" style="color:var(--success);"></i>
        <span>Convertidos: <strong>{{ stats.converted }}</strong></span>
      </div>
      <div
        class="stat-chip"
        role="button"
        tabindex="0"
        @click="setTab('lost')"
        @keydown.enter="setTab('lost')"
        :class="{ 'stat-chip-active': activeTab === 'lost' }"
        title="Filtrar descartados"
      >
        <i class="bi bi-x-circle-fill" style="color:var(--danger);"></i>
        <span>Descartados: <strong>{{ stats.lost }}</strong></span>
      </div>
      <div
        class="stat-chip"
        role="button"
        tabindex="0"
        @click="setTab('all')"
        @keydown.enter="setTab('all')"
        :class="{ 'stat-chip-active': activeTab === 'all' }"
        title="Filtrar todos os leads"
      >
        <i class="bi bi-inbox"></i>
        <span>Total: <strong>{{ stats.total }}</strong></span>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left">
        <!-- Filter Tabs -->
        <div class="filter-tabs" role="tablist" aria-label="Filtros por status do lead" @keydown="onTabKeydown">
          <button
            v-for="tab in filterTabs"
            :key="tab.id"
            :id="'tab-' + tab.id"
            :class="['filter-tab', { active: activeTab === tab.id }]"
            role="tab"
            :aria-selected="activeTab === tab.id"
            :tabindex="activeTab === tab.id ? 0 : -1"
            @click="setTab(tab.id)"
          >
            {{ tab.label }}
          </button>
        </div>
      </div>

      <div class="toolbar-right">
        <!-- Search Box -->
        <div class="search-input-wrap">
          <i class="bi bi-search search-icon"></i>
          <input
            type="text"
            v-model="localSearch"
            class="form-control search-input"
            placeholder="Buscar nome, e-mail, telefone..."
            aria-label="Buscar leads"
            @input="onSearchInput"
            @keyup.enter="loadLeads(1)"
          >
          <button
            v-if="localSearch"
            type="button"
            class="btn-clear"
            @click="clearSearch"
            aria-label="Limpar pesquisa"
            title="Limpar pesquisa"
          >
            <i class="bi bi-x"></i>
          </button>
        </div>

        <!-- Filter Area / Ramo do Direito -->
        <select
          v-model="selectedArea"
          class="form-control form-select select-area"
          aria-label="Filtrar por Área de Interesse"
          @change="loadLeads(1)"
        >
          <option value="">Todas as Áreas</option>
          <option v-for="area in ramosOptions" :key="area.id" :value="String(area.id)">
            {{ area.nome }}
          </option>
        </select>

        <!-- Sort Order -->
        <select
          v-model="selectedSort"
          class="form-control form-select select-sort"
          aria-label="Ordenar leads"
          @change="onSortChange"
        >
          <option value="recentes">Mais recentes</option>
          <option value="antigos">Mais antigos</option>
        </select>

        <!-- Clear filters button if any active -->
        <button
          v-if="hasActiveFilters"
          type="button"
          class="btn btn-ghost btn-sm"
          @click="resetFilters"
          title="Limpar todos os filtros aplicados"
        >
          <i class="bi bi-funnel"></i> Limpar filtros
        </button>
      </div>
    </div>

    <!-- Content Table / Skeletons / Empty State -->
    <div class="content-table-wrapper">
      <!-- Skeletons Loading -->
      <div v-if="loading" class="card p-3">
        <div class="skeleton-card mb-2" v-for="i in 5" :key="i">
          <div class="skeleton-line" style="width: 35%; height: 16px;"></div>
          <div class="skeleton-line" style="width: 70%; height: 12px;"></div>
          <div class="skeleton-line" style="width: 20%; height: 10px;"></div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="!leads.length" class="empty-state card">
        <i class="bi bi-inbox" style="font-size:3rem; color:var(--accent);"></i>
        <h4>Nenhum lead localizado</h4>
        <p>Tente alterar os termos de busca, limpar a pesquisa ou selecionar outro status.</p>
        <div class="mt-3" v-if="hasActiveFilters">
          <button class="btn btn-primary btn-sm" @click="resetFilters">
            <i class="bi bi-arrow-counterclockwise"></i> Restaurar todos os leads
          </button>
        </div>
      </div>

      <!-- Data Table -->
      <div v-else class="card table-card">
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th style="width: 50px;">#</th>
                <th>Lead / Contato</th>
                <th>Telefone</th>
                <th>Área de Interesse</th>
                <th>Mensagem</th>
                <th>Recebido em</th>
                <th>Status</th>
                <th class="col-end" style="width: 140px;">Ações</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="lead in leads"
                :key="lead.id"
                :class="'lead-status-row-' + lead.status"
                @click="openViewModal(lead)"
                style="cursor: pointer;"
              >
                <td class="lead-id-cell">
                  <span class="status-indicator" :class="'indicator-' + lead.status"></span>
                  {{ lead.id }}
                </td>
                <td>
                  <div class="lead-identity">
                    <span class="lead-name">{{ lead.name }}</span>
                    <a
                      :href="'mailto:' + lead.email"
                      class="lead-email"
                      @click.stop
                      title="Enviar e-mail para o lead"
                    >
                      <i class="bi bi-envelope"></i> {{ lead.email }}
                    </a>
                  </div>
                </td>
                <td @click.stop>
                  <div v-if="lead.phone" class="phone-wrapper">
                    <span>{{ formatPhone(lead.phone) }}</span>
                    <a
                      v-if="getWhatsAppLink(lead.phone)"
                      :href="getWhatsAppLink(lead.phone)"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="whatsapp-link"
                      title="Conversar via WhatsApp"
                    >
                      <i class="bi bi-whatsapp"></i>
                    </a>
                  </div>
                  <span v-else class="text-muted">—</span>
                </td>
                <td>
                  <span class="badge badge-tag badge-area">
                    <i class="bi bi-tag-fill me-1"></i>
                    {{ lead.area_interesse_nome || lead.area_interesse || 'Geral' }}
                  </span>
                </td>
                <td>
                  <span class="lead-message-preview" :title="lead.message">
                    {{ truncate(lead.message, 65) }}
                  </span>
                </td>
                <td>
                  <span class="lead-date">
                    <i class="bi bi-clock me-1"></i> {{ formatDate(lead.created_at, true) }}
                  </span>
                </td>
                <td>
                  <span class="badge" :class="getStatusBadgeClass(lead.status)">
                    <i :class="getStatusIcon(lead.status)" class="me-1"></i>
                    {{ getStatusLabel(lead.status) }}
                  </span>
                </td>
                <td class="col-end" @click.stop>
                  <div class="row-actions">
                    <button
                      type="button"
                      class="btn btn-ghost btn-sm"
                      @click="openViewModal(lead)"
                      title="Visualizar Detalhes"
                      aria-label="Visualizar Detalhes"
                    >
                      <i class="bi bi-eye text-primary"></i>
                    </button>
                    <button
                      type="button"
                      class="btn btn-ghost btn-sm"
                      v-if="lead.status !== 'converted'"
                      @click="openConvertModal(lead)"
                      title="Converter em Oportunidade"
                      aria-label="Converter em Oportunidade"
                    >
                      <i class="bi bi-lightning-charge-fill text-success"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Table Footer / Pagination -->
        <div class="table-footer" v-if="totalPages > 1 || totalRecords > 0">
          <div class="pagination-info text-muted">
            Mostrando <strong>{{ leads.length }}</strong> de <strong>{{ totalRecords }}</strong> leads
          </div>

          <div class="pagination" v-if="totalPages > 1" role="navigation" aria-label="Navegação de página">
            <button
              class="page-btn"
              :disabled="currentPage <= 1"
              @click="loadLeads(currentPage - 1)"
              aria-label="Página anterior"
              title="Página anterior"
            >
              <i class="bi bi-chevron-left"></i>
            </button>

            <template v-for="page in visiblePages" :key="page">
              <span v-if="page === '...'" class="page-ellipsis">...</span>
              <button
                v-else
                class="page-btn"
                :class="{ active: page === currentPage }"
                @click="loadLeads(Number(page))"
              >
                {{ page }}
              </button>
            </template>

            <button
              class="page-btn"
              :disabled="currentPage >= totalPages"
              @click="loadLeads(currentPage + 1)"
              aria-label="Próxima página"
              title="Próxima página"
            >
              <i class="bi bi-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Visualizar Detalhes do Lead -->
    <div
      class="modal-backdrop"
      v-if="showViewModal && currentLead"
      @click.self="closeViewModal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="modal-lead-title"
    >
      <div class="modal-box modal-lead-box">
        <div class="modal-header">
          <h2 id="modal-lead-title">
            <i class="bi bi-person-lines-fill"></i> Detalhes do Lead #{{ currentLead.id }}
          </h2>
          <button type="button" class="modal-close" @click="closeViewModal" aria-label="Fechar modal">
            <i class="bi bi-x"></i>
          </button>
        </div>

        <div class="modal-body">
          <!-- Lead Info Grid -->
          <div class="detail-grid">
            <div class="detail-field">
              <label>Nome Completo</label>
              <span>{{ currentLead.name }}</span>
            </div>
            <div class="detail-field">
              <label>E-mail</label>
              <span>
                <a :href="'mailto:' + currentLead.email" class="text-primary">
                  {{ currentLead.email }}
                </a>
              </span>
            </div>
            <div class="detail-field">
              <label>Telefone / WhatsApp</label>
              <span class="d-flex align-items-center gap-2">
                {{ currentLead.phone ? formatPhone(currentLead.phone) : '—' }}
                <a
                  v-if="getWhatsAppLink(currentLead.phone)"
                  :href="getWhatsAppLink(currentLead.phone)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="btn btn-ghost btn-sm text-success"
                  style="padding: 2px 6px;"
                  title="Abrir WhatsApp"
                >
                  <i class="bi bi-whatsapp"></i> Conversar
                </a>
              </span>
            </div>
            <div class="detail-field">
              <label>Área de Interesse</label>
              <span>{{ currentLead.area_interesse_nome || currentLead.area_interesse || 'Geral' }}</span>
            </div>
            <div class="detail-field">
              <label>Data de Recebimento</label>
              <span>{{ formatDate(currentLead.created_at, true) }}</span>
            </div>
            <div class="detail-field">
              <label>Status Atual</label>
              <div class="d-flex align-items-center gap-2 mt-1">
                <span class="badge" :class="getStatusBadgeClass(currentLead.status)">
                  <i :class="getStatusIcon(currentLead.status)" class="me-1"></i>
                  {{ getStatusLabel(currentLead.status) }}
                </span>
                <select
                  class="form-control form-select form-select-sm"
                  style="width: auto; min-width: 140px; height: 30px; font-size: .78rem;"
                  :value="currentLead.status"
                  @change="updateLeadStatus(currentLead.id, ($event.target as HTMLSelectElement).value)"
                  aria-label="Alterar status do lead"
                >
                  <option value="new">Novo</option>
                  <option value="contacted">Contatado</option>
                  <option value="qualified">Qualificado</option>
                  <option value="converted">Convertido</option>
                  <option value="lost">Descartado</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Mensagem Enviada -->
          <div class="mt-3">
            <label class="form-label fw-bold">Mensagem Enviada pelo Lead:</label>
            <div class="lead-message-box">
              {{ currentLead.message }}
            </div>
          </div>

          <!-- LGPD Privacy Info if exists -->
          <div v-if="currentLead.privacidade_aceite_em" class="lgpd-banner mt-3">
            <i class="bi bi-shield-check text-success"></i>
            <span>
              Consentimento LGPD aceito em {{ formatDate(currentLead.privacidade_aceite_em, true) }}
              <template v-if="currentLead.privacidade_versao">(Versão {{ currentLead.privacidade_versao }})</template>
            </span>
          </div>
        </div>

        <div class="modal-footer">
          <button
            type="button"
            class="btn btn-danger"
            v-if="currentLead.status !== 'converted' && currentLead.status !== 'lost'"
            @click="discardLead(currentLead.id)"
          >
            <i class="bi bi-x-circle"></i> Descartar Lead
          </button>
          <button type="button" class="btn btn-ghost" @click="closeViewModal">Fechar</button>
          <button
            type="button"
            class="btn btn-primary"
            v-if="currentLead.status !== 'converted'"
            @click="openConvertModal(currentLead)"
          >
            <i class="bi bi-lightning-charge"></i> Converter em Oportunidade
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Converter em Oportunidade -->
    <div
      class="modal-backdrop"
      v-if="showConvertModal && currentLead"
      @click.self="closeConvertModal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="modal-convert-title"
    >
      <div class="modal-box modal-convert-box">
        <div class="modal-header">
          <h2 id="modal-convert-title">
            <i class="bi bi-lightning-charge text-success"></i> Converter para Oportunidade CRM
          </h2>
          <button type="button" class="modal-close" @click="closeConvertModal" aria-label="Fechar modal">
            <i class="bi bi-x"></i>
          </button>
        </div>

        <div class="modal-body">
          <div class="convert-notice mb-3">
            <i class="bi bi-info-circle-fill text-primary"></i>
            <p class="m-0">
              Ao confirmar a conversão, o lead <strong>{{ currentLead.name }}</strong> será cadastrado como
              <strong>Cliente</strong> e uma nova <strong>Oportunidade</strong> será incluída no seu Funil de Vendas.
            </p>
          </div>

          <form @submit.prevent="submitConvert">
            <div class="form-group">
              <label class="form-label" for="convert-title">Título da Oportunidade *</label>
              <input
                id="convert-title"
                type="text"
                class="form-control"
                v-model="convertForm.titulo"
                required
                placeholder="Ex: Consultoria Tributária - João da Silva"
              >
            </div>

            <div class="form-group">
              <label class="form-label" for="convert-etapa">Etapa Inicial no Funil *</label>
              <select
                id="convert-etapa"
                class="form-control form-select"
                v-model="convertForm.etapa_id"
                required
              >
                <option value="" disabled>Selecione uma etapa do funil</option>
                <option v-for="etapa in etapas" :key="etapa.id" :value="etapa.id">
                  {{ etapa.nome }}
                </option>
              </select>
            </div>
          </form>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" @click="closeConvertModal" :disabled="converting">
            Cancelar
          </button>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="converting"
            @click="submitConvert"
          >
            <i v-if="converting" class="bi bi-arrow-repeat spin-icon"></i>
            <i v-else class="bi bi-check-lg"></i>
            {{ converting ? 'Convertendo...' : 'Confirmar Conversão' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { apiFetch, formatDate as apiFormatDate, formatPhone, truncate } from '../../services/api'

interface Lead {
  id: number
  name: string
  email: string
  phone: string | null
  message: string
  area_interesse: number | string | null
  area_interesse_nome?: string | null
  status: 'new' | 'contacted' | 'qualified' | 'converted' | 'lost'
  created_at: string
  privacidade_aceite_em?: string | null
  privacidade_versao?: string | null
}

interface Etapa {
  id: number
  nome: string
}

interface RamoOption {
  id: number
  nome: string
}

const props = defineProps({
  searchQuery: {
    type: String,
    default: ''
  }
})

const emit = defineEmits<{
  (e: 'toast', payload: { msg: string; type: 'success' | 'danger' | 'warning' | 'info' }): void
  (e: 'set-view', view: string): void
}>()

// State
const leads = ref<Lead[]>([])
const loading = ref(true)
const converting = ref(false)
const localSearch = ref('')
const activeTab = ref('new')
const selectedArea = ref('')
const selectedSort = ref('recentes')
const currentPage = ref(1)
const totalPages = ref(1)
const totalRecords = ref(0)

const stats = ref({
  total: 0,
  new: 0,
  contacted: 0,
  qualified: 0,
  converted: 0,
  lost: 0
})

const etapas = ref<Etapa[]>([])
const ramosOptions = ref<RamoOption[]>([])
const currentLead = ref<Lead | null>(null)

const showViewModal = ref(false)
const showConvertModal = ref(false)

const convertForm = ref({
  titulo: '',
  etapa_id: '' as number | string
})

let debounceTimer: ReturnType<typeof setTimeout> | null = null

const filterTabs = [
  { id: 'new', label: 'Novos' },
  { id: 'contacted', label: 'Contatados' },
  { id: 'qualified', label: 'Qualificados' },
  { id: 'converted', label: 'Convertidos' },
  { id: 'lost', label: 'Descartados' },
  { id: 'all', label: 'Todos' }
]

const hasActiveFilters = computed(() => {
  return activeTab.value !== 'new' || !!localSearch.value || !!selectedArea.value || selectedSort.value !== 'recentes'
})

const visiblePages = computed(() => {
  const pages: (number | string)[] = []
  const maxButtons = 5
  const total = totalPages.value
  const current = currentPage.value

  if (total <= maxButtons) {
    for (let i = 1; i <= total; i++) pages.push(i)
  } else {
    pages.push(1)
    if (current > 3) pages.push('...')

    const start = Math.max(2, current - 1)
    const end = Math.min(total - 1, current + 1)

    for (let i = start; i <= end; i++) pages.push(i)

    if (current < total - 2) pages.push('...')
    pages.push(total)
  }

  return pages
})

// Sync search query from prop
watch(
  () => props.searchQuery,
  (newVal) => {
    if (newVal !== undefined && newVal !== localSearch.value) {
      localSearch.value = newVal
      loadLeads(1)
    }
  }
)

onMounted(async () => {
  if (props.searchQuery) {
    localSearch.value = props.searchQuery
  }
  await Promise.all([fetchEtapas(), fetchRamos()])
  await loadLeads(1)
})

function onSearchInput() {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    loadLeads(1)
  }, 350)
}

function clearSearch() {
  localSearch.value = ''
  loadLeads(1)
}

function onSortChange() {
  // Sort leads client-side or trigger reload
  if (leads.value.length > 1) {
    sortCurrentLeads()
  } else {
    loadLeads(1)
  }
}

function sortCurrentLeads() {
  leads.value.sort((a, b) => {
    const timeA = new Date(a.created_at || 0).getTime()
    const timeB = new Date(b.created_at || 0).getTime()
    return selectedSort.value === 'antigos' ? timeA - timeB : timeB - timeA
  })
}

function setTab(tabId: string) {
  activeTab.value = tabId
  loadLeads(1)
}

function resetFilters() {
  activeTab.value = 'new'
  localSearch.value = ''
  selectedArea.value = ''
  selectedSort.value = 'recentes'
  loadLeads(1)
}

function onTabKeydown(e: KeyboardEvent) {
  const currentIndex = filterTabs.findIndex(t => t.id === activeTab.value)
  if (currentIndex === -1) return

  if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
    e.preventDefault()
    const nextIndex = (currentIndex + 1) % filterTabs.length
    setTab(filterTabs[nextIndex].id)
  } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
    e.preventDefault()
    const prevIndex = (currentIndex - 1 + filterTabs.length) % filterTabs.length
    setTab(filterTabs[prevIndex].id)
  }
}

const formatDate = (dateString: string | null | undefined, withTime = false) => apiFormatDate(dateString, withTime)

function getWhatsAppLink(phone: string | number | null | undefined): string | null {
  if (!phone) return null
  const clean = String(phone).replace(/\D/g, '')
  if (clean.length < 10) return null
  // Prepend country code if missing
  const ddi = clean.startsWith('55') ? clean : '55' + clean
  return `https://wa.me/${ddi}`
}

function getStatusBadgeClass(status: string): string {
  switch (status) {
    case 'new': return 'badge-primary'
    case 'contacted': return 'badge-info'
    case 'qualified': return 'badge-warning'
    case 'converted': return 'badge-success'
    case 'lost': return 'badge-danger'
    default: return 'badge-secondary'
  }
}

function getStatusIcon(status: string): string {
  switch (status) {
    case 'new': return 'bi bi-envelope-fill'
    case 'contacted': return 'bi bi-telephone-outbound-fill'
    case 'qualified': return 'bi bi-star-fill'
    case 'converted': return 'bi bi-check-circle-fill'
    case 'lost': return 'bi bi-x-circle-fill'
    default: return 'bi bi-circle'
  }
}

function getStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    new: 'Novo',
    contacted: 'Contatado',
    qualified: 'Qualificado',
    converted: 'Convertido',
    lost: 'Descartado'
  }
  return labels[status] || status
}

async function fetchEtapas() {
  try {
    const res = await apiFetch('crm/pipeline')
    if (res && res.status === 'success' && res.data) {
      etapas.value = res.data.map((col: any) => col.etapa)
    } else if (res && Array.isArray(res)) {
      etapas.value = res.map((col: any) => col.etapa)
    }
  } catch (e) {
    console.warn('Erro ao carregar etapas do CRM:', e)
  }
}

async function fetchRamos() {
  try {
    const res = await apiFetch('crm/leads/ramos')
    if (res && Array.isArray(res)) {
      ramosOptions.value = res.map((r: any) => ({ id: r.id, nome: r.nome }))
    } else if (res && res.data && Array.isArray(res.data)) {
      ramosOptions.value = res.data.map((r: any) => ({ id: r.id, nome: r.nome }))
    }
  } catch (e) {
    // Fallback silencioso caso a rota de ramos não esteja disponível
    try {
      const fallbackRes = await apiFetch('conhecimento/ramos')
      if (fallbackRes && Array.isArray(fallbackRes)) {
        ramosOptions.value = fallbackRes.map((r: any) => ({ id: r.id, nome: r.nome }))
      }
    } catch {
      // Ignora erro
    }
  }
}

async function loadLeads(page = 1) {
  loading.value = true
  currentPage.value = page

  try {
    const params = new URLSearchParams()
    params.append('page', String(page))
    params.append('limit', '20')

    if (localSearch.value.trim()) {
      params.append('search', localSearch.value.trim())
    }

    if (activeTab.value && activeTab.value !== 'all') {
      params.append('status', activeTab.value)
    }

    if (selectedArea.value) {
      params.append('area_id', selectedArea.value)
    }

    const res = await apiFetch(`crm/leads?${params.toString()}`)

    if (res && res.data) {
      leads.value = res.data

      // Atualiza paginação
      if (res.pager) {
        if (typeof res.pager.pageCount === 'number') {
          totalPages.value = Math.max(1, res.pager.pageCount)
          totalRecords.value = res.pager.total || res.data.length
        } else if (res.meta && res.meta.last_page) {
          totalPages.value = res.meta.last_page
          totalRecords.value = res.meta.total || res.data.length
        } else {
          totalPages.value = res.data.length === 20 ? page + 1 : page
          totalRecords.value = res.data.length
        }
      } else {
        totalPages.value = 1
        totalRecords.value = res.data.length
      }

      // Atualiza stats consolidadas se fornecidas pelo backend
      if (res.stats) {
        stats.value = {
          total: res.stats.total ?? 0,
          new: res.stats.new ?? 0,
          contacted: res.stats.contacted ?? 0,
          qualified: res.stats.qualified ?? 0,
          converted: res.stats.converted ?? 0,
          lost: res.stats.lost ?? 0
        }
      }

      sortCurrentLeads()
    } else if (Array.isArray(res)) {
      leads.value = res
      totalPages.value = 1
      totalRecords.value = res.length
      sortCurrentLeads()
    }
  } catch (e: any) {
    console.error('Erro ao carregar leads:', e)
    emit('toast', { msg: 'Erro ao carregar a lista de leads', type: 'danger' })
  } finally {
    loading.value = false
  }
}

function openViewModal(lead: Lead) {
  currentLead.value = lead
  showViewModal.value = true
}

function closeViewModal() {
  showViewModal.value = false
  currentLead.value = null
}

function openConvertModal(lead?: Lead) {
  const targetLead = lead || currentLead.value
  if (!targetLead) return

  currentLead.value = targetLead
  convertForm.value.titulo = `Interesse - ${targetLead.name}`
  convertForm.value.etapa_id = etapas.value.length > 0 ? etapas.value[0].id : ''

  showViewModal.value = false
  showConvertModal.value = true
}

function closeConvertModal() {
  showConvertModal.value = false
}

async function updateLeadStatus(id: number, newStatus: string) {
  try {
    const res = await apiFetch(`crm/leads/status/${id}`, {
      method: 'PUT',
      body: { status: newStatus }
    })

    if (res && res.success) {
      emit('toast', { msg: `Status alterado para ${getStatusLabel(newStatus)}`, type: 'success' })
      if (currentLead.value && currentLead.value.id === id) {
        currentLead.value.status = newStatus as Lead['status']
      }
      loadLeads(currentPage.value)
    } else {
      throw new Error(res?.messages || res?.message || 'Falha ao atualizar status.')
    }
  } catch (e: any) {
    console.error(e)
    emit('toast', { msg: e.message || 'Erro ao alterar o status.', type: 'danger' })
  }
}

async function discardLead(id: number) {
  if (!confirm('Deseja realmente marcar este lead como Descartado?')) return

  try {
    const res = await apiFetch(`crm/leads/status/${id}`, {
      method: 'PUT',
      body: { status: 'lost' }
    })

    if (res && res.success) {
      emit('toast', { msg: 'Lead marcado como descartado com sucesso.', type: 'success' })
      closeViewModal()
      loadLeads(currentPage.value)
    } else {
      throw new Error(res?.messages || res?.message || 'Erro ao descartar lead.')
    }
  } catch (e: any) {
    console.error(e)
    emit('toast', { msg: e.message || 'Erro ao descartar o lead.', type: 'danger' })
  }
}

async function submitConvert() {
  if (!currentLead.value) return

  if (!convertForm.value.titulo || !convertForm.value.etapa_id) {
    emit('toast', { msg: 'Preencha todos os campos obrigatórios.', type: 'warning' })
    return
  }

  converting.value = true
  try {
    const fd = new FormData()
    fd.append('titulo', convertForm.value.titulo)
    fd.append('etapa_id', String(convertForm.value.etapa_id))

    const res = await apiFetch(`crm/leads/converter/${currentLead.value.id}`, {
      method: 'POST',
      body: fd
    })

    if (res && res.success) {
      emit('toast', { msg: 'Lead convertido em Oportunidade com sucesso!', type: 'success' })
      closeConvertModal()
      loadLeads(currentPage.value)
    } else {
      throw new Error(res?.messages || res?.message || 'Erro ao converter lead.')
    }
  } catch (e: any) {
    console.error(e)
    emit('toast', { msg: e.message || 'Erro ao converter o lead.', type: 'danger' })
  } finally {
    converting.value = false
  }
}
</script>

<style scoped>
.header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.stat-chip {
  cursor: pointer;
  transition: all 0.15s ease;
  user-select: none;
}
.stat-chip:hover {
  background: var(--surface);
  border-color: #c5bfb5;
  transform: translateY(-1px);
}
.stat-chip-active {
  border-color: var(--primary) !important;
  background: var(--primary-lt) !important;
}

.search-input-wrap {
  position: relative;
  min-width: 260px;
  max-width: 340px;
  flex: 1;
}
.search-icon {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--ink-muted);
  font-size: 0.85rem;
  pointer-events: none;
}
.search-input {
  padding-left: 32px !important;
  padding-right: 28px !important;
  height: 38px;
}
.btn-clear {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--ink-muted);
  cursor: pointer;
  font-size: 1rem;
  padding: 0;
}
.btn-clear:hover {
  color: var(--danger);
}

.select-area {
  min-width: 170px;
  max-width: 220px;
  height: 38px;
}
.select-sort {
  min-width: 140px;
  max-width: 160px;
  height: 38px;
}

.content-table-wrapper {
  margin-top: 1rem;
}

.table-card {
  overflow: hidden;
  border-radius: var(--radius);
}

.table-responsive {
  width: 100%;
  overflow-x: auto;
  scrollbar-width: thin;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}
.data-table th {
  text-align: left;
  padding: 12px 18px;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--ink-muted);
  text-transform: uppercase;
  letter-spacing: 0.03em;
  border-bottom: 1px solid var(--border);
  background: var(--surface);
  white-space: nowrap;
}
.data-table td {
  padding: 13px 18px;
  font-size: 0.85rem;
  color: var(--ink-dim);
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.data-table tbody tr:hover {
  background: var(--surface);
}
.data-table tbody tr:last-child td {
  border-bottom: none;
}

/* Indicador de status lateral */
.lead-id-cell {
  position: relative;
  font-weight: 600;
  color: var(--ink-muted);
}
.status-indicator {
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
}
.indicator-new { background: var(--primary); }
.indicator-contacted { background: #2563eb; }
.indicator-qualified { background: var(--accent); }
.indicator-converted { background: var(--success); }
.indicator-lost { background: var(--danger); }

.lead-identity {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.lead-name {
  font-weight: 600;
  color: var(--ink);
  font-size: 0.9rem;
}
.lead-email {
  color: var(--ink-muted);
  font-size: 0.78rem;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.lead-email:hover {
  color: var(--primary);
  text-decoration: underline;
}

.phone-wrapper {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.whatsapp-link {
  color: #16a34a;
  font-size: 1rem;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  transition: transform 0.15s ease;
}
.whatsapp-link:hover {
  transform: scale(1.15);
  color: #15803d;
}

.badge-area {
  background: var(--surface);
  color: var(--ink-dim);
  border: 1px solid var(--border);
  font-weight: 500;
}

.lead-message-preview {
  color: var(--ink-dim);
  font-size: 0.82rem;
  display: block;
  max-width: 320px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.lead-date {
  font-size: 0.78rem;
  color: var(--ink-muted);
  white-space: nowrap;
}

.col-end {
  text-align: right;
}
.row-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 4px;
}

.table-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 20px;
  border-top: 1px solid var(--border);
  background: var(--card);
  flex-wrap: wrap;
  gap: 12px;
}

.pagination-info {
  font-size: 0.82rem;
}

/* Modais */
.modal-lead-box {
  max-width: 680px;
}
.modal-convert-box {
  max-width: 520px;
}

.lead-message-box {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 14px 16px;
  font-size: 0.86rem;
  color: var(--ink);
  line-height: 1.5;
  white-space: pre-wrap;
  max-height: 240px;
  overflow-y: auto;
}

.lgpd-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  font-size: 0.78rem;
  color: #15803d;
}

.convert-notice {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  background: var(--primary-lt);
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.82rem;
  color: var(--ink-dim);
}

.spin-icon {
  animation: spin 1s linear infinite;
  display: inline-block;
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@media (max-width: 992px) {
  .toolbar {
    flex-direction: column;
    align-items: stretch;
  }
  .toolbar-left, .toolbar-right {
    width: 100%;
    margin-left: 0;
  }
  .toolbar-right {
    flex-wrap: wrap;
  }
  .search-input-wrap {
    min-width: 100%;
  }
}
</style>
