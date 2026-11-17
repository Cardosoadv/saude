<template>
  <div class="section-card">
    <div class="section-header">
      <span><i class="bi bi-houses"></i> Objetos Vinculados</span>
      <button v-if="podeEditar" class="btn btn-ghost btn-sm" @click="$emit('open-modal', 'objetoVinculo')">
        <i class="bi bi-link"></i> Vincular
      </button>
    </div>
    <div class="section-body" style="padding-top:8px; padding-bottom:8px;">
      <div v-if="!listaObjetos.length" style="font-size:.8rem;color:var(--ink-muted);padding:10px 0;text-align:center;">
        Nenhum objeto vinculado.
      </div>
      <div v-else>
        <div
          v-for="o in listaObjetos"
          :key="o.id_objeto"
          style="display:flex; align-items:flex-start; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--border);"
        >
          <div style="flex:1; min-width: 0; padding-right: 8px;">
            <a :href="'#/objetos/editar/' + o.id_objeto" target="_blank" style="font-size:.83rem; font-weight:600; color:var(--accent); text-decoration:none; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
              {{ nomeObjeto(o) }}
            </a>
            <div style="font-size:.72rem; color:var(--ink-muted); margin-top:2px;">
              {{ o.cidade || '—' }}<template v-if="o.cod_interno"> · Código {{ o.cod_interno }}</template>
            </div>
          </div>
          <button v-if="podeEditar" class="btn btn-ghost btn-sm" @click="removerVinculo(o.id_objeto)" style="border:none; padding:2px; color:var(--danger);" title="Remover Vínculo">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { apiFetch } from '../../../services/api'
import { pode } from '../../../composables/useAcesso'

// Vincular/desvincular exige objetos.edit (rotas processos/*Objeto no backend).
const podeEditar = computed(() => pode('objetos.edit'))

const props = defineProps({
  processId: { type: Number, required: true },
  objetos: {
    type: [Object, Array],
    default: () => ({ objetos: [] })
  }
})

const emit = defineEmits(['open-modal', 'toast', 'refresh'])

// O backend retorna { objetos: [...], pager: ... } para o processo
const listaObjetos = computed(() => {
  if (Array.isArray(props.objetos)) return props.objetos
  return props.objetos?.objetos || []
})

function nomeObjeto(o) {
  const partes = [o.bairro, o.quadra ? `Quadra ${o.quadra}` : null, o.lote ? `Lote ${o.lote}` : null].filter(Boolean)
  return partes.length ? partes.join(' - ') : (o.cod_interno || `Objeto ${o.id_objeto}`)
}

async function removerVinculo(objetoId) {
  if (confirm('Confirma a remoção definitiva deste vínculo?')) {
    try {
      const res = await apiFetch(`processos/desvincularObjeto/${props.processId}/${objetoId}`, {
        method: 'POST'
      })
      if (res && res.success) {
        emit('toast', { msg: 'Vínculo removido com sucesso!', type: 'success' })
        emit('refresh')
      } else {
        throw new Error(res?.message || 'Erro ao remover vínculo.')
      }
    } catch (err) {
      emit('toast', { msg: err.message || 'Erro ao remover vínculo no backend.', type: 'danger' })
    }
  }
}
</script>
