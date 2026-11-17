<template>
  <div class="page-content">
    <!-- Hero: Pesquisa Global da Base de Conhecimento -->
    <div class="knowledge-hero">
      <div class="knowledge-hero-header">
        <div>
          <h2 class="m-0"><i class="bi bi-brain me-2 text-primary"></i>Base de Conhecimento</h2>
          <p class="text-muted m-0">Pesquise argumentos e modelos de documentos em um só lugar.</p>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-primary" @click="$emit('open-modelo-detail', null)">
            <i class="bi bi-file-earmark-plus me-1"></i> Novo Modelo
          </button>
          <button class="btn btn-primary" @click="openArgumentoForm(null)">
            <i class="bi bi-plus me-1"></i> Novo Argumento
          </button>
        </div>
      </div>

      <div class="knowledge-search-box">
        <i class="bi bi-search knowledge-search-icon"></i>
        <input
          type="text"
          class="knowledge-search-input"
          v-model="searchQuery"
          @keyup.enter="realizarBusca"
          placeholder="Pesquisar argumentos ou modelos de documentos (ex: Dano moral)..."
          aria-label="Pesquisar na base de conhecimento"
        >
        <span v-if="searching" class="spinner-border spinner-border-sm text-primary me-2"></span>
        <button v-else-if="searchQuery" type="button" class="knowledge-search-clear" @click="clearSearch" aria-label="Limpar busca" title="Limpar busca">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <p class="knowledge-search-hint" v-if="isSearching">Resultados mais relevantes de cada categoria para "{{ searchQuery }}".</p>
    </div>

    <!-- Resultados da Pesquisa Global -->
    <template v-if="isSearching">
      <div v-if="searching && !searchResults" class="empty-state">
        <div class="spinner"></div>
        <p>Pesquisando na base de conhecimento...</p>
      </div>

      <template v-else-if="searchResults">
        <div v-if="!temResultados" class="alert alert-warning">
          Nenhum resultado encontrado para "{{ searchQuery }}".
        </div>

        <div v-else class="search-results-grid">
          <div class="section-card">
            <div class="section-header">
              <h3 class="m-0 font-weight-bold"><i class="bi bi-lightbulb me-2"></i>Argumentos</h3>
            </div>
            <ul class="widget-list" v-if="searchResults.argumentos?.length">
              <li v-for="arg in searchResults.argumentos" :key="arg.id_argumento" class="p-3 border-bottom d-flex flex-column gap-1">
                <div class="d-flex justify-content-between align-items-start gap-2">
                  <div class="fw-bold">{{ arg.titulo }}</div>
                  <span v-if="arg.avaliacao === 'like'" class="badge bg-primary text-nowrap"><i class="bi bi-hand-thumbs-up-fill"></i></span>
                  <span v-else-if="arg.avaliacao === 'dislike'" class="badge bg-danger text-nowrap"><i class="bi bi-hand-thumbs-down-fill"></i></span>
                </div>
                <div class="small text-muted text-truncate">{{ arg.estrategia_recomendada }}</div>
                <div class="small">
                  <template v-if="arg.processo_id">
                    <a href="#" @click.prevent="$emit('open-detail', arg.processo_id)" class="text-primary">{{ arg.numero_processo ? arg.numero_processo + ' - ' : '' }}{{ arg.processo_titulo }}</a>
                  </template>
                  <template v-else-if="arg.caso_id">
                    <a href="#" @click.prevent="$emit('open-caso-detail', arg.caso_id)" class="text-primary">{{ arg.processo_titulo }}</a>
                  </template>
                  <template v-else>
                    <span class="text-muted"><i class="bi bi-globe"></i> Argumento Geral</span>
                  </template>
                </div>
              </li>
            </ul>
            <div v-else class="p-4 text-center text-muted">Nenhum argumento encontrado.</div>
          </div>

          <div class="section-card">
            <div class="section-header">
              <h3 class="m-0 font-weight-bold"><i class="bi bi-file-earmark-text me-2"></i>Modelos de Documentos</h3>
            </div>
            <ul class="widget-list" v-if="searchResults.modelos?.length">
              <li
                v-for="item in searchResults.modelos" :key="item.id"
                class="p-3 border-bottom d-flex justify-content-between align-items-center"
                role="button" tabindex="0"
                @click="$emit('open-modelo-detail', item.id)"
                @keydown.enter="$emit('open-modelo-detail', item.id)"
                @keydown.space.prevent="$emit('open-modelo-detail', item.id)"
              >
                <strong>{{ item.titulo }}</strong>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge" :class="item.ativo ? 'bg-success' : 'bg-secondary'">{{ item.status }}</span>
                  <FavoritoButton tipo="modelo" :entidade-id="item.id" @toast="m => $emit('toast', m)" />
                </div>
              </li>
            </ul>
            <div v-else class="p-4 text-center text-muted">Nenhum modelo encontrado.</div>
          </div>

          <div class="section-card">
            <div class="section-header">
              <h3 class="m-0 font-weight-bold"><i class="bi bi-diagram-3 me-2"></i>Ramos do Direito</h3>
            </div>
            <div class="section-body p-3" v-if="searchResults.ramos?.length">
              <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-primary" v-for="ramo in searchResults.ramos" :key="ramo.id">{{ ramo.nome }}</span>
              </div>
            </div>
            <div v-else class="p-4 text-center text-muted">Nenhum ramo encontrado.</div>
          </div>
        </div>
      </template>
    </template>

    <!-- Navegação da Base (quando não há pesquisa ativa) -->
    <div v-else class="workspace-split">
      <div class="main-column" style="flex: 2; min-width: 0;">
        <!-- Loading State -->
        <div v-if="loading" class="empty-state">
          <div class="spinner"></div>
          <p>Carregando base de conhecimento...</p>
        </div>

        <template v-else>
          <!-- Sec 1: KPIs -->
          <div class="dashboard-kpis">
            <div class="kpi-card kpi-info">
              <div class="kpi-icon"><i class="bi bi-file-earmark-text"></i></div>
              <div class="kpi-info">
                <span class="kpi-title">Modelos Ativos</span>
                <span class="kpi-value">{{ estatisticas?.modelos?.total || 0 }}</span>
              </div>
            </div>
            <div class="kpi-card kpi-warning">
              <div class="kpi-icon"><i class="bi bi-lightbulb"></i></div>
              <div class="kpi-info">
                <span class="kpi-title">Argumentos</span>
                <span class="kpi-value">{{ estatisticas?.argumentos?.total || 0 }}</span>
              </div>
            </div>
            <div class="kpi-card kpi-danger">
              <div class="kpi-icon"><i class="bi bi-diagram-3"></i></div>
              <div class="kpi-info">
                <span class="kpi-title">Ramos do Direito</span>
                <span class="kpi-value">{{ estatisticas?.ramos?.total || 0 }}</span>
              </div>
            </div>
          </div>

          <!-- Sec 2: Modelos -->
          <div class="section-card mt-4">
            <div class="section-header d-flex justify-content-between align-items-center">
              <h3 class="m-0 font-weight-bold"><i class="bi bi-file-earmark-text me-2"></i>Modelos de Documentos</h3>
              <button class="btn btn-sm btn-outline-primary" @click="$emit('set-view', 'modelos')">Gerenciar todos</button>
            </div>
            <div class="section-body p-0">
              <ul class="widget-list">
                <li
                  v-for="modelo in modelos" :key="modelo.id"
                  class="p-3 border-bottom d-flex justify-content-between align-items-center"
                  role="button" tabindex="0"
                  @click="$emit('open-modelo-detail', modelo.id)"
                  @keydown.enter="$emit('open-modelo-detail', modelo.id)"
                  @keydown.space.prevent="$emit('open-modelo-detail', modelo.id)"
                >
                  <div>
                    <div class="fw-bold">{{ modelo.titulo }}</div>
                    <div class="small text-muted">{{ modelo.ramo_nome || 'Geral' }}</div>
                  </div>
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success" v-if="modelo.status === 'Publicado'">{{ modelo.status }}</span>
                    <span class="badge bg-warning text-dark" v-else>{{ modelo.status }}</span>
                    <FavoritoButton tipo="modelo" :entidade-id="modelo.id" @toast="m => $emit('toast', m)" />
                  </div>
                </li>
                <li v-if="!modelos?.length" class="p-4 text-center text-muted">Nenhum modelo cadastrado.</li>
              </ul>
            </div>
          </div>

          <!-- Sec 3: Banco de Argumentos -->
          <div class="section-card mt-4">
            <div class="section-header argumentos-header">
              <h3 class="m-0 font-weight-bold"><i class="bi bi-lightbulb me-2"></i>Banco de Argumentos IA</h3>
              <div class="argumentos-toolbar">
                <button class="btn btn-primary btn-sm text-nowrap" @click="openArgumentoForm(null)"><i class="bi bi-plus-lg"></i> Novo Argumento Global</button>
                <div class="toolbar-group">
                  <select class="form-control form-select form-select-sm ramo-select" v-model="argumentosRamoFiltro" @change="loadArgumentos(1)">
                    <option value="">Todos os ramos</option>
                    <option v-for="ramo in ramosOptionsFlat" :key="ramo.id" :value="ramo.id">{{ '  '.repeat(ramo.nivel) }}{{ ramo.nivel > 0 ? '↳ ' : '' }}{{ ramo.nome }}</option>
                  </select>
                  <button class="btn btn-ghost btn-sm" @click="activeModal = 'gerenciarRamos'" title="Gerenciar ramos do direito">
                    <i class="bi bi-diagram-3"></i>
                  </button>
                </div>
                <div class="toolbar-group">
                  <input type="text" class="form-control form-control-sm argumentos-search-input" v-model="argumentosSearch" @keyup.enter="loadArgumentos(1)" placeholder="Filtrar argumentos cadastrados...">
                  <button class="btn btn-outline-primary btn-sm" @click="loadArgumentos(1)"><i class="bi bi-search"></i></button>
                </div>
              </div>
            </div>
            <div class="section-body p-0">
              <div v-if="loadingArgumentos" class="p-4 text-center">
                <div class="spinner-border spinner-border-sm text-primary"></div>
              </div>
              <ul v-else class="widget-list">
                <li v-for="arg in argumentos" :key="arg.id_argumento" class="p-3 border-bottom d-flex flex-column gap-2">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <div class="fw-bold" style="font-size: 1.05rem; color: var(--ink);">{{ arg.titulo }}</div>
                      <span v-if="arg.ramo_nome" class="badge bg-light text-dark border mt-1">{{ arg.ramo_nome }}</span>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                      <span v-if="arg.avaliacao === 'like'" class="badge bg-primary"><i class="bi bi-hand-thumbs-up-fill"></i> Bem Sucedido</span>
                      <span v-else-if="arg.avaliacao === 'dislike'" class="badge bg-danger"><i class="bi bi-hand-thumbs-down-fill"></i> Mal Sucedido</span>
                      <span v-else-if="arg.processo_id || arg.caso_id" class="badge border text-muted">Sem Avaliação</span>

                      <button class="btn btn-sm btn-light p-1 border text-muted hover-primary" style="line-height:1;" @click="openArgumentoForm(arg)" title="Editar Argumento">
                        <i class="bi bi-pencil-fill" style="font-size:0.8rem;"></i>
                      </button>
                    </div>
                  </div>
                  <div class="small text-muted mb-1">
                    <template v-if="arg.processo_id">
                      Processo: <a href="#" @click.prevent="$emit('open-detail', arg.processo_id)" class="text-primary">{{ arg.numero_processo ? arg.numero_processo + ' - ' : '' }}{{ arg.processo_titulo }}</a>
                    </template>
                    <template v-else-if="arg.caso_id">
                      Caso: <a href="#" @click.prevent="$emit('open-caso-detail', arg.caso_id)" class="text-primary">{{ arg.processo_titulo }}</a>
                    </template>
                    <template v-else>
                      <span class="text-muted"><i class="bi bi-globe"></i> Vínculo: Nenhum (Argumento Geral)</span>
                    </template>
                  </div>
                  <div class="text-truncate" style="max-height: 3rem; white-space: normal; overflow: hidden; font-size: 0.9rem; color: var(--ink-dim);">
                    {{ arg.estrategia_recomendada }}
                  </div>
                </li>
                <li v-if="!argumentos?.length" class="p-4 text-center text-muted">Nenhum argumento salvo encontrado.</li>
              </ul>

              <!-- Paginação Argumentos -->
              <div class="p-3 border-top d-flex justify-content-between align-items-center" v-if="argumentosPagination.total_pages > 1">
                <span class="small text-muted">Página {{ argumentosPagination.current_page }} de {{ argumentosPagination.total_pages }} (Total: {{ argumentosPagination.total_items }})</span>
                <div class="btn-group">
                  <button class="btn btn-sm btn-outline-secondary" :disabled="argumentosPagination.current_page <= 1" @click="loadArgumentos(argumentosPagination.current_page - 1)">Anterior</button>
                  <button class="btn btn-sm btn-outline-secondary" :disabled="argumentosPagination.current_page >= argumentosPagination.total_pages" @click="loadArgumentos(argumentosPagination.current_page + 1)">Próxima</button>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- Sidebar Widgets -->
      <div class="sidebar-widgets" style="flex: 1; width: auto; min-width: 0;">

        <!-- Painel IA -->
        <div class="widget-card section-card ai-panel">
          <div class="section-body p-3 d-flex gap-3">
            <div class="ai-icon">
              <i class="bi bi-stars text-primary fs-3"></i>
            </div>
            <div>
              <h5 class="text-primary mb-1">Assistente Jurídico</h5>
              <p class="small text-muted m-0">Utilize a busca acima para pesquisar argumentos e modelos para as suas defesas.</p>
            </div>
          </div>
        </div>

        <!-- Meus Favoritos -->
        <div class="widget-card section-card mt-3">
          <div class="section-header d-flex justify-content-between align-items-center">
            <h4 class="m-0"><i class="bi bi-star-fill me-2 text-warning"></i>Meus Favoritos</h4>
            <button class="btn btn-sm btn-light border" title="Atualizar" @click="loadFavoritos">
              <i class="bi bi-arrow-clockwise"></i>
            </button>
          </div>
          <div class="section-body p-0">
            <div v-if="loadingFavoritos" class="p-4 text-center">
              <div class="spinner-border spinner-border-sm text-primary"></div>
            </div>
            <ul v-else class="widget-list">
              <li v-for="fav in favoritos" :key="fav.id" class="p-3 border-bottom d-flex justify-content-between align-items-start gap-2">
                <div
                  class="min-w-0"
                  role="button" tabindex="0"
                  @click="abrirFavorito(fav)"
                  @keydown.enter="abrirFavorito(fav)"
                >
                  <span class="badge bg-light text-dark border me-1">Modelo</span>
                  <div class="small text-truncate" style="max-width: 100%;">{{ fav.titulo || 'Item removido' }}</div>
                </div>
                <button class="btn btn-sm btn-light p-1 border text-muted" title="Remover favorito" @click="removerFavorito(fav.id)">
                  <i class="bi bi-x-lg" style="font-size: 0.75rem;"></i>
                </button>
              </li>
              <li v-if="!favoritos?.length" class="p-4 text-center text-muted">Nenhum favorito salvo ainda.</li>
            </ul>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Formulario Argumento Modal -->
  <FormularioArgumentoModal
    v-if="activeModal === 'formularioArgumento'"
    :argumento-para-editar="editingArgumento"
    @close="activeModal = null; editingArgumento = null"
    @saved="onArgumentosSaved"
    @toast="m => $emit('toast', m)"
  />

  <!-- Gerenciar Ramos do Direito -->
  <GerenciarRamosModal
    v-if="activeModal === 'gerenciarRamos'"
    @close="activeModal = null"
    @updated="onRamosUpdated"
    @toast="m => $emit('toast', m)"
  />
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, reactive, watch } from 'vue'
import { apiFetch, formatDate } from '../../services/api'
import FormularioArgumentoModal from '../../components/modals/Processos/FormularioArgumentoModal.vue'
import GerenciarRamosModal from '../../components/modals/Conhecimento/GerenciarRamosModal.vue'
import FavoritoButton from '../../components/widgets/FavoritoButton.vue'

const emit = defineEmits(['set-view', 'open-detail', 'open-caso-detail', 'open-modelo-detail', 'toast'])

const loading = ref(true)
const searching = ref(false)
const searchQuery = ref('')
const searchResults = ref(null)
const activeModal = ref(null)

const ramos = ref([])
const estatisticas = ref(null)
const modelos = ref([])

const loadingArgumentos = ref(false)
const argumentos = ref([])
const argumentosSearch = ref('')
const argumentosRamoFiltro = ref('')
const ramosArvore = ref([])
const argumentosPagination = reactive({ current_page: 1, total_pages: 1, total_items: 0 })
const editingArgumento = ref(null)

const loadingFavoritos = ref(false)
const favoritos = ref([])

function flattenRamos(nodes, nivel = 0) {
  let out = []
  for (const nodo of nodes || []) {
    out.push({ id: nodo.id, nome: nodo.nome, nivel })
    if (nodo.filhos?.length) {
      out = out.concat(flattenRamos(nodo.filhos, nivel + 1))
    }
  }
  return out
}

const ramosOptionsFlat = computed(() => flattenRamos(ramosArvore.value))

const isSearching = computed(() => searchQuery.value.trim().length > 0)
const temResultados = computed(() => {
  if (!searchResults.value) return false
  return !!(
    searchResults.value.argumentos?.length ||
    searchResults.value.modelos?.length ||
    searchResults.value.ramos?.length
  )
})

function openArgumentoForm(arg) {
  editingArgumento.value = arg
  activeModal.value = 'formularioArgumento'
}

function onArgumentosSaved() {
  loadArgumentos(argumentosPagination.current_page)
}

async function loadData() {
  loading.value = true
  try {
    const res = await apiFetch('conhecimento')
    if (res && res.status === 'success' && res.data) {
      ramos.value = res.data.ramos || []
      estatisticas.value = res.data.estatisticas || null
      modelos.value = res.data.modelos || []
    } else if (res && !res.status) {
      // Se a resposta vier direta (sem envelopamento de status)
      ramos.value = res.ramos || []
      estatisticas.value = res.estatisticas || null
      modelos.value = res.modelos || []
    }
  } catch (error) {
    console.error('Erro ao carregar conhecimento:', error)
    emit('toast', { msg: 'Erro ao carregar base de conhecimento', type: 'danger' })
  } finally {
    loading.value = false
  }
}

async function realizarBusca() {
  clearTimeout(searchDebounce)

  if (!searchQuery.value.trim()) {
    searchResults.value = null
    return
  }

  searching.value = true
  try {
    const res = await apiFetch(`conhecimento/buscar?q=${encodeURIComponent(searchQuery.value)}`)
    if (res && res.success && res.data) {
      searchResults.value = res.data
    } else if (res && res.data) {
       searchResults.value = res.data
    } else {
       searchResults.value = { argumentos: [], modelos: [], ramos: [] }
    }
  } catch (error) {
    console.error('Erro na busca:', error)
    emit('toast', { msg: 'Erro ao pesquisar', type: 'danger' })
    searchResults.value = null
  } finally {
    searching.value = false
  }
}

let searchDebounce = null
watch(searchQuery, (valor) => {
  clearTimeout(searchDebounce)
  if (!valor || !valor.trim()) {
    searchResults.value = null
    return
  }
  searchDebounce = setTimeout(realizarBusca, 400)
})

onBeforeUnmount(() => {
  clearTimeout(searchDebounce)
})

function clearSearch() {
  clearTimeout(searchDebounce)
  searchQuery.value = ''
  searchResults.value = null
}

onMounted(() => {
  loadData()
  loadArgumentos(1)
  loadRamosOptions()
  loadFavoritos()
})

async function loadArgumentos(page = 1) {
  loadingArgumentos.value = true
  try {
    const params = new URLSearchParams({ page: String(page), search: argumentosSearch.value })
    if (argumentosRamoFiltro.value) {
      params.set('ramo_id', argumentosRamoFiltro.value)
    }
    const res = await apiFetch(`conhecimento/argumentos?${params.toString()}`)
    if (res && res.success) {
      argumentos.value = res.data || []
      if (res.pagination) {
        Object.assign(argumentosPagination, res.pagination)
      }
    }
  } catch (error) {
    console.error('Erro ao carregar argumentos:', error)
    emit('toast', { msg: 'Erro ao carregar banco de argumentos', type: 'danger' })
  } finally {
    loadingArgumentos.value = false
  }
}

async function loadRamosOptions() {
  try {
    const res = await apiFetch('conhecimento/ramos')
    ramosArvore.value = Array.isArray(res) ? res : (res?.data || [])
  } catch (error) {
    console.error('Erro ao carregar ramos do direito:', error)
  }
}

async function onRamosUpdated() {
  await loadRamosOptions()
  const idsValidos = new Set(ramosOptionsFlat.value.map(r => r.id))
  if (argumentosRamoFiltro.value && !idsValidos.has(Number(argumentosRamoFiltro.value))) {
    argumentosRamoFiltro.value = ''
  }
  loadArgumentos(1)
}

async function loadFavoritos() {
  loadingFavoritos.value = true
  try {
    const res = await apiFetch('conhecimento/favoritos')
    if (res && res.success) {
      favoritos.value = (res.data || []).filter(f => f.entidade_type === 'modelo')
    }
  } catch (error) {
    console.error('Erro ao carregar favoritos:', error)
  } finally {
    loadingFavoritos.value = false
  }
}

function abrirFavorito(fav) {
  if (!fav.existe) return
  emit('open-modelo-detail', fav.entidade_id)
}

async function removerFavorito(id) {
  try {
    const res = await apiFetch(`conhecimento/favoritos/${id}`, { method: 'DELETE' })
    if (res && res.success) {
      favoritos.value = favoritos.value.filter(f => f.id !== id)
    }
  } catch (error) {
    emit('toast', { msg: 'Erro ao remover favorito.', type: 'danger' })
  }
}
</script>

<style scoped>
.knowledge-hero {
  background: linear-gradient(135deg, rgba(1, 59, 81, 0.06) 0%, rgba(59, 130, 246, 0.06) 100%);
  border: 1px solid rgba(1, 59, 81, 0.1);
  border-radius: var(--radius);
  padding: 20px 24px;
  margin-bottom: 20px;
}

.knowledge-hero-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.knowledge-search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 4px 8px 4px 18px;
  box-shadow: var(--shadow);
}

.knowledge-search-icon {
  color: var(--primary);
  font-size: 1.1rem;
}

.knowledge-search-input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  padding: 12px 4px;
  font-size: 1rem;
  color: var(--ink);
}

.knowledge-search-clear {
  border: none;
  background: none;
  color: var(--ink-muted);
  cursor: pointer;
  padding: 6px 10px;
  border-radius: 50%;
  line-height: 1;
}

.knowledge-search-clear:hover {
  background: rgba(0, 0, 0, 0.06);
  color: var(--ink);
}

.knowledge-search-hint {
  margin: 10px 2px 0;
  font-size: 0.85rem;
  color: var(--ink-muted);
}

.search-results-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.dashboard-kpis {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.kpi-card {
  background: var(--card);
  border-radius: var(--radius);
  padding: 20px;
  display: flex;
  align-items: center;
  box-shadow: var(--shadow);
}

.kpi-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  margin-right: 16px;
}

.kpi-primary .kpi-icon { background: rgba(1, 59, 81, 0.1); color: var(--primary); }
.kpi-success .kpi-icon { background: rgba(22, 163, 74, 0.1); color: var(--success); }
.kpi-info .kpi-icon { background: rgba(59, 130, 246, 0.1); color: var(--info); }
.kpi-warning .kpi-icon { background: rgba(217, 119, 6, 0.1); color: var(--warning); }
.kpi-danger .kpi-icon { background: rgba(220, 38, 38, 0.1); color: var(--danger); }

.kpi-info {
  display: flex;
  flex-direction: column;
}

.kpi-title {
  font-size: 13px;
  color: var(--ink-muted);
  font-weight: 600;
  text-transform: uppercase;
}

.kpi-value {
  font-size: 24px;
  font-weight: 700;
  color: var(--ink);
  line-height: 1.2;
}

.ai-panel {
  background: linear-gradient(135deg, rgba(1, 59, 81, 0.05) 0%, rgba(59, 130, 246, 0.05) 100%);
  border: 1px solid rgba(1, 59, 81, 0.1);
}


.section-header.argumentos-header {
  flex-wrap: wrap;
  row-gap: 10px;
}

.argumentos-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}

.toolbar-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ramo-select {
  min-width: 160px;
  max-width: 220px;
}

.argumentos-search-input {
  min-width: 180px;
  max-width: 260px;
}

@media (max-width: 1024px) {
  .dashboard-kpis {
    grid-template-columns: 1fr 1fr;
  }
  .search-results-grid {
    grid-template-columns: 1fr;
  }
}
</style>
