<template>
  <div v-if="show" class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box" style="max-width: 540px;">
      <div class="modal-header">
        <h2><i class="bi bi-person-check text-accent"></i> Marcar como Cliente</h2>
        <button class="modal-close" @click="$emit('close')" aria-label="Fechar">&times;</button>
      </div>

      <div class="modal-body" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
        <div class="alert alert-info" style="font-size: 0.85rem; line-height: 1.45; margin: 0;">
          <i class="bi bi-info-circle-fill"></i>
          O cadastro deste fornecedor passa a ser também de <strong>cliente</strong>. Nada é copiado: é a mesma pessoa,
          que continua fornecedora e passa a aparecer em Clientes e no CRM.
        </div>

        <div class="summary-card">
          <div class="summary-title"><i class="bi bi-person-badge"></i> Cadastro:</div>
          <div class="summary-item"><strong>Nome / Fantasia:</strong> {{ fornecedor.nome }}</div>
          <div v-if="fornecedor.razao_social && fornecedor.razao_social !== fornecedor.nome" class="summary-item">
            <strong>Razão Social:</strong> {{ fornecedor.razao_social }}
          </div>
          <div class="summary-item"><strong>Documento:</strong> {{ formatDocument(fornecedor.documento) || '—' }}</div>
          <div class="summary-item"><strong>E-mail:</strong> {{ fornecedor.email || '—' }}</div>
          <div class="summary-item"><strong>Telefone:</strong> {{ formatPhone(fornecedor.telefone) || '—' }}</div>
          <div v-if="fornecedor.cidade || fornecedor.uf" class="summary-item">
            <strong>Localização:</strong> {{ fornecedor.cidade || '' }}<span v-if="fornecedor.uf"> / {{ fornecedor.uf }}</span>
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
          {{ loading ? 'Salvando...' : 'Marcar como cliente' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { apiFetch, formatDocument, formatPhone } from '../../../services/api'

export interface FornecedorResumo {
  id_fornecedor: number | string
  nome: string
  razao_social?: string | null
  documento?: string | null
  email?: string | null
  telefone?: string | null
  cidade?: string | null
  uf?: string | null
  endereco?: string | null
  numero?: string | null
  cep?: string | null
}

const props = defineProps<{
  show: boolean
  fornecedor: FornecedorResumo
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'success', payload: { clienteId: number; message: string }): void
  (e: 'toast', toast: { msg: string; type: string }): void
}>()

const loading = ref(false)
const errorMessage = ref('')

watch(
  () => props.show,
  (val) => {
    if (val) {
      loading.value = false
      errorMessage.value = ''
    }
  }
)

async function confirmar() {
  if (!props.fornecedor?.id_fornecedor) return

  loading.value = true
  errorMessage.value = ''

  try {
    const res = await apiFetch(`fornecedores/transformar-em-cliente/${props.fornecedor.id_fornecedor}`, {
      method: 'POST',
      body: {}
    })

    if (res && res.success) {
      emit('toast', { msg: res.message || 'Fornecedor marcado como cliente.', type: 'success' })
      emit('success', { clienteId: res.cliente_id, message: res.message })
      emit('close')
    } else {
      errorMessage.value = res?.message || 'Não foi possível marcar como cliente.'
    }
  } catch (err: any) {
    errorMessage.value = err?.message || 'Erro inesperado ao marcar o fornecedor como cliente.'
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
