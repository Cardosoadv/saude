<template>
  <div class="memoria">
    <div v-if="carregando" class="mem-vazio">Carregando memória de cálculo...</div>
    <div v-else-if="erro" class="mem-vazio mem-erro" role="alert">{{ erro }}</div>
    <div v-else-if="!memoria" class="mem-vazio">
      Este item ainda não foi calculado. Defina um critério e use <strong>Recalcular</strong>.
    </div>

    <template v-else>
      <p class="mem-resumo">
        Data-base <strong>{{ formatDate(memoria.data_base) }}</strong> ·
        <template v-if="memoria.tipo === 'manual'">valor informado manualmente: <strong>{{ formatarReais(memoria.valor) }}</strong></template>
        <template v-else>valor do item: <strong>{{ formatarReais(memoria.valor) }}</strong></template>
      </p>

      <p v-if="memoria.tipo === 'percentual'" class="mem-resumo">
        {{ percentual }}% sobre {{ baseTexto }} ({{ formatarReais(memoria.base_valor) }}) = <strong>{{ formatarReais(memoria.valor) }}</strong>
      </p>

      <div v-if="memoria.tipo === 'calculado'" class="mem-tabela">
        <table class="table-premium">
          <thead>
            <tr>
              <th>Competência</th><th class="num">Valor original</th><th class="num">Fator</th>
              <th class="num">Corrigido</th><th class="num">Juros</th><th class="num">Subtotal</th><th></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(p, i) in memoria.parcelas" :key="i">
              <tr :class="{ abatimento: p.abatimento }">
                <td>{{ formatDate(p.data) }}<span v-if="p.abatimento" class="badge badge-info mem-tag">abatimento</span></td>
                <td class="num">{{ formatarReais(p.valor) }}</td>
                <td class="num">{{ fator(p.fator_correcao) }}</td>
                <td class="num">{{ formatarReais(p.corrigido) }}</td>
                <td class="num">{{ formatarReais(p.juros?.valor ?? 0) }}</td>
                <td class="num"><strong>{{ formatarReais(p.subtotal) }}</strong></td>
                <td class="num">
                  <button type="button" class="btn btn-ghost btn-sm" :aria-expanded="aberta === i" :aria-label="`Detalhar competência ${formatDate(p.data)}`" @click="aberta = aberta === i ? null : i">
                    <i :class="aberta === i ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"></i>
                  </button>
                </td>
              </tr>
              <tr v-if="aberta === i" class="mem-detalhe">
                <td colspan="7">
                  <div v-for="(c, j) in p.correcao" :key="'c' + j" class="mem-bloco">
                    <strong>Correção pelo {{ c.indice }}</strong>
                    de {{ formatDate(c.de) }} a {{ formatDate(c.ate) }} · fator acumulado {{ fator(c.fator) }}
                    <table class="mem-meses">
                      <thead><tr><th>Mês</th><th class="num">Variação (%)</th><th class="num">Fração</th><th class="num">Fator</th></tr></thead>
                      <tbody>
                        <tr v-for="(m, k) in c.passos" :key="k">
                          <td>{{ m.competencia }}</td><td class="num">{{ m.valor }}</td><td class="num">{{ fator(m.fracao) }}</td><td class="num">{{ fator(m.fator) }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <p v-if="!p.correcao?.length" class="mem-bloco">Sem correção monetária nesta competência.</p>
                  <div v-for="(s, j) in p.juros?.segmentos ?? []" :key="'j' + j" class="mem-bloco">
                    <strong>Juros {{ s.tipo }}</strong>
                    de {{ formatDate(s.de) }} até {{ formatDate(s.ate_exclusive) }} ({{ s.meses }} meses)
                    <template v-if="s.indice"> · índice {{ s.indice }}</template>
                    <template v-else> · {{ s.taxa_mensal }}% ao mês</template>
                    <template v-if="s.taxa_acumulada"> · taxa acumulada {{ s.taxa_acumulada }}</template>
                    <template v-if="s.fator"> · fator {{ fator(s.fator) }}</template>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
          <tfoot>
            <tr>
              <td>Total</td><td></td><td></td>
              <td class="num">{{ formatarReais(memoria.total_corrigido) }}</td>
              <td class="num">{{ formatarReais(memoria.total_juros) }}</td>
              <td class="num"><strong>{{ formatarReais(memoria.valor) }}</strong></td><td></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <ul v-if="memoria.avisos?.length" class="mem-avisos">
        <li v-for="a in memoria.avisos" :key="a"><i class="bi bi-exclamation-triangle"></i> {{ a }}</li>
      </ul>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { formatDate } from '../../services/api'
import { memoriaDoItem } from '../../services/liquidacoesApi'
import type { MemoriaCalculo } from '../../types/liquidacao'
import type { Id } from '../../types/domain'
import { formatarReais } from '../../utils/liquidacao'

const props = defineProps<{ itemId: Id }>()

const memoria = ref<MemoriaCalculo | null>(null)
const carregando = ref(false)
const erro = ref('')
const aberta = ref<number | null>(null)

const percentual = computed(() => String(memoria.value?.percentual ?? '').replace('.', ','))
const baseTexto = computed(() => ({ principal: 'o principal corrigido', principal_juros: 'o principal corrigido com juros', total: 'o total dos demais itens' } as Record<string, string>)[memoria.value?.base_percentual ?? 'principal'])

/** Fator com até 8 casas, sem zeros à direita. */
function fator(valor: string | number | undefined): string {
  if (valor === undefined || valor === null || valor === '') return ''
  const n = Number(valor)
  return Number.isFinite(n) ? n.toFixed(8).replace(/0+$/, '').replace(/\.$/, '') : String(valor)
}

async function carregar() {
  carregando.value = true
  erro.value = ''
  aberta.value = null
  try {
    memoria.value = await memoriaDoItem(props.itemId)
  } catch (e) {
    memoria.value = null
    erro.value = e instanceof Error ? e.message : 'Não foi possível carregar a memória de cálculo.'
  } finally {
    carregando.value = false
  }
}

watch(() => props.itemId, carregar, { immediate: true })
</script>

<style scoped>
.mem-vazio { padding: 14px; text-align: center; color: var(--ink-muted); font-size: .88rem; }
.mem-erro { color: var(--danger); }
.mem-resumo { margin: 0 0 8px; font-size: .88rem; }
.mem-tabela { overflow-x: auto; }
.table-premium { width: 100%; border-collapse: collapse; font-size: .84rem; }
.table-premium th, .table-premium td { padding: 6px 8px; border-bottom: 1px solid var(--border); text-align: left; }
.num { text-align: right; white-space: nowrap; }
tr.abatimento td { color: var(--ink-muted); }
.mem-tag { margin-left: 6px; }
.mem-detalhe td { background: var(--surface-alt, #f8fafc); font-size: .8rem; }
.mem-bloco { margin: 4px 0 8px; }
.mem-meses { width: 100%; max-width: 520px; border-collapse: collapse; margin-top: 4px; }
.mem-meses th, .mem-meses td { padding: 2px 6px; border-bottom: 1px dashed var(--border); text-align: left; }
.mem-meses .num { text-align: right; }
.mem-avisos { list-style: none; margin: 10px 0 0; padding: 0; color: #92400e; font-size: .82rem; }
tfoot td { font-weight: 600; border-top: 2px solid var(--border); }
</style>
