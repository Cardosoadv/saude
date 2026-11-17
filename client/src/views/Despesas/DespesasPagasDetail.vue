<template>
  <div class="page-content animate-fade-in pb-5">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="#" @click.prevent="$emit('set-view', 'list')">Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <a href="#" @click.prevent="$emit('set-view', 'despesas-pagas')">Pagamentos</a>
      <i class="bi bi-chevron-right"></i>
      <span>Editar Pagamento</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1>Editar Pagamento #{{ despesaId }}</h1>
        <p>Modifique os dados do pagamento e rateio</p>
      </div>
      <div style="display:flex;gap:.5rem;">
        <button class="btn btn-info" v-if="form.despesa_id" @click="openDespesaOriginal">
          <i class="bi bi-pencil-square"></i> Editar Despesa Original
        </button>
        <button class="btn btn-outline-secondary" @click="$emit('set-view', 'despesas-pagas')">
          <i class="bi bi-arrow-left-circle"></i> Voltar
        </button>
        <button class="btn btn-primary" @click="saveDespesa" :disabled="saving">
          <i class="bi bi-save"></i> {{ saving ? 'Salvando...' : 'Salvar Pagamento' }}
        </button>
      </div>
    </div>

    <div class="content-grid" style="margin-top: 1.5rem;">
      
      <!-- Main Form Area -->
      <div>
        <div class="card glass-panel form-card">
          <div class="card-header">
            <h3><i class="bi bi-receipt"></i> Dados do Pagamento</h3>
          </div>
          
          <div class="card-body">
          
            <div class="form-row col-1">
              <div class="form-group">
                <label for="despesa"><i class="bi bi-fonts"></i> Despesa Relacionada</label>
                <input type="text" class="form-control" id="despesa" :value="form.despesa" readonly disabled style="background: rgba(0,0,0,0.05);">
              </div>
            </div>

            <div class="form-row col-2">
              <div class="form-group">
                <label for="valor" class="required-field"><i class="bi bi-cash-stack"></i> Valor Pago (R$)</label>
                <div class="input-group">
                  <span class="input-group-addon" style="background:var(--input-bg); border-color:var(--border-color); color:var(--text-main);">R$</span>
                  <input type="text" class="form-control" id="valor" v-model="displayValor" @blur="formatDisplayValor" @input="recalcRateioAutomatico" placeholder="0,00" :class="{'is-invalid': errors.valor}">
                </div>
                <small class="text-danger" style="font-size: 12px; margin-top: 4px;" v-if="errors.valor">{{ errors.valor }}</small>
              </div>

              <div class="form-group">
                <label for="pagamento_despesa_dt" class="required-field"><i class="bi bi-calendar-event"></i> Data do Pagamento</label>
                <input type="date" class="form-control" id="pagamento_despesa_dt" v-model="form.pagamento_despesa_dt" :class="{'is-invalid': errors.pagamento_despesa_dt}">
                <small class="text-danger" style="font-size: 12px; margin-top: 4px;" v-if="errors.pagamento_despesa_dt">{{ errors.pagamento_despesa_dt }}</small>
              </div>
            </div>

            <div class="form-row col-2">
              <div class="form-group">
                <label for="conta_id" class="required-field"><i class="bi bi-bank"></i> Conta Bancária</label>
                <select class="form-control" id="conta_id" v-model="form.conta_id" :class="{'is-invalid': errors.conta_id}">
                  <option value="">Selecione a Conta...</option>
                  <option v-for="c in contas" :key="c.id_conta" :value="c.id_conta">
                    {{ c.conta }}
                  </option>
                </select>
                <small class="text-danger" style="font-size: 12px; margin-top: 4px;" v-if="errors.conta_id">{{ errors.conta_id }}</small>
              </div>
            </div>

            <div class="form-row col-1">
              <div class="form-group">
                <label for="observacoes"><i class="bi bi-chat-left-text"></i> Comentário / Observações</label>
                <textarea class="form-control" id="observacoes" v-model="form.observacoes" rows="3" placeholder="Anotações internas sobre esse pagamento..."></textarea>
              </div>
            </div>

          </div>
        </div>

        <!-- Rateio Module -->
        <div class="card glass-panel form-card" style="margin-top: 24px;">
          <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h3><i class="bi bi-diagram-3"></i> Rateio entre Advogados</h3>
            <div style="display:flex; gap: 8px;">
              <button class="btn btn-sm btn-outline-primary" @click="dividirIgualmente" v-if="form.rateio.length > 0">
                <i class="bi bi-calculator"></i> Dividir Igualmente
              </button>
              <button class="btn btn-sm btn-success" @click="addRateioRow">
                <i class="bi bi-plus-circle"></i> Adicionar Sócio
              </button>
            </div>
          </div>
          
          <div class="card-body">
            <div v-if="!form.rateio.length" class="empty-rateio text-center">
               <i class="bi bi-people" style="font-size: 2rem; color: var(--ink-muted); margin-bottom: 10px; display: block;"></i>
               <p style="color: var(--ink-muted); margin:0;">Nenhum rateio configurado.</p>
            </div>
            
            <div class="rateio-list" v-else>
              <div v-for="(r, index) in form.rateio" :key="index" class="rateio-item">
                <div class="form-group" style="flex: 2;">
                  <select class="form-control" v-model="r.id" style="font-size: 0.9rem; padding: 0.4rem 0.75rem;">
                    <option value="">Selecione um Sócio...</option>
                    <option v-for="u in usuarios" :key="u.id" :value="u.id">{{ u.text || u.username || u.nome || u.name }}</option>
                  </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <div class="input-group">
                      <span class="input-group-addon" style="font-size: 0.9rem; padding: 0.4rem 0.6rem; background:var(--input-bg); border-color:var(--border-color); color:var(--text-main);">R$</span>
                      <input type="text" class="form-control" v-model="r.valor" @blur="formatRateioValor(r)" placeholder="0,00" style="font-size: 0.9rem; padding: 0.4rem 0.75rem;">
                    </div>
                </div>
                <div>
                  <button class="btn btn-sm btn-outline-danger" @click="removeRateioRow(index)" title="Remover Rateio" style="padding: 0.4rem 0.75rem; border-color: transparent;">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>

              <!-- Rateio Summary Footer -->
              <div class="rateio-footer" :class="{'has-error': totalRateioError}">
                <div class="rateio-footer-info" v-if="totalRateioError">
                   <i class="bi bi-exclamation-triangle-fill"></i> O valor rateado difere do valor total do pagamento!
                </div>
                <div class="rateio-footer-info" v-else>
                   <i class="bi bi-check-circle-fill" style="color: var(--success);"></i> Divisão completa e equivalente ao total.
                </div>
                <div class="rateio-footer-total">
                   Total Rateado: <strong>{{ formatCurrency(totalRateioReal) }}</strong> / <span style="color:var(--ink-muted);">{{ formatCurrency(valorNum) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Sidebar Area -->
      <div class="sidebar-widgets">
        <!-- Info Widget -->
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
            <h3><i class="bi bi-info-circle"></i> Metadados</h3>
          </div>
          <ul class="widget-list">
            <li style="cursor:default; display:flex; justify-content:space-between; align-items:center;">
              <span class="process-date" style="font-size:.82rem;"><i class="bi bi-hash"></i> ID Pagamento</span>
              <span class="process-num" style="font-size:.82rem;">#{{ despesaId }}</span>
            </li>
          </ul>
        </div>

        <!-- Comprovantes (Google Drive) -->
        <ComprovantesPagamento
          v-if="despesaId"
          tipo="despesa"
          :pagamento-id="despesaId"
          @toast="$emit('toast', $event)"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { apiFetch, formatCurrency, formatNumberBr } from '../../services/api'
import ComprovantesPagamento from '../../components/Financeiro/ComprovantesPagamento.vue'

const props = defineProps({
  despesaId: {
    type: [Number, String],
    default: null
  }
})

const emit = defineEmits(['set-view', 'toast'])

const saving = ref(false)

const usuarios = ref([])
const contas = ref([])

// Form state
const form = reactive<Record<string, any>>({
  id_pgto_despesa: null,
  id_despesa: null,
  despesa: '',
  valor: 0,
  pagamento_despesa_dt: '',
  conta_id: '',
  observacoes: '',
  rateio: []
})

const displayValor = ref('')
const errors = reactive<Record<string, string>>({})

// Init fetch
onMounted(async () => {
  await Promise.all([
    fetchUsuarios(),
    fetchContas()
  ])

  if (props.despesaId) {
    loadPagamento()
  }
})

async function fetchUsuarios() {
  try {
    const res = await apiFetch('usuarios/participa-rateio')
    if (res) {
      usuarios.value = res.results || res.data || (Array.isArray(res) ? res : [])
    }
  } catch(e) { console.error('Erro usuarios:', e) }
}

async function fetchContas() {
  try {
    const res = await apiFetch('financeiro/contas/listar')
    if (res && res.success) contas.value = res.data || []
  } catch(e) { console.error('Erro contas:', e) }
}

async function loadPagamento() {
  try {
    const res = await apiFetch(`financeiro/pagamentoDespesas/detalhes/${props.despesaId}`)
    if (res && res.success && res.data) {
      Object.assign(form, res.data)
      displayValor.value = formatNumberBr(form.valor)
      
      form.observacoes = res.data.observacoes || res.data.comentario || ''
      if (res.data.pagamento_despesa_dt) {
         form.pagamento_despesa_dt = res.data.pagamento_despesa_dt.split(' ')[0]
      }

      if (!form.rateio) {
        form.rateio = []
      } else if (typeof form.rateio === 'string') {
        try {
          form.rateio = JSON.parse(form.rateio) || []
        } catch(e) {
          form.rateio = []
        }
      }
      
      form.rateio.forEach(r => {
        if (r.id) {
          r.id = String(r.id)
          if (!usuarios.value.some(u => String(u.id) === r.id)) {
            usuarios.value.push({ id: r.id, text: `Sócio #${r.id} (Inativo/Ausente)` })
          }
        }
        const val = parseFloat(r.valor)
        if (!isNaN(val)) {
           r.valor = formatNumberBr(val)
        }
      })
    }
  } catch(err) {
    emit('toast', { msg: 'Erro ao carregar pagamento', type: 'danger' })
  }
}

function parseNumberBr(str) {
  if (!str) return 0
  const clean = String(str).replace(/[^\d,-]/g, '').replace(',', '.')
  return parseFloat(clean) || 0
}

const valorNum = computed(() => parseNumberBr(displayValor.value))

function formatDisplayValor() {
  form.valor = valorNum.value
  displayValor.value = formatNumberBr(form.valor)
}

function formatRateioValor(r) {
  r.valor = formatNumberBr(parseNumberBr(r.valor))
}

const totalRateioReal = computed(() => {
  return form.rateio.reduce((acc, r) => acc + parseNumberBr(r.valor), 0)
})

const totalRateioError = computed(() => {
  if (form.rateio.length === 0) return false
  const diff = Math.abs(totalRateioReal.value - valorNum.value)
  return diff > 0.05
})

function addRateioRow() {
  form.rateio.push({ id: '', valor: '' })
  dividirIgualmente()
}

function removeRateioRow(index) {
  form.rateio.splice(index, 1)
  dividirIgualmente()
}

function dividirIgualmente() {
  if (!form.rateio.length || !valorNum.value) return
  const part = valorNum.value / form.rateio.length
  form.rateio.forEach(r => {
    r.valor = formatNumberBr(part)
  })
}

function recalcRateioAutomatico() {
  // only manual button triggers recalculation
}

function validate() {
  Object.keys(errors).forEach(k => delete errors[k])
  let valid = true

  if (!valorNum.value) { errors.valor = 'Valor inválido ou zerado.'; valid = false }
  if (!form.pagamento_despesa_dt) { errors.pagamento_despesa_dt = 'Data obrigatória.'; valid = false }
  if (!form.conta_id) { errors.conta_id = 'Informe a conta bancária.'; valid = false }

  if (totalRateioError.value) {
    emit('toast', { msg: 'Valor do rateio difere do valor total!', type: 'danger' })
    valid = false
  }

  return valid
}

function openDespesaOriginal() {
  if (form.despesa_id) {
    window.location.hash = `#/despesas/detalhes/${form.despesa_id}`
  }
}

async function saveDespesa() {
  if (!validate()) return

  saving.value = true
  try {
    const payload = { ...form }
    payload.valor = valorNum.value
    
    const cleanRateio = form.rateio.filter(r => r.id).map(r => ({
      id: r.id,
      valor: parseNumberBr(r.valor)
    }))
    payload.rateio = cleanRateio
    
    // Fallback if backend expects "id_pgto_despesa" specifically
    payload.id_pgto_despesa = props.despesaId

    const res = await apiFetch('financeiro/pagamentoDespesas/salvar', {
      method: 'POST',
      body: payload
    })

    if (res && res.success) {
      emit('toast', { msg: res.message || 'Pagamento salvo com sucesso', type: 'success' })
      window.dispatchEvent(new Event('sync-global-stats'))
      emit('set-view', 'despesas-pagas')
    } else {
      throw new Error(res.message || 'Erro ao salvar')
    }
  } catch (err) {
    console.error(err)
    emit('toast', { msg: err.message || 'Falha na comunicação com o servidor', type: 'danger' })
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
/* Grid Framework */
.form-card { border-radius: 8px; }
.form-row { margin-bottom: 1.25rem; }
.form-row.col-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; }
.form-row.col-1 { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
.form-group { display: flex; flex-direction: column; gap: 0.4rem; }
.required-field::after { content: ' *'; color: var(--danger); font-weight: bold; }

/* Rateio Module */
.rateio-list { display: flex; flex-direction: column; gap: 12px; }
.rateio-item { display: flex; gap: 12px; align-items: center; background: rgba(255, 255, 255, 0.02); padding: 10px 14px; border-radius: 6px; border: 1px solid rgba(255, 255, 255, 0.05); }
.rateio-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; padding: 12px 14px; background: rgba(255,255,255,0.03); border-radius: 6px; font-size: 0.9rem; border: 1px solid rgba(255,255,255,0.05); }
.rateio-footer.has-error { border-color: var(--danger); background: rgba(220, 38, 38, 0.05); }
.rateio-footer-info { color: var(--danger); font-weight: 500; }
.rateio-footer-info .bi-check-circle-fill { color: var(--success); }
.rateio-footer-total { font-size: 1rem; }

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
</style>
