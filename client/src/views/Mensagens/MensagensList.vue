<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'dashboard')">Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <span>Mensagens Internas</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Mensagens</h1>
        <p>Suas mensagens internas do sistema e comunicações com a equipe</p>
      </div>
      <button class="btn btn-primary" @click="novaMensagem">
        <i class="bi bi-pencil-square"></i> Nova Mensagem
      </button>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="toolbar-left">
        <!-- Filter Tabs -->
        <div class="filter-tabs" role="tablist" aria-label="Filtro de mensagens" @keydown="handleTabNav">
          <button 
            role="tab"
            :aria-selected="currentFilter === 'todas'"
            :tabindex="currentFilter === 'todas' ? 0 : -1"
            :class="['filter-tab', { active: currentFilter === 'todas' }]" 
            @click="setFilter('todas')"
            ref="tabTodas"
          >Todas</button>
          <button 
            role="tab"
            :aria-selected="currentFilter === 'nao_lidas'"
            :tabindex="currentFilter === 'nao_lidas' ? 0 : -1"
            :class="['filter-tab', { active: currentFilter === 'nao_lidas' }]" 
            @click="setFilter('nao_lidas')"
            ref="tabNaoLidas"
          >Não Lidas</button>
          <button 
            role="tab"
            :aria-selected="currentFilter === 'enviadas'"
            :tabindex="currentFilter === 'enviadas' ? 0 : -1"
            :class="['filter-tab', { active: currentFilter === 'enviadas' }]" 
            @click="setFilter('enviadas')"
            ref="tabEnviadas"
          >Enviadas</button>
        </div>
      </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid" style="margin-top:1.5rem;">
      <!-- Left Column Main Card List -->
      <div style="flex: 1;">
        <!-- Skeletons Loading -->
        <div v-if="loading" class="process-list">
          <div class="skeleton-card" v-for="i in 4" :key="i">
             <div class="skeleton-line" style="width: 40%; height: 16px;"></div>
             <div class="skeleton-line" style="width: 80%; height: 12px;"></div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!mensagens.length" class="empty-state card">
          <i class="bi bi-inbox" style="font-size:3rem;color:var(--border);"></i>
          <h4>Nenhuma mensagem encontrada</h4>
          <p>Você não possui mensagens nesta categoria.</p>
        </div>

        <!-- List -->
        <TransitionGroup v-else tag="div" name="fade" class="process-list">
          <div 
            v-for="msg in mensagens" 
            :key="msg.id" 
            :class="['process-card', { unread: currentFilter !== 'enviadas' && !msg.data_leitura }]"
            role="button"
            tabindex="0"
            :aria-label="'Mensagem: ' + (msg.assunto || 'Sem assunto') + '. De: ' + (currentFilter === 'enviadas' ? msg.destinatario_nome : msg.remetente_nome)"
            @click="abrirMensagem(msg)"
            @keydown.enter="abrirMensagem(msg)"
            @keydown.space.prevent="abrirMensagem(msg)"
            style="cursor: pointer;"
          >
            <div class="process-main" style="flex-direction: row; align-items: center; gap: 16px;">
              <div class="avatar-circle">
                 {{ getInitials(currentFilter === 'enviadas' ? msg.destinatario_nome : msg.remetente_nome) }}
              </div>
              <div style="flex: 1; min-width: 0;">
                <div class="process-title" :style="{ fontWeight: (currentFilter !== 'enviadas' && !msg.data_leitura) ? '700' : '500', color: 'var(--ink)' }">
                  {{ msg.assunto || 'Sem assunto' }}
                </div>
                <div class="process-meta" style="margin-top: 4px;">
                  <span style="font-weight: 600; color: var(--ink);">{{ currentFilter === 'enviadas' ? 'Para: ' + msg.destinatario_nome : msg.remetente_nome }}</span>
                  <span><i class="bi bi-clock"></i> {{ formatDateTime(msg.data_envio) }}</span>
                </div>
                <div class="process-tags" style="margin-top: 8px;">
                  <span style="font-size: 0.85rem; color: var(--ink-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;">
                    {{ msg.conteudo ? msg.conteudo.replace(/<[^>]+>/g, '').substring(0, 100) + '...' : '' }}
                  </span>
                </div>
              </div>
            </div>
            <div class="process-actions" style="display:flex; flex-direction:column; align-items:flex-end; gap:6px;">
               <span v-if="currentFilter !== 'enviadas' && !msg.data_leitura" class="badge badge-warning">
                 Nova
               </span>
               <span
                 v-if="currentFilter !== 'enviadas' && !msg.data_leitura"
                 :class="['badge', urgenciaBadgeClass(urgencias[msg.id]?.urgencia)]"
                 :title="urgencias[msg.id]?.justificativa || 'Classificação de urgência por IA'"
               >
                 <i class="bi bi-robot" style="font-size:0.8em;"></i>
                 {{ urgencias[msg.id] ? urgencias[msg.id].urgencia : 'Analisando...' }}
               </span>
            </div>
          </div>
        </TransitionGroup>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import { apiFetch, formatDate } from '../../services/api'

const emit = defineEmits(['navigate', 'set-view'])

const mensagens = ref([])
const loading = ref(false)
const currentFilter = ref('todas')
const currentPage = ref(1)
const urgencias = ref({})

const tabTodas = ref(null)
const tabNaoLidas = ref(null)
const tabEnviadas = ref(null)

const setFilter = (f) => {
  currentFilter.value = f
  currentPage.value = 1
  loadMensagens()
  nextTick(() => {
    const el = document.getElementById('tab-' + f)
    if (el) el.focus()
  })
}

const onFilterKeydown = (e) => {
  const filters = ['todas', 'nao_lidas', 'enviadas']
  const idx = filters.indexOf(currentFilter.value)
  let nextIdx

  if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
    e.preventDefault()
    nextIdx = (idx + 1) % filters.length
    setFilter(filters[nextIdx])
  } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
    e.preventDefault()
    nextIdx = (idx - 1 + filters.length) % filters.length
    setFilter(filters[nextIdx])
  } else if (e.key === 'Home') {
    e.preventDefault()
    setFilter(filters[0])
  } else if (e.key === 'End') {
    e.preventDefault()
    setFilter(filters[filters.length - 1])
  }
}

const handleTabNav = (e) => {
  const filters = ['todas', 'nao_lidas', 'enviadas']
  const currentIndex = filters.indexOf(currentFilter.value)

  if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) {
    e.preventDefault()
    let nextIndex = currentIndex

    if (e.key === 'ArrowRight') nextIndex = (currentIndex + 1) % filters.length
    else if (e.key === 'ArrowLeft') nextIndex = (currentIndex - 1 + filters.length) % filters.length
    else if (e.key === 'Home') nextIndex = 0
    else if (e.key === 'End') nextIndex = filters.length - 1

    setFilter(filters[nextIndex])

    nextTick(() => {
      if (currentFilter.value === 'todas') tabTodas.value?.focus()
      else if (currentFilter.value === 'nao_lidas') tabNaoLidas.value?.focus()
      else if (currentFilter.value === 'enviadas') tabEnviadas.value?.focus()
    })
  }
}

const loadMensagens = async () => {
  loading.value = true
  try {
    const res = await apiFetch(`mensagens?filter=${currentFilter.value}&page=${currentPage.value}`)
    if (res && res.success) {
      mensagens.value = res.data
      classificarUrgenciasNaoLidas()
    }
  } catch (err) {
    console.error('Erro ao carregar mensagens:', err)
  } finally {
    loading.value = false
  }
}

// Classifica em segundo plano, via IA, a urgência das mensagens não lidas exibidas na lista.
// Restrito às não lidas para evitar chamadas desnecessárias de IA em mensagens já tratadas.
const classificarUrgenciasNaoLidas = () => {
  if (currentFilter.value === 'enviadas') return

  const pendentes = mensagens.value.filter(msg => !msg.data_leitura && !urgencias.value[msg.id])

  pendentes.forEach(async (msg) => {
    try {
      const res = await apiFetch(`mensagens/classificarUrgencia/${msg.id}`)
      if (res && res.success) {
        urgencias.value = { ...urgencias.value, [msg.id]: res.data }
      }
    } catch (err) {
      console.error(`Erro ao classificar urgência da mensagem ${msg.id}:`, err)
    }
  })
}

const urgenciaBadgeClass = (urgencia) => {
  switch (urgencia) {
    case 'Urgente': return 'badge-danger'
    case 'Baixa': return 'badge-muted'
    case 'Normal':
    default: return 'badge-info'
  }
}

const abrirMensagem = (msg) => {
  window.location.hash = `#/mensagens/detalhes/${msg.id}`
}

const novaMensagem = () => {
  window.location.hash = `#/mensagens/nova`
}

const formatDateTime = (ds) => {
  return formatDate(ds, true)
}

const getInitials = (name) => {
  if (!name) return '?'
  return name.substring(0, 2).toUpperCase()
}

onMounted(() => {
  loadMensagens()
})
</script>

<style scoped>
.avatar-circle {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: var(--primary);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 1.1rem;
  flex-shrink: 0;
}
.process-card.unread {
  background: #f8fafc;
  border-left: 4px solid var(--primary);
}
</style>
