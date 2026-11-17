<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box">
      <div class="modal-header">
        <h2><i class="bi bi-link-45deg"></i> Vincular Processo</h2>
        <button class="modal-close" @click="$emit('close')" aria-label="Fechar modal">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="modal-body">
        <!-- Process Autocomplete -->
        <div class="form-group position-relative" style="position: relative;">
          <label class="form-label"><i class="bi bi-search"></i> Buscar Processo (Digite o CNJ ou Título) *</label>
          <div class="input-group-autocomplete">
            <span class="input-icon-left"><i class="bi bi-folder-symlink"></i></span>
            <input id="processo-input" class="form-control" type="text" v-model="processoBusca"
              placeholder="Digite ao menos 2 dígitos do CNJ ou nome..." autocomplete="off" @input="onProcessoInput"
              @focus="onProcessoFocus">
            <button v-if="form.id_processo_b || processoBusca" class="btn-clear-input" type="button"
              @click="clearProcesso" title="Limpar processo selecionado">
              <i class="bi bi-x-circle-fill"></i>
            </button>
          </div>

          <!-- Autocomplete results dropdown -->
          <div v-if="showDropdown" class="autocomplete-results shadow-lg">
            <div v-if="loadingProcessos" class="autocomplete-loading">
              <span class="spinner-small"></span> Buscando processos...
            </div>
            <div v-else-if="!processosList.length" class="autocomplete-no-results">
              Nenhum processo encontrado
            </div>
            <ul v-else class="autocomplete-list">
              <li v-for="proc in processosList" :key="proc.id" @click="selectProcesso(proc)" class="autocomplete-item">
                <i :class="proc.is_arquivado ? 'bi bi-archive' : 'bi bi-file-earmark-text'"></i>
                <span class="user-text">{{ proc.text }}</span>
                <span v-if="proc.is_arquivado" class="badge-arquivado">Arquivado</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Tipo de Vínculo -->
        <div class="form-group">
          <label class="form-label"><i class="bi bi-diagram-3"></i> Tipo de Vínculo *</label>
          <select class="form-control form-select" v-model="form.tipo_vinculo" required>
            <option value="Conexo">Conexo</option>
            <option value="Recurso">Recurso</option>
            <option value="Incidente">Incidente</option>
            <option value="Apenso">Apenso</option>
            <option value="Dependente">Dependente</option>
            <option value="Cautelar">Cautelar</option>
            <option value="Embargos">Embargos</option>
            <option value="Cumprimento">Cumprimento de Sentença</option>
            <option value="Relacionado">Relacionado</option>
            <option value="Outro">Outro</option>
          </select>
        </div>

        <!-- Descrição/Observações do Vínculo -->
        <div class="form-group">
          <label class="form-label"><i class="bi bi-card-text"></i> Observações / Descrição do Vínculo</label>
          <textarea class="form-control" v-model="form.descricao_vinculo"
            placeholder="Ex: Apenso principal de execução de honorários, Recurso de apelação..." rows="3"></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-ghost" @click="$emit('close')">Cancelar</button>
        <button class="btn btn-primary" @click="onSubmit">
          <i class="bi bi-check-circle"></i> Vincular Processo
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
   * Modal de Vinculação de Processos
   * @version 1.3.8
   */
import { reactive, ref, onMounted, onBeforeUnmount } from 'vue'
import { apiFetch } from '../../../services/api'
import { useSearchAutocomplete } from '../../../composables/useSearchAutocomplete'

const props = defineProps({
  currentProcessId: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['close', 'submit', 'warn'])

const form = reactive({
  id_processo_b: null,
  tipo_vinculo: 'Relacionado',
  descricao_vinculo: ''
})

const processoBusca = ref('')

const {
  results: processosList,
  isLoading: loadingProcessos,
  showDropdown,
  onInput: searchProcessos,
  clear: clearAutocomplete
} = useSearchAutocomplete({
  searchEndpoint: 'processos/buscar',
  processResults: (results) => results.filter(p => Number(p.id) !== Number(props.currentProcessId))
})

function onProcessoInput() {
  form.id_processo_b = null
  searchProcessos(processoBusca.value)
}

function onProcessoFocus() {
  if (processoBusca.value.trim().length >= 2) {
    showDropdown.value = true
  }
}

function selectProcesso(proc) {
  form.id_processo_b = proc.id
  processoBusca.value = proc.text
  showDropdown.value = false
}

function clearProcesso() {
  form.id_processo_b = null
  processoBusca.value = ''
  clearAutocomplete()
}

const clickOutsideHandler = (event) => {
  const dropdown = document.querySelector('.autocomplete-results')
  const input = document.querySelector('#processo-input')
  if (dropdown && !dropdown.contains(event.target) && event.target !== input) {
    showDropdown.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', clickOutsideHandler)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', clickOutsideHandler)
})

function onSubmit() {
  if (!form.id_processo_b) {
    emit('warn', 'Selecione um processo na busca para poder vincular.')
    return
  }
  if (!form.tipo_vinculo) {
    emit('warn', 'Selecione o tipo de vínculo.')
    return
  }

  emit('submit', {
    id_processo_a: props.currentProcessId,
    id_processo_b: form.id_processo_b,
    tipo_vinculo: form.tipo_vinculo,
    descricao_vinculo: form.descricao_vinculo
  })
}
</script>

<style scoped>
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

.badge-arquivado {
  font-size: 0.68rem;
  background: rgba(108, 117, 125, 0.25);
  color: var(--ink-muted);
  padding: 2px 6px;
  border-radius: 4px;
  margin-left: auto;
  white-space: nowrap;
}
</style>
