<template>
  <div class="page-content animate-fade-in">
    <div class="breadcrumb">
      <a href="#" @click.prevent="emit('set-view', 'dashboard')">Saúde</a>
      <i class="bi bi-chevron-right"></i>
      <span>Importar Extrato</span>
    </div>

    <div class="page-header">
      <div>
        <h1>Importar Extrato</h1>
        <p>Envie o arquivo OFX do banco. Movimentos já importados nunca são repetidos.</p>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" @click="emit('set-view', 'extrato-movimentos')">
        <i class="bi bi-bank"></i> Movimentos do Extrato
      </button>
    </div>

    <!-- Passos 1 e 2: conta, arquivo e prévia -->
    <div class="card ex-card" v-if="podeImportar && !resultado">
      <div class="ex-card-body">
        <h3 class="ex-card-title"><i class="bi bi-1-circle"></i> Conta e arquivo</h3>
        <div class="d-flex flex-wrap gap-3 align-items-end">
          <div class="ex-field">
            <label class="form-label" for="extrato-conta">Conta</label>
            <select id="extrato-conta" class="form-control form-select" v-model="contaId" :disabled="ocupado" @change="limparPrevia">
              <option v-for="c in contas" :key="c.id_conta" :value="c.id_conta">{{ c.conta }}</option>
            </select>
          </div>
        </div>

        <div
          class="ofx-drop mt-3"
          :class="{ 'ofx-drop-ativo': arrastando }"
          @dragover.prevent="arrastando = true"
          @dragleave.prevent="arrastando = false"
          @drop.prevent="aoSoltar"
        >
          <input ref="seletor" type="file" :accept="ACCEPT_OFX" hidden @change="aoEscolher" />
          <i class="bi bi-cloud-arrow-up fs-3 d-block mb-2"></i>
          <template v-if="arquivo">
            <strong>{{ arquivo.name }}</strong> <span class="text-ink-muted">({{ formatarTamanho(arquivo.size) }})</span>
          </template>
          <template v-else>Arraste o arquivo .ofx aqui ou</template>
          <div class="mt-2">
            <button type="button" class="btn btn-ghost btn-sm" :disabled="ocupado" @click="seletor?.click()">
              {{ arquivo ? 'Trocar arquivo' : 'Escolher arquivo' }}
            </button>
          </div>
          <small class="text-ink-muted d-block mt-2">Formato OFX · até {{ formatarTamanho(TAMANHO_MAXIMO_OFX) }}</small>
        </div>

        <p v-if="erro" class="ofx-erro mt-3" role="alert"><i class="bi bi-exclamation-octagon"></i> {{ erro }}</p>

        <button type="button" class="btn btn-primary mt-3" :disabled="!arquivo || !contaId || ocupado" @click="gerarPrevia">
          <span v-if="carregandoPrevia" class="spinner spinner-sm me-2"></span>
          <i v-else class="bi bi-eye me-2"></i> Ver prévia
        </button>
      </div>
    </div>

    <div class="card ex-card ex-state" v-else-if="!podeImportar">
      <i class="bi bi-lock"></i> Você não tem permissão para importar extratos. O histórico abaixo continua disponível.
    </div>

    <!-- Passo 2: prévia -->
    <div class="card ex-card" v-if="previa && !resultado" aria-live="polite">
      <div class="ex-card-body">
        <h3 class="ex-card-title"><i class="bi bi-2-circle"></i> Prévia <small class="text-ink-muted">(nada foi gravado)</small></h3>
        <p class="mb-2">
          Conta: <strong>{{ nomeConta }}</strong> · Período: <strong>{{ periodoLegivel(previa.periodo) }}</strong>
        </p>
        <div class="ex-totais">
          <div><span>{{ previa.lidos }}</span> lidos</div>
          <div class="ex-novos"><span>{{ previa.novos }}</span> novos</div>
          <div><span>{{ previa.duplicados }}</span> já importados</div>
          <div :class="{ 'ex-conflito': previa.conflitos > 0 }"><span>{{ previa.conflitos }}</span> conflitos</div>
        </div>
        <p v-if="nadaNovo(previa)" class="ofx-info mt-3"><i class="bi bi-info-circle"></i> 0 novos, {{ previa.duplicados }} já importados: este arquivo não traz nada que o sistema ainda não tenha.</p>

        <ul class="ofx-alertas" v-if="previa.alertas.length">
          <li v-for="a in previa.alertas" :key="a.codigo" :class="{ 'ofx-alerta-forte': exigeConfirmacao(a) }">
            <i :class="exigeConfirmacao(a) ? 'bi bi-exclamation-triangle-fill' : 'bi bi-info-circle'"></i> {{ a.mensagem }}
          </li>
        </ul>

        <div class="d-flex flex-wrap gap-2 mt-3">
          <button type="button" class="btn btn-primary" :disabled="ocupado" @click="importar(false)">
            <span v-if="importando" class="spinner spinner-sm me-2"></span>
            <i v-else class="bi bi-check2-circle me-2"></i> {{ importando ? 'Importando...' : 'Importar' }}
          </button>
          <button type="button" class="btn btn-ghost" :disabled="ocupado" @click="reiniciar">Cancelar</button>
        </div>
      </div>
    </div>

    <!-- Passo 3: resultado -->
    <div class="card ex-card" v-if="resultado" aria-live="polite">
      <div class="ex-card-body">
        <h3 class="ex-card-title"><i class="bi bi-check-circle text-success"></i> Extrato importado</h3>
        <p class="mb-2">Período: <strong>{{ periodoLegivel(resultado.periodo) }}</strong></p>
        <div class="ex-totais">
          <div><span>{{ resultado.lidos }}</span> lidos</div>
          <div class="ex-novos"><span>{{ resultado.novos }}</span> novos</div>
          <div><span>{{ resultado.duplicados }}</span> duplicados ignorados</div>
          <div :class="{ 'ex-conflito': resultado.conflitos > 0 }"><span>{{ resultado.conflitos }}</span> conflitos</div>
        </div>
        <ul class="ofx-alertas" v-if="resultado.alertas.length">
          <li v-for="a in resultado.alertas" :key="a.codigo"><i class="bi bi-info-circle"></i> {{ a.mensagem }}</li>
        </ul>
        <div class="d-flex flex-wrap gap-2 mt-3">
          <button type="button" class="btn btn-primary" @click="emit('set-view', 'extrato-movimentos')"><i class="bi bi-bank me-2"></i> Conciliar movimentos</button>
          <button type="button" class="btn btn-ghost" @click="reiniciar"><i class="bi bi-plus-circle me-2"></i> Importar outro arquivo</button>
        </div>
      </div>
    </div>

    <!-- Histórico -->
    <div class="card ex-card">
      <div class="card-header">
        <h3><i class="bi bi-clock-history"></i> Histórico de importações</h3>
      </div>
      <div class="ex-state" v-if="carregandoHistorico"><div class="spinner spinner-sm"></div> Carregando histórico...</div>
      <p v-else-if="erroHistorico" class="ofx-erro ex-state mb-0" role="alert">{{ erroHistorico }}</p>
      <div class="ex-table-wrap" v-else-if="historico.length">
        <table class="ex-table">
          <thead>
            <tr><th>Data</th><th>Conta</th><th>Período</th><th class="num">Lidos</th><th class="num">Novos</th><th class="num">Duplicados</th><th class="num">Conflitos</th><th>Usuário</th></tr>
          </thead>
          <tbody>
            <tr v-for="h in historico" :key="String(h.id_importacao)">
              <td>{{ formatDate(h.created_at, true) }}</td>
              <td>{{ nomeDaConta(h.conta_id) }}</td>
              <td>{{ periodoLegivel([h.periodo_inicio, h.periodo_fim]) }}</td>
              <td class="num">{{ h.total_lidos }}</td>
              <td class="num">{{ h.total_novos }}</td>
              <td class="num">{{ h.total_duplicados }}</td>
              <td class="num">{{ h.total_conflitos }}</td>
              <td>{{ h.enviado_por_nome || (h.enviado_por ? `Usuário ${h.enviado_por}` : '—') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="ex-paginacao" v-if="!carregandoHistorico && !erroHistorico && total > POR_PAGINA">
        <button class="btn btn-ghost btn-sm" :disabled="pagina <= 1" @click="irParaPagina(pagina - 1)">Anterior</button>
        <span>Página {{ pagina }} de {{ totalPaginas }}</span>
        <button class="btn btn-ghost btn-sm" :disabled="pagina >= totalPaginas" @click="irParaPagina(pagina + 1)">Próxima</button>
      </div>
      <div class="ex-state" v-if="!carregandoHistorico && !erroHistorico && !historico.length">Nenhuma importação registrada.</div>
    </div>

    <!-- Confirmação de alertas -->
    <div class="modal-backdrop" v-if="confirmacao" @click.self="confirmacao = null">
      <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="extrato-confirma-titulo">
        <div class="modal-header">
          <h2 id="extrato-confirma-titulo"><i class="bi bi-exclamation-triangle-fill"></i> Confirmar importação</h2>
        </div>
        <div class="modal-body" style="padding: 1.5rem;">
          <ul class="ofx-alertas">
            <li v-for="a in confirmacao" :key="a.codigo" class="ofx-alerta-forte"><i class="bi bi-exclamation-triangle-fill"></i> {{ a.mensagem }}</li>
          </ul>
          <p class="mb-0">Deseja importar mesmo assim?</p>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem;">
          <button type="button" class="btn btn-ghost" :disabled="importando" @click="confirmacao = null">Voltar</button>
          <button type="button" class="btn btn-danger" :disabled="importando" @click="importar(true)">
            {{ importando ? 'Importando...' : 'Importar mesmo assim' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { pode } from '../../composables/useAcesso'
import { apiFetch, formatDate } from '../../services/api'
import {
  ConfirmacaoNecessaria, importarExtrato, listarImportacoes, previaExtrato,
  type AlertaExtrato, type ImportacaoExtrato, type ResultadoExtrato,
} from '../../services/extratosApi'
import { ACCEPT_OFX, TAMANHO_MAXIMO_OFX, alertasQueExigemConfirmacao, exigeConfirmacao, nadaNovo, periodoLegivel, validarArquivoOfx } from '../../utils/extratos'
import { formatarTamanho } from '../../utils/midias'

const emit = defineEmits<{ (e: 'toast', t: { msg: string; type: 'success' | 'danger' | 'info' | 'warning' }): void; (e: 'set-view', v: string): void }>()

const POR_PAGINA = 10
const podeImportar = computed(() => pode('financeiro.extratos_importar'))

const contas = ref<any[]>([])
const contaId = ref<number | string>('')
const arquivo = ref<File | null>(null)
const seletor = ref<HTMLInputElement | null>(null)
const arrastando = ref(false)
const erro = ref('')

const previa = ref<ResultadoExtrato | null>(null)
const resultado = ref<ResultadoExtrato | null>(null)
const confirmacao = ref<AlertaExtrato[] | null>(null)
const carregandoPrevia = ref(false)
const importando = ref(false)
const ocupado = computed(() => carregandoPrevia.value || importando.value)

const historico = ref<ImportacaoExtrato[]>([])
const total = ref(0)
const pagina = ref(1)
const carregandoHistorico = ref(true)
const erroHistorico = ref('')
const totalPaginas = computed(() => Math.max(1, Math.ceil(total.value / POR_PAGINA)))

const nomeDaConta = (id: number | string) => contas.value.find((c) => c.id_conta == id)?.conta ?? `Conta ${id}`
const nomeConta = computed(() => nomeDaConta(contaId.value))

function limparPrevia() {
  previa.value = null
  erro.value = ''
}

function escolher(f: File | undefined) {
  if (!f) return
  const problema = validarArquivoOfx(f)
  limparPrevia()
  if (problema) {
    arquivo.value = null
    erro.value = problema
    return
  }
  arquivo.value = f
}

function aoEscolher(ev: Event) {
  const input = ev.target as HTMLInputElement
  escolher(input.files?.[0])
  input.value = ''
}

function aoSoltar(ev: DragEvent) {
  arrastando.value = false
  if (!ocupado.value) escolher(ev.dataTransfer?.files?.[0])
}

function mensagemDe(e: unknown, padrao: string): string {
  return e instanceof Error && e.message ? e.message : padrao
}

async function gerarPrevia() {
  if (!arquivo.value || !contaId.value || ocupado.value) return
  carregandoPrevia.value = true
  erro.value = ''
  previa.value = null
  try {
    previa.value = await previaExtrato(contaId.value, arquivo.value)
  } catch (e) {
    erro.value = mensagemDe(e, 'Não foi possível gerar a prévia.')
  } finally {
    carregandoPrevia.value = false
  }
}

async function importar(confirmado: boolean) {
  if (!arquivo.value || !contaId.value || importando.value) return
  // Alerta que exige confirmação aparece em diálogo explícito antes de enviar.
  if (!confirmado && previa.value) {
    const fortes = alertasQueExigemConfirmacao(previa.value.alertas)
    if (fortes.length) {
      confirmacao.value = fortes
      return
    }
  }
  importando.value = true
  erro.value = ''
  try {
    resultado.value = await importarExtrato(contaId.value, arquivo.value, confirmado)
    confirmacao.value = null
    emit('toast', { msg: `Extrato importado: ${resultado.value.novos} novo(s).`, type: 'success' })
    pagina.value = 1
    carregarHistorico()
  } catch (e) {
    if (e instanceof ConfirmacaoNecessaria) {
      confirmacao.value = e.alertas.length ? e.alertas : [{ codigo: 'confirmar', mensagem: e.message }]
    } else {
      confirmacao.value = null
      erro.value = mensagemDe(e, 'Não foi possível importar o extrato.')
    }
  } finally {
    importando.value = false
  }
}

function reiniciar() {
  arquivo.value = null
  previa.value = null
  resultado.value = null
  confirmacao.value = null
  erro.value = ''
}

async function carregarHistorico() {
  carregandoHistorico.value = true
  erroHistorico.value = ''
  try {
    const p = await listarImportacoes(null, pagina.value, POR_PAGINA)
    historico.value = p.itens
    total.value = p.total
  } catch (e) {
    erroHistorico.value = mensagemDe(e, 'Não foi possível carregar o histórico.')
  } finally {
    carregandoHistorico.value = false
  }
}

function irParaPagina(p: number) {
  pagina.value = Math.min(Math.max(1, p), totalPaginas.value)
  carregarHistorico()
}

async function carregarContas() {
  try {
    const res = await apiFetch('financeiro/contas/listar')
    contas.value = res?.data || []
    if (contas.value.length) contaId.value = contas.value[0].id_conta
  } catch (e) {
    erro.value = 'Não foi possível carregar as contas.'
  }
}

onMounted(async () => {
  await carregarContas()
  carregarHistorico()
})
</script>

<style scoped>
.ofx-drop { border: 2px dashed var(--border, #cbd5e1); border-radius: 12px; padding: 1.5rem; text-align: center; transition: background .15s, border-color .15s; }
.ofx-drop-ativo { border-color: var(--accent, #2563eb); background: rgba(37, 99, 235, .06); }
.ofx-alertas { list-style: none; padding: 0; margin: 1rem 0 0; display: grid; gap: .5rem; }
.ofx-alertas li { padding: .6rem .8rem; border-radius: 8px; background: rgba(100, 116, 139, .1); }
.ofx-alertas li.ofx-alerta-forte { background: rgba(220, 38, 38, .1); color: #b91c1c; }
.ofx-erro { color: #b91c1c; }
.ofx-info { color: var(--ink-muted); }
</style>
