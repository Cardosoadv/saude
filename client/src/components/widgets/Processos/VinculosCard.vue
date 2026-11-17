<template>
  <div class="section-card">
    <div class="section-header">
      <span><i class="bi bi-link-45deg"></i> Processos Vinculados</span>
      <button class="btn btn-ghost btn-sm" @click="$emit('open-modal', 'vinculacao')">
        <i class="bi bi-link"></i> Vincular
      </button>
    </div>
    <div class="section-body" style="padding-top:8px; padding-bottom:8px;">
      <div v-if="!vinculos || !vinculos.length" style="font-size:.8rem;color:var(--ink-muted);padding:10px 0;text-align:center;">
        Nenhum processo vinculado.
      </div>
      <div v-else>
        <div 
          v-for="v in vinculos" 
          :key="v.id_vinculo" 
          style="display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--border);"
        >
          <div style="flex:1; min-width: 0; padding-right: 8px;">
            <a :href="'#/processos/detalhes/' + v.id_processo_vinculado" @click="onOpenDetail($event, v.id_processo_vinculado)" style="font-size:.83rem; font-weight:600; color:var(--accent); text-decoration:none; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" :title="v.titulo_processo || v.numeroprocessocommascara">
              {{ v.numeroprocessocommascara || ('Processo #' + v.id_processo_vinculado) }}
            </a>
            <div style="display:flex; align-items:center; gap:6px; margin-top:2px; flex-wrap: wrap;">
              <span class="badge" style="font-size:.68rem; background: var(--primary-lt); color: var(--primary); padding: 2px 6px;">{{ v.tipo_vinculo }}</span>
              <span v-if="v.is_arquivado" class="badge" style="font-size:.68rem; background: rgba(108, 117, 125, 0.25); color: var(--ink-muted); padding: 2px 6px;" title="Processo Arquivado">
                <i class="bi bi-archive"></i> Arquivado
              </span>
              <span v-if="v.descricao_vinculo" style="font-size:.72rem; color:var(--ink-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 150px;" :title="v.descricao_vinculo">{{ v.descricao_vinculo }}</span>
            </div>
          </div>
          <button class="btn btn-ghost btn-sm" @click="removerVinculo(v.id_vinculo)" style="border:none; padding:2px; color:var(--danger);" title="Remover Vínculo">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
/**
 * Widget de Processos Vinculados
 * @version 1.1.2
 */
import { apiFetch } from '../../../services/api'
import type { VinculoProcesso } from '../../../types/domain'

const props = defineProps({
  vinculos: { type: Array as PropType<VinculoProcesso[]>, default: () => [] }
})

const emit = defineEmits(['open-modal', 'open-detail', 'toast', 'refresh'])

function onOpenDetail(event, id) {
  if (event.ctrlKey || event.metaKey || event.shiftKey) return
  event.preventDefault()
  emit('open-detail', id)
}

async function removerVinculo(id) {
  if (confirm('Confirma a remoção definitiva deste vínculo?')) {
    try {
      const res = await apiFetch(`processos/removerVinculo/${id}`, {
        method: 'POST'
      })
      if (res && res.success) {
        emit('toast', { msg: 'Vínculo removido com sucesso!', type: 'success' })
        emit('refresh')
      } else {
        throw new Error(res.message || 'Erro ao remover vínculo.')
      }
    } catch (err) {
      emit('toast', { msg: err.message || 'Erro ao remover vínculo no backend.', type: 'danger' })
    }
  }
}
</script>
