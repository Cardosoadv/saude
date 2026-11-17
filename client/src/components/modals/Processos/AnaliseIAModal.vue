<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box" style="max-width: 600px;">
      <div class="modal-header">
        <h2><i class="bi bi-graph-up-arrow"></i> Previsão de Desfecho (IA)</h2>
        <button class="modal-close" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
        <div v-if="loading" style="text-align:center; padding: 40px 20px;">
          <div class="spinner-border" style="color: var(--accent); width: 2.5rem; height: 2.5rem;" role="status"></div>
          <p style="margin-top: 15px; color: var(--ink-dim); font-size: 0.95rem;">A Inteligência Artificial está analisando o histórico e os movimentos do processo para prever o desfecho...</p>
        </div>
        <div v-else-if="error" style="padding: 20px; background: rgba(220, 53, 69, 0.1); border-left: 4px solid var(--danger); border-radius: 4px; color: var(--danger);">
          <strong>Erro:</strong> {{ error }}
        </div>
        <div v-else-if="!analise" style="padding: 20px; text-align: center; color: var(--ink-muted);">
          Nenhuma análise preditiva disponível para este processo.
        </div>
        <div v-else class="analise-container">
          <div style="display:flex; gap: 12px; margin-bottom: 12px;">
            <div style="flex:1; background: var(--surface); padding: 16px; border-radius: 8px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.05); text-align:center;">
              <strong style="display:block; margin-bottom:8px; color: var(--ink-dim); font-size: 0.8rem; text-transform: uppercase;"><i class="bi bi-exclamation-triangle" style="margin-right: 4px;"></i> Risco Predito</strong>
              <span :class="['badge', riscoBadgeClass]" style="font-size: 0.85rem;">{{ analise.risco_predito }}</span>
            </div>
            <div style="flex:1; background: var(--surface); padding: 16px; border-radius: 8px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.05); text-align:center;">
              <strong style="display:block; margin-bottom:8px; color: var(--ink-dim); font-size: 0.8rem; text-transform: uppercase;"><i class="bi bi-flag" style="margin-right: 4px;"></i> Resultado Predito</strong>
              <span :class="['badge', resultadoBadgeClass]" style="font-size: 0.85rem;">{{ analise.resultado_predito }}</span>
            </div>
          </div>

          <div style="background: var(--surface); padding: 16px; border-radius: 8px; margin-bottom: 12px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <strong style="display:block; margin-bottom:8px; color: var(--primary); font-size: 0.95rem;"><i class="bi bi-shield-check" style="margin-right: 6px;"></i> Confiança da Análise</strong>
            <div style="display:flex; align-items:center; gap: 10px;">
              <div style="flex:1; background: rgba(0,0,0,0.06); border-radius: 6px; height: 10px; overflow: hidden;">
                <div :style="{ width: scoreConfiancaPercent + '%', background: 'var(--accent)', height: '100%' }"></div>
              </div>
              <span style="font-size: 0.85rem; color: var(--ink); font-weight: bold;">{{ scoreConfiancaPercent }}%</span>
            </div>
          </div>

          <div v-if="analise.justificativa_estrategica" style="background: var(--surface); padding: 16px; border-radius: 8px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <strong style="display:block; margin-bottom:8px; color: var(--primary); font-size: 0.95rem;"><i class="bi bi-journal-text" style="margin-right: 6px;"></i> Justificativa Estratégica</strong>
            <p style="white-space: pre-wrap; font-size: 0.9rem; color: var(--ink); margin: 0; line-height: 1.5;">{{ analise.justificativa_estrategica }}</p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" @click="$emit('close')">Fechar</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { apiFetch } from '../../../services/api'

const props = defineProps({
  processoId: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['close', 'toast'])

const loading = ref(true)
const error = ref(null)
const analise = ref(null)

const scoreConfiancaPercent = computed(() => {
  const score = Number(analise.value?.score_confianca) || 0
  return Math.round(Math.min(Math.max(score, 0), 1) * 100)
})

const riscoBadgeClass = computed(() => {
  const risco = (analise.value?.risco_predito || '').toLowerCase()
  if (risco === 'remoto') return 'badge-success'
  if (risco === 'provável' || risco === 'provavel') return 'badge-danger'
  return 'badge-warning'
})

const resultadoBadgeClass = computed(() => {
  const resultado = (analise.value?.resultado_predito || '').toLowerCase()
  if (resultado === 'sucesso') return 'badge-success'
  if (resultado === 'derrota') return 'badge-danger'
  return 'badge-warning'
})

onMounted(async () => {
  try {
    const res = await apiFetch(`processos/analiseIA/${props.processoId}`)
    if (res && res.success) {
      analise.value = res.data
    } else {
      error.value = res.message || 'Falha ao buscar a análise preditiva. O processo pode não ter dados suficientes.'
    }
  } catch (err) {
    error.value = err.message || 'Erro de conexão com o serviço de Inteligência Artificial.'
  } finally {
    loading.value = false
  }
})
</script>
