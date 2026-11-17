<template>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3><i class="bi bi-file-earmark-text"></i> Documentos</h3>
      <button class="btn btn-primary btn-sm" @click="showEscolherModal = true">
        <i class="bi bi-file-earmark-plus"></i> Usar modelo
      </button>
    </div>

    <div class="card-body">
      <div v-if="documentos.length" class="filters-bar mb-3">
        <select class="form-control form-select form-select-sm" v-model="filtroStatus" style="max-width:150px;">
          <option value="">Todos os status</option>
          <option value="Rascunho">Rascunho</option>
          <option value="Finalizado">Finalizado</option>
        </select>
        <select v-if="ramosDisponiveis.length" class="form-control form-select form-select-sm" v-model="filtroRamo" style="max-width:180px;">
          <option value="">Todos os ramos</option>
          <option v-for="r in ramosDisponiveis" :key="r" :value="r">{{ r }}</option>
        </select>
        <select v-if="etiquetasDisponiveis.length" class="form-control form-select form-select-sm" v-model="filtroEtiqueta" style="max-width:180px;">
          <option value="">Todas as etiquetas</option>
          <option v-for="e in etiquetasDisponiveis" :key="e.id" :value="e.id">{{ e.nome }}</option>
        </select>
      </div>

      <div v-if="loading" class="text-center" style="padding: 20px 0;"><div class="spinner"></div></div>

      <div v-else-if="!documentos.length" class="empty-state" style="padding: 20px 0;">
        <i class="bi bi-file-earmark-text" style="font-size:2.2rem;color:var(--accent);"></i>
        <p class="text-muted small mb-0">Nenhum documento gerado ainda a partir de modelos.</p>
      </div>

      <div v-else-if="!documentosFiltrados.length" class="empty-state" style="padding: 20px 0;">
        <p class="text-muted small mb-0">Nenhum documento corresponde aos filtros selecionados.</p>
      </div>

      <div v-else class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Título</th>
              <th>Modelo</th>
              <th>Ramo</th>
              <th>Etiquetas</th>
              <th>Status</th>
              <th>Data</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="doc in documentosFiltrados" :key="doc.id">
              <td>
                <a href="#" class="fw-bold" @click.prevent="$emit('abrir-documento', doc.id)">{{ doc.titulo }}</a>
              </td>
              <td class="small text-muted">{{ doc.modelo_titulo_snapshot || '—' }}</td>
              <td><span v-if="doc.ramo_nome" class="badge bg-light text-dark border">{{ doc.ramo_nome }}</span></td>
              <td>
                <div class="d-flex flex-wrap gap-1">
                  <span
                    v-for="tag in doc.etiquetas" :key="tag.id"
                    class="badge" :style="{ background: '#' + tag.cor, color: '#fff' }"
                  >{{ tag.nome }}</span>
                </div>
              </td>
              <td><span class="badge" :class="doc.status === 'Finalizado' ? 'badge-success' : 'badge-warning'">{{ doc.status }}</span></td>
              <td class="small">{{ formatDate(doc.status === 'Finalizado' ? doc.finalizado_em : doc.created_at, true) }}</td>
              <td class="text-end">
                <div class="d-flex gap-1 justify-content-end">
                  <button
                    v-if="doc.status === 'Rascunho'" class="btn btn-ghost btn-sm" title="Continuar editando"
                    @click="$emit('abrir-documento', doc.id)"
                  ><i class="bi bi-pencil-square" style="color:var(--accent);"></i></button>
                  <button
                    v-else class="btn btn-ghost btn-sm" title="Visualizar"
                    @click="$emit('abrir-documento', doc.id)"
                  ><i class="bi bi-eye"></i></button>
                  <a
                    v-if="doc.drive_link" :href="doc.drive_link" target="_blank" rel="noopener"
                    class="btn btn-ghost btn-sm" title="Baixar PDF"
                  ><i class="bi bi-download" style="color:var(--primary);"></i></a>
                  <button
                    v-if="doc.status === 'Rascunho'" class="btn btn-ghost btn-sm" title="Excluir"
                    @click="excluir(doc)"
                  ><i class="bi bi-trash" style="color:var(--danger);"></i></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <EscolherModeloModal
      v-if="showEscolherModal"
      :entidade-tipo="entidadeTipo"
      :entidade-id="entidadeId"
      :saving="iniciando"
      @close="showEscolherModal = false"
      @submit="iniciarGeracao"
      @set-view="v => $emit('set-view', v)"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { apiFetch, formatDate } from '../../services/api'
import EscolherModeloModal from '../modals/Documentos/EscolherModeloModal.vue'

const props = defineProps({
  entidadeTipo: { type: String, required: true }, // 'processo' | 'caso'
  entidadeId: { type: [Number, String], required: true }
})

const emit = defineEmits(['toast', 'abrir-documento', 'set-view'])

const loading = ref(true)
const documentos = ref([])
const showEscolherModal = ref(false)
const iniciando = ref(false)

const filtroStatus = ref('')
const filtroRamo = ref('')
const filtroEtiqueta = ref('')

const ramosDisponiveis = computed(() => {
  const set = new Set<string>()
  documentos.value.forEach(d => { if (d.ramo_nome) set.add(d.ramo_nome) })
  return Array.from(set)
})

const etiquetasDisponiveis = computed(() => {
  const map = new Map()
  documentos.value.forEach(d => (d.etiquetas || []).forEach(t => map.set(t.id, t)))
  return Array.from(map.values())
})

const documentosFiltrados = computed(() => {
  return documentos.value.filter(d => {
    if (filtroStatus.value && d.status !== filtroStatus.value) return false
    if (filtroRamo.value && d.ramo_nome !== filtroRamo.value) return false
    if (filtroEtiqueta.value && !(d.etiquetas || []).some(t => t.id === filtroEtiqueta.value)) return false
    return true
  })
})

async function fetchDocumentos() {
  loading.value = true
  try {
    documentos.value = await apiFetch(`conhecimento/documentos-gerados/entidade/${props.entidadeTipo}/${props.entidadeId}`) || []
  } catch (err) {
    console.error('Erro ao carregar documentos gerados:', err)
    emit('toast', { msg: 'Não foi possível carregar os documentos gerados.', type: 'danger' })
  } finally {
    loading.value = false
  }
}

async function iniciarGeracao(payload) {
  iniciando.value = true
  try {
    const documento = await apiFetch('conhecimento/documentos-gerados/iniciar', { method: 'POST', body: payload })
    showEscolherModal.value = false
    emit('abrir-documento', documento.id)
  } catch (err) {
    console.error('Erro ao iniciar geração do documento:', err)
    emit('toast', { msg: err.message || 'Não foi possível gerar o documento a partir do modelo.', type: 'danger' })
  } finally {
    iniciando.value = false
  }
}

async function excluir(doc) {
  if (!confirm(`Excluir o rascunho "${doc.titulo}"? Esta ação não pode ser desfeita.`)) return
  try {
    await apiFetch(`conhecimento/documentos-gerados/${doc.id}`, { method: 'DELETE' })
    documentos.value = documentos.value.filter(d => d.id !== doc.id)
    emit('toast', { msg: 'Rascunho excluído com sucesso.', type: 'success' })
  } catch (err) {
    console.error('Erro ao excluir rascunho:', err)
    emit('toast', { msg: 'Não foi possível excluir o rascunho.', type: 'danger' })
  }
}

defineExpose({ refresh: fetchDocumentos })

onMounted(fetchDocumentos)
</script>

<style scoped>
.filters-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}
</style>
