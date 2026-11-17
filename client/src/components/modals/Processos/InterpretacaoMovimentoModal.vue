<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box" style="max-width: 550px;">
      <div class="modal-header">
        <h2><i class="bi bi-translate"></i> Traduzir Movimento (IA)</h2>
        <button class="modal-close" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
        <!-- Original Text -->
        <div style="margin-bottom: 20px;">
          <label style="font-size: 0.8rem; font-weight: bold; color: var(--ink-dim); text-transform: uppercase;">Texto Original (Juridiquês)</label>
          <div style="background: rgba(0,0,0,0.03); padding: 12px; border-radius: 6px; font-size: 0.85rem; color: var(--ink-muted); margin-top: 6px; border: 1px solid var(--border);">
            {{ textoMovimento }}
          </div>
        </div>

        <!-- Translation -->
        <div>
          <label style="font-size: 0.8rem; font-weight: bold; color: var(--accent); text-transform: uppercase;">Explicação para o Cliente</label>
          
          <div v-if="loading" style="text-align:center; padding: 30px;">
            <div class="spinner-border spinner-border-sm" style="color: var(--accent); width: 1.5rem; height: 1.5rem;" role="status"></div>
            <p style="margin-top: 10px; color: var(--ink-dim); font-size: 0.85rem;">Gerando tradução acessível...</p>
          </div>
          
          <div v-else-if="error" style="padding: 15px; background: rgba(220, 53, 69, 0.1); border-left: 3px solid var(--danger); border-radius: 4px; color: var(--danger); font-size: 0.85rem; margin-top: 6px;">
            {{ error }}
          </div>
          
          <div v-else style="background: var(--surface); padding: 16px; border-radius: 8px; margin-top: 6px; border: 1px solid var(--accent); box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            <p style="white-space: pre-wrap; font-size: 0.95rem; color: var(--ink); margin: 0; line-height: 1.6;">{{ interpretacao }}</p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" @click="copiarTexto" v-if="interpretacao">
          <i class="bi bi-clipboard"></i> Copiar Tradução
        </button>
        <button class="btn btn-primary" @click="$emit('close')">Concluir</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { apiFetch, toFormData } from '../../../services/api'

const props = defineProps({
  textoMovimento: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['close', 'toast'])

const loading = ref(true)
const error = ref(null)
const interpretacao = ref('')

onMounted(async () => {
  if (!props.textoMovimento) {
    error.value = 'Nenhum texto fornecido.'
    loading.value = false
    return
  }

  try {
    const res = await apiFetch('processos/interpretarMovimento', {
      method: 'POST',
      body: toFormData({
        texto: props.textoMovimento
      })
    })

    if (res && res.success) {
      interpretacao.value = res.data
    } else {
      error.value = res.message || 'Falha ao interpretar o movimento.'
    }
  } catch (err) {
    error.value = err.message || 'Erro de comunicação com a IA.'
  } finally {
    loading.value = false
  }
})

async function copiarTexto() {
  if (!interpretacao.value) return
  try {
    await navigator.clipboard.writeText(interpretacao.value)
    emit('toast', { msg: 'Tradução copiada para a área de transferência!', type: 'success' })
  } catch (err) {
    emit('toast', { msg: 'Erro ao copiar.', type: 'danger' })
  }
}
</script>
