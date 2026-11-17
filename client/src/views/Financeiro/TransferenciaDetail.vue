<template>
  <div class="page-content animate-fade-in pb-5">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'list')">Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <a href="#" @click.prevent="$emit('set-view', 'transferencias')">Lista de Transferências</a>
      <i class="bi bi-chevron-right"></i>
      <span>{{ isEdit ? 'Editar Transferência' : 'Nova Transferência' }}</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>{{ isEdit ? 'Editar Transferência' : 'Nova Transferência' }}</h1>
        <p>{{ isEdit ? 'Modifique os valores e rateio' : 'Cadastre uma nova transferência e defina o rateio' }}</p>
      </div>
      <div style="display:flex;gap:.5rem;">
        <button class="btn btn-outline-secondary" @click="$emit('set-view', 'transferencias')" title="Voltar para lista de transferências" aria-label="Voltar para lista de transferências">
          <i class="bi bi-arrow-left-circle" aria-hidden="true"></i> Voltar
        </button>
        <button class="btn btn-primary" @click="saveTransferencia" :disabled="saving" title="Salvar transferência" aria-label="Salvar transferência">
          <i class="bi bi-save" aria-hidden="true"></i> {{ saving ? 'Salvando...' : 'Salvar Transferência' }}
        </button>
      </div>
    </div>

    <div class="content-grid" style="margin-top: 1.5rem;">
      <!-- Main Form Area -->
      <div>
        <div class="card glass-panel form-card">
          <div class="card-header">
            <h3><i class="bi bi-arrow-left-right"></i> Dados da Transferência</h3>
          </div>
          
          <div class="card-body">
            <div class="form-row col-1">
              <div class="form-group">
                <label for="transferencia" class="required-field"><i class="bi bi-fonts"></i> Descrição / Motivo</label>
                <input type="text" class="form-control" id="transferencia" v-model="form.transferencia" placeholder="Ex: Transferência para Caixa Interno" :class="{'is-invalid': errors.transferencia}">
                <small class="text-danger" style="font-size: 12px; margin-top: 4px;" v-if="errors.transferencia">{{ errors.transferencia }}</small>
              </div>
            </div>

            <div class="form-row col-2">
              <div class="form-group">
                <label for="valor" class="required-field"><i class="bi bi-cash-stack"></i> Valor (R$)</label>
                <div class="input-group">
                  <span class="input-group-addon" style="background:var(--input-bg); border-color:var(--border-color); color:var(--text-main);">R$</span>
                  <input type="text" class="form-control" id="valor" v-model="displayValor" @blur="formatDisplayValor" @input="recalcRateioAutomatico" placeholder="0,00" :class="{'is-invalid': errors.valor}">
                </div>
                <small class="text-danger" style="font-size: 12px; margin-top: 4px;" v-if="errors.valor">{{ errors.valor }}</small>
              </div>

              <div class="form-group">
                <label for="data_transferencia" class="required-field"><i class="bi bi-calendar-event"></i> Data</label>
                <input type="date" class="form-control" id="data_transferencia" v-model="form.data_transferencia" :class="{'is-invalid': errors.data_transferencia}">
                <small class="text-danger" style="font-size: 12px; margin-top: 4px;" v-if="errors.data_transferencia">{{ errors.data_transferencia }}</small>
              </div>
            </div>

            <div class="form-row col-2">
              <div class="form-group">
                <label for="id_conta_origem" class="required-field"><i class="bi bi-dash-circle" style="color:var(--danger)"></i> Conta de Origem</label>
                <select class="form-control" id="id_conta_origem" v-model="form.id_conta_origem" :class="{'is-invalid': errors.id_conta_origem}">
                  <option value="">Selecione a Origem...</option>
                  <option v-for="c in contas" :key="c.id_conta" :value="c.id_conta">
                    {{ c.conta }}
                  </option>
                </select>
                <small class="text-danger" style="font-size: 12px; margin-top: 4px;" v-if="errors.id_conta_origem">{{ errors.id_conta_origem }}</small>
              </div>

              <div class="form-group">
                <label for="id_conta_destino" class="required-field"><i class="bi bi-plus-circle" style="color:var(--success)"></i> Conta de Destino</label>
                <select class="form-control" id="id_conta_destino" v-model="form.id_conta_destino" :class="{'is-invalid': errors.id_conta_destino}">
                  <option value="">Selecione o Destino...</option>
                  <option v-for="c in contas" :key="c.id_conta" :value="c.id_conta">
                    {{ c.conta }}
                  </option>
                </select>
                <small class="text-danger" style="font-size: 12px; margin-top: 4px;" v-if="errors.id_conta_destino">{{ errors.id_conta_destino }}</small>
              </div>
            </div>
          </div>
        </div>

        <!-- Rateio Module -->
        <div class="card glass-panel form-card" style="margin-top: 24px;">
          <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h3><i class="bi bi-diagram-3" aria-hidden="true"></i> Rateio</h3>
            <div style="display:flex; gap: 8px;">
              <button class="btn btn-sm btn-outline-primary" @click="dividirIgualmente" v-if="form.rateio.length > 0" title="Dividir valor igualmente entre os sócios selecionados" aria-label="Dividir valor igualmente">
                <i class="bi bi-calculator" aria-hidden="true"></i> Dividir Igualmente
              </button>
              <button class="btn btn-sm btn-success" @click="addRateioRow" title="Adicionar nova linha de rateio" aria-label="Adicionar rateio">
                <i class="bi bi-plus-circle" aria-hidden="true"></i> Adicionar Rateio
              </button>
            </div>
          </div>
          
          <div class="card-body">
            <Transition name="fade" mode="out-in">
              <div v-if="!form.rateio.length" class="empty-rateio text-center">
                 <i class="bi bi-people" style="font-size: 2rem; color: var(--ink-muted); margin-bottom: 10px; display: block;" aria-hidden="true"></i>
                 <p style="color: var(--ink-muted); margin:0;">Nenhum rateio configurado. O valor será contabilizado integralmente sem divisão.</p>
              </div>
              
              <div class="rateio-list" v-else>
                <TransitionGroup name="fade" tag="div">
                  <div v-for="(r, index) in form.rateio" :key="index" class="rateio-item">
                    <div class="form-row" style="margin-bottom:0; align-items: center;">
                      <div class="form-group col-sm-8" style="margin-bottom:0; flex: 1;">
                        <label :for="'rateio-user-' + index" class="sr-only" style="display:none;">Sócio / Advogado</label>
                        <select :id="'rateio-user-' + index" class="form-control" v-model="r.id" style="font-size: 0.9rem; padding: 0.4rem 0.75rem;" aria-label="Sócio ou Advogado para rateio">
                          <option value="">Selecione o Sócio/Advogado...</option>
                          <option v-for="u in userOptions" :key="u.id" :value="u.id">{{ u.text || u.username || u.nome || u.name }}</option>
                        </select>
                      </div>
                      <div class="form-group col-sm-4 d-flex align-items-center" style="margin-bottom:0; gap:10px; flex: 1;">
                        <div class="input-group">
                          <span class="input-group-addon" style="font-size: 0.8rem; padding: 0.4rem 0.5rem;">R$</span>
                          <label :for="'rateio-valor-' + index" class="sr-only" style="display:none;">Valor do rateio</label>
                          <input :id="'rateio-valor-' + index" type="text" class="form-control" v-model="r.valor" @blur="formatRateioValor(r)" placeholder="0,00" style="font-size: 0.9rem; padding: 0.4rem 0.75rem;" aria-label="Valor do rateio individual">
                        </div>
                      </div>
                      <button class="btn btn-sm btn-outline-danger" @click="removeRateioRow(index)" title="Remover linha de rateio" aria-label="Remover rateio" style="padding: 0.4rem 0.75rem; border-color: transparent;">
                        <i class="bi bi-trash" aria-hidden="true"></i>
                      </button>
                    </div>
                  </div>
                </TransitionGroup>

                <!-- Rateio Summary Footer -->
                <div class="rateio-footer" :class="{'has-error': totalRateioError}">
                  <div class="rateio-footer-info" v-if="totalRateioError">
                     <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> O valor do rateio difere do valor total da transferência!
                  </div>
                  <div class="rateio-footer-info" v-else>
                     <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Valores conferem.
                  </div>
                  <div class="rateio-footer-total">
                     Total Dividido: <strong>{{ formatCurrency(totalRateioReal) }}</strong> / <span style="color:var(--ink-muted);">{{ formatCurrency(valorNum) }}</span>
                  </div>
                </div>
              </div>
            </Transition>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { apiFetch, formatDate, formatCurrency, formatNumberBr } from '../../services/api'

const props = defineProps({
  transferenciaId: {
    type: [Number, String],
    default: null
  }
})
const emit = defineEmits(['set-view', 'toast'])

const isEdit = computed(() => !!props.transferenciaId)
const saving = ref(false)

const form = reactive({
  id_transferencia: null,
  transferencia: '',
  data_transferencia: new Date().toISOString().split('T')[0],
  id_conta_origem: '',
  id_conta_destino: '',
  valor: '',
  rateio: []
})

const displayValor = ref('')
const errors = reactive<Record<string, string>>({})
const contas = ref([])
const userOptions = ref([])

// Helper: parse R$ para number
function parseNumberBr(str) {
  if (!str) return 0
  if (typeof str === 'number') return str
  const clean = str.replace(/[^\d,-]/g, '').replace(',', '.')
  return parseFloat(clean) || 0
}

const valorNum = computed(() => parseNumberBr(displayValor.value))

const totalRateioReal = computed(() => {
  return form.rateio.reduce((acc, r) => acc + parseNumberBr(r.valor), 0)
})

const totalRateioError = computed(() => {
  if (form.rateio.length === 0) return false
  const diff = Math.abs(totalRateioReal.value - valorNum.value)
  return diff > 0.01 // tolerancia centavos
})

function formatDisplayValor() {
  const v = parseNumberBr(displayValor.value)
  if (v > 0) {
    displayValor.value = formatNumberBr(v)
    form.valor = displayValor.value
  } else {
    displayValor.value = ''
    form.valor = ''
  }
}

function formatRateioValor(r) {
  const v = parseNumberBr(r.valor)
  if (v > 0) {
    r.valor = formatNumberBr(v)
  } else {
    r.valor = ''
  }
}

function addRateioRow() {
  form.rateio.push({ id: '', valor: '' })
}

function removeRateioRow(index) {
  form.rateio.splice(index, 1)
}

function dividirIgualmente() {
  if (!form.rateio.length || !valorNum.value) return
  const part = valorNum.value / form.rateio.length
  form.rateio.forEach(r => {
    r.valor = formatCurrency(part)
  })
}

function recalcRateioAutomatico() {
  // if all users have something and we change value, re-divide if length > 0
  // for now, we leave manual "Dividir Igualmente"
}

async function loadData() {
  try {
    const resContas = await apiFetch('financeiro/contas/listar')
    if (resContas && resContas.success) {
      contas.value = resContas.data || []
    }
    
    const resUsers = await apiFetch('usuarios/participa-rateio')
    if (resUsers) {
      userOptions.value = resUsers.results || resUsers.data || (Array.isArray(resUsers) ? resUsers : [])
    }
  } catch (error) {
    console.error('Erro ao carregar dados:', error)
  }
}

function validate() {
  Object.keys(errors).forEach(k => delete errors[k])
  if (!form.transferencia) errors.transferencia = 'Descrição obrigatória'
  if (!displayValor.value) errors.valor = 'Valor obrigatório'
  if (!form.data_transferencia) errors.data_transferencia = 'Data obrigatória'
  if (!form.id_conta_origem) errors.id_conta_origem = 'Selecione a conta de origem'
  if (!form.id_conta_destino) errors.id_conta_destino = 'Selecione a conta de destino'
  if (form.id_conta_origem && form.id_conta_destino && form.id_conta_origem === form.id_conta_destino) {
    errors.id_conta_destino = 'A conta destino não pode ser igual à de origem'
  }
  
  if (totalRateioError.value) {
    emit('toast', { msg: 'Valor do rateio difere do valor total da transferência!', type: 'danger' })
    return false
  }

  return Object.keys(errors).length === 0
}

async function saveTransferencia() {
  if (!validate()) return
  
  saving.value = true
  try {
    const payload: Record<string, any> = {
      transferencia: form.transferencia,
      data_transferencia: form.data_transferencia,
      id_conta_origem: form.id_conta_origem,
      id_conta_destino: form.id_conta_destino,
      valor: valorNum.value
    }
    if (isEdit.value) payload.id_transferencia = props.transferenciaId

    if (form.rateio.length > 0) {
      payload.rateio = form.rateio
        .filter(r => r.id)
        .map(r => ({ id: r.id, valor: parseNumberBr(r.valor) }))
    }

    const res = await apiFetch('financeiro/transferencias/salvar', {
      method: 'POST',
      body: payload
    })
    
    if (res && res.success) {
      emit('toast', { msg: res.message || 'Transferência salva com sucesso!', type: 'success' })
      emit('set-view', 'transferencias')
    } else {
      emit('toast', { msg: res.message || 'Erro ao salvar transferência', type: 'danger' })
    }
  } catch (error) {
    console.error('Erro ao salvar:', error)
    emit('toast', { msg: 'Erro de comunicação ao salvar', type: 'danger' })
  } finally {
    saving.value = false
  }
}

async function loadTransferencia() {
  try {
    const res = await apiFetch(`financeiro/transferencias/detalhes/${props.transferenciaId}`)
    if (res && res.success && res.data) {
      Object.assign(form, res.data)
      if (form.data_transferencia) {
        form.data_transferencia = form.data_transferencia.split(' ')[0]
      }
      displayValor.value = formatNumberBr(form.valor)
      form.valor = displayValor.value

      if (!form.rateio) {
        form.rateio = []
      } else if (typeof form.rateio === 'string') {
        try {
          form.rateio = JSON.parse(form.rateio) || []
        } catch (e) {
          form.rateio = []
        }
      }
      form.rateio.forEach(r => {
        r.id = String(r.id || r.user_id || '')
        if (r.id && !userOptions.value.some(u => String(u.id) === r.id)) {
          userOptions.value.push({ id: r.id, text: `Sócio #${r.id} (Inativo/Ausente)` })
        }
        const val = parseFloat(r.valor)
        if (!isNaN(val)) {
          r.valor = formatNumberBr(val)
        }
      })
    } else {
      emit('toast', { msg: (res && res.message) || 'Erro ao carregar transferência', type: 'danger' })
    }
  } catch (error) {
    console.error('Erro ao carregar transferência:', error)
    emit('toast', { msg: 'Erro de comunicação ao carregar transferência', type: 'danger' })
  }
}

onMounted(async () => {
  await loadData()
  if (isEdit.value) {
    await loadTransferencia()
  }
})
</script>

<style scoped>
.form-card { border: none; overflow: hidden; margin-bottom: 24px; }
.card-header { background: rgba(0,0,0,0.03); border-bottom: 1px solid var(--border-color); padding: 16px 20px; }
.card-header h3 { margin: 0; font-size: 1.1rem; color: var(--text-main); font-weight: 600; display:flex; align-items:center; gap:8px;}
.card-body { padding: 20px; }
.form-row { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 15px; }
.form-row.col-2 > .form-group { flex: 1; min-width: 250px; }
.form-row.col-1 > .form-group { flex: 1; width: 100%; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: var(--ink-lighter); font-size: 0.9rem; }
.form-group label.required-field::after { content: "*"; color: var(--danger); margin-left: 4px; }

.rateio-list { border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; }
.rateio-item { padding: 12px 16px; border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.02); }
.rateio-item:last-child { border-bottom: none; }
.rateio-footer { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: rgba(0,0,0,0.04); font-size: 0.9rem; }
.rateio-footer.has-error { background: rgba(220, 53, 69, 0.1); color: var(--danger); }
.rateio-footer-total { font-size: 1rem; }
</style>
