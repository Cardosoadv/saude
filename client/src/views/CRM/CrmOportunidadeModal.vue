<template>
  <div class="modal-backdrop" @click.self="closeModal">
    <div class="modal-box">
      <div class="modal-header">
        <h2>
          <i class="bi bi-briefcase text-primary me-2"></i>
          {{ form.id ? 'Editar Oportunidade' : 'Nova Oportunidade' }}
        </h2>
        <button class="modal-close" @click="closeModal" aria-label="Fechar modal" title="Fechar modal"><i class="bi bi-x"></i></button>
      </div>
      <div class="modal-body">
        <form @submit.prevent="save">
          <div class="form-group">
            <label class="form-label" for="oportunidade-titulo">Título da Oportunidade <span class="text-danger">*</span></label>
            <input id="oportunidade-titulo" type="text" class="form-control" v-model="form.titulo" required placeholder="Ex: Inventário Família Silva">
          </div>

          <div class="form-group autocomplete-container client-autocomplete-container">
            <label class="form-label" for="client-search">Cliente (Busca ou Novo Nome)</label>
            <div class="input-group">
              <input 
                id="client-search"
                type="text" 
                class="form-control" 
                v-model="clientSearch" 
                @input="onClientInput"
                @focus="showClients = true" 
                placeholder="Digite para buscar cliente..." 
                autocomplete="off"
                aria-autocomplete="list"
                :aria-expanded="showClients && clientResults.length > 0"
                role="combobox"
                aria-controls="client-results-list"
              >
              <button v-if="form.cliente_id" type="button" class="btn-clear" @click="clearClient" title="Limpar cliente" aria-label="Limpar cliente">
                <i class="bi bi-x-circle-fill"></i>
              </button>
            </div>
            <ul v-if="showClients && clientResults.length" id="client-results-list" class="autocomplete-dropdown" role="listbox">
              <li 
                v-for="res in clientResults" 
                :key="res.id" 
                @click="selectClient(res)"
                role="option"
                tabindex="0"
                @keydown.enter="selectClient(res)"
                @keydown.space.prevent="selectClient(res)"
              >
                {{ res.text }}
              </li>
            </ul>
          </div>

          <div class="form-group">
            <label class="form-label" for="valor_estimado">Valor Estimado (R$)</label>
            <div class="input-group">
              <span class="input-group-addon">R$</span>
              <input 
                id="valor_estimado"
                type="text" 
                class="form-control" 
                :value="form.valor_estimado" 
                @input="e => formatMoneyInput(e, 'valor_estimado')" 
                placeholder="0,00"
              >
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" @click="closeModal" :disabled="saving">Cancelar</button>
        <button type="button" class="btn btn-primary" @click="save" :disabled="saving">
          <span v-if="saving" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
          Salvar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { apiFetch, formatNumberBr } from '../../services/api'

const props = defineProps({
  oportunidadeId: {
    type: [Number, String],
    default: null
  },
  oportunidadeData: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'toast', 'saved'])

const saving = ref(false)

const form = ref({
  id: null,
  titulo: '',
  cliente_nome: '',
  cliente_id: null,
  valor_estimado: '',
  etapa_id: 1 // Default Prospect
})

const clientSearch = ref('')
const selectedClientText = ref('')
const clientResults = ref([])
const showClients = ref(false)
let clientTimeout = null

function handleClickOutside(e) {
  const clientContainer = document.querySelector('.client-autocomplete-container')
  if (clientContainer && !clientContainer.contains(e.target)) {
    showClients.value = false
  }
}

function formatMoneyInput(e, field) {
  let val = e.target.value.replace(/\D/g, '')
  if (!val) {
    form.value[field] = ''
    return
  }
  val = (parseFloat(val) / 100).toFixed(2)
  val = val.replace('.', ',')
  val = val.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.')
  form.value[field] = val
}

function onClientInput() {
  form.value.cliente_id = null
  clearTimeout(clientTimeout)
  if (clientSearch.value.trim().length < 2) {
    clientResults.value = []
    showClients.value = false
    return
  }
  clientTimeout = setTimeout(async () => {
    try {
      const res = await apiFetch('processos/clientes-busca?term=' + encodeURIComponent(clientSearch.value))
      if (res && res.results) {
        clientResults.value = res.results
        showClients.value = true
      }
    } catch (err) {
      console.error(err)
    }
  }, 350)
}

function selectClient(item) {
  form.value.cliente_id = item.id
  selectedClientText.value = item.text
  clientSearch.value = item.text
  clientResults.value = []
  showClients.value = false
}

function clearClient() {
  form.value.cliente_id = null
  selectedClientText.value = ''
  clientSearch.value = ''
  clientResults.value = []
  showClients.value = false
}

onMounted(() => {
  window.addEventListener('click', handleClickOutside)
  if (props.oportunidadeData) {
    form.value.id = props.oportunidadeData.id
    form.value.titulo = props.oportunidadeData.titulo || ''
    form.value.cliente_nome = props.oportunidadeData.cliente_nome || ''
    form.value.cliente_id = props.oportunidadeData.cliente_id || null
    if (props.oportunidadeData.valor_estimado) {
      form.value.valor_estimado = formatNumberBr(parseFloat(props.oportunidadeData.valor_estimado))
    }
    form.value.etapa_id = props.oportunidadeData.etapa_id || 1
    
    if (form.value.cliente_nome) {
      clientSearch.value = form.value.cliente_nome
      selectedClientText.value = form.value.cliente_nome
    }
  } else if (props.oportunidadeId) {
    form.value.id = props.oportunidadeId
  }
})

onUnmounted(() => {
  window.removeEventListener('click', handleClickOutside)
})

async function save() {
  if (!form.value.titulo) {
    emit('toast', { msg: 'O título é obrigatório', type: 'warning' })
    return
  }

  saving.value = true
  try {
    const fd = new FormData()
    if (form.value.id) fd.append('id', form.value.id)
    fd.append('titulo', form.value.titulo)
    fd.append('cliente_nome', clientSearch.value || form.value.cliente_nome)
    if (form.value.cliente_id) fd.append('cliente_id', form.value.cliente_id)
    
    let valorEstimado = form.value.valor_estimado ? form.value.valor_estimado.toString().replace(/\./g, '').replace(',', '.') : 0
    fd.append('valor_estimado', String(valorEstimado))
    if (!form.value.id) {
      fd.append('etapa_id', String(form.value.etapa_id))
    }

    const res = await apiFetch('crm/salvar', { method: 'POST', body: fd })
    
    if (res && res.success) {
      emit('toast', { msg: 'Oportunidade salva com sucesso!', type: 'success' })
      emit('saved')
      closeModal()
    } else {
      throw new Error(res?.message || 'Erro desconhecido')
    }
  } catch (error) {
    console.error('Error saving oportunidade:', error)
    emit('toast', { msg: 'Erro ao salvar: ' + error.message, type: 'danger' })
  } finally {
    saving.value = false
  }
}

function closeModal() {
  emit('close')
}
</script>

<style scoped>
.autocomplete-container {
  position: relative;
}
.input-group {
  display: flex;
  position: relative;
  width: 100%;
}
.input-group-addon {
  background: var(--surface);
  border: 1px solid var(--border);
  border-right: none;
  padding: 8px 12px;
  font-size: 0.85rem;
  color: var(--ink-dim);
  border-radius: 8px 0 0 8px;
  display: flex;
  align-items: center;
  font-weight: 600;
}
.input-group-addon + .form-control {
  border-radius: 0 8px 8px 0;
}
.btn-clear {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--ink-muted);
  cursor: pointer;
  z-index: 10;
  display: flex;
  align-items: center;
  font-size: 1rem;
}
.btn-clear:hover {
  color: var(--danger);
}
.autocomplete-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 0 0 8px 8px;
  box-shadow: var(--shadow-lg);
  max-height: 200px;
  overflow-y: auto;
  list-style: none;
  padding: 0;
  margin: 0;
  z-index: 2000;
}
.autocomplete-dropdown li {
  padding: 9px 12px;
  font-size: 0.82rem;
  cursor: pointer;
  border-bottom: 1px solid var(--border);
  color: var(--ink-dim);
  transition: background 0.15s;
}
.autocomplete-dropdown li:last-child {
  border-bottom: none;
}
.autocomplete-dropdown li:hover {
  background: var(--surface);
  color: var(--primary);
}
</style>
