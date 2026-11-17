<template>
  <div class="card" style="margin-bottom:16px;">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3><i class="bi bi-journal-text"></i> Anotações</h3>
      <button class="btn btn-sm btn-ghost" @click="openModal()" title="Nova Anotação">
        <i class="bi bi-plus-lg"></i>
      </button>
    </div>
    
    <div class="card-body p-0">
      <div v-if="loading" class="text-center p-3">
        <div class="spinner-border spinner-border-sm text-primary"></div>
      </div>
      <div v-else class="notes-widget-list">
        <div class="note-item" v-for="n in notes" :key="n.id_anotacao">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <h6 class="mb-0">
              <i :class="n.privacidade == 2 ? 'bi bi-people-fill text-info' : 'bi bi-lock-fill text-muted'"></i>
              {{ n.titulo }}
            </h6>
            <small class="text-muted">{{ formatDate(n.created_at) }}</small>
          </div>
          <p class="mb-2 text-muted small" style="white-space: pre-wrap;">{{ n.anotacao || n.conteudo }}</p>
          <div class="text-end">
             <button class="btn btn-ghost btn-sm text-primary p-1" @click="openModal(n)" title="Editar"><i class="bi bi-pencil"></i></button>
             <button class="btn btn-ghost btn-sm text-danger p-1 ms-1" @click="deleteNote(n.id_anotacao)" title="Excluir"><i class="bi bi-trash"></i></button>
          </div>
        </div>
        <div v-if="!notes.length" class="text-center p-3 text-muted small">
          Nenhuma anotação encontrada.
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <div class="modal-box">
        <div class="modal-header">
          <h2>
            <i class="bi bi-journal-text"></i>
            {{ form.id_anotacao ? 'Editar Anotação' : 'Nova Anotação' }}
          </h2>
          <button class="modal-close" @click="closeModal"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
          <div class="form-group mb-3">
            <label class="form-label">Título</label>
            <input type="text" v-model="form.titulo" class="form-control" placeholder="Resumo do assunto">
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Anotação</label>
            <textarea v-model="form.anotacao" class="form-control" rows="4" placeholder="Detalhes da observação..."></textarea>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Privacidade</label>
            <div class="form-check">
              <input class="form-check-input" type="radio" v-model="form.privacidade" value="1" id="priv1">
              <label class="form-check-label" for="priv1"><i class="bi bi-lock"></i> Privado</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" v-model="form.privacidade" value="2" id="priv2">
              <label class="form-check-label" for="priv2"><i class="bi bi-people"></i> Compartilhado</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" @click="closeModal">Cancelar</button>
          <button class="btn btn-primary" @click="saveNote" :disabled="saving">
            {{ saving ? 'Salvando...' : 'Salvar' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Widget de Anotações do Cliente
 * @version 1.1.0
 */
import { ref, reactive, onMounted } from 'vue'
import { apiFetch, formatDate } from '../../services/api'

const props = defineProps({
  documento: {
    type: String,
    required: false
  },
  clientId: {
    type: [Number, String],
    required: false
  }
})

const emit = defineEmits(['toast'])

const notes = ref([])
const loading = ref(false)
const showModal = ref(false)
const saving = ref(false)

const form = reactive({
  id_anotacao: '',
  documento: props.documento || '',
  cliente_id: props.clientId || '',
  titulo: '',
  anotacao: '',
  privacidade: '1'
})

const loadNotes = async () => {
  if (!props.documento && !props.clientId) return
  loading.value = true
  try {
    // Tenta usar endpoint de listagem global por documento ou o endpoint de cliente
    let url = props.documento ? `anotacoes/listar?documento=${encodeURIComponent(props.documento)}` : `clientes/anotacoes/${props.clientId}`
    const res = await apiFetch(url)
    if (res && res.success) {
      notes.value = res.data || []
    } else if (res && res.data) {
      notes.value = res.data
    }
  } catch (err) {
    console.error(err)
  } finally {
    loading.value = false
  }
}

const openModal = (item = null) => {
  if (item) {
    form.id_anotacao = item.id_anotacao || item.id || ''
    form.titulo = item.titulo || ''
    form.anotacao = item.anotacao || item.conteudo || ''
    form.privacidade = String(item.privacidade || '1')
  } else {
    form.id_anotacao = ''
    form.titulo = ''
    form.anotacao = ''
    form.privacidade = '1'
  }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const saveNote = async () => {
  if (!form.titulo || !form.anotacao) {
    emit('toast', { msg: 'Preencha o título e a anotação.', type: 'warning' })
    return
  }
  saving.value = true
  try {
    const payload: Record<string, any> = {
      titulo: form.titulo,
      anotacao: form.anotacao,
      privacidade: form.privacidade
    }
    if (form.id_anotacao) payload.id_anotacao = form.id_anotacao
    if (form.documento) payload.documento = form.documento
    if (form.cliente_id) payload.cliente_id = form.cliente_id

    const res = await apiFetch('anotacoes/salvar', {
      method: 'POST',
      body: payload
    })
    
    if (res && res.success) {
      emit('toast', { msg: 'Anotação salva com sucesso.', type: 'success' })
      closeModal()
      loadNotes()
    } else {
      throw new Error(res.message || 'Erro ao salvar.')
    }
  } catch (err) {
    console.error(err)
    emit('toast', { msg: err.message || 'Falha ao salvar anotação.', type: 'danger' })
  } finally {
    saving.value = false
  }
}

const deleteNote = async (id) => {
  if (!confirm('Deseja realmente excluir esta anotação?')) return
  try {
    const res = await apiFetch(`anotacoes/excluir/${id}`, { method: 'DELETE' })
    if (res && res.success) {
      emit('toast', { msg: 'Anotação excluída.', type: 'success' })
      loadNotes()
    } else {
      throw new Error(res.message || 'Erro ao excluir.')
    }
  } catch (err) {
    console.error(err)
    emit('toast', { msg: err.message || 'Falha ao excluir.', type: 'danger' })
  }
}

onMounted(() => {
  loadNotes()
})
</script>

<style scoped>
.notes-widget-list {
  max-height: 300px;
  overflow-y: auto;
  padding: 1rem;
}
.note-item {
  background: var(--surface);
  border: 1px solid var(--border);
  padding: 12px;
  border-radius: 6px;
  margin-bottom: 12px;
  border-left: 3px solid var(--primary);
}

</style>
