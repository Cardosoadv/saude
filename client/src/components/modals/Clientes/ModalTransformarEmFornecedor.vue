<template>
  <div v-if="show" class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box" style="max-width: 580px;">
      <div class="modal-header">
        <h2><i class="bi bi-truck text-accent"></i> Marcar como Fornecedor</h2>
        <button class="modal-close" @click="$emit('close')" aria-label="Fechar">&times;</button>
      </div>

      <div class="modal-body" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
        <div class="alert alert-info" style="font-size: 0.85rem; line-height: 1.45; margin: 0;">
          <i class="bi bi-info-circle-fill"></i>
          O cadastro deste cliente passa a ser também de <strong>fornecedor</strong>. Nada é copiado: é a mesma pessoa,
          com os mesmos dados, que continua cliente e aparece também na lista de fornecedores e nas despesas.
        </div>

        <div class="summary-card">
          <div class="summary-title"><i class="bi bi-person-badge"></i> Cadastro:</div>
          <div class="summary-item"><strong>Nome / Razão Social:</strong> {{ cliente.nome }}</div>
          <div v-if="cliente.razao_social && cliente.razao_social !== cliente.nome" class="summary-item">
            <strong>Razão Social:</strong> {{ cliente.razao_social }}
          </div>
          <div class="summary-item"><strong>Documento:</strong> {{ formatDocument(cliente.documento) || '—' }}</div>
          <div class="summary-item"><strong>E-mail:</strong> {{ cliente.email || '—' }}</div>
          <div class="summary-item"><strong>Telefone:</strong> {{ formatPhone(cliente.telefone || cliente.celular) || '—' }}</div>
          <div v-if="cliente.cidade || cliente.uf" class="summary-item">
            <strong>Localização:</strong> {{ cliente.cidade || '' }}<span v-if="cliente.uf"> / {{ cliente.uf }}</span>
          </div>
        </div>

        <!-- Dados Bancários Opcionais -->
        <div class="banco-card">
          <div class="section-title"><i class="bi bi-bank"></i> Dados Bancários & PIX (Opcional)</div>
          <div class="form-row col-3" style="margin-top: 0.5rem;">
            <div class="form-group">
              <label for="tf_banco">Banco</label>
              <input type="text" id="tf_banco" v-model="bancoForm.banco" class="form-control" placeholder="Ex: Itaú" />
            </div>
            <div class="form-group">
              <label for="tf_agencia">Agência</label>
              <input type="text" id="tf_agencia" v-model="bancoForm.agencia" class="form-control" placeholder="0001" />
            </div>
            <div class="form-group">
              <label for="tf_conta">Conta</label>
              <input type="text" id="tf_conta" v-model="bancoForm.conta" class="form-control" placeholder="12345-6" />
            </div>
          </div>
          <div class="form-row col-2" style="margin-top: 0.65rem;">
            <div class="form-group">
              <label for="tf_tipo_conta">Tipo de Conta</label>
              <select id="tf_tipo_conta" v-model="bancoForm.tipo_conta" class="form-control">
                <option value="">Selecione...</option>
                <option value="corrente">Conta Corrente</option>
                <option value="poupanca">Poupança</option>
                <option value="pagamento">Pagamento</option>
              </select>
            </div>
            <div class="form-group">
              <label for="tf_pix">Chave PIX</label>
              <input type="text" id="tf_pix" v-model="bancoForm.pix" class="form-control" placeholder="E-mail, CPF/CNPJ ou tel" />
            </div>
          </div>
        </div>

        <div v-if="errorMessage" class="alert alert-danger" style="font-size: 0.85rem; margin: 0;">
          <i class="bi bi-exclamation-triangle-fill"></i>
          {{ errorMessage }}
        </div>
      </div>

      <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.5rem;">
        <button class="btn btn-outline-secondary" @click="$emit('close')" :disabled="loading">
          Cancelar
        </button>
        <button class="btn btn-primary" @click="confirmar" :disabled="loading">
          <i class="bi bi-arrow-repeat" :class="{ 'spin-icon': loading }"></i>
          {{ loading ? 'Salvando...' : 'Marcar como fornecedor' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { apiFetch, formatDocument, formatPhone } from '../../../services/api'

export interface ClienteResumo {
  id_cliente: number | string
  nome: string
  razao_social?: string | null
  documento?: string | null
  email?: string | null
  telefone?: string | null
  celular?: string | null
  cidade?: string | null
  uf?: string | null
  endereco?: string | null
  numero?: string | null
  cep?: string | null
}

const props = defineProps<{
  show: boolean
  cliente: ClienteResumo
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'success', payload: { fornecedorId: number; message: string }): void
  (e: 'toast', toast: { msg: string; type: string }): void
}>()

const loading = ref(false)
const errorMessage = ref('')

const bancoForm = reactive({
  banco: '',
  agencia: '',
  conta: '',
  tipo_conta: '',
  pix: ''
})

watch(
  () => props.show,
  (val) => {
    if (val) {
      loading.value = false
      errorMessage.value = ''
      bancoForm.banco = ''
      bancoForm.agencia = ''
      bancoForm.conta = ''
      bancoForm.tipo_conta = ''
      bancoForm.pix = ''
    }
  }
)

async function confirmar() {
  if (!props.cliente?.id_cliente) return

  loading.value = true
  errorMessage.value = ''

  try {
    const res = await apiFetch(`clientes/transformar-em-fornecedor/${props.cliente.id_cliente}`, {
      method: 'POST',
      body: {
        banco: bancoForm.banco || null,
        agencia: bancoForm.agencia || null,
        conta: bancoForm.conta || null,
        tipo_conta: bancoForm.tipo_conta || null,
        pix: bancoForm.pix || null
      }
    })

    if (res && res.success) {
      emit('toast', { msg: res.message || 'Cliente marcado como fornecedor.', type: 'success' })
      emit('success', { fornecedorId: res.fornecedor_id, message: res.message })
      emit('close')
    } else {
      errorMessage.value = res?.message || 'Não foi possível marcar como fornecedor.'
    }
  } catch (err: any) {
    errorMessage.value = err?.message || 'Erro inesperado ao marcar o cliente como fornecedor.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.text-accent {
  color: var(--accent);
}

.summary-card {
  background: var(--bg-card, rgba(255, 255, 255, 0.03));
  border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
  border-radius: 8px;
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.88rem;
}

.summary-title {
  font-weight: 600;
  color: var(--accent);
  margin-bottom: 0.25rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.summary-item {
  color: var(--ink-base, var(--text-main));
  line-height: 1.35;
}

.banco-card {
  background: var(--bg-card, rgba(255, 255, 255, 0.02));
  border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
  border-radius: 8px;
  padding: 12px 14px;
}

.section-title {
  font-weight: 600;
  font-size: 0.88rem;
  color: var(--ink-base, var(--text-main));
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.form-row.col-3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.75rem;
}

.form-row.col-2 {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.form-group label {
  font-size: 0.8rem;
  color: var(--ink-muted, #8e95a5);
}

.form-check-card {
  background: var(--bg-card, rgba(255, 255, 255, 0.02));
  border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
  border-radius: 8px;
  padding: 10px 14px;
}

.checkbox-label {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  cursor: pointer;
  margin: 0;
}

.checkbox-label input[type='checkbox'] {
  margin-top: 3px;
  cursor: pointer;
}

.label-text {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  font-size: 0.88rem;
}

.label-text small {
  color: var(--ink-muted, #8e95a5);
  font-size: 0.8rem;
}

.spin-icon {
  animation: spin 1s linear infinite;
  display: inline-block;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
