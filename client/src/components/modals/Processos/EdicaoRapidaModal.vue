<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box modal-large">
      <div class="modal-header">
        <h2><i class="bi bi-pencil-square"></i> Edição Rápida de Processo</h2>
        <button class="modal-close" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
      </div>

      <form @submit.prevent="onSubmit">
        <div class="modal-body">
          <div class="form-grid">
            <!-- Título do Processo -->
            <div class="form-group span-2">
              <label class="form-label"><i class="bi bi-card-heading"></i> Título do Processo</label>
              <input type="text" class="form-control" v-model="form.titulo_processo"
                placeholder="Ex: Ação de Cobrança contra Empresa X" maxlength="255">
            </div>

            <!-- Classe -->
            <div class="form-group">
              <label class="form-label"><i class="bi bi-folder"></i> Classe / Tipo Documento</label>
              <input type="text" class="form-control" v-model="form.classe" placeholder="Ex: Procedimento Comum Cível"
                maxlength="100">
            </div>

            <!-- Valor da Causa -->
            <div class="form-group">
              <label class="form-label"><i class="bi bi-currency-dollar"></i> Valor da Causa</label>
              <div class="input-group">
                <span class="input-group-addon">R$</span>
                <input type="text" class="form-control" :value="form.valorCausa"
                  @input="e => formatMoneyInput(e, 'valorCausa')" placeholder="0,00">
              </div>
            </div>

            <!-- Ramo do Direito -->
            <div class="form-group span-2">
              <label class="form-label"><i class="bi bi-diagram-3"></i> Ramo do Direito</label>
              <select class="form-control form-select" v-model="form.ramo_direito">
                <option value="">Selecione o Ramo do Direito...</option>
                <template v-for="ramo in ramos" :key="ramo.id">
                  <option :value="String(ramo.id)" class="option-parent">{{ ramo.nome }}</option>
                  <template v-if="ramo.filhos && ramo.filhos.length">
                    <template v-for="filho in ramo.filhos" :key="filho.id">
                      <option :value="String(filho.id)" class="option-child">&nbsp;&nbsp;&nbsp;↳ {{ filho.nome }}</option>
                      <template v-if="filho.filhos && filho.filhos.length">
                        <option v-for="neto in filho.filhos" :key="neto.id" :value="String(neto.id)" class="option-grandchild">
                          &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;• {{ neto.nome }}
                        </option>
                      </template>
                    </template>
                  </template>
                </template>
              </select>
            </div>

            <!-- Risco -->
            <div class="form-group">
              <label class="form-label"><i class="bi bi-exclamation-triangle"></i> Risco</label>
              <select class="form-control form-select" v-model="form.risco">
                <option value="">Selecione...</option>
                <option value="Remoto">Remoto</option>
                <option value="Possível">Possível</option>
                <option value="Provável">Provável</option>
              </select>
            </div>

            <!-- Provisão CPC 25 -->
            <div class="form-group">
              <label class="form-label"><i class="bi bi-piggy-bank"></i> Provisão CPC 25</label>
              <div class="input-group">
                <span class="input-group-addon">R$</span>
                <input type="text" class="form-control" :value="form.provisao_cpc25"
                  @input="e => formatMoneyInput(e, 'provisao_cpc25')" placeholder="0,00">
              </div>
            </div>

            <!-- Resultado -->
            <div class="form-group">
              <label class="form-label"><i class="bi bi-flag"></i> Resultado</label>
              <select class="form-control form-select" v-model="form.resultado">
                <option value="">Não Finalizado</option>
                <option value="Sucesso">Sucesso</option>
                <option value="Sucesso Parcial">Sucesso Parcial</option>
                <option value="Derrota">Derrota</option>
              </select>
            </div>

            <!-- Valor da Condenação -->
            <div class="form-group">
              <label class="form-label"><i class="bi bi-cash-stack"></i> Valor da Condenação</label>
              <div class="input-group">
                <span class="input-group-addon">R$</span>
                <input type="text" class="form-control" :value="form.valorCondenacao"
                  @input="e => formatMoneyInput(e, 'valorCondenacao')" placeholder="0,00">
              </div>
            </div>

            <!-- Data de Revisão -->
            <div class="form-group">
              <label class="form-label"><i class="bi bi-calendar-check"></i> Data de Revisão</label>
              <input type="date" class="form-control" v-model="form.dataRevisao">
            </div>

            <!-- Link da Pasta -->
            <div class="form-group">
              <label class="form-label"><i class="bi bi-folder-open"></i> Link da Pasta</label>
              <input type="url" class="form-control" v-model="form.pasta" placeholder="https://...">
            </div>

            <!-- Autocomplete Cliente -->
            <div class="form-group autocomplete-container client-autocomplete-container">
              <label class="form-label"><i class="bi bi-person"></i> Cliente</label>
              <div class="input-group">
                <input type="text" class="form-control" v-model="clientSearch" @input="onClientInput"
                  @focus="showClients = true"
                  placeholder="Digite para buscar cliente..." autocomplete="off">
                <button v-if="form.cliente_id" type="button" class="btn-clear" @click="clearClient"
                  title="Limpar cliente">
                  <i class="bi bi-x-circle-fill"></i>
                </button>
              </div>
              <ul v-if="showClients && clientResults.length" class="autocomplete-dropdown">
                <li v-for="res in clientResults" :key="res.id" @click="selectClient(res)">
                  {{ res.text }}
                </li>
              </ul>
              <small class="form-text-muted">Busque por nome ou CPF/CNPJ (mín. 2 carac.).</small>
            </div>

            <!-- Autocomplete Contrato Vinculado -->
            <div class="form-group autocomplete-container contract-autocomplete-container">
              <label class="form-label"><i class="bi bi-file-earmark-text"></i> Contrato Vinculado</label>
              <div class="input-group">
                <input type="text" class="form-control" v-model="contractSearch" @input="onContractInput"
                  @focus="showContracts = true"
                  placeholder="Digite para buscar contrato..." autocomplete="off">
                <button v-if="form.contrato_id" type="button" class="btn-clear" @click="clearContract"
                  title="Limpar contrato">
                  <i class="bi bi-x-circle-fill"></i>
                </button>
              </div>
              <ul v-if="showContracts && contractResults.length" class="autocomplete-dropdown">
                <li v-for="res in contractResults" :key="res.id" @click="selectContract(res)">
                  {{ res.text }}
                </li>
              </ul>
              <small class="form-text-muted">Busque por número ou nome (mín. 2 carac.).</small>
            </div>

            <!-- Comentário -->
            <div class="form-group span-2">
              <label class="form-label"><i class="bi bi-chat-left-text"></i> Comentário / Observações</label>
              <textarea class="form-control" rows="3" v-model="form.comentario"
                placeholder="Adicione observações sobre o processo..."></textarea>
            </div>

            <!-- Processo Encerrado Switch -->
            <div class="form-group span-2 flex-align">
              <div class="form-switch">
                <input type="checkbox" id="modal_processo_encerrado" class="switch-input" v-model="form.encerrado">
                <label for="modal_processo_encerrado" class="switch-label">
                  <i class="bi bi-lock"></i> Processo Encerrado
                </label>
              </div>
            </div>

            <!-- Data de Encerramento (Condicional) -->
            <transition name="slide-fade">
              <div v-if="form.encerrado" class="form-group span-2">
                <label class="form-label"><i class="bi bi-calendar-x"></i> Data de Encerramento</label>
                <input type="date" class="form-control" v-model="form.data_encerramento" required>
              </div>
            </transition>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" @click="$emit('close')" :disabled="saving">
            Cancelar
          </button>
          <button type="submit" class="btn btn-accent" :disabled="saving">
            <span v-if="saving"><i class="bi bi-arrow-repeat spin"></i> Salvando...</span>
            <span v-else><i class="bi bi-check-circle"></i> Salvar Alterações</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted, onUnmounted, watch } from 'vue'
import { apiFetch, toFormData } from '../../../services/api'

const props = defineProps({
  processoData: {
    type: Object,
    required: true
  }
})

// 'submit' declarado para o listener do pai não cair como evento nativo na div raiz
// (o submit do <form> borbulharia até ela e dispararia handleModalSubmit sem endpoint).
const emit = defineEmits(['close', 'success', 'warn', 'submit'])

// Form reactive state
const form = reactive({
  titulo_processo: props.processoData?.titulo_processo || '',
  classe: props.processoData?.tipoDocumento || props.processoData?.classe || '',
  valorCausa: '',
  valorCondenacao: '',
  provisao_cpc25: '',
  risco: props.processoData?.risco || '',
  resultado: props.processoData?.resultado || '',
  dataRevisao: props.processoData?.dataRevisao ? props.processoData.dataRevisao.split(' ')[0] : '',
  cliente_id: props.processoData?.cliente_id || '',
  contrato_id: props.processoData?.contrato_id || '',
  ramo_direito: props.processoData?.ramo_direito ? String(props.processoData.ramo_direito) : '',
  pasta: props.processoData?.pasta || '',
  comentario: props.processoData?.comentario || '',
  encerrado: props.processoData?.encerrado === 1 || props.processoData?.encerrado === '1' || false,
  data_encerramento: props.processoData?.data_encerramento ? props.processoData.data_encerramento.split(' ')[0] : new Date().toISOString().split('T')[0]
})

// UI & Data lookups state
const saving = ref(false)
const ramos = ref([])

// Client autocomplete
const clientSearch = ref('')
const selectedClientText = ref('')
const clientResults = ref([])
const showClients = ref(false)
let clientTimeout = null

// Contract autocomplete
const contractSearch = ref('')
const selectedContractText = ref('')
const contractResults = ref([])
const showContracts = ref(false)
let contractTimeout = null

function handleClickOutside(e) {
  const clientContainer = document.querySelector('.client-autocomplete-container')
  const contractContainer = document.querySelector('.contract-autocomplete-container')
  if (clientContainer && !clientContainer.contains(e.target)) {
    showClients.value = false
  }
  if (contractContainer && !contractContainer.contains(e.target)) {
    showContracts.value = false
  }
}

// Initialize money fields with formatting
onMounted(async () => {
  window.addEventListener('click', handleClickOutside)

  if (props.processoData?.valorCausa) {
    form.valorCausa = formatInitialMoney(props.processoData.valorCausa)
  }
  if (props.processoData?.valorCondenacao) {
    form.valorCondenacao = formatInitialMoney(props.processoData.valorCondenacao)
  }
  if (props.processoData?.provisao_cpc25) {
    form.provisao_cpc25 = formatInitialMoney(props.processoData.provisao_cpc25)
  }

  // Pre-load Client search input text
  if (props.processoData?.cliente_id) {
    selectedClientText.value = props.processoData.nome_cliente
      ? `Cliente #${props.processoData.cliente_id} - ${props.processoData.nome_cliente}`
      : `Cliente #${props.processoData.cliente_id}`
    clientSearch.value = selectedClientText.value
  }

  // Pre-load Contract search input text
  if (props.processoData?.contrato_id) {
    selectedContractText.value = props.processoData.contrato_nome
      ? `Contrato #${props.processoData.contrato_nome}`
      : `Contrato #${props.processoData.contrato_id}`
    contractSearch.value = selectedContractText.value
  }

  // Load law branches tree
  try {
    const res = await apiFetch('processos/ramos')
    if (res) {
      ramos.value = Array.isArray(res) ? res : (res.data || [])
    }
  } catch (err) {
    console.error('Erro ao buscar ramos:', err)
  }
})

onUnmounted(() => {
  window.removeEventListener('click', handleClickOutside)
})

// Watch to sync closure date required/value
watch(() => form.encerrado, (newVal) => {
  if (newVal && !form.data_encerramento) {
    form.data_encerramento = new Date().toISOString().split('T')[0]
  }
})

// Currency mask utility
function formatMoneyInput(e, field) {
  let val = e.target.value.replace(/\D/g, '')
  if (!val) {
    form[field] = ''
    return
  }
  val = (parseFloat(val) / 100).toFixed(2)
  val = val.replace('.', ',')
  val = val.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.')
  form[field] = val
}

function formatInitialMoney(value) {
  if (value === null || value === undefined || value === '') return ''
  const parsed = parseFloat(value)
  if (isNaN(parsed)) return ''
  return parsed.toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.')
}

// Autocomplete actions for Client
function onClientInput() {
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
  form.cliente_id = item.id
  selectedClientText.value = item.text
  clientSearch.value = item.text
  clientResults.value = []
  showClients.value = false
}

function clearClient() {
  form.cliente_id = ''
  selectedClientText.value = ''
  clientSearch.value = ''
  clientResults.value = []
  showClients.value = false
}

// Autocomplete actions for Contract
function onContractInput() {
  clearTimeout(contractTimeout)
  if (contractSearch.value.trim().length < 2) {
    contractResults.value = []
    showContracts.value = false
    return
  }
  contractTimeout = setTimeout(async () => {
    try {
      const res = await apiFetch('processos/contratos-busca?term=' + encodeURIComponent(contractSearch.value))
      if (res && res.results) {
        contractResults.value = res.results
        showContracts.value = true
      }
    } catch (err) {
      console.error(err)
    }
  }, 350)
}

function selectContract(item) {
  form.contrato_id = item.id
  selectedContractText.value = item.text
  contractSearch.value = item.text
  contractResults.value = []
  showContracts.value = false
}

function clearContract() {
  form.contrato_id = ''
  selectedContractText.value = ''
  contractSearch.value = ''
  contractResults.value = []
}

// Form Submission
async function onSubmit() {
  if (!form.titulo_processo) {
    emit('warn', 'O título do processo é obrigatório.')
    return
  }

  // URL Validation for Pasta Link
  if (form.pasta && !form.pasta.match(/^https?:\/\/.+/)) {
    emit('warn', 'O link da pasta deve começar com http:// ou https://')
    return
  }

  saving.value = true
  try {
    const payload = {
      titulo_processo: form.titulo_processo,
      classe: form.classe,
      valorCausa: form.valorCausa,
      valorCondenacao: form.valorCondenacao,
      provisao_cpc25: form.provisao_cpc25,
      risco: form.risco,
      resultado: form.resultado,
      dataRevisao: form.dataRevisao,
      cliente_id: form.cliente_id,
      contrato_id: form.contrato_id,
      ramo_direito: form.ramo_direito,
      pasta: form.pasta,
      comentario: form.comentario,
      encerrado: form.encerrado ? '1' : '',
      data_encerramento: form.encerrado ? form.data_encerramento : ''
    }

    const res = await apiFetch('processos/edicaorapida/' + props.processoData.id_processo, {
      method: 'POST',
      body: toFormData(payload)
    })

    if (res && res.success) {
      emit('success', res.message || 'Processo atualizado com sucesso!')
    } else {
      throw new Error(res.message || 'Erro ao atualizar dados.')
    }
  } catch (err) {
    emit('warn', err.message || 'Falha ao conectar ao servidor para atualizar o processo.')
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
/* Responsive wide modal styled under Ordo Juris theme rules */
.modal-large {
  max-width: 750px !important;
  width: 95%;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.span-2 {
  grid-column: span 2;
}

/* Custom Autocomplete dropdown styles */
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

.input-group-addon+.form-control {
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

/* Switch Styles */
.flex-align {
  display: flex;
  align-items: center;
  padding-top: 8px;
}

.form-switch {
  display: flex;
  align-items: center;
  gap: 10px;
}

.switch-input {
  width: 44px;
  height: 22px;
  appearance: none;
  background-color: var(--border);
  border-radius: 20px;
  position: relative;
  outline: none;
  cursor: pointer;
  transition: background-color 0.2s;
}

.switch-input:checked {
  background-color: var(--accent);
}

.switch-input::before {
  content: '';
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background-color: white;
  position: absolute;
  top: 2px;
  left: 2px;
  transition: transform 0.2s;
}

.switch-input:checked::before {
  transform: translateX(22px);
}

.switch-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--ink-dim);
  cursor: pointer;
}

/* Branch dropdown specific option styles */
.option-parent {
  font-weight: 700;
  color: var(--primary);
}

.option-child {
  font-weight: 500;
  color: var(--ink-dim);
}

.option-grandchild {
  font-size: 0.8rem;
  color: var(--ink-muted);
}

.form-text-muted {
  font-size: 0.72rem;
  color: var(--ink-muted);
  display: block;
  margin-top: 4px;
}

/* Modal body overflow rules */
.modal-body {
  max-height: 70vh;
  overflow-y: auto;
}

/* Animations */
.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: all 0.25s ease-out;
}

.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateY(-8px);
  opacity: 0;
}

.spin {
  animation: spin 1s linear infinite;
  display: inline-block;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }

  100% {
    transform: rotate(360deg);
  }
}

@media (max-width: 600px) {
  .form-grid {
    grid-template-columns: 1fr;
  }

  .span-2 {
    grid-column: span 1;
  }
}
</style>
