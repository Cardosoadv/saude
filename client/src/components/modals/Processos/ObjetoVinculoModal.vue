<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box">
      <div class="modal-header">
        <h2><i class="bi bi-houses"></i> Vincular Objeto</h2>
        <button class="modal-close" @click="$emit('close')" aria-label="Fechar modal">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="modal-body">
        <!-- Tabs -->
        <div class="obj-tabs" role="tablist">
          <button type="button" class="obj-tab" :class="{ active: aba === 'existente' }" @click="aba = 'existente'">
            <i class="bi bi-search"></i> Vincular Existente
          </button>
          <button type="button" class="obj-tab" :class="{ active: aba === 'novo' }" @click="aba = 'novo'">
            <i class="bi bi-plus-circle"></i> Novo Objeto
          </button>
        </div>

        <!-- Aba: Vincular Existente -->
        <div v-if="aba === 'existente'">
          <div class="form-group position-relative" style="position: relative;">
            <label class="form-label"><i class="bi bi-search"></i> Buscar Objeto *</label>
            <div class="input-group-autocomplete">
              <span class="input-icon-left"><i class="bi bi-houses"></i></span>
              <input id="objeto-input" class="form-control" type="text" v-model="buscaText"
                placeholder="Digite cidade, bairro ou código..." autocomplete="off" @input="onInput"
                @focus="onFocus">
              <button v-if="objetoSelecionado || buscaText" class="btn-clear-input" type="button"
                @click="clearSelection" title="Limpar seleção">
                <i class="bi bi-x-circle-fill"></i>
              </button>
            </div>

            <div v-if="showDropdown" class="autocomplete-results shadow-lg">
              <div v-if="isLoading" class="autocomplete-loading">
                <span class="spinner-small"></span> Buscando...
              </div>
              <div v-else-if="!results.length" class="autocomplete-no-results">
                Nenhum objeto encontrado
              </div>
              <ul v-else class="autocomplete-list">
                <li v-for="item in results" :key="item.id" @click="selectItem(item)" class="autocomplete-item">
                  <i class="bi bi-house"></i>
                  <span class="user-text">{{ item.text }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Aba: Novo Objeto -->
        <div v-else>
          <div class="form-row" style="display:grid;grid-template-columns: 140px 1fr;gap: 1rem;">
            <div class="form-group">
              <label class="form-label">CEP</label>
              <input type="text" class="form-control" v-model="novo.cep" @input="onCepInput" placeholder="00000-000" maxlength="9" inputmode="numeric">
            </div>
            <div class="form-group">
              <label class="form-label">Cidade</label>
              <input type="text" class="form-control" v-model="novo.cidade">
            </div>
          </div>
          <div class="form-row" style="display:grid;grid-template-columns: 1fr 100px;gap: 1rem;">
            <div class="form-group">
              <label class="form-label">Logradouro</label>
              <input type="text" class="form-control" v-model="novo.logradouro">
            </div>
            <div class="form-group">
              <label class="form-label">Número</label>
              <input type="text" class="form-control" v-model="novo.numero">
            </div>
          </div>
          <div class="form-row" style="display:grid;grid-template-columns: repeat(3, 1fr);gap: 1rem;">
            <div class="form-group">
              <label class="form-label required-field">Bairro</label>
              <input type="text" class="form-control" v-model="novo.bairro">
            </div>
            <div class="form-group">
              <label class="form-label required-field">Quadra</label>
              <input type="text" class="form-control" v-model="novo.quadra">
            </div>
            <div class="form-group">
              <label class="form-label required-field">Lote</label>
              <input type="text" class="form-control" v-model="novo.lote">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Inscrição Imobiliária</label>
              <input type="text" class="form-control" v-model="novo.inscricao">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Comentários</label>
              <textarea class="form-control" v-model="novo.comentarios" rows="2"></textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-ghost" @click="$emit('close')">Cancelar</button>
        <button class="btn btn-primary" @click="onSubmit">
          <i class="bi bi-check-circle"></i> {{ aba === 'existente' ? 'Vincular Objeto' : 'Criar e Vincular' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted, onBeforeUnmount } from 'vue'
import { useSearchAutocomplete } from '../../../composables/useSearchAutocomplete'
import { buscarCep, formatarCep } from '../../../utils/cep'

const props = defineProps({
  currentProcessId: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['close', 'submit', 'warn'])

const aba = ref('existente')
const buscaText = ref('')
const objetoSelecionado = ref(null)

const novo = reactive({
  cep: '',
  cidade: '',
  logradouro: '',
  numero: '',
  bairro: '',
  quadra: '',
  lote: '',
  inscricao: '',
  comentarios: ''
})

const {
  results,
  isLoading,
  showDropdown,
  onInput: searchObjetos,
  clear: clearAutocomplete
} = useSearchAutocomplete({ searchEndpoint: 'objetos/buscar' })

let ultimoCepConsultado = ''

async function onCepInput() {
  novo.cep = formatarCep(novo.cep)
  const digitos = novo.cep.replace(/\D/g, '')
  if (digitos.length !== 8 || digitos === ultimoCepConsultado) return
  ultimoCepConsultado = digitos
  try {
    const endereco = await buscarCep(digitos)
    if (!endereco) {
      emit('warn', 'CEP não encontrado.')
      return
    }
    novo.logradouro = endereco.logradouro || novo.logradouro
    novo.bairro = endereco.bairro || novo.bairro
    novo.cidade = endereco.cidade || novo.cidade
  } catch (err) {
    console.error(err)
    emit('warn', 'Não foi possível consultar o CEP agora.')
  }
}

function onInput() {
  objetoSelecionado.value = null
  searchObjetos(buscaText.value)
}

function onFocus() {
  if (buscaText.value.trim().length >= 2) {
    showDropdown.value = true
  }
}

function selectItem(item) {
  objetoSelecionado.value = item.id
  buscaText.value = item.text
  showDropdown.value = false
}

function clearSelection() {
  objetoSelecionado.value = null
  buscaText.value = ''
  clearAutocomplete()
}

const clickOutsideHandler = (event) => {
  const dropdown = document.querySelector('.autocomplete-results')
  const input = document.querySelector('#objeto-input')
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
  if (aba.value === 'existente') {
    if (!objetoSelecionado.value) {
      emit('warn', 'Selecione um objeto na busca para poder vincular.')
      return
    }
    emit('submit', {
      processo_id: props.currentProcessId,
      objeto_id: objetoSelecionado.value
    })
    return
  }

  if (!novo.bairro || !novo.quadra || !novo.lote) {
    emit('warn', 'Preencha Bairro, Quadra e Lote para criar o novo objeto.')
    return
  }

  emit('submit', {
    processo_id: props.currentProcessId,
    ...novo
  })
}
</script>

<style scoped>
.obj-tabs {
  display: flex;
  align-items: center;
  gap: 4px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 4px;
  width: fit-content;
  margin-bottom: 1rem;
}

.obj-tab {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 7px;
  font-size: 0.82rem;
  font-weight: 500;
  border: none;
  background: transparent;
  color: var(--ink-muted);
  cursor: pointer;
  transition: all 0.15s ease;
}

.obj-tab:hover {
  color: var(--ink);
  background: var(--card);
}

.obj-tab.active {
  background: var(--primary);
  color: #fff;
}

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
  max-height: 220px;
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

.form-row {
  margin-bottom: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.required-field::after {
  content: ' *';
  color: var(--danger);
  font-weight: bold;
}
</style>
