<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="voltar">{{ entidadeLabel || 'Voltar' }}</a>
      <i class="bi bi-chevron-right"></i>
      <span>Documento gerado</span>
    </div>

    <div v-if="loading" class="empty-state card">
      <div class="spinner"></div>
      <p>Carregando documento...</p>
    </div>

    <template v-else>
      <div class="page-header">
        <div style="flex:1; min-width: 260px;">
          <input
            type="text" class="titulo-input" v-model="form.titulo"
            :readonly="readonly" @input="onDirtyChange"
          >
          <div class="origem-line">
            <span class="badge" :class="documento.status === 'Finalizado' ? 'badge-success' : 'badge-warning'">
              {{ documento.status }}
            </span>
            <span v-if="ramoNome" class="badge bg-light text-dark border">{{ ramoNome }}</span>
            <span class="text-muted small">
              Gerado a partir de: <strong>{{ documento.modelo_titulo_snapshot || 'Modelo removido' }}</strong>
              · <a href="#" @click.prevent="voltar">{{ entidadeLabel }}</a>
            </span>
          </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
          <span v-if="salvandoLabel" class="text-muted small align-self-center">{{ salvandoLabel }}</span>
          <button v-if="documento.status === 'Rascunho'" class="btn btn-outline-danger" @click="excluirRascunho">
            <i class="bi bi-trash"></i> Excluir
          </button>
          <button v-if="documento.status === 'Rascunho'" class="btn btn-outline-primary" :disabled="salvando" @click="salvarManual">
            <i class="bi bi-save"></i> Salvar rascunho
          </button>
          <a v-if="documento.drive_link" :href="documento.drive_link" target="_blank" rel="noopener" class="btn btn-outline-secondary">
            <i class="bi bi-download"></i> Baixar PDF
          </a>
          <button v-if="documento.status === 'Rascunho'" class="btn btn-primary" :disabled="finalizando" @click="finalizarDocumento">
            <i class="bi bi-check2-circle"></i> {{ finalizando ? 'Finalizando...' : 'Finalizar documento' }}
          </button>
        </div>
      </div>

      <div class="editor-layout">
        <!-- Editor -->
        <div class="editor-main">
          <DocumentoEditor
            v-model="form.conteudo_html"
            :placeholder-catalog="placeholderCatalog"
            :readonly="readonly"
            :page-frame="true"
            @dirty="onDirtyChange"
            @save-shortcut="salvarManual"
          />
        </div>

        <!-- Painel lateral -->
        <div class="editor-sidebar">
          <div class="card mb-3">
            <div class="card-header">
              <h4 class="m-0"><i class="bi bi-tags"></i> Etiquetas</h4>
            </div>
            <div class="card-body">
              <button type="button" class="btn btn-outline-secondary btn-sm mb-2" @click="showEtiquetaModal = true">
                <i class="bi bi-tags"></i> Gerenciar etiquetas
              </button>
              <div class="d-flex flex-wrap gap-1">
                <span
                  v-for="tag in selectedTags" :key="tag.id"
                  class="badge" :style="{ background: '#' + tag.cor, color: '#fff' }"
                >{{ tag.nome }}</span>
                <span v-if="!selectedTags.length" class="text-muted small">Nenhuma etiqueta.</span>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header collapsible" @click="painelPlaceholdersAberto = !painelPlaceholdersAberto">
              <h4 class="m-0"><i class="bi bi-list-check"></i> Campos utilizados</h4>
              <i class="bi" :class="painelPlaceholdersAberto ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
            </div>
            <div class="card-body" v-if="painelPlaceholdersAberto">
              <div v-if="!placeholdersAgrupados.length" class="text-muted small">
                Nenhum campo foi resolvido na geração deste documento.
              </div>
              <div v-else v-for="grupo in placeholdersAgrupados" :key="grupo.origem" class="mb-3">
                <div class="text-uppercase small fw-bold text-muted mb-1">{{ grupo.origem }}</div>
                <ul class="list-unstyled m-0">
                  <li v-for="item in grupo.itens" :key="item.campo" class="mb-1 small">
                    <code>{{ item.campo }}</code>: {{ item.valor || '—' }}
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <EtiquetaModal
      v-if="showEtiquetaModal"
      :selected-tags="selectedTags"
      :available-tags="availableTags"
      @toggle-tag="toggleTag"
      @close="showEtiquetaModal = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { apiFetch } from '../../services/api'
import DocumentoEditor from '../../components/editor/DocumentoEditor.vue'
import EtiquetaModal from '../../components/modals/Processos/EtiquetaModal.vue'

const props = defineProps({
  documentoGeradoId: { type: [Number, String], required: true }
})

const emit = defineEmits(['toast', 'set-view', 'open-detail', 'open-caso-detail'])

const loading = ref(true)
const salvando = ref(false)
const finalizando = ref(false)
const dirty = ref(false)
const lastSavedAt = ref(null)
const entidadeLabel = ref('')
const ramos = ref([])
const availableTags = ref([])
const selectedTags = ref([])
const showEtiquetaModal = ref(false)
const painelPlaceholdersAberto = ref(true)
const placeholderCatalog = ref({})

const documento = reactive({
  id: null,
  modelo_id: null,
  modelo_titulo_snapshot: '',
  entidade_tipo: '',
  entidade_id: null,
  ramo_id: null,
  status: 'Rascunho',
  drive_link: null,
  placeholders_utilizados: null
})

const form = reactive({
  titulo: '',
  conteudo_html: ''
})

const readonly = computed(() => documento.status === 'Finalizado')

const ramoNome = computed(() => {
  if (!documento.ramo_id) return ''
  const ramo = ramos.value.find(r => r.id === documento.ramo_id)
  return ramo ? ramo.nome : ''
})

const salvandoLabel = computed(() => {
  if (salvando.value) return 'Salvando...'
  if (lastSavedAt.value) {
    const hh = String(lastSavedAt.value.getHours()).padStart(2, '0')
    const mm = String(lastSavedAt.value.getMinutes()).padStart(2, '0')
    return `Salvo às ${hh}:${mm}`
  }
  return ''
})

const placeholdersUtilizados = computed(() => {
  const raw = documento.placeholders_utilizados
  if (!raw) return {}
  if (typeof raw === 'object') return raw
  try {
    return JSON.parse(raw)
  } catch {
    return {}
  }
})

const placeholdersAgrupados = computed(() => {
  const grupos: Record<string, any[]> = {}
  for (const [chave, valor] of Object.entries(placeholdersUtilizados.value)) {
    const [origem, campo] = chave.split('.')
    if (!grupos[origem]) grupos[origem] = []
    grupos[origem].push({ campo, valor })
  }
  return Object.entries(grupos).map(([origem, itens]) => ({ origem, itens }))
})

let saveTimeout = null
let saveInFlight = false
let saveQueued = false

async function executarSalvar() {
  if (readonly.value) return
  if (saveInFlight) {
    saveQueued = true
    return
  }
  saveInFlight = true
  salvando.value = true
  try {
    await apiFetch(`conhecimento/documentos-gerados/${props.documentoGeradoId}`, {
      method: 'PUT',
      body: { conteudo_html: form.conteudo_html, titulo: form.titulo }
    })
    dirty.value = false
    lastSavedAt.value = new Date()
  } catch (err) {
    console.error('Erro ao salvar rascunho:', err)
    emit('toast', { msg: 'Não foi possível salvar o rascunho.', type: 'danger' })
  } finally {
    salvando.value = false
    saveInFlight = false
    if (saveQueued) {
      saveQueued = false
      executarSalvar()
    }
  }
}

function agendarAutosave() {
  if (saveTimeout) clearTimeout(saveTimeout)
  saveTimeout = setTimeout(executarSalvar, 3000)
}

function salvarManual() {
  if (readonly.value) return
  if (saveTimeout) {
    clearTimeout(saveTimeout)
    saveTimeout = null
  }
  executarSalvar()
}

function onDirtyChange() {
  if (readonly.value) return
  dirty.value = true
  agendarAutosave()
}

async function toggleTag(tag) {
  const idx = selectedTags.value.findIndex(t => t.id === tag.id)
  if (idx >= 0) selectedTags.value.splice(idx, 1)
  else selectedTags.value.push(tag)

  try {
    await apiFetch(`conhecimento/documentos-gerados/${props.documentoGeradoId}`, {
      method: 'PUT',
      body: { etiqueta_ids: selectedTags.value.map(t => t.id) }
    })
  } catch (err) {
    console.error('Erro ao salvar etiquetas:', err)
    emit('toast', { msg: 'Não foi possível salvar as etiquetas.', type: 'danger' })
  }
}

async function finalizarDocumento() {
  if (!confirm('Finalizar este documento? O conteúdo será travado e um PDF será gerado e enviado ao Drive. Esta ação não pode ser desfeita.')) return

  finalizando.value = true
  try {
    if (dirty.value) {
      if (saveTimeout) { clearTimeout(saveTimeout); saveTimeout = null }
      await executarSalvar()
    }

    const res = await apiFetch(`conhecimento/documentos-gerados/${props.documentoGeradoId}/finalizar`, { method: 'POST' })
    documento.status = 'Finalizado'
    documento.drive_link = res?.drive_link || null
    emit('toast', { msg: 'Documento finalizado com sucesso.', type: 'success' })
  } catch (err) {
    console.error('Erro ao finalizar documento:', err)
    emit('toast', { msg: err.message || 'Não foi possível finalizar o documento. O rascunho foi preservado.', type: 'danger' })
  } finally {
    finalizando.value = false
  }
}

async function excluirRascunho() {
  if (!confirm('Excluir este rascunho? Esta ação não pode ser desfeita.')) return
  try {
    await apiFetch(`conhecimento/documentos-gerados/${props.documentoGeradoId}`, { method: 'DELETE' })
    emit('toast', { msg: 'Rascunho excluído com sucesso.', type: 'success' })
    dirty.value = false
    navegarParaEntidade()
  } catch (err) {
    console.error('Erro ao excluir rascunho:', err)
    emit('toast', { msg: 'Não foi possível excluir o rascunho.', type: 'danger' })
  }
}

function navegarParaEntidade() {
  if (documento.entidade_tipo === 'processo') {
    emit('open-detail', documento.entidade_id)
  } else if (documento.entidade_tipo === 'caso') {
    emit('open-caso-detail', documento.entidade_id)
  } else {
    emit('set-view', 'conhecimento')
  }
}

function voltar() {
  if (dirty.value && !confirm('Existem alterações não salvas. Deseja sair mesmo assim?')) return
  navegarParaEntidade()
}

function handleBeforeUnload(event) {
  if (!dirty.value) return
  event.preventDefault()
  event.returnValue = ''
}

async function carregarEntidadeLabel() {
  try {
    if (documento.entidade_tipo === 'processo') {
      const res = await apiFetch('processos/detalhes/' + documento.entidade_id)
      const p = res?.data?.processo
      entidadeLabel.value = p ? `Processo ${p.numeroprocessocommascara || p.numero_processo || ''}`.trim() : `Processo #${documento.entidade_id}`
    } else if (documento.entidade_tipo === 'caso') {
      const res = await apiFetch('casos/detalhes/' + documento.entidade_id)
      const c = res?.data?.caso || res?.data
      entidadeLabel.value = c?.titulo ? `Caso ${c.titulo}` : `Caso #${documento.entidade_id}`
    }
  } catch (err) {
    entidadeLabel.value = documento.entidade_tipo === 'processo'
      ? `Processo #${documento.entidade_id}`
      : `Caso #${documento.entidade_id}`
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

let placeholderCatalogCache = null
async function fetchPlaceholderCatalog() {
  if (placeholderCatalogCache) {
    placeholderCatalog.value = placeholderCatalogCache
    return
  }
  try {
    const res = await apiFetch('conhecimento/modelos/placeholders')
    placeholderCatalogCache = res || {}
    placeholderCatalog.value = placeholderCatalogCache
  } catch (err) {
    console.error('Erro ao carregar catálogo de placeholders:', err)
  }
}

async function fetchDocumento() {
  loading.value = true
  try {
    const res = await apiFetch(`conhecimento/documentos-gerados/${props.documentoGeradoId}`)
    Object.assign(documento, res)
    form.titulo = res.titulo
    form.conteudo_html = res.conteudo_html
    selectedTags.value = res.etiquetas || []
    await carregarEntidadeLabel()
  } catch (err) {
    console.error('Erro ao carregar documento gerado:', err)
    emit('toast', { msg: 'Não foi possível carregar o documento.', type: 'danger' })
  } finally {
    loading.value = false
    dirty.value = false
  }
}

onMounted(async () => {
  window.addEventListener('beforeunload', handleBeforeUnload)
  await Promise.all([fetchDocumento(), fetchRamos(), fetchTags(), fetchPlaceholderCatalog()])
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', handleBeforeUnload)
  if (saveTimeout) clearTimeout(saveTimeout)
})
</script>

<style scoped>
.titulo-input {
  font-family: var(--font-head);
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--ink);
  border: none;
  background: transparent;
  width: 100%;
  padding: 2px 0;
}
.titulo-input:not([readonly]):focus {
  outline: none;
  border-bottom: 1px dashed var(--accent);
}
.titulo-input[readonly] { cursor: default; }

.origem-line {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 6px;
}

.editor-layout {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: 16px;
  align-items: start;
}

.editor-sidebar { position: sticky; top: 16px; }
.editor-main { min-width: 0; }

.card-header.collapsible {
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

@media (max-width: 1100px) {
  .editor-layout { grid-template-columns: 1fr; }
  .editor-sidebar { position: static; }
}
</style>
