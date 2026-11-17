<template>
  <div class="criterio-form">
    <label class="crit-check">
      <input type="checkbox" v-model="f.usar_padrao" />
      Usar o critério padrão da liquidação
      <small>(o que for preenchido abaixo sobrepõe o padrão)</small>
    </label>

    <!-- Multa e honorários: percentual sobre os demais itens -->
    <fieldset v-if="percentual" class="crit-grupo">
      <legend>Percentual</legend>
      <div class="form-row col-2">
        <div class="form-group">
          <label class="form-label" :for="id('pct')">Percentual (%)</label>
          <input :id="id('pct')" class="form-control" type="text" inputmode="decimal" v-model="f.percentual" placeholder="Ex.: 10" />
        </div>
        <div class="form-group">
          <label class="form-label" :for="id('base')">Calculado sobre</label>
          <select :id="id('base')" class="form-control form-select" v-model="f.base_percentual">
            <option value="principal">Principal corrigido (sem juros)</option>
            <option value="principal_juros">Principal corrigido com juros</option>
            <option value="total">Total dos demais itens</option>
          </select>
        </div>
      </div>
    </fieldset>

    <template v-else>
      <fieldset class="crit-grupo">
        <legend>Correção monetária</legend>
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label" :for="id('indice')">Índice</label>
            <select :id="id('indice')" class="form-control form-select" v-model="f.correcao_indice">
              <option value="">Sem correção</option>
              <option v-for="i in indices" :key="i" :value="i">{{ i }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" :for="id('dini')">Termo inicial</label>
            <input :id="id('dini')" class="form-control" type="date" v-model="f.correcao_data" :disabled="!f.correcao_indice" />
          </div>
        </div>
        <div class="form-row col-2" v-if="f.correcao_indice">
          <div class="form-group">
            <label class="form-label" :for="id('mini')">Mês inicial</label>
            <select :id="id('mini')" class="form-control form-select" v-model="f.mes_inicial">
              <option value="cheio">Mês cheio</option>
              <option value="pro_rata">Pro rata dia</option>
              <option value="excluir">Excluir o mês</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" :for="id('mfim')">Mês final</label>
            <select :id="id('mfim')" class="form-control form-select" v-model="f.mes_final">
              <option value="cheio">Mês cheio</option>
              <option value="pro_rata">Pro rata dia</option>
              <option value="excluir">Excluir o mês</option>
            </select>
          </div>
        </div>
      </fieldset>

      <fieldset class="crit-grupo">
        <legend>Juros</legend>
        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label" :for="id('jtipo')">Tipo</label>
            <select :id="id('jtipo')" class="form-control form-select" v-model="f.juros_tipo">
              <option value="">Não definido</option>
              <option value="nenhum">Sem juros</option>
              <option value="simples">Simples</option>
              <option value="compostos">Compostos</option>
            </select>
          </div>
          <div class="form-group" v-if="comJuros">
            <label class="form-label" :for="id('jbase')">Incidem sobre</label>
            <select :id="id('jbase')" class="form-control form-select" v-model="f.juros_base">
              <option value="corrigido">Valor corrigido</option>
              <option value="nominal">Valor nominal</option>
            </select>
          </div>
        </div>
        <div class="form-row col-2" v-if="comJuros">
          <div class="form-group">
            <label class="form-label" :for="id('jtaxa')">Taxa mensal (%)</label>
            <input :id="id('jtaxa')" class="form-control" type="text" inputmode="decimal" v-model="f.juros_taxa" :disabled="!!f.juros_indice" placeholder="Ex.: 1" />
          </div>
          <div class="form-group">
            <label class="form-label" :for="id('jindice')">ou índice (ex.: Selic)</label>
            <select :id="id('jindice')" class="form-control form-select" v-model="f.juros_indice">
              <option value="">Taxa fixa</option>
              <option v-for="i in indices" :key="i" :value="i">{{ i }}</option>
            </select>
          </div>
        </div>
        <div class="form-group" v-if="comJuros">
          <label class="form-label" :for="id('jdata')">Termo inicial dos juros</label>
          <input :id="id('jdata')" class="form-control" type="date" v-model="f.juros_data" />
          <small class="crit-ajuda">Em branco: a partir do vencimento de cada competência.</small>
        </div>
      </fieldset>

      <fieldset class="crit-grupo">
        <legend>Troca de critério por data</legend>
        <p class="crit-ajuda">Vale desde o primeiro dia do mês informado. Ex.: IPCA-E até a emenda e Selic depois.</p>
        <div v-for="(m, i) in f.mudancas" :key="i" class="crit-linha">
          <input class="form-control" type="date" v-model="m.a_partir_de" :aria-label="`Troca ${i + 1}: a partir de`" />
          <select class="form-control form-select" v-model="m.indice" :aria-label="`Troca ${i + 1}: índice de correção`">
            <option value="">Mantém a correção</option>
            <option v-for="x in indices" :key="x" :value="x">{{ x }}</option>
          </select>
          <select class="form-control form-select" v-model="m.juros_tipo" :aria-label="`Troca ${i + 1}: juros`">
            <option value="">Mantém os juros</option>
            <option value="nenhum">Sem juros</option>
            <option value="simples">Simples</option>
            <option value="compostos">Compostos</option>
          </select>
          <input v-if="m.juros_tipo && m.juros_tipo !== 'nenhum'" class="form-control crit-curto" type="text" inputmode="decimal"
                 v-model="m.juros_taxa" placeholder="% a.m." :aria-label="`Troca ${i + 1}: taxa mensal`" />
          <button type="button" class="btn btn-ghost btn-sm" :aria-label="`Remover troca ${i + 1}`" @click="f.mudancas.splice(i, 1)"><i class="bi bi-x-lg"></i></button>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" @click="f.mudancas.push(novaMudanca())"><i class="bi bi-plus-circle"></i> Adicionar troca</button>
      </fieldset>

      <fieldset class="crit-grupo">
        <legend>Competências (parcelas do principal)</legend>
        <p class="crit-ajuda">Quando o principal é devido em várias datas, informe cada uma; cada valor é corrigido desde a sua data. Em branco: usa o valor base do item.</p>
        <div v-for="(p, i) in f.parcelas" :key="i" class="crit-linha">
          <input class="form-control" type="date" v-model="p.data" :aria-label="`Competência ${i + 1}: data`" />
          <input class="form-control" type="text" inputmode="decimal" v-model="p.valor" placeholder="Valor (R$)" :aria-label="`Competência ${i + 1}: valor`" />
          <button type="button" class="btn btn-ghost btn-sm" :aria-label="`Remover competência ${i + 1}`" @click="f.parcelas.splice(i, 1)"><i class="bi bi-x-lg"></i></button>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" @click="f.parcelas.push(novoLancamento())"><i class="bi bi-plus-circle"></i> Adicionar competência</button>
      </fieldset>

      <fieldset class="crit-grupo">
        <legend>Abatimentos (pagamentos e depósitos)</legend>
        <div v-for="(a, i) in f.abatimentos" :key="i" class="crit-linha">
          <input class="form-control" type="date" v-model="a.data" :aria-label="`Abatimento ${i + 1}: data`" />
          <input class="form-control" type="text" inputmode="decimal" v-model="a.valor" placeholder="Valor (R$)" :aria-label="`Abatimento ${i + 1}: valor`" />
          <button type="button" class="btn btn-ghost btn-sm" :aria-label="`Remover abatimento ${i + 1}`" @click="f.abatimentos.splice(i, 1)"><i class="bi bi-x-lg"></i></button>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" @click="f.abatimentos.push(novoLancamento())"><i class="bi bi-plus-circle"></i> Adicionar abatimento</button>
      </fieldset>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import type { Criterio, CriterioJuros } from '../../types/liquidacao'
import { centavosParaTexto, limparCriterio, textoParaCentavos } from '../../utils/liquidacao'

const props = defineProps<{
  modelValue: Criterio
  /** Códigos dos índices cadastrados. */
  indices: string[]
  /** Multa e honorários usam percentual sobre os demais itens. */
  percentual?: boolean
}>()
const emit = defineEmits<{ (e: 'update:modelValue', c: Criterio): void }>()

interface Linha { data: string; valor: string }
interface Mudanca { a_partir_de: string; indice: string; juros_tipo: string; juros_taxa: string }

const novoLancamento = (): Linha => ({ data: '', valor: '' })
const novaMudanca = (): Mudanca => ({ a_partir_de: '', indice: '', juros_tipo: '', juros_taxa: '' })

const uid = Math.random().toString(36).slice(2, 8)
const id = (nome: string) => `crit-${uid}-${nome}`

const f = reactive({
  usar_padrao: false,
  percentual: '',
  base_percentual: 'principal' as 'principal' | 'principal_juros' | 'total',
  correcao_indice: '',
  correcao_data: '',
  mes_inicial: 'cheio',
  mes_final: 'cheio',
  juros_tipo: '',
  juros_base: 'corrigido',
  juros_taxa: '',
  juros_indice: '',
  juros_data: '',
  mudancas: [] as Mudanca[],
  parcelas: [] as Linha[],
  abatimentos: [] as Linha[],
})

const comJuros = computed(() => f.juros_tipo === 'simples' || f.juros_tipo === 'compostos')

function preencher(c: Criterio | null | undefined) {
  c = c ?? {}
  f.usar_padrao = !!c.usar_padrao
  f.percentual = c.percentual ? String(c.percentual).replace('.', ',') : ''
  f.base_percentual = c.base_percentual ?? 'principal'
  f.correcao_indice = c.correcao?.indice ?? ''
  f.correcao_data = c.correcao?.data_inicial ?? ''
  f.mes_inicial = c.correcao?.mes_inicial ?? 'cheio'
  f.mes_final = c.correcao?.mes_final ?? 'cheio'
  f.juros_tipo = c.juros?.tipo ?? ''
  f.juros_base = c.juros?.base ?? 'corrigido'
  f.juros_taxa = c.juros?.taxa_mensal ? String(c.juros.taxa_mensal).replace('.', ',') : ''
  f.juros_indice = c.juros?.indice ?? ''
  f.juros_data = c.juros?.data_inicial && c.juros.data_inicial !== 'vencimento' ? c.juros.data_inicial : ''
  f.mudancas = (c.mudancas ?? []).map((m) => ({
    a_partir_de: m.a_partir_de,
    indice: m.correcao?.indice ?? '',
    juros_tipo: m.juros?.tipo ?? '',
    juros_taxa: m.juros?.taxa_mensal ? String(m.juros.taxa_mensal).replace('.', ',') : '',
  }))
  f.parcelas = (c.parcelas ?? []).map((p) => ({ data: p.data, valor: centavosParaTexto(p.valor) }))
  f.abatimentos = (c.abatimentos ?? []).map((p) => ({ data: p.data, valor: centavosParaTexto(p.valor) }))
}

function lancamentos(linhas: Linha[]) {
  return linhas
    .map((l) => ({ data: l.data, valor: textoParaCentavos(l.valor) }))
    .filter((l): l is { data: string; valor: number } => !!l.data && l.valor !== null)
}

/** Critério que o formulário representa agora (campos vazios saem do objeto). */
function montar(): Criterio {
  const c: Criterio = { usar_padrao: f.usar_padrao }

  if (props.percentual) {
    if (f.percentual.trim()) {
      c.percentual = f.percentual
      c.base_percentual = f.base_percentual
    }
    return limparCriterio(c)
  }

  if (f.correcao_indice) {
    c.correcao = { indice: f.correcao_indice }
    if (f.correcao_data) c.correcao.data_inicial = f.correcao_data
    if (f.mes_inicial !== 'cheio') c.correcao.mes_inicial = f.mes_inicial as 'pro_rata' | 'excluir'
    if (f.mes_final !== 'cheio') c.correcao.mes_final = f.mes_final as 'pro_rata' | 'excluir'
  }
  if (f.juros_tipo) {
    const j: CriterioJuros = { tipo: f.juros_tipo as CriterioJuros['tipo'] }
    if (comJuros.value) {
      if (f.juros_indice) j.indice = f.juros_indice
      else if (f.juros_taxa.trim()) j.taxa_mensal = f.juros_taxa
      if (f.juros_data) j.data_inicial = f.juros_data
      j.base = f.juros_base as 'corrigido' | 'nominal'
    }
    c.juros = j
  }
  const mudancas = f.mudancas.filter((m) => m.a_partir_de && (m.indice || m.juros_tipo)).map((m) => {
    const saida: NonNullable<Criterio['mudancas']>[number] = { a_partir_de: m.a_partir_de }
    if (m.indice) saida.correcao = { indice: m.indice }
    if (m.juros_tipo) {
      saida.juros = { tipo: m.juros_tipo as CriterioJuros['tipo'] }
      if (m.juros_tipo !== 'nenhum' && m.juros_taxa.trim()) saida.juros.taxa_mensal = m.juros_taxa.replace(',', '.')
    }
    return saida
  })
  if (mudancas.length) c.mudancas = mudancas
  const parcelas = lancamentos(f.parcelas)
  if (parcelas.length) c.parcelas = parcelas
  const abatimentos = lancamentos(f.abatimentos).map((a) => ({ ...a, valor: Math.abs(a.valor) }))
  if (abatimentos.length) c.abatimentos = abatimentos

  return limparCriterio(c)
}

let ultimo = ''
watch(() => props.modelValue, (c) => {
  if (JSON.stringify(c ?? {}) === ultimo) return
  preencher(c)
}, { immediate: true, deep: true })

watch(f, () => {
  const c = montar()
  ultimo = JSON.stringify(c)
  emit('update:modelValue', c)
}, { deep: true })
</script>

<style scoped>
.criterio-form { display: flex; flex-direction: column; gap: 12px; }
.crit-check { display: flex; align-items: center; gap: 8px; font-size: .9rem; }
.crit-check small, .crit-ajuda { color: var(--ink-muted); font-size: .78rem; }
.crit-grupo { border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px 12px; margin: 0; min-width: 0; }
.crit-grupo legend { font-size: .82rem; font-weight: 600; padding: 0 6px; float: none; width: auto; margin-bottom: 4px; }
.crit-linha { display: flex; gap: 6px; align-items: center; margin-bottom: 6px; flex-wrap: wrap; }
.crit-linha .form-control { flex: 1; min-width: 120px; }
.crit-linha .crit-curto { flex: 0 0 90px; min-width: 90px; }
.crit-ajuda { margin: 0 0 6px; display: block; }
</style>
