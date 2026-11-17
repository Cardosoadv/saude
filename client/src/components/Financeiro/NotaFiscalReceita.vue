<template>
  <div class="card nfse" style="margin-bottom:16px;">
    <div class="card-header nfse-header">
      <h3><i class="bi bi-receipt"></i> Nota Fiscal (NFS-e)</h3>
      <button
        v-if="podeConfigurar"
        type="button"
        class="btn btn-ghost btn-sm"
        title="Configurar emissão de NFS-e"
        aria-label="Configurar emissão de NFS-e"
        @click="configurando = true"
      >
        <i class="bi bi-gear"></i>
      </button>
    </div>

    <div class="card-body">
      <p v-if="carregando" class="nfse-vazio">Carregando...</p>
      <p v-else-if="erro" class="nfse-erro" role="alert">{{ erro }}</p>

      <template v-else>
        <div v-if="config && !config.pronto" class="nfse-aviso">
          <strong>Emissão ainda não configurada.</strong>
          <ul><li v-for="p in config.pendencias" :key="p">{{ p }}</li></ul>
          <button v-if="podeConfigurar" type="button" class="btn btn-sm btn-outline-primary" @click="configurando = true">
            Configurar
          </button>
          <small v-else>Peça a um administrador do financeiro para configurar.</small>
        </div>

        <p v-if="!notas.length" class="nfse-vazio">Nenhuma nota emitida para esta receita.</p>

        <ul v-else class="widget-list nfse-lista">
          <li v-for="n in notas" :key="n.id_nota_fiscal">
            <div class="nfse-linha">
              <span class="badge" :class="STATUS_CLASSE[n.status]">{{ rotulo(n) }}</span>
              <strong>{{ formatCurrency(n.valor_servicos) }}</strong>
            </div>
            <div class="nfse-meta">
              <span v-if="n.numero_nfse">NFS-e nº {{ n.numero_nfse }}</span>
              <span v-else-if="n.numero">DPS nº {{ n.numero }}</span>
              <span v-if="n.codigo_verificacao"> · Verificação {{ n.codigo_verificacao }}</span>
              <br />
              <span>{{ n.tomador_nome }} · {{ formatDate(n.emitida_em || n.created_at, true) }}</span>
            </div>
            <p v-if="n.ultimo_erro && n.status !== 'autorizado' && n.status !== 'cancelado'" class="nfse-erro">{{ n.ultimo_erro }}</p>
            <p v-if="n.status === 'indeterminado'" class="nfse-erro">
              Confira no portal da prefeitura se a nota foi emitida. Se não foi, descarte o registro para emitir de novo.
            </p>

            <div class="nfse-acoes">
              <a v-if="n.pdf_url" :href="n.pdf_url" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm" title="Abrir a nota no portal">
                <i class="bi bi-box-arrow-up-right"></i> Portal
              </a>
              <button v-if="n.tem_documento && podeEmitir" type="button" class="btn btn-ghost btn-sm" :disabled="ocupado === n.id_nota_fiscal" title="Baixar PDF (consulta tarifada: R$ 0,50)" @click="documento(n, 'pdf')">
                <i class="bi bi-file-earmark-pdf"></i> PDF
              </button>
              <button v-if="n.tem_documento && podeEmitir" type="button" class="btn btn-ghost btn-sm" :disabled="ocupado === n.id_nota_fiscal" title="Baixar XML (consulta tarifada: R$ 0,50)" @click="documento(n, 'xml')">
                <i class="bi bi-filetype-xml"></i> XML
              </button>
              <button v-if="n.pode_atualizar && podeEmitir" type="button" class="btn btn-ghost btn-sm" :disabled="ocupado === n.id_nota_fiscal" title="Consultar a situação na prefeitura (R$ 0,50)" @click="atualizar(n)">
                <i class="bi bi-arrow-clockwise"></i>
              </button>
              <button v-if="n.pode_cancelar && podeEmitir" type="button" class="btn btn-ghost btn-sm" :disabled="ocupado === n.id_nota_fiscal" title="Cancelar a nota" @click="cancelando = n">
                <i class="bi bi-x-circle" style="color:var(--danger);"></i>
              </button>
              <button v-if="n.pode_descartar && podeEmitir" type="button" class="btn btn-ghost btn-sm" :disabled="ocupado === n.id_nota_fiscal" title="Descartar registro" @click="descartar(n)">
                <i class="bi bi-trash" style="color:var(--danger);"></i>
              </button>
            </div>
          </li>
        </ul>

        <button
          v-if="podeEmitir && !notaAtiva"
          type="button"
          class="btn btn-sm btn-success nfse-emitir"
          :disabled="!config?.pronto"
          @click="emitindo = true"
        >
          <i class="bi bi-receipt-cutoff"></i> Emitir NFS-e
        </button>
      </template>
    </div>

    <NotaFiscalEmissaoModal
      v-if="emitindo"
      :receita-id="receitaId"
      @fechar="emitindo = false"
      @emitida="aoEmitir"
    />

    <NotaFiscalConfigModal
      v-if="configurando"
      @fechar="configurando = false"
      @salvo="carregar"
      @toast="$emit('toast', $event)"
    />

    <!-- Cancelamento -->
    <div v-if="cancelando" class="modal-backdrop" @click.self="cancelando = null">
      <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="nfse-cancelar-titulo" style="max-width:520px;">
        <div class="modal-header">
          <h2 id="nfse-cancelar-titulo"><i class="bi bi-x-circle"></i> Cancelar NFS-e {{ cancelando.numero_nfse || '' }}</h2>
          <button type="button" class="modal-close" :disabled="!!ocupado" @click="cancelando = null"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body" style="padding:1.5rem;">
          <p class="text-ink-muted">O pedido vai para a prefeitura, que confirma em seguida. Algumas prefeituras não aceitam cancelamento pela API.</p>
          <div class="form-group">
            <label for="nfse-cancel-codigo">Motivo</label>
            <select id="nfse-cancel-codigo" v-model="cancelamento.codigo" class="form-control">
              <option value="">Selecione...</option>
              <option v-for="(rot, cod) in config?.codigos_cancelamento ?? {}" :key="cod" :value="cod">{{ rot }}</option>
            </select>
          </div>
          <div class="form-group" style="margin-top:12px;">
            <label for="nfse-cancel-motivo">Justificativa (15 a 255 caracteres)</label>
            <textarea id="nfse-cancel-motivo" v-model="cancelamento.motivo" class="form-control" rows="3" maxlength="255"></textarea>
            <small class="text-ink-muted">{{ cancelamento.motivo.trim().length }}/255</small>
          </div>
        </div>
        <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:8px; padding:1rem;">
          <button type="button" class="btn btn-outline-secondary" :disabled="!!ocupado" @click="cancelando = null">Voltar</button>
          <button
            type="button"
            class="btn btn-danger"
            :disabled="!!ocupado || !cancelamento.codigo || cancelamento.motivo.trim().length < 15"
            @click="confirmarCancelamento"
          >
            {{ ocupado ? 'Enviando...' : 'Solicitar cancelamento' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { formatCurrency, formatDate } from '../../services/api'
import { pode } from '../../composables/useAcesso'
import {
  STATUS_CLASSE, abrirDocumento, atualizarNota, cancelarNota, descartarNota, listarNotas, obterConfiguracao,
  type ConfiguracaoNfse, type NotaFiscal,
} from '../../services/notasFiscaisApi'
import NotaFiscalEmissaoModal from './NotaFiscalEmissaoModal.vue'
import NotaFiscalConfigModal from './NotaFiscalConfigModal.vue'

const props = defineProps<{ receitaId: number | string }>()
const emit = defineEmits<{
  (e: 'toast', t: { msg: string; type: 'success' | 'danger' | 'info' | 'warning' }): void
}>()

const notas = ref<NotaFiscal[]>([])
const config = ref<ConfiguracaoNfse | null>(null)
const carregando = ref(false)
const erro = ref('')
const ocupado = ref<number | null>(null)
const emitindo = ref(false)
const configurando = ref(false)
const cancelando = ref<NotaFiscal | null>(null)
const cancelamento = reactive({ codigo: '', motivo: '' })

const podeEmitir = computed(() => pode('financeiro.nfse_emitir'))
const podeConfigurar = computed(() => pode('financeiro.nfse_configurar'))

const ATIVOS = ['enviando', 'indeterminado', 'criado', 'em_processamento', 'autorizado', 'cancelando']
const notaAtiva = computed(() => notas.value.find((n) => ATIVOS.includes(n.status)) ?? null)

const mensagem = (e: unknown, padrao: string) => (e instanceof Error ? e.message : padrao)
const rotulo = (n: NotaFiscal) => n.status_rotulo.charAt(0).toUpperCase() + n.status_rotulo.slice(1)

async function carregar() {
  if (!props.receitaId) return
  carregando.value = true
  erro.value = ''
  try {
    const [lista, cfg] = await Promise.all([listarNotas(props.receitaId), obterConfiguracao()])
    notas.value = lista
    config.value = cfg
  } catch (e) {
    erro.value = mensagem(e, 'Não foi possível carregar as notas fiscais.')
  } finally {
    carregando.value = false
  }
}

function substituir(nota: NotaFiscal) {
  const i = notas.value.findIndex((n) => n.id_nota_fiscal === nota.id_nota_fiscal)
  if (i >= 0) notas.value.splice(i, 1, nota)
  else notas.value.unshift(nota)
}

function aoEmitir(nota: NotaFiscal, msg: string) {
  emitindo.value = false
  substituir(nota)
  emit('toast', { msg, type: nota.status === 'autorizado' ? 'success' : 'info' })
}

async function executar(nota: NotaFiscal, acao: () => Promise<{ message?: string; data: NotaFiscal }>) {
  ocupado.value = nota.id_nota_fiscal
  try {
    const res = await acao()
    substituir(res.data)
    if (res.message) emit('toast', { msg: res.message, type: 'success' })
  } catch (e) {
    emit('toast', { msg: mensagem(e, 'Não foi possível concluir a operação.'), type: 'danger' })
  } finally {
    ocupado.value = null
  }
}

function atualizar(nota: NotaFiscal) {
  void executar(nota, () => atualizarNota(nota.id_nota_fiscal))
}

function descartar(nota: NotaFiscal) {
  if (!confirm('Descartar este registro? Confirme antes, no portal da prefeitura, que a nota NÃO foi emitida — senão a receita ficará com duas notas.')) return
  void executar(nota, () => descartarNota(nota.id_nota_fiscal))
}

async function confirmarCancelamento() {
  const nota = cancelando.value
  if (!nota) return
  await executar(nota, () => cancelarNota(nota.id_nota_fiscal, cancelamento.codigo, cancelamento.motivo.trim()))
  cancelando.value = null
  cancelamento.codigo = ''
  cancelamento.motivo = ''
}

async function documento(nota: NotaFiscal, formato: 'pdf' | 'xml') {
  ocupado.value = nota.id_nota_fiscal
  try {
    await abrirDocumento(nota.id_nota_fiscal, formato)
  } catch (e) {
    emit('toast', { msg: mensagem(e, 'Não foi possível baixar o documento.'), type: 'danger' })
  } finally {
    ocupado.value = null
  }
}

// Notas em processamento: o webhook atualiza o banco; a tela relê a lista (sem custo) a cada 20 s.
let timer: ReturnType<typeof setInterval> | null = null
watch(notas, (lista) => {
  const pendente = lista.some((n) => ['enviando', 'criado', 'em_processamento', 'cancelando'].includes(n.status))
  if (pendente && !timer) {
    timer = setInterval(async () => {
      try { notas.value = await listarNotas(props.receitaId) } catch { /* tenta de novo no próximo ciclo */ }
    }, 20000)
  } else if (!pendente && timer) {
    clearInterval(timer)
    timer = null
  }
})

onMounted(carregar)
onUnmounted(() => { if (timer) clearInterval(timer) })
watch(() => props.receitaId, carregar)
</script>

<style scoped>
.nfse-header { display: flex; justify-content: space-between; align-items: center; }
.nfse-vazio { color: var(--ink-muted); font-size: .85rem; margin: 0 0 10px; text-align: center; }
.nfse-erro { color: var(--danger); font-size: .8rem; margin: 6px 0 0; }
.nfse-aviso { font-size: .82rem; background: rgba(176, 142, 53, .08); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; }
.nfse-aviso ul { margin: 6px 0 8px; padding-left: 18px; }
.nfse-lista li { display: block; cursor: default; }
.nfse-linha { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.nfse-meta { font-size: .76rem; color: var(--ink-muted); margin-top: 4px; }
.nfse-acoes { display: flex; flex-wrap: wrap; gap: 2px; margin-top: 6px; }
.nfse-acoes .btn { padding: 2px 6px; font-size: .78rem; }
.nfse-emitir { width: 100%; margin-top: 10px; }
</style>
