<template>
  <div class="section-card">
    <div class="section-header">
      <span><i class="bi bi-journal-text"></i> Anotações</span>
      <button class="btn btn-ghost btn-sm" @click="$emit('open-modal', 'anotacao')" style="padding:2px 8px; font-size:.78rem;">
        <i class="bi bi-plus-circle"></i> Nova
      </button>
    </div>
    <div class="section-body" style="padding-top:8px; padding-bottom:8px;">
      <div v-if="!anotacoes || !anotacoes.length" style="font-size:.8rem;color:var(--ink-muted);padding:10px 0;text-align:center;">Nenhuma anotação vinculada.</div>
      <div v-for="n in anotacoesPagina" :key="n.id_anotacao || n.id" style="padding:10px 0; border-bottom: 1px solid var(--border);">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;">
          <strong style="font-size:.82rem; color:var(--ink);">{{ n.titulo }}</strong>
          <span class="badge" :class="String(n.privacidade || n.nivel_privacidade) === '1' ? 'badge-warning' : 'badge-success'">
            {{ String(n.privacidade || n.nivel_privacidade) === '1' ? 'Privado' : 'Público' }}
          </span>
        </div>
        <p style="font-size:.8rem; color:var(--ink-dim); line-height:1.4;">{{ n.anotacao }}</p>
        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:6px;">
          <button class="btn btn-ghost btn-sm" @click="$emit('edit-anotacao', n)" style="border:none; padding:2px 8px; color:var(--primary); font-size:.75rem;">
            <i class="bi bi-pencil"></i> Editar
          </button>
          <button class="btn btn-ghost btn-sm" @click="removerAnotacao(n.id_anotacao || n.id)" style="border:none; padding:2px 8px; color:var(--danger); font-size:.75rem;">
            <i class="bi bi-trash"></i> Excluir
          </button>
        </div>
      </div>
      <div v-if="totalPaginas > 1" class="pagination" role="navigation" aria-label="Paginação das anotações" style="margin-top:10px; justify-content:center;">
        <button class="page-btn" :disabled="pagina === 1" @click="pagina--" aria-label="Página anterior">
          <i class="bi bi-chevron-left"></i>
        </button>
        <span style="font-size:.78rem; color:var(--ink-muted); padding:0 6px;">{{ pagina }} / {{ totalPaginas }}</span>
        <button class="page-btn" :disabled="pagina === totalPaginas" @click="pagina++" aria-label="Próxima página">
          <i class="bi bi-chevron-right"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch, type PropType } from 'vue'
import { apiFetch } from '../../../services/api'
import type { Anotacao } from '../../../types/domain'

const props = defineProps({
  processId: { type: Number, required: true },
  anotacoes: { type: Array as PropType<Anotacao[]>, default: () => [] }
})

const emit = defineEmits(['open-modal', 'edit-anotacao', 'toast', 'refresh'])

const POR_PAGINA = 5
const pagina = ref(1)
const totalPaginas = computed(() => Math.max(1, Math.ceil((props.anotacoes?.length || 0) / POR_PAGINA)))
const anotacoesPagina = computed(() => {
  const inicio = (pagina.value - 1) * POR_PAGINA
  return (props.anotacoes || []).slice(inicio, inicio + POR_PAGINA)
})

// Após excluir/recarregar, mantém a página dentro do intervalo válido
watch(totalPaginas, (total) => {
  if (pagina.value > total) pagina.value = total
})

async function removerAnotacao(id) {
  if (confirm('Confirma a exclusão desta anotação?')) {
    try {
      const res = await apiFetch('processos/removerAnotacao', {
        method: 'POST',
        body: {
          processo_id: props.processId,
          anotacao_id: id
        }
      })
      if (res && res.success) {
        emit('toast', { msg: 'Anotação excluída com sucesso!', type: 'success' })
        emit('refresh')
      } else {
        throw new Error(res.message)
      }
    } catch (err) {
      emit('toast', { msg: 'Erro ao remover anotação no backend.', type: 'danger' })
    }
  }
}
</script>
