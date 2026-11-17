<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box">
      <div class="modal-header">
        <h2>
          <i class="bi" :class="isEdit ? 'bi-pencil-square' : 'bi-check2-square'"></i>
          {{ isEdit ? 'Editar Tarefa' : 'Nova Tarefa' }}
        </h2>
        <button class="modal-close" @click="$emit('close')" aria-label="Fechar modal"><i
            class="bi bi-x-lg"></i></button>
      </div>

      <div class="modal-body">
        <!-- Título da Tarefa -->
        <div class="form-group">
          <label class="form-label"><i class="bi bi-type"></i> Título da Tarefa *</label>
          <input class="form-control" type="text" v-model="form.tarefa"
            placeholder="Ex: Preparar contestação, Protocolar recurso..." required>
        </div>

        <!-- Prazo -->
        <div class="form-group">
          <label class="form-label"><i class="bi bi-calendar-event"></i> Prazo *</label>
          <input class="form-control" type="date" v-model="form.prazo" required>
          <PrazoAConferirBadge v-if="isEdit && prazoAConferir(taskData)" style="margin-top:6px;" />
        </div>

        <!-- Como o prazo foi contado (só para tarefas geradas com memória de cálculo) -->
        <details v-if="isEdit && taskData?.prazo_memoria_calculo" class="form-group">
          <summary class="form-label" style="cursor:pointer;"><i class="bi bi-calculator"></i> Como este prazo foi contado</summary>
          <MemoriaCalculoPrazo tipo="tarefa" :id="taskData.id_tarefa || taskData.id" />
        </details>

        <!-- Status e Prioridade (Row) -->
        <div class="form-row" style="display: flex; gap: 12px; margin-bottom: 16px;">
          <div class="form-group" style="flex: 1; margin-bottom: 0;">
            <label class="form-label"><i class="bi bi-flag"></i> Status *</label>
            <select class="form-control form-select" v-model="form.status" required>
              <option value="1">Eventos</option>
              <option value="2">A Fazer</option>
              <option value="3">Fazendo</option>
              <option value="4">Feito</option>
              <option value="5">Cancelado</option>
            </select>
          </div>
          <div class="form-group" style="flex: 1; margin-bottom: 0;">
            <label class="form-label"><i class="bi bi-exclamation-triangle"></i> Prioridade</label>
            <select class="form-control form-select" v-model="form.prioridade">
              <option value="1">Muito Baixa</option>
              <option value="2">Baixa</option>
              <option value="3">Média</option>
              <option value="4">Alta</option>
              <option value="5">Urgente</option>
            </select>
          </div>
        </div>

        <!-- Responsável Autocomplete -->
        <div class="form-group position-relative" style="position: relative;">
          <label class="form-label"><i class="bi bi-person"></i> Responsável (Buscar Usuário)</label>
          <div class="input-group-autocomplete">
            <span class="input-icon-left"><i class="bi bi-person-search"></i></span>
            <input id="responsavel-input" class="form-control" type="text" v-model="responsavelBusca"
              placeholder="Nome, e-mail ou username..." autocomplete="off" @input="onResponsavelInput"
              @focus="onResponsavelFocus">
            <button v-if="form.responsavel || responsavelBusca" class="btn-clear-input" type="button"
              @click="clearResponsavel" title="Limpar responsável">
              <i class="bi bi-x-circle-fill"></i>
            </button>
          </div>

          <!-- Autocomplete results dropdown -->
          <div v-if="showDropdown" class="autocomplete-results shadow-lg">
            <div v-if="loadingResponsaveis" class="autocomplete-loading">
              <span class="spinner-small"></span> Buscando usuários...
            </div>
            <div v-else-if="!responsaveisList.length" class="autocomplete-no-results">
              Nenhum usuário encontrado
            </div>
            <ul v-else class="autocomplete-list">
              <li v-for="user in responsaveisList" :key="user.id" @click="selectResponsavel(user)"
                class="autocomplete-item">
                <i class="bi bi-person-circle"></i>
                <span class="user-text">{{ user.text }}</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Descrição -->
        <div class="form-group">
          <label class="form-label"><i class="bi bi-text-paragraph"></i> Descrição</label>
          <textarea class="form-control" v-model="form.detalhes" placeholder="Detalhes sobre a tarefa..."
            rows="4"></textarea>
        </div>

        <!-- Checkbox Notificar -->
        <div class="form-check-group">
          <label class="form-checkbox-label">
            <input type="checkbox" v-model="form.notificar" :true-value="1" :false-value="0" class="form-checkbox">
            <span><i class="bi bi-bell"></i> Enviar notificação no prazo</span>
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-ghost" @click="$emit('close')">Cancelar</button>
        <button class="btn btn-primary" @click="onSubmit">
          <i class="bi" :class="isEdit ? 'bi-check-circle' : 'bi-plus-circle'"></i>
          {{ isEdit ? 'Salvar Alterações' : 'Criar Tarefa' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Modal de Edição/Criação de Tarefas
 * @version 1.3.6
 */
import { reactive, ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { apiFetch } from '../../../services/api'
import { useSearchAutocomplete } from '../../../composables/useSearchAutocomplete'
import MemoriaCalculoPrazo from '../../widgets/MemoriaCalculoPrazo.vue'
import PrazoAConferirBadge from '../../widgets/PrazoAConferirBadge.vue'
import { prazoAConferir } from '../../../utils/prazos'

const props = defineProps({
  taskData: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'submit', 'warn'])

const isEdit = computed(() => !!props.taskData)

const form = reactive({
  tarefa: '',
  prazo: '',
  status: '2',
  prioridade: '3',
  responsavel: null,
  detalhes: '',
  notificar: 0
})

const responsavelBusca = ref('')

const {
  results: responsaveisList,
  isLoading: loadingResponsaveis,
  showDropdown,
  onInput: searchResponsaveis,
  clear: clearAutocomplete
} = useSearchAutocomplete({
  // Só quem está marcado como "pode ser responsável por tarefas" aparece na busca.
  searchEndpoint: (query: string) => `usuarios/buscarUsuario?contexto=tarefas&term=${encodeURIComponent(query)}`
})

function onResponsavelInput() {
  form.responsavel = null // Reset actual ID if they continue typing
  searchResponsaveis(responsavelBusca.value)
}

function onResponsavelFocus() {
  if (responsavelBusca.value.trim().length >= 2) {
    showDropdown.value = true
  }
}

function selectResponsavel(user) {
  form.responsavel = user.id
  responsavelBusca.value = user.text
  showDropdown.value = false
}

function clearResponsavel() {
  form.responsavel = null
  responsavelBusca.value = ''
  clearAutocomplete()
}

const clickOutsideHandler = (event) => {
  const dropdown = document.querySelector('.autocomplete-results')
  const input = document.querySelector('#responsavel-input')
  if (dropdown && !dropdown.contains(event.target) && event.target !== input) {
    showDropdown.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', clickOutsideHandler)

  if (props.taskData) {
    form.tarefa = props.taskData.tarefa || ''
    form.prazo = props.taskData.prazo || ''
    form.status = String(props.taskData.status || '2')
    form.prioridade = String(props.taskData.prioridade || '3')
    form.responsavel = props.taskData.responsavel || null
    responsavelBusca.value = props.taskData.responsavel_nome || props.taskData.responsavel_username || ''
    form.detalhes = props.taskData.detalhes || ''
    form.notificar = Number(props.taskData.notificar) === 1 ? 1 : 0
  } else {
    // Set default deadline to today + 7 days
    const date = new Date()
    date.setDate(date.getDate() + 7)
    form.prazo = date.toISOString().split('T')[0]
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('click', clickOutsideHandler)
})

function onSubmit() {
  if (!form.tarefa) {
    emit('warn', 'Informe o título da tarefa')
    return
  }
  if (!form.prazo) {
    emit('warn', 'Informe o prazo da tarefa')
    return
  }

  // Clone payload and format types correctly
  const payload: Record<string, any> = {
    ...form,
    status: Number(form.status),
    prioridade: Number(form.prioridade),
    notificar: Number(form.notificar),
    responsavel_nome: form.responsavel ? responsavelBusca.value : ''
  }

  if (isEdit.value) {
    payload.id_tarefa = props.taskData.id_tarefa || props.taskData.id
  }

  emit('submit', payload)
}
</script>

<style scoped>
/* Premium Autocomplete & Form layout styling */
.position-relative {
  position: relative;
}

.input-group-autocomplete {
  position: relative;
  display: flex;
  align-items: center;
}

.input-icon-left {
  position: absolute;
  left: 12px;
  color: var(--ink-muted);
  font-size: 0.95rem;
  pointer-events: none;
  display: flex;
  align-items: center;
}

.input-group-autocomplete .form-control {
  padding-left: 36px;
  padding-right: 36px;
  width: 100%;
}

.btn-clear-input {
  position: absolute;
  right: 12px;
  background: transparent;
  border: none;
  color: var(--ink-muted);
  cursor: pointer;
  padding: 0;
  display: flex;
  align-items: center;
  font-size: 0.95rem;
  transition: color 0.2s ease;
}

.btn-clear-input:hover {
  color: var(--danger);
}

.autocomplete-results {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  z-index: 1100;
  background: rgba(18, 30, 49, 0.95);
  backdrop-filter: blur(10px);
  border: 1px solid var(--border);
  border-radius: 8px;
  margin-top: 4px;
  max-height: 200px;
  overflow-y: auto;
}

.autocomplete-loading,
.autocomplete-no-results {
  padding: 12px;
  font-size: 0.82rem;
  color: var(--ink-muted);
  display: flex;
  align-items: center;
  gap: 8px;
}

.spinner-small {
  width: 12px;
  height: 12px;
  border: 2px solid var(--border);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  display: inline-block;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.autocomplete-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.autocomplete-item {
  padding: 10px 12px;
  font-size: 0.83rem;
  color: var(--ink-dim);
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  transition: all 0.2s ease;
}

.autocomplete-item:last-child {
  border-bottom: none;
}

.autocomplete-item:hover {
  background: rgba(212, 175, 55, 0.1);
  color: var(--accent);
  padding-left: 16px;
}

.form-checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 0.83rem;
  color: var(--ink-dim);
  user-select: none;
  margin-top: 6px;
}

.form-checkbox {
  width: 16px;
  height: 16px;
  accent-color: var(--accent);
  cursor: pointer;
}
</style>
