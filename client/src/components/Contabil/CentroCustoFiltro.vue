<template>
  <div class="centro-custo-filtro">
    <label class="form-label">Centro de custo</label>
    <div class="cc-linha">
      <select class="form-control form-select cc-tipo" :value="modelValue?.tipo ?? ''" @change="trocarTipo(($event.target as HTMLSelectElement).value)" aria-label="Tipo de centro de custo">
        <option value="">Todos</option>
        <option value="cliente">Cliente</option>
      </select>

      <template v-if="modelValue?.tipo">
        <div v-if="modelValue.id" class="cc-escolhido">
          <span>{{ modelValue.nome || `#${modelValue.id}` }}</span>
          <button type="button" class="btn btn-sm btn-outline-danger" title="Trocar" @click="emitir({ ...modelValue, id: null, nome: null })"><i class="bi bi-x-lg"></i></button>
        </div>

        <ClienteAutocomplete v-else @select="c => emitir({ tipo: 'cliente', id: Number(c.id), nome: c.nome || c.text })" />
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import ClienteAutocomplete from '../ClienteAutocomplete.vue'

export type TipoCentroCusto = 'cliente'
export interface CentroCusto { tipo: TipoCentroCusto, id: number | null, nome: string | null }

/**
 * Filtro de centro de custo dos relatórios contábeis. Vira `dimension_type` / `dimension_id`
 * na chamada ao contábil. Sem id, o relatório soma tudo que tem aquele tipo de centro de custo.
 */
defineProps<{ modelValue: CentroCusto | null }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: CentroCusto | null): void }>()

function emitir(v: CentroCusto | null) {
  emit('update:modelValue', v)
}

function trocarTipo(tipo: string) {
  emitir(tipo ? { tipo: tipo as TipoCentroCusto, id: null, nome: null } : null)
}
</script>

<style scoped>
.cc-linha { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.cc-tipo { width: auto; min-width: 140px; }
.cc-linha > :deep(.cliente-autocomplete) { flex: 1; min-width: 200px; }
.cc-escolhido { display: flex; align-items: center; gap: 8px; padding: 4px 4px 4px 10px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--input-bg); font-size: .85rem; }
</style>
