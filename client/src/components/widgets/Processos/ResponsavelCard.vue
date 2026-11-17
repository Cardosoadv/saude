<template>
  <div class="section-card">
    <div class="section-header">
      <span><i class="bi bi-person-badge"></i> Responsável</span>
    </div>
    <div class="section-body">
      <div class="form-group">
        <select
          class="form-control"
          :value="responsavelId"
          @change="atribuirResponsavel"
          style="width: 100%; padding: 8px; border-radius: 6px; background: rgba(0,0,0,0.2); color: var(--ink); border: 1px solid var(--border);"
        >
          <option value="">Selecione um responsável...</option>
          <option v-for="adv in responsavelOpcoes" :key="adv.id" :value="adv.id">
            {{ adv.nome || adv.username }}
          </option>
        </select>
      </div>
      <div v-if="responsavelId" style="margin-top: 10px; display: flex; align-items: center; gap: 8px;">
        <div class="avatar-sm" style="width: 24px; height: 24px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold;">
          {{ responsavelNome?.charAt(0) || 'R' }}
        </div>
        <span style="font-size: 0.85rem; color: var(--ink-dim);">
          {{ responsavelNome }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Card de seleção do responsável pelo processo
 * @version 1.1.0
 */
import { computed, type PropType } from 'vue'
import { apiFetch } from '../../../services/api'
import type { Usuario } from '../../../types/domain'

const props = defineProps({
  processId: { type: Number, required: true },
  responsavelId: { type: [String, Number], default: null },
  responsavelNomeAtual: { type: String, default: null },
  advogados: { type: Array as PropType<Usuario[]>, default: () => [] }
})

const emit = defineEmits(['toast', 'refresh'])

const responsavelOpcoes = computed(() =>
  props.advogados.filter(a => (a as any).responsavel_tarefas === undefined || Number((a as any).responsavel_tarefas) === 1)
)

const responsavelNome = computed(() => {
  if (!props.responsavelId) return null
  const adv = props.advogados.find(a => String(a.id) === String(props.responsavelId))
  return adv ? (adv.nome || adv.username) : (props.responsavelNomeAtual || 'Responsável atribuído')
})

async function atribuirResponsavel(event) {
  const userId = event.target.value
  if (!userId) return

  const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || ''

  try {
    const res = await apiFetch(`processos/atribuir/${props.processId}`, {
      method: 'PATCH',
      body: {
        user_id: userId,
        csrf_test_name: csrfToken
      }
    })
    if (res && res.success) {
      emit('toast', { msg: 'Responsável atribuído com sucesso!', type: 'success' })
      emit('refresh')
    } else {
      throw new Error(res.message || 'Erro ao atribuir responsável')
    }
  } catch (err) {
    emit('toast', { msg: err.message || 'Erro ao atribuir responsável no backend.', type: 'danger' })
  }
}
</script>
