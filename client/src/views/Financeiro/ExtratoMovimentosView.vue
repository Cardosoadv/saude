<template>
  <div class="page-content animate-fade-in">
    <div class="breadcrumb">
      <a href="#" @click.prevent="emit('set-view', 'dashboard')">Ordo Juris</a>
      <i class="bi bi-chevron-right"></i>
      <span>Movimentos do Extrato</span>
    </div>

    <div class="page-header">
      <div>
        <h1>Movimentos do Extrato</h1>
        <p>Concilie com lançamentos existentes ou lance o que ainda não está no sistema.</p>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" @click="emit('set-view', 'extrato-importacao')">
        <i class="bi bi-file-earmark-arrow-up"></i> Importar Extrato
      </button>
    </div>

    <div class="ex-toolbar">
        <div class="ex-field">
          <label class="form-label" for="mov-conta">Conta</label>
          <select id="mov-conta" class="form-control form-select" v-model="filtro.conta_id" @change="aplicarFiltros">
            <option v-for="c in contas" :key="c.id_conta" :value="c.id_conta">{{ c.conta }}</option>
          </select>
        </div>
        <div class="ex-field"><label class="form-label" for="mov-ini">De</label><input id="mov-ini" type="date" class="form-control" v-model="filtro.data_inicio" @change="aplicarFiltros" /></div>
        <div class="ex-field"><label class="form-label" for="mov-fim">Até</label><input id="mov-fim" type="date" class="form-control" v-model="filtro.data_fim" @change="aplicarFiltros" /></div>
        <div class="ex-field">
          <label class="form-label" for="mov-status">Status</label>
          <select id="mov-status" class="form-control form-select" v-model="filtro.status" @change="aplicarFiltros">
            <option value="">Todos</option>
            <option v-for="(r, k) in ROTULO_STATUS" :key="k" :value="k">{{ r }}</option>
          </select>
        </div>
        <div class="ex-field ex-field-grow">
          <label class="form-label" for="mov-q">Buscar</label>
          <input id="mov-q" class="form-control" v-model="filtro.q" placeholder="Descrição ou contraparte" @input="buscarComAtraso" @keydown.enter="aplicarFiltros" />
        </div>
    </div>

    <div class="ex-toolbar" v-if="tab === 'movimentos' && (podeConciliar || podeLancar)" style="align-items:center;">
        <button v-if="podeConciliar" class="btn btn-primary btn-sm" :disabled="ocupadoLote || !filtro.conta_id" @click="abrirPreviaLote">
          <i class="bi bi-magic"></i> Conciliar sugestões seguras
        </button>
        <button v-if="podeLancar" class="btn btn-ghost btn-sm" :disabled="ocupadoLote || !selecionados.length" @click="lancarSelecionados">
          <span v-if="lancandoLote" class="spinner spinner-sm me-1"></span><i v-else class="bi bi-journal-plus"></i>
          Lançar selecionados pela regra ({{ selecionados.length }})
        </button>
        <small class="text-ink-muted">Seleção em lote: até {{ LIMITE_LOTE }} movimentos pendentes.</small>
    </div>

    <div class="filter-tabs mb-4" role="tablist">
      <button v-for="a in ABAS" :key="a.id" type="button" role="tab" :aria-selected="tab === a.id" class="filter-tab" :class="{ active: tab === a.id }" @click="tab = a.id">{{ a.nome }}</button>
    </div>

    <div class="card ex-card">

      <template v-if="tab === 'movimentos'">
        <div class="ex-state" v-if="carregando"><div class="spinner spinner-sm"></div> Carregando movimentos...</div>
        <p v-else-if="erro" class="mov-erro ex-state mb-0" role="alert">{{ erro }}</p>
        <div class="ex-state" v-else-if="!movimentos.length">Nenhum movimento encontrado para os filtros escolhidos.</div>
        <div class="ex-table-wrap" v-else>
          <table class="ex-table">
            <thead>
              <tr>
                <th style="width:2rem"></th><th>Data</th><th>Descrição</th><th class="num">Valor</th><th>Status</th><th class="num">Ações</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in movimentos" :key="String(m.id_movimento)">
                <td><input v-if="m.status === 'pendente' && podeLancar" type="checkbox" :checked="marcados.has(m.id_movimento)" :aria-label="'Selecionar ' + descricaoMovimento(m)" @change="alternar(m)" /></td>
                <td>{{ formatDate(m.data) }}</td>
                <td>{{ descricaoMovimento(m) }}<br /><small v-if="m.memo && m.nome_contraparte" class="text-ink-muted">{{ m.memo }}</small></td>
                <td class="num" :class="m.valor_centavos < 0 ? 'text-danger' : 'text-success'">{{ formatarCentavos(m.valor_centavos) }}</td>
                <td>
                  <span class="badge" :class="'badge-' + COR_STATUS[m.status]">{{ ROTULO_STATUS[m.status] }}</span>
                  <br v-if="m.motivo_ignorado" /><small v-if="m.motivo_ignorado" class="text-ink-muted">{{ m.motivo_ignorado }}</small>
                </td>
                <td class="num mov-acoes">
                  <template v-if="m.status === 'pendente'">
                    <button v-if="podeConciliar" class="btn btn-sm btn-primary" @click="conciliando = m">Conciliar</button>
                    <button v-if="podeLancar" class="btn btn-sm btn-ghost" @click="lancando = m">Lançar</button>
                    <button v-if="podeConciliar" class="btn btn-sm btn-ghost" @click="abrirIgnorar(m)">Ignorar</button>
                  </template>
                  <template v-else>
                    <button v-if="podeAnexar(m)" class="btn btn-sm btn-ghost" :disabled="anexando === m.id_movimento" @click="anexar(m)"><i class="bi bi-paperclip"></i> Comprovante</button>
                    <button v-if="podeConciliar && (m.status === 'conciliado' || m.status === 'ignorado')" class="btn btn-sm btn-ghost" :disabled="desfazendo === m.id_movimento" @click="desfazer(m)">Desfazer</button>
                    <small v-else-if="m.status === 'lancado'" class="text-ink-muted" title="Para desfazer, exclua o pagamento lançado.">Excluir o pagamento desfaz</small>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="ex-paginacao" v-if="!carregando && !erro && movimentos.length && total > POR_PAGINA">
          <button class="btn btn-ghost btn-sm" :disabled="pagina <= 1" @click="irParaPagina(pagina - 1)">Anterior</button>
          <span>Página {{ pagina }} de {{ totalPaginas }} · {{ total }} movimentos</span>
          <button class="btn btn-ghost btn-sm" :disabled="pagina >= totalPaginas" @click="irParaPagina(pagina + 1)">Próxima</button>
        </div>
        <input ref="seletorComprovante" type="file" multiple hidden :accept="ACCEPT_INPUT" @change="aoEscolherComprovante" />
      </template>

      <ConferenciaSaldoPanel v-else-if="tab === 'conferencia'" :conta-id="filtro.conta_id || null" />
      <RegrasExtratoPanel v-else :conta-id="filtro.conta_id || null" @toast="(t) => emit('toast', t)" />
    </div>

    <ConciliarMovimentoModal v-if="conciliando" :movimento="conciliando" @close="conciliando = null" @feito="aoFeito" />
    <LancarMovimentoModal v-if="lancando" :movimento="lancando" @close="lancando = null" @feito="aoFeito" />

    <!-- Ignorar -->
    <div class="modal-backdrop" v-if="ignorando" @click.self="ignorando = null">
      <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="ign-titulo">
        <div class="modal-header"><h2 id="ign-titulo">Ignorar movimento</h2></div>
        <div class="modal-body" style="padding:1.5rem;">
          <p><strong>{{ descricaoMovimento(ignorando) }}</strong> · {{ formatarCentavos(ignorando.valor_centavos) }}</p>
          <label class="form-label" for="ign-motivo">Motivo</label>
          <input id="ign-motivo" class="form-control" v-model="motivo" maxlength="255" @keydown.enter.prevent="confirmarIgnorar" />
          <p v-if="erroIgnorar" class="mov-erro mt-2" role="alert">{{ erroIgnorar }}</p>
        </div>
        <div class="modal-footer" style="display:flex;justify-content:flex-end;gap:.5rem;padding:1rem;">
          <button class="btn btn-ghost" :disabled="ignorandoEnvio" @click="ignorando = null">Cancelar</button>
          <button class="btn btn-primary" :disabled="ignorandoEnvio || !motivo.trim()" @click="confirmarIgnorar">{{ ignorandoEnvio ? 'Ignorando...' : 'Ignorar' }}</button>
        </div>
      </div>
    </div>

    <!-- Prévia da conciliação em lote -->
    <div class="modal-backdrop" v-if="previaLote" @click.self="fecharLote">
      <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="lote-titulo">
        <div class="modal-header"><h2 id="lote-titulo">Conciliar sugestões seguras</h2></div>
        <div class="modal-body" style="padding:1.5rem;">
          <template v-if="!resultadoLote">
            <p v-if="!previaLote.length" class="mb-0">Nenhuma sugestão de confiança alta para esta conta.</p>
            <template v-else>
              <p>Valor e data exatos, um só candidato. Confira antes de confirmar ({{ previaLote.length }}).</p>
              <ul class="mov-lote">
                <li v-for="i in previaLote" :key="String(i.movimento.id_movimento)">
                  {{ formatDate(i.movimento.data) }} · {{ descricaoMovimento(i.movimento) }} · {{ formatarCentavos(i.movimento.valor_centavos) }}
                  <i class="bi bi-arrow-right"></i> {{ i.pagamento.descricao || i.pagamento.contraparte || `${i.pagamento_tipo} #${i.pagamento_id}` }}
                </li>
              </ul>
            </template>
          </template>
          <template v-else>
            <p><strong>{{ resultadoLote.conciliados.length }}</strong> conciliados, <strong>{{ resultadoLote.rejeitados.length }}</strong> rejeitados.</p>
            <ul class="mov-lote" v-if="resultadoLote.rejeitados.length">
              <li v-for="r in resultadoLote.rejeitados" :key="String(r.movimento_id)">Movimento {{ r.movimento_id }}: {{ r.motivo }}</li>
            </ul>
          </template>
          <p v-if="erroLote" class="mov-erro" role="alert">{{ erroLote }}</p>
        </div>
        <div class="modal-footer" style="display:flex;justify-content:flex-end;gap:.5rem;padding:1rem;">
          <button class="btn btn-ghost" :disabled="conciliandoLote" @click="fecharLote">Fechar</button>
          <button v-if="!resultadoLote && previaLote.length" class="btn btn-primary" :disabled="conciliandoLote" @click="confirmarLote">{{ conciliandoLote ? 'Conciliando...' : 'Confirmar conciliação' }}</button>
        </div>
      </div>
    </div>

    <!-- Resultado do lançamento em lote -->
    <div class="modal-backdrop" v-if="resultadoLancamento" @click.self="resultadoLancamento = null">
      <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="lanc-lote-titulo">
        <div class="modal-header"><h2 id="lanc-lote-titulo">Resultado do lote</h2></div>
        <div class="modal-body" style="padding:1.5rem;">
          <p><strong>{{ resultadoLancamento.lancados }}</strong> lançados, <strong>{{ resultadoLancamento.rejeitados }}</strong> não lançados.</p>
          <ul class="mov-lote">
            <li v-for="i in resultadoLancamento.itens" :key="String(i.movimento_id)">
              <i :class="i.ok ? 'bi bi-check-circle text-success' : 'bi bi-x-circle text-danger'"></i>
              {{ nomeDoMovimento(i.movimento_id) }}{{ i.ok ? '' : ': ' + i.motivo }}
            </li>
          </ul>
        </div>
        <div class="modal-footer" style="display:flex;justify-content:flex-end;padding:1rem;">
          <button class="btn btn-primary" @click="resultadoLancamento = null">Fechar</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import ConciliarMovimentoModal from '../../components/Financeiro/ConciliarMovimentoModal.vue'
import ConferenciaSaldoPanel from '../../components/Financeiro/ConferenciaSaldoPanel.vue'
import LancarMovimentoModal from '../../components/Financeiro/LancarMovimentoModal.vue'
import RegrasExtratoPanel from '../../components/Financeiro/RegrasExtratoPanel.vue'
import { pode } from '../../composables/useAcesso'
import { apiFetch, debounce, formatDate } from '../../services/api'
import {
  anexarComprovantesAoMovimento, conciliarLote, desconciliarMovimento, ignorarMovimento, lancarLote,
  listarMovimentos, previaConciliacaoLote, sugestaoLancamento,
  type MovimentoExtrato, type ResultadoLancamentoLote, type ResultadoConciliacaoLote, type SugestaoLote,
} from '../../services/extratosApi'
import { ACCEPT_INPUT, validarLote } from '../../utils/comprovantes'
import { LIMITE_LOTE, ROTULO_STATUS, dadosPorRegra, descricaoMovimento, formatarCentavos, limitarSelecao } from '../../utils/extratos'

const emit = defineEmits<{ (e: 'toast', t: { msg: string; type: 'success' | 'danger' | 'info' | 'warning' }): void; (e: 'set-view', v: string): void }>()

const POR_PAGINA = 50
const ABAS = [
  { id: 'movimentos', nome: 'Movimentos' },
  { id: 'conferencia', nome: 'Conferência de saldo' },
  { id: 'regras', nome: 'Regras de classificação' },
] as const
const COR_STATUS: Record<string, string> = { pendente: 'warning', conciliado: 'info', lancado: 'success', ignorado: 'secondary' }

const podeConciliar = pode('financeiro.extratos_conciliar')
const podeLancar = pode('financeiro.extratos_lancar')
const podeAnexar = (m: MovimentoExtrato) =>
  pode('financeiro.edit') && (m.status === 'conciliado' || m.status === 'lancado') && (m.pagamento_tipo === 'despesa' || m.pagamento_tipo === 'receita')

const tab = ref<(typeof ABAS)[number]['id']>('movimentos')
const contas = ref<any[]>([])
const filtro = reactive({ conta_id: '' as number | string, data_inicio: '', data_fim: '', status: 'pendente' as string, q: '' })

const movimentos = ref<MovimentoExtrato[]>([])
const total = ref(0)
const pagina = ref(1)
const carregando = ref(false)
const erro = ref('')
const totalPaginas = computed(() => Math.max(1, Math.ceil(total.value / POR_PAGINA)))

const marcados = reactive(new Map<number | string, MovimentoExtrato>())
const selecionados = computed(() => [...marcados.values()])

const conciliando = ref<MovimentoExtrato | null>(null)
const lancando = ref<MovimentoExtrato | null>(null)

async function carregar() {
  if (!filtro.conta_id) return
  carregando.value = true
  erro.value = ''
  try {
    const r = await listarMovimentos({ ...filtro, status: filtro.status as any }, pagina.value, POR_PAGINA)
    movimentos.value = r.itens
    total.value = r.total
  } catch (e) {
    movimentos.value = []
    erro.value = e instanceof Error ? e.message : 'Não foi possível carregar os movimentos.'
  } finally {
    carregando.value = false
  }
}

function aplicarFiltros() {
  pagina.value = 1
  marcados.clear()
  carregar()
}
const buscarComAtraso = debounce(aplicarFiltros, 400)

function irParaPagina(p: number) {
  pagina.value = Math.min(Math.max(1, p), totalPaginas.value)
  carregar()
}

function alternar(m: MovimentoExtrato) {
  if (marcados.has(m.id_movimento)) {
    marcados.delete(m.id_movimento)
    return
  }
  if (marcados.size >= LIMITE_LOTE) {
    emit('toast', { msg: `Selecione no máximo ${LIMITE_LOTE} movimentos por lote.`, type: 'warning' })
    return
  }
  marcados.set(m.id_movimento, m)
}

function aoFeito(mensagem: string) {
  conciliando.value = null
  lancando.value = null
  emit('toast', { msg: mensagem, type: 'success' })
  marcados.clear()
  carregar()
}

const nomeDoMovimento = (id: number | string) => {
  const m = marcados.get(id) ?? movimentos.value.find((x) => String(x.id_movimento) === String(id))
  return m ? `${formatDate(m.data)} · ${descricaoMovimento(m)}` : `Movimento ${id}`
}

// --- Ignorar / desfazer ---
const ignorando = ref<MovimentoExtrato | null>(null)
const motivo = ref('')
const erroIgnorar = ref('')
const ignorandoEnvio = ref(false)
const desfazendo = ref<number | string | null>(null)

function abrirIgnorar(m: MovimentoExtrato) {
  ignorando.value = m
  motivo.value = ''
  erroIgnorar.value = ''
}

async function confirmarIgnorar() {
  if (!ignorando.value || ignorandoEnvio.value || !motivo.value.trim()) return
  ignorandoEnvio.value = true
  erroIgnorar.value = ''
  try {
    await ignorarMovimento(ignorando.value.id_movimento, motivo.value.trim())
    ignorando.value = null
    emit('toast', { msg: 'Movimento ignorado.', type: 'success' })
    carregar()
  } catch (e) {
    erroIgnorar.value = e instanceof Error ? e.message : 'Não foi possível ignorar o movimento.'
  } finally {
    ignorandoEnvio.value = false
  }
}

async function desfazer(m: MovimentoExtrato) {
  if (desfazendo.value) return
  desfazendo.value = m.id_movimento
  try {
    await desconciliarMovimento(m.id_movimento)
    emit('toast', { msg: 'O movimento voltou para pendente.', type: 'success' })
    carregar()
  } catch (e) {
    emit('toast', { msg: e instanceof Error ? e.message : 'Não foi possível desfazer.', type: 'danger' })
  } finally {
    desfazendo.value = null
  }
}

// --- Comprovante ---
const seletorComprovante = ref<HTMLInputElement | null>(null)
const anexando = ref<number | string | null>(null)
let alvoComprovante: MovimentoExtrato | null = null

function anexar(m: MovimentoExtrato) {
  alvoComprovante = m
  seletorComprovante.value?.click()
}

async function aoEscolherComprovante(ev: Event) {
  const input = ev.target as HTMLInputElement
  const arquivos = Array.from(input.files ?? [])
  input.value = ''
  const alvo = alvoComprovante
  if (!alvo || !arquivos.length) return
  const problema = validarLote(arquivos)
  if (problema) {
    emit('toast', { msg: problema, type: 'danger' })
    return
  }
  anexando.value = alvo.id_movimento
  try {
    const r = await anexarComprovantesAoMovimento(alvo.id_movimento, arquivos)
    emit('toast', r.erros?.length ? { msg: `Alguns arquivos falharam: ${r.erros[0].mensagem}`, type: 'warning' } : { msg: 'Comprovante anexado.', type: 'success' })
  } catch (e) {
    emit('toast', { msg: e instanceof Error ? e.message : 'Não foi possível anexar o comprovante.', type: 'danger' })
  } finally {
    anexando.value = null
  }
}

// --- Lote ---
const previaLote = ref<SugestaoLote[] | null>(null)
const resultadoLote = ref<ResultadoConciliacaoLote | null>(null)
const erroLote = ref('')
const conciliandoLote = ref(false)
const lancandoLote = ref(false)
const resultadoLancamento = ref<ResultadoLancamentoLote | null>(null)
const ocupadoLote = computed(() => conciliandoLote.value || lancandoLote.value)

async function abrirPreviaLote() {
  erroLote.value = ''
  resultadoLote.value = null
  try {
    previaLote.value = await previaConciliacaoLote(filtro.conta_id)
  } catch (e) {
    emit('toast', { msg: e instanceof Error ? e.message : 'Não foi possível buscar as sugestões.', type: 'danger' })
  }
}

function fecharLote() {
  if (conciliandoLote.value) return
  const houveMudanca = !!resultadoLote.value
  previaLote.value = null
  resultadoLote.value = null
  if (houveMudanca) carregar()
}

async function confirmarLote() {
  if (!previaLote.value || conciliandoLote.value) return
  conciliandoLote.value = true
  erroLote.value = ''
  try {
    resultadoLote.value = await conciliarLote(previaLote.value.map((i) => ({
      movimento_id: i.movimento.id_movimento, pagamento_tipo: i.pagamento_tipo, pagamento_id: i.pagamento_id,
    })))
  } catch (e) {
    erroLote.value = e instanceof Error ? e.message : 'Não foi possível conciliar em lote.'
  } finally {
    conciliandoLote.value = false
  }
}

async function lancarSelecionados() {
  if (ocupadoLote.value || !selecionados.value.length) return
  const { ids } = limitarSelecao(selecionados.value)
  lancandoLote.value = true
  try {
    const itens: Array<Record<string, unknown> & { movimento_id: number | string }> = []
    const semRegra: ResultadoLancamentoLote['itens'] = []
    for (const m of ids) {
      const plano = dadosPorRegra(await sugestaoLancamento(m.id_movimento))
      if ('dados' in plano) itens.push({ movimento_id: m.id_movimento, ...plano.dados })
      else semRegra.push({ movimento_id: m.id_movimento, ok: false, pagamento_tipo: null, pagamento_id: null, motivo: plano.motivo })
    }
    const r = itens.length ? await lancarLote(itens as any) : { total: 0, lancados: 0, rejeitados: 0, itens: [] }
    resultadoLancamento.value = {
      total: r.total + semRegra.length,
      lancados: r.lancados,
      rejeitados: r.rejeitados + semRegra.length,
      itens: [...r.itens, ...semRegra],
    }
    marcados.clear()
    carregar()
  } catch (e) {
    emit('toast', { msg: e instanceof Error ? e.message : 'Não foi possível lançar em lote.', type: 'danger' })
  } finally {
    lancandoLote.value = false
  }
}

onMounted(async () => {
  try {
    const res = await apiFetch('financeiro/contas/listar')
    contas.value = res?.data || []
    if (contas.value.length) filtro.conta_id = contas.value[0].id_conta
  } catch {
    erro.value = 'Não foi possível carregar as contas.'
  }
  carregar()
})
</script>

<style scoped>
.mov-acoes { white-space: nowrap; }
.mov-acoes .btn { margin-left: .25rem; }
.mov-erro { color: #b91c1c; }
.mov-lote { list-style: none; padding: 0; display: grid; gap: .4rem; max-height: 50vh; overflow: auto; }
</style>
