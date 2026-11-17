<template>
  <div class="card" style="margin-bottom:16px;">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3><i class="bi bi-clock-history"></i> Timeline CRM</h3>
      <button class="btn btn-sm btn-ghost" @click="openModal()" title="Registrar Contato">
        <i class="bi bi-plus-lg"></i>
      </button>
    </div>
    
    <div class="card-body p-0">
      <div v-if="loading" class="text-center p-3">
        <div class="spinner-border spinner-border-sm text-primary"></div>
      </div>
      <div v-else class="timeline-widget-list">
        <div class="timeline-item-small" v-for="i in interactions" :key="i.id">
          <div class="timeline-icon-small">
            <i :class="getIcon(i.tipo)"></i>
          </div>
          <div class="timeline-content-small">
            <div class="d-flex justify-content-between">
              <strong>{{ formatType(i.tipo) }}</strong>
              <small class="text-muted">{{ formatDate(i.created_at) }}</small>
            </div>
            <p class="mb-0 text-muted small">{{ i.descricao }}</p>
          </div>
          <div class="timeline-actions">
             <button class="btn btn-ghost btn-sm text-primary p-1" @click="openModal(i)" title="Editar"><i class="bi bi-pencil"></i></button>
             <button class="btn btn-ghost btn-sm text-danger p-1" @click="deleteInteraction(i.id)" title="Excluir"><i class="bi bi-trash"></i></button>
          </div>
        </div>
        <div v-if="!interactions.length" class="text-center p-3 text-muted small">
          Nenhuma interação registrada.
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <div class="modal-box">
        <div class="modal-header">
          <h2>
            <i class="bi bi-clock-history"></i>
            {{ form.id ? 'Editar Interação' : 'Registrar Contato' }}
          </h2>
          <button class="modal-close" @click="closeModal"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
          <div class="form-group mb-3">
            <label class="form-label">Tipo de Contato</label>
            <select v-model="form.tipo" class="form-control form-select">
              <option value="chamada">📞 Chamada Telefônica</option>
              <option value="reuniao">🤝 Reunião Presencial/Online</option>
              <option value="whatsapp">📱 WhatsApp</option>
              <option value="email">📧 Email</option>
              <option value="outro">📝 Outro</option>
            </select>
          </div>
          <div class="form-group mb-3">
            <label class="form-label">Descrição</label>
            <textarea v-model="form.descricao" class="form-control" rows="4" placeholder="O que foi conversado?"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" @click="closeModal">Cancelar</button>
          <button class="btn btn-primary" @click="saveInteraction" :disabled="saving">
            {{ saving ? 'Salvando...' : 'Registrar' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Widget de Timeline CRM do Cliente
 * @version 1.1.0
 */
import { ref, reactive, onMounted } from 'vue'
import { apiFetch, formatDate } from '../../services/api'

const props = defineProps({
  clientId: {
    type: [Number, String],
    required: true
  }
})

const emit = defineEmits(['toast'])

const interactions = ref([])
const loading = ref(false)
const showModal = ref(false)
const saving = ref(false)

const form = reactive({
  id: '',
  cliente_id: props.clientId,
  tipo: 'outro',
  descricao: ''
})

const getIcon = (tipo) => {
  const icons = {
    chamada: 'bi bi-telephone',
    reuniao: 'bi bi-people',
    whatsapp: 'bi bi-whatsapp',
    email: 'bi bi-envelope',
    outro: 'bi bi-journal-text'
  }
  return icons[tipo] || icons.outro
}

const formatType = (tipo) => {
  const types = {
    chamada: 'Chamada Telefônica',
    reuniao: 'Reunião',
    whatsapp: 'WhatsApp',
    email: 'Email',
    outro: 'Outro'
  }
  return types[tipo] || 'Outro'
}

const loadInteractions = async () => {
  if (!props.clientId) return
  loading.value = true
  try {
    const res = await apiFetch(`clientes/interacoes/${props.clientId}`)
    if (res && res.success) {
      interactions.value = res.data || []
    } else {
      // Falback se endpoint não existir, tentar o antigo nome ou mock
      if(res && res.data) interactions.value = res.data;
    }
  } catch (err) {
    console.error(err)
  } finally {
    loading.value = false
  }
}

const openModal = (item = null) => {
  if (item) {
    form.id = item.id || item.id_interacao
    form.tipo = item.tipo || 'outro'
    form.descricao = item.descricao || ''
  } else {
    form.id = ''
    form.tipo = 'outro'
    form.descricao = ''
  }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const saveInteraction = async () => {
  if (!form.descricao) {
    emit('toast', { msg: 'Preencha a descrição.', type: 'warning' })
    return
  }
  saving.value = true
  try {
    const body = new FormData()
    body.append('cliente_id', String(form.cliente_id))
    body.append('tipo', form.tipo)
    body.append('descricao', form.descricao)
    if (form.id) body.append('id', form.id)

    const res = await apiFetch('clientes/interacao/salvar', {
      method: 'POST',
      body
    })
    
    if (res && res.success) {
      emit('toast', { msg: 'Interação registrada com sucesso.', type: 'success' })
      closeModal()
      loadInteractions()
    } else {
      throw new Error(res.message || 'Erro ao salvar.')
    }
  } catch (err) {
    console.error(err)
    emit('toast', { msg: err.message || 'Falha ao salvar interação.', type: 'danger' })
  } finally {
    saving.value = false
  }
}

const deleteInteraction = async (id) => {
  if (!confirm('Deseja realmente excluir esta interação?')) return
  try {
    const res = await apiFetch(`clientes/interacao/excluir/${id}`, { method: 'DELETE' })
    if (res && res.success) {
      emit('toast', { msg: 'Interação excluída.', type: 'success' })
      loadInteractions()
    } else {
      throw new Error(res.message || 'Erro ao excluir.')
    }
  } catch (err) {
    console.error(err)
    emit('toast', { msg: err.message || 'Falha ao excluir.', type: 'danger' })
  }
}

onMounted(() => {
  loadInteractions()
})
</script>

<style scoped>
.timeline-widget-list {
  max-height: 300px;
  overflow-y: auto;
  padding: 1rem;
}
.timeline-item-small {
  display: flex;
  gap: 10px;
  margin-bottom: 12px;
  position: relative;
}
.timeline-icon-small {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--primary-lt);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.timeline-content-small {
  flex-grow: 1;
  background: var(--surface);
  border: 1px solid var(--border);
  padding: 8px 12px;
  border-radius: 6px;
}
.timeline-actions {
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
}

</style>
