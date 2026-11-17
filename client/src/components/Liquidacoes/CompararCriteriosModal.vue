<template>
  <div class="modal-backdrop" @click.self="fechar">
    <div class="modal-box cmp-modal" role="dialog" aria-modal="true" aria-label="Comparar critérios">
      <div class="modal-header">
        <h2><i class="bi bi-columns-gap"></i> Comparar critérios — {{ item.descricao }}</h2>
        <button type="button" class="modal-close" aria-label="Fechar" @click="fechar"><i class="bi bi-x-lg"></i></button>
      </div>

      <div class="modal-body" style="padding: 1.25rem;">
        <p class="cmp-ajuda">
          Calcula o mesmo item na data-base ({{ formatDate(dataBase) }}) com critérios diferentes, sem gravar nada.
          Útil para avaliar o impacto de uma impugnação.
        </p>

        <div v-for="(c, i) in cenarios" :key="c.chave" class="cmp-cenario">
          <div class="cmp-topo">
            <input class="form-control" type="text" v-model="c.nome" maxlength="60" :aria-label="`Nome do cenário ${i + 1}`" />
            <button v-if="cenarios.length > 1" type="button" class="btn btn-ghost btn-sm" :aria-label="`Remover cenário ${i + 1}`" @click="cenarios.splice(i, 1)"><i class="bi bi-x-lg"></i></button>
          </div>
          <CriterioForm v-model="c.criterio" :indices="indices" />
        </div>

        <div class="cmp-acoes">
          <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="cenarios.length >= 4" @click="adicionar"><i class="bi bi-plus-circle"></i> Novo cenário</button>
          <button type="button" class="btn btn-primary btn-sm" :disabled="calculando" @click="comparar">{{ calculando ? 'Calculando...' : 'Comparar' }}</button>
        </div>

        <p v-if="erro" class="cmp-erro" role="alert">{{ erro }}</p>

        <table v-if="resultados.length" class="cmp-resultado">
          <thead><tr><th>Cenário</th><th class="num">Valor</th><th class="num">Diferença para o 1º</th></tr></thead>
          <tbody>
            <tr v-for="r in resultados" :key="r.nome">
              <td>{{ r.nome }}</td>
              <td class="num"><strong>{{ formatarReais(r.valor) }}</strong></td>
              <td class="num" :class="{ mais: r.diferenca > 0, menos: r.diferenca < 0 }">{{ r.diferenca === 0 ? '—' : formatarReais(r.diferenca) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="modal-footer" style="display:flex; justify-content:flex-end; padding:1rem;">
        <button type="button" class="btn btn-outline-secondary" @click="fechar">Fechar</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { ApiError, formatDate } from '../../services/api'
import { simular } from '../../services/liquidacoesApi'
import type { Criterio, ItemLiquidacao, ResultadoCenario } from '../../types/liquidacao'
import { formatarReais, limparCriterio } from '../../utils/liquidacao'
import CriterioForm from './CriterioForm.vue'

const props = defineProps<{ item: ItemLiquidacao; dataBase: string; indices: string[] }>()
const emit = defineEmits<{ (e: 'close'): void }>()

interface CenarioForm { chave: number; nome: string; criterio: Criterio }
let seq = 0
const cenarios = reactive<CenarioForm[]>([
  { chave: ++seq, nome: 'Critério atual', criterio: JSON.parse(JSON.stringify(props.item.criterio ?? {})) },
  { chave: ++seq, nome: 'Alternativa', criterio: JSON.parse(JSON.stringify(props.item.criterio ?? {})) },
])
const resultados = ref<ResultadoCenario[]>([])
const calculando = ref(false)
const erro = ref('')

function adicionar() {
  cenarios.push({ chave: ++seq, nome: `Cenário ${cenarios.length + 1}`, criterio: JSON.parse(JSON.stringify(props.item.criterio ?? {})) })
}

async function comparar() {
  calculando.value = true
  erro.value = ''
  try {
    const r = await simular({
      item_id: props.item.item_id,
      data_base: props.dataBase,
      cenarios: cenarios.map((c) => ({ nome: c.nome.trim() || 'Cenário', criterio: limparCriterio(c.criterio) })),
    })
    resultados.value = r.cenarios
  } catch (e) {
    resultados.value = []
    erro.value = e instanceof ApiError || e instanceof Error ? e.message : 'Não foi possível comparar os critérios.'
  } finally {
    calculando.value = false
  }
}

function fechar() { emit('close') }
</script>

<style scoped>
.cmp-modal { max-width: 820px; width: 100%; max-height: 92vh; display: flex; flex-direction: column; }
.cmp-modal .modal-body { overflow-y: auto; }
.cmp-ajuda { color: var(--ink-muted); font-size: .85rem; margin: 0 0 12px; }
.cmp-cenario { border: 1px solid var(--border); border-radius: 8px; padding: 10px; margin-bottom: 10px; }
.cmp-topo { display: flex; gap: 6px; margin-bottom: 8px; }
.cmp-acoes { display: flex; justify-content: space-between; margin: 8px 0 12px; }
.cmp-erro { color: var(--danger); font-size: .85rem; }
.cmp-resultado { width: 100%; border-collapse: collapse; font-size: .88rem; }
.cmp-resultado th, .cmp-resultado td { padding: 6px 8px; border-bottom: 1px solid var(--border); text-align: left; }
.num { text-align: right !important; white-space: nowrap; }
.mais { color: var(--danger); }
.menos { color: var(--success); }
</style>
