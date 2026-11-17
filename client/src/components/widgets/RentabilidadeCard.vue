<template>
  <div class="section-card rentabilidade-card">
    <div class="section-header">
      <span><i class="bi bi-graph-up-arrow"></i> Rentabilidade</span>
      <select class="form-control form-select form-select-sm rent-periodo" v-model="periodo" aria-label="Período">
        <option value="ano">Este ano</option>
        <option value="12m">Últimos 12 meses</option>
        <option value="tudo">Desde o início</option>
      </select>
    </div>

    <div class="section-body">
      <div v-if="carregando" class="rent-vazio">Calculando...</div>
      <div v-else-if="erro" class="rent-vazio text-danger">{{ erro }}</div>
      <template v-else-if="linha">
        <div class="rent-grade">
          <div><small>Receitas</small><strong class="pos">{{ formatCurrency(linha.receita) }}</strong></div>
          <div><small>Despesas</small><strong class="neg">{{ formatCurrency(linha.despesa) }}</strong></div>
          <div><small>Resultado</small><strong :class="linha.resultado >= 0 ? 'pos' : 'neg'">{{ formatCurrency(linha.resultado) }}</strong></div>
          <div><small>Margem</small><strong>{{ linha.margem === null ? '—' : formatPercent(linha.margem) }}</strong></div>
        </div>

        <div v-if="!lancamentos.length" class="rent-vazio">
          Nenhum recebimento ou pagamento vinculado no período. Vincule receitas e despesas a este {{ tipo }} na tela do lançamento.
        </div>
        <template v-else>
          <button type="button" class="rent-toggle" @click="mostrarLancamentos = !mostrarLancamentos">
            <i :class="mostrarLancamentos ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"></i>
            {{ lancamentos.length }} {{ lancamentos.length === 1 ? 'lançamento' : 'lançamentos' }}
          </button>
          <ul v-if="mostrarLancamentos" class="widget-list rent-lista">
            <li v-for="l in lancamentos" :key="l.tipo + l.titulo_id + l.data">
              <span class="rent-desc">
                <small>{{ formatDate(l.data) }}</small> {{ l.descricao || (l.tipo === 'receita' ? 'Receita' : 'Despesa') }}
              </span>
              <span :class="l.tipo === 'receita' ? 'pos' : 'neg'">{{ l.tipo === 'receita' ? '+' : '−' }} {{ formatCurrency(l.valor) }}</span>
            </li>
          </ul>
        </template>
        <small class="rent-nota">Regime de caixa: valores efetivamente recebidos e pagos.</small>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { apiFetch, formatCurrency, formatDate } from '../../services/api'

/**
 * Rentabilidade de um caso ou processo (issue #892): receitas recebidas, despesas pagas, resultado e margem,
 * vindos de `legal-ops/rentabilidade`. Quem usa decide se exibe (módulo gestão + `financeiro.access`).
 * @version 1.0.1
 */
const props = defineProps<{
  tipo: 'caso' | 'processo'
  id: number | string
}>()

interface Linha { id: number | null, nome: string, receita: number, despesa: number, resultado: number, margem: number | null, qtd_lancamentos: number }
interface Lancamento { tipo: 'receita' | 'despesa', titulo_id: number, data: string, descricao: string, valor: number }

const periodo = ref<'ano' | '12m' | 'tudo'>('ano')
const carregando = ref(false)
const erro = ref('')
const linha = ref<Linha | null>(null)
const lancamentos = ref<Lancamento[]>([])
const mostrarLancamentos = ref(false)

const hoje = () => new Date().toISOString().slice(0, 10)

const intervalo = computed(() => {
  const fim = hoje()
  const d = new Date()
  if (periodo.value === '12m') {
    d.setFullYear(d.getFullYear() - 1)
    return { inicio: d.toISOString().slice(0, 10), fim }
  }
  if (periodo.value === 'tudo') return { inicio: '2000-01-01', fim }
  return { inicio: `${d.getFullYear()}-01-01`, fim }
})

function formatPercent(v: number): string {
  return `${(v * 100).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`
}

async function carregar() {
  if (!props.id) return
  carregando.value = true
  erro.value = ''
  try {
    const q = new URLSearchParams({ agrupar: props.tipo, id: String(props.id), ...intervalo.value })
    const res = await apiFetch(`legal-ops/rentabilidade?${q}`)
    linha.value = res?.grupos?.[0] ?? null
    lancamentos.value = res?.lancamentos ?? []
  } catch (e: any) {
    erro.value = e?.message || 'Não foi possível calcular a rentabilidade.'
    linha.value = null
    lancamentos.value = []
  } finally {
    carregando.value = false
  }
}

watch(() => [props.tipo, props.id, periodo.value], carregar, { immediate: true })
</script>

<style scoped>
.rent-periodo { width: auto; font-size: .78rem; font-weight: 500; padding: 4px 28px 4px 10px; border-radius: 6px; color: var(--ink-dim); background-color: var(--card); cursor: pointer; }
.rent-grade { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.rent-grade div { display: flex; flex-direction: column; }
.rent-grade small { font-size: .72rem; color: var(--ink-muted); }
.rent-grade strong { font-size: .95rem; font-variant-numeric: tabular-nums; }
.pos { color: var(--success, #2e7d32); }
.neg { color: var(--danger, #c0392b); }
.rent-vazio { font-size: .8rem; color: var(--ink-muted); margin-top: 8px; }
.rent-toggle { margin-top: 10px; border: 0; background: transparent; color: var(--primary); font-size: .8rem; padding: 0; cursor: pointer; }
.rent-lista li { display: flex; justify-content: space-between; gap: 8px; font-size: .8rem; padding: 6px 0; }
.rent-desc { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rent-desc small { color: var(--ink-muted); margin-right: 4px; }
.rent-nota { display: block; margin-top: 10px; font-size: .7rem; color: var(--ink-muted); }
</style>
