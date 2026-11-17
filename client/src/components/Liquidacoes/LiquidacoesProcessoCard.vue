<template>
  <div class="section-card">
    <div class="section-header">
      <span><i class="bi bi-calculator"></i> Liquidação</span>
      <button v-if="pode('liquidacoes.create')" class="btn btn-outline-primary btn-sm" @click="novaAberta = true">
        <i class="bi bi-plus-circle"></i> Nova liquidação
      </button>
    </div>
    <div class="section-body">
      <p v-if="erro" class="liq-erro" role="alert">{{ erro }}</p>
      <p v-else-if="carregando" class="liq-vazio">Carregando...</p>
      <p v-else-if="!itens.length" class="liq-vazio">Nenhuma liquidação neste processo.</p>
      <ul v-else class="liq-lista">
        <li
          v-for="l in itens"
          :key="l.liquidacao_id"
          class="liq-linha"
          role="button"
          tabindex="0"
          @click="$emit('open-liquidacao', l.liquidacao_id)"
          @keydown.enter="$emit('open-liquidacao', l.liquidacao_id)"
        >
          <div>
            <span :class="['badge', `badge-${tomStatus(l.status)}`]">{{ STATUS_LIQUIDACAO[l.status] ?? l.status }}</span>
            <span class="liq-tipo">{{ TIPOS_LIQUIDACAO[l.tipo_liquidacao] ?? l.tipo_liquidacao }}</span>
          </div>
          <div class="liq-valores">
            <strong>{{ formatarReais(l.valor_total) }}</strong>
            <small>Saldo: {{ formatarReais(saldoAReceber(l.valor_total, Number(l.total_pago ?? 0))) }}</small>
          </div>
        </li>
      </ul>
    </div>

    <NovaLiquidacaoModal
      v-if="novaAberta"
      :processo-id="processId"
      @close="novaAberta = false"
      @criada="(id) => { novaAberta = false; $emit('open-liquidacao', id) }"
    />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { listarPorProcesso } from '../../services/liquidacoesApi'
import { pode } from '../../composables/useAcesso'
import type { Liquidacao } from '../../types/liquidacao'
import { STATUS_LIQUIDACAO, TIPOS_LIQUIDACAO, formatarReais, saldoAReceber, tomStatus } from '../../utils/liquidacao'
import NovaLiquidacaoModal from './NovaLiquidacaoModal.vue'

const props = defineProps<{ processId: number | string }>()
defineEmits<{ (e: 'open-liquidacao', id: number | string): void }>()

const itens = ref<Liquidacao[]>([])
const carregando = ref(false)
const erro = ref('')
const novaAberta = ref(false)

async function carregar() {
  carregando.value = true
  erro.value = ''
  try {
    itens.value = await listarPorProcesso(props.processId)
  } catch (e) {
    erro.value = e instanceof Error ? e.message : 'Não foi possível carregar as liquidações.'
  } finally {
    carregando.value = false
  }
}

watch(() => props.processId, carregar)
onMounted(carregar)
</script>

<style scoped>
.liq-vazio { color: var(--ink-muted); margin: 0; }
.liq-erro { color: var(--danger); margin: 0; }
.liq-lista { list-style: none; margin: 0; padding: 0; display: grid; gap: .5rem; }
.liq-linha { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .6rem .75rem; border: 1px solid var(--border); border-radius: 8px; cursor: pointer; }
.liq-linha:hover { background: var(--surface-hover, rgba(0,0,0,.03)); }
.liq-tipo { margin-left: .5rem; font-size: .85rem; }
.liq-valores { display: flex; flex-direction: column; align-items: flex-end; }
</style>
