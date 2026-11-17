<template>
  <div class="page-content animate-fade-in">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="voltar">Modelos de Documentos</a>
      <i class="bi bi-chevron-right"></i>
      <span>{{ modeloId ? 'Editar Modelo' : 'Novo Modelo' }}</span>
    </div>

    <div v-if="loading" class="empty-state card">
      <div class="spinner"></div>
      <p>Carregando modelo...</p>
    </div>

    <template v-else>
      <div class="page-header">
        <div>
          <h1>{{ modeloId ? 'Editar Modelo' : 'Novo Modelo' }}</h1>
          <p>{{ form.titulo || 'Sem título' }}</p>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" @click="voltar">Cancelar</button>
          <button class="btn btn-outline-primary" :disabled="saving" @click="salvar(false)">
            <i class="bi bi-save"></i> Salvar Rascunho
          </button>
          <button class="btn btn-primary" :disabled="saving" @click="salvar(true)">
            <i class="bi bi-check2-circle"></i> Salvar e Publicar
          </button>
        </div>
      </div>

      <div class="editor-layout">
        <!-- Formulário -->
        <div class="card editor-form">
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Título *</label>
              <input type="text" class="form-control" v-model="form.titulo" @input="dirty = true">
            </div>

            <div class="mb-3">
              <label class="form-label">Ramo do Direito</label>
              <select class="form-select" v-model="form.ramo_id" @change="dirty = true">
                <option :value="null">Geral</option>
                <option v-for="ramo in ramos" :key="ramo.id" :value="ramo.id">{{ ramo.nome }}</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Usado a partir de</label>
              <div class="d-flex gap-3">
                <div class="form-check" v-for="opt in tipoAlvoOpcoes" :key="opt.value">
                  <input
                    type="radio" class="form-check-input" :id="'tipo-alvo-' + opt.value"
                    :value="opt.value" v-model="form.tipo_alvo" @change="dirty = true"
                  >
                  <label class="form-check-label" :for="'tipo-alvo-' + opt.value">{{ opt.label }}</label>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Status</label>
              <select class="form-select" v-model="form.status" @change="dirty = true">
                <option value="Rascunho">Rascunho</option>
                <option value="Revisão">Revisão</option>
                <option value="Publicado">Publicado</option>
              </select>
            </div>

            <div class="row mb-3">
              <div class="col-6">
                <label class="form-label">Versão</label>
                <input type="text" class="form-control" v-model="form.versao" @input="dirty = true">
              </div>
              <div class="col-6">
                <label class="form-label">Alerta de vencimento (dias)</label>
                <input type="number" min="0" class="form-control" v-model.number="form.alerta_vencimento" @input="dirty = true">
              </div>
            </div>

            <div class="mb-1">
              <label class="form-label d-block">Etiquetas</label>
              <button type="button" class="btn btn-outline-secondary btn-sm mb-2" @click="showEtiquetaModal = true">
                <i class="bi bi-tags"></i> Gerenciar etiquetas
              </button>
              <div class="d-flex flex-wrap gap-1">
                <span
                  v-for="tag in selectedTags" :key="tag.id"
                  class="badge" :style="{ background: '#' + tag.cor, color: '#fff' }"
                >{{ tag.nome }}</span>
                <span v-if="!selectedTags.length" class="text-muted small">Nenhuma etiqueta selecionada.</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Editor -->
        <div class="editor-main">
          <DocumentoEditor
            ref="editorRef"
            v-model="form.conteudo"
            :placeholder-catalog="placeholderCatalog"
            :page-frame="true"
            @dirty="onEditorDirty"
            @save-shortcut="salvar(false)"
          />
        </div>

        <!-- Painel de campos usados -->
        <div class="card editor-sidebar">
          <div class="card-header">
            <h4 class="m-0"><i class="bi bi-list-check"></i> Campos usados neste modelo</h4>
          </div>
          <div class="card-body">
            <div v-if="!usedPlaceholders.length" class="text-muted small">
              Nenhum campo inserido ainda. Use o botão "Inserir campo" na barra de ferramentas do editor.
            </div>
            <div v-else v-for="grupo in groupedPlaceholders" :key="grupo.origem" class="mb-3">
              <div class="text-uppercase small fw-bold text-muted mb-1">{{ grupo.origem }}</div>
              <ul class="list-unstyled m-0">
                <li v-for="item in grupo.itens" :key="item.campo" class="d-flex align-items-center gap-1 small mb-1">
                  <i v-if="item.desconhecido" class="bi bi-exclamation-triangle-fill text-warning" title="Campo não existe no catálogo"></i>
                  <i v-else class="bi bi-check-circle text-success"></i>
                  <code>{{ item.origem }}.{{ item.campo }}</code>
                </li>
              </ul>
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
import { ref, reactive, computed, onMounted } from 'vue'
import { apiFetch, debounce } from '../../services/api'
import DocumentoEditor from '../../components/editor/DocumentoEditor.vue'
import EtiquetaModal from '../../components/modals/Processos/EtiquetaModal.vue'

const props = defineProps({
  modeloId: { type: [Number, String], default: null }
})

const emit = defineEmits(['set-view', 'toast'])

const tipoAlvoOpcoes = [
  { value: 'processo', label: 'Processo' },
  { value: 'caso', label: 'Caso' },
  { value: 'ambos', label: 'Ambos' }
]

const loading = ref(true)
const saving = ref(false)
const dirty = ref(false)
const ramos = ref([])
const availableTags = ref([])
const selectedTags = ref([])
const showEtiquetaModal = ref(false)
const editorRef = ref(null)
const usedPlaceholders = ref([])

const form = reactive({
  titulo: '',
  ramo_id: null,
  tipo_alvo: 'ambos',
  status: 'Rascunho',
  versao: '1.0.0',
  alerta_vencimento: 365,
  conteudo: ''
})

// Catálogo de placeholders é o mesmo para qualquer modelo na sessão: cacheado em memória de módulo.
let placeholderCatalogCache = null
const placeholderCatalog = ref({})

const groupedPlaceholders = computed(() => {
  const groups: Record<string, any[]> = {}
  for (const item of usedPlaceholders.value) {
    if (!groups[item.origem]) groups[item.origem] = []
    groups[item.origem].push(item)
  }
  return Object.entries(groups).map(([origem, itens]) => ({ origem, itens }))
})

function toggleTag(tag) {
  const idx = selectedTags.value.findIndex(t => t.id === tag.id)
  if (idx >= 0) {
    selectedTags.value.splice(idx, 1)
  } else {
    selectedTags.value.push(tag)
  }
}

const updateUsedPlaceholders = debounce(() => {
  if (!editorRef.value) return
  const used = editorRef.value.extractPlaceholdersUsed()
  usedPlaceholders.value = used.map(item => ({
    ...item,
    desconhecido: !placeholderCatalog.value[item.origem]?.[item.campo]
  }))
}, 400)

function onEditorDirty() {
  dirty.value = true
  updateUsedPlaceholders()
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

async function fetchModelo() {
  if (!props.modeloId) return
  try {
    const modelo = await apiFetch(`conhecimento/modelos/${props.modeloId}`)
    form.titulo = modelo.titulo
    form.ramo_id = modelo.ramo_id
    form.tipo_alvo = modelo.tipo_alvo || 'ambos'
    form.status = modelo.status
    form.versao = modelo.versao
    form.alerta_vencimento = modelo.alerta_vencimento
    form.conteudo = modelo.conteudo
    selectedTags.value = modelo.etiquetas || []
  } catch (err) {
    console.error('Erro ao carregar modelo:', err)
    emit('toast', { msg: 'Não foi possível carregar o modelo.', type: 'danger' })
  }
}

async function salvar(publicar) {
  if (!form.titulo || !form.conteudo) {
    emit('toast', { msg: 'Título e conteúdo são obrigatórios.', type: 'danger' })
    return
  }

  saving.value = true
  try {
    const payload = {
      titulo: form.titulo,
      ramo_id: form.ramo_id,
      tipo_alvo: form.tipo_alvo,
      status: publicar ? 'Publicado' : form.status,
      versao: form.versao,
      alerta_vencimento: form.alerta_vencimento,
      conteudo: form.conteudo,
      etiqueta_ids: selectedTags.value.map(t => t.id)
    }

    if (props.modeloId) {
      await apiFetch(`conhecimento/modelos/atualizar/${props.modeloId}`, { method: 'PUT', body: payload })
    } else {
      await apiFetch('conhecimento/modelos/salvar', { method: 'POST', body: payload })
    }

    dirty.value = false
    emit('toast', { msg: publicar ? 'Modelo publicado com sucesso.' : 'Modelo salvo com sucesso.', type: 'success' })
    emit('set-view', 'modelos')
  } catch (err) {
    console.error('Erro ao salvar modelo:', err)
    emit('toast', { msg: 'Não foi possível salvar o modelo.', type: 'danger' })
  } finally {
    saving.value = false
  }
}

function voltar() {
  if (dirty.value && !confirm('Existem alterações não salvas. Deseja sair mesmo assim?')) return
  emit('set-view', 'modelos')
}

onMounted(async () => {
  loading.value = true
  await Promise.all([fetchRamos(), fetchTags(), fetchPlaceholderCatalog(), fetchModelo()])
  loading.value = false
  dirty.value = false
})
</script>

<style scoped>
.editor-layout {
  display: grid;
  grid-template-columns: 280px 1fr 260px;
  gap: 16px;
  align-items: start;
}

.editor-form,
.editor-sidebar {
  position: sticky;
  top: 16px;
}

.editor-main {
  min-width: 0;
}

@media (max-width: 1200px) {
  .editor-layout {
    grid-template-columns: 1fr;
  }
  .editor-form,
  .editor-sidebar {
    position: static;
  }
}
</style>
