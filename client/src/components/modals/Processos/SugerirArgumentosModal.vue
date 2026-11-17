<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-box" style="max-width: 650px;">
      <div class="modal-header">
        <h2><i class="bi bi-lightbulb"></i> Sugerir Argumentos (IA)</h2>
        <button class="modal-close" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
        <div v-if="loading" style="text-align:center; padding: 40px 20px;">
          <div class="spinner-border" style="color: var(--accent); width: 2.5rem; height: 2.5rem;" role="status"></div>
          <p style="margin-top: 15px; color: var(--ink-dim); font-size: 0.95rem;">A Inteligência Artificial está analisando os detalhes e o ramo do direito do processo para formular argumentos estratégicos...</p>
        </div>
        <div v-else-if="error" style="padding: 20px; background: rgba(220, 53, 69, 0.1); border-left: 4px solid var(--danger); border-radius: 4px; color: var(--danger);">
          <strong>Erro:</strong> {{ error }}
        </div>
        <div v-else-if="!sugestoes || Object.keys(sugestoes).length === 0" style="padding: 20px; text-align: center; color: var(--ink-muted);">
          Nenhuma sugestão de argumento encontrada para o contexto atual do processo.
        </div>
        <div v-else class="argumentos-container">
          <div v-if="sugestoes.argumentos && sugestoes.argumentos.length" style="background: var(--surface); padding: 16px; border-radius: 8px; margin-bottom: 12px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <strong style="display:block; margin-bottom:8px; color: var(--primary); font-size: 0.95rem;"><i class="bi bi-card-heading" style="margin-right: 6px;"></i> Títulos de Argumentos</strong>
            <ul style="margin: 0; padding-left: 20px; font-size: 0.9rem; color: var(--ink);">
              <li v-for="(arg, index) in sugestoes.argumentos" :key="index">{{ arg.titulo }}</li>
            </ul>
          </div>
          
          <div v-if="sugestoes.estrategia_recomendada" style="background: var(--surface); padding: 16px; border-radius: 8px; margin-bottom: 12px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <strong style="display:block; margin-bottom:8px; color: var(--primary); font-size: 0.95rem;"><i class="bi bi-compass" style="margin-right: 6px;"></i> Estratégia Recomendada</strong>
            <p style="white-space: pre-wrap; font-size: 0.9rem; color: var(--ink); margin: 0; line-height: 1.5;">{{ sugestoes.estrategia_recomendada }}</p>
          </div>

          <div v-if="sugestoes.teses_relacionadas && sugestoes.teses_relacionadas.length" style="background: var(--surface); padding: 16px; border-radius: 8px; margin-bottom: 12px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <strong style="display:block; margin-bottom:8px; color: var(--primary); font-size: 0.95rem;"><i class="bi bi-journal-text" style="margin-right: 6px;"></i> Teses Relacionadas</strong>
            <ul style="margin: 0; padding-left: 20px; font-size: 0.9rem; color: var(--ink);">
              <li v-for="(tese, index) in sugestoes.teses_relacionadas" :key="index">{{ tese }}</li>
            </ul>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-ghost" @click="$emit('close')">Cancelar</button>
        <button v-if="sugestoes && Object.keys(sugestoes).length > 0" class="btn btn-primary" :disabled="saving" @click="salvarArgumentos">
          <span v-if="saving" class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="margin-right: 5px;"></span>
          <i v-else class="bi bi-cloud-arrow-up" style="margin-right: 5px;"></i>
          {{ saving ? 'Salvando...' : 'Salvar no Banco' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { apiFetch } from '../../../services/api'

const props = defineProps({
  processoId: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['close', 'toast', 'saved'])

const loading = ref(true)
const saving = ref(false)
const error = ref(null)
const sugestoes = ref(null)

onMounted(async () => {
  try {
    const res = await apiFetch(`processos/sugerirArgumentos/${props.processoId}`)
    if (res && res.success) {
      sugestoes.value = res.data
    } else {
      error.value = res.message || 'Falha ao buscar sugestões. O processo pode não ter dados suficientes.'
    }
  } catch (err) {
    error.value = err.message || 'Erro de conexão com o serviço de Inteligência Artificial.'
  } finally {
    loading.value = false
  }
})

async function salvarArgumentos() {
  saving.value = true
  try {
    // A IA atual retorna { argumentos: [{titulo: ''}], estrategia_recomendada: '', teses_relacionadas: [] }
    // Transformamos isso em um array de "argumentos_sugeridos" para o banco.
    // O mais comum é a estratégia ser a justificativa de 1 ou mais argumentos.
    const argumentosIA = sugestoes.value.argumentos || []
    
    let argsParaSalvar = []

    if (argumentosIA.length > 0) {
      argsParaSalvar = argumentosIA.map(arg => ({
        titulo: arg.titulo || 'Argumento Sem Título',
        estrategia_recomendada: sugestoes.value.estrategia_recomendada || '',
        teses_relacionadas: sugestoes.value.teses_relacionadas || []
      }))
    } else {
      // Se por algum motivo a IA não retornou o array "argumentos", mas retornou a estratégia
      argsParaSalvar = [{
        titulo: 'Argumento Estratégico Base',
        estrategia_recomendada: sugestoes.value.estrategia_recomendada || '',
        teses_relacionadas: sugestoes.value.teses_relacionadas || []
      }]
    }

    const payload = {
      processo_id: props.processoId,
      argumentos: argsParaSalvar
    }

    const res = await apiFetch('argumentos/lote', {
      method: 'POST',
      body: payload
    })

    if (res && res.success) {
      emit('toast', { msg: 'Argumentos salvos com sucesso no banco!', type: 'success' })
      emit('saved')
      emit('close')
    } else {
      emit('toast', { msg: res.message || 'Erro ao salvar argumentos.', type: 'danger' })
    }
  } catch (err) {
    emit('toast', { msg: err.message || 'Falha na comunicação ao tentar salvar.', type: 'danger' })
  } finally {
    saving.value = false
  }
}
</script>
