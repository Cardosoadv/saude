<template>
  <div class="centro-custo-filtro">
    <label class="form-label">Centro de custo</label>
    <div class="cc-linha">
      <select class="form-control form-select cc-tipo" :value="modelValue?.tipo ?? ''" @change="trocarTipo(($event.target as HTMLSelectElement).value)" aria-label="Tipo de centro de custo">
        <option value="">Todos</option>
        <option value="processo">Processo</option>
        <option value="caso">Caso</option>
        <option value="cliente">Cliente</option>
        <option value="ramo_direito">Ramo do direito</option>
      </select>

      <template v-if="modelValue?.tipo">
        <div v-if="modelValue.id" class="cc-escolhido">
          <span>{{ modelValue.nome || `#${modelValue.id}` }}</span>
          <button type="button" class="btn btn-sm btn-outline-danger" title="Trocar" @click="emitir({ ...modelValue, id: null, nome: null })"><i class="bi bi-x-lg"></i></button>
        </div>

        <select v-else-if="modelValue.tipo === 'ramo_direito'" class="form-control form-select" @change="escolherRamo(($event.target as HTMLSelectElement))" aria-label="Ramo do direito">
          <option value="">Selecione...</option>
          <option v-for="r in ramosPlanos" :key="r.id" :value="String(r.id)">{{ r.rotulo }}</option>
        </select>

        <ClienteAutocomplete v-else-if="modelValue.tipo === 'cliente'" @select="c => emitir({ tipo: 'cliente', id: Number(c.id), nome: c.nome || c.text })" />

        <div v-else class="position-relative cc-busca">
          <input
            type="text"
            class="form-control"
            v-model="busca"
            @input="buscar(busca)"
            :placeholder="modelValue.tipo === 'caso' ? 'Buscar caso...' : 'Buscar processo...'"
            autocomplete="off"
          >
          <ul v-if="opcoes.length" class="autocomplete-list glass-panel shadow z-3 position-absolute w-100">
            <li v-for="o in opcoes" :key="o.id" @click="escolher(o)">
              <div class="autocomplete-item-title">{{ o.rotulo }}</div>
              <small class="autocomplete-item-sub">{{ o.cliente_nome || 'Sem cliente' }}</small>
            </li>
          </ul>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { apiFetch, debounce } from '../../services/api'
import ClienteAutocomplete from '../ClienteAutocomplete.vue'

export type TipoCentroCusto = 'processo' | 'caso' | 'cliente' | 'ramo_direito'
export interface CentroCusto { tipo: TipoCentroCusto, id: number | null, nome: string | null }

/**
 * Filtro de centro de custo dos relatórios contábeis (issue #892). Vira `dimension_type` / `dimension_id`
 * na chamada ao contábil. Sem id, o relatório soma tudo que tem aquele tipo de centro de custo.
 */
const props = defineProps<{ modelValue: CentroCusto | null }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: CentroCusto | null): void }>()

interface Ramo { id: number | string, nome: string, filhos?: Ramo[] }

const busca = ref('')
const opcoes = ref<{ id: number, rotulo: string, cliente_nome: string | null }[]>([])
const ramos = ref<Ramo[]>([])

const ramosPlanos = computed(() => {
  const saida: { id: number | string, nome: string, rotulo: string }[] = []
  const percorrer = (lista: Ramo[], nivel: number) => {
    for (const r of lista) {
      saida.push({ id: r.id, nome: r.nome, rotulo: `${'   '.repeat(nivel)}${nivel ? '↳ ' : ''}${r.nome}` })
      if (r.filhos?.length) percorrer(r.filhos, nivel + 1)
    }
  }
  percorrer(ramos.value, 0)
  return saida
})

function emitir(v: CentroCusto | null) {
  emit('update:modelValue', v)
}

function trocarTipo(tipo: string) {
  busca.value = ''
  opcoes.value = []
  emitir(tipo ? { tipo: tipo as TipoCentroCusto, id: null, nome: null } : null)
}

function escolherRamo(sel: HTMLSelectElement) {
  const r = ramosPlanos.value.find(x => String(x.id) === sel.value)
  if (r) emitir({ tipo: 'ramo_direito', id: Number(r.id), nome: r.nome })
}

function escolher(o: { id: number, rotulo: string }) {
  if (!props.modelValue) return
  emitir({ tipo: props.modelValue.tipo, id: o.id, nome: o.rotulo })
  busca.value = ''
  opcoes.value = []
}

const buscar = debounce(async (termo: string) => {
  const tipo = props.modelValue?.tipo
  if (termo.trim().length < 2 || (tipo !== 'caso' && tipo !== 'processo')) {
    opcoes.value = []
    return
  }
  try {
    const res = await apiFetch(`financeiro/vinculos/buscar?q=${encodeURIComponent(termo.trim())}&limite=15`)
    opcoes.value = (res?.data || []).filter((o: any) => o.tipo === tipo)
  } catch (e) {
    opcoes.value = []
  }
}, 300)

watch(() => props.modelValue?.tipo, async (tipo) => {
  if (tipo === 'ramo_direito' && !ramos.value.length) {
    try {
      const res = await apiFetch('processos/ramos')
      ramos.value = Array.isArray(res) ? res : (res?.data || [])
    } catch (e) {
      console.error('Erro ao buscar ramos:', e)
    }
  }
})
</script>

<style scoped>
.cc-linha { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.cc-tipo { width: auto; min-width: 140px; }
.cc-busca, .cc-linha > :deep(.cliente-autocomplete) { flex: 1; min-width: 200px; }
.cc-escolhido { display: flex; align-items: center; gap: 8px; padding: 4px 4px 4px 10px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--input-bg); font-size: .85rem; }
</style>
