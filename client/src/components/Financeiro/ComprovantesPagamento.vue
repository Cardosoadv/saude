<template>
  <div class="card comprovantes" style="margin-bottom:16px;">
    <div class="card-header">
      <h3><i class="bi bi-paperclip"></i> Comprovantes</h3>
    </div>

    <div class="card-body">
      <div
        v-if="podeEnviar"
        class="comprovantes-drop"
        :class="{ 'is-over': arrastando, 'is-disabled': enviando }"
        @dragover.prevent="arrastando = true"
        @dragleave.prevent="arrastando = false"
        @drop.prevent="aoSoltar"
      >
        <i class="bi bi-cloud-arrow-up"></i>
        <p>Arraste os arquivos aqui ou</p>
        <button type="button" class="btn btn-sm btn-outline-primary" :disabled="enviando" @click="seletor?.click()">
          {{ enviando ? 'Enviando...' : 'Escolher arquivos' }}
        </button>
        <small>PDF, JPG, PNG ou WEBP · até {{ formatarTamanho(TAMANHO_MAXIMO) }} · {{ MAX_ARQUIVOS }} por vez</small>
        <input ref="seletor" type="file" multiple :accept="ACCEPT_INPUT" hidden @change="aoEscolher" />
      </div>

      <p v-if="erro" class="comprovantes-erro" role="alert">{{ erro }}</p>

      <p v-if="carregando" class="comprovantes-vazio">Carregando...</p>
      <p v-else-if="!itens.length" class="comprovantes-vazio">Nenhum comprovante anexado.</p>

      <ul v-else class="widget-list comprovantes-lista">
        <li v-for="c in itens" :key="String(c.id_comprovante)">
          <a :href="c.drive_link" target="_blank" rel="noopener noreferrer" class="comprovantes-nome" :title="c.nome">
            <i :class="c.mime_type === 'application/pdf' ? 'bi bi-file-earmark-pdf' : 'bi bi-file-earmark-image'"></i>
            {{ c.nome }}
          </a>
          <span class="comprovantes-meta">
            {{ mesLegivel(c.pasta_mes) }}<template v-if="c.tamanho"> · {{ formatarTamanho(c.tamanho) }}</template>
          </span>
          <button
            v-if="podeRemover"
            type="button"
            class="btn btn-ghost btn-sm"
            title="Remover comprovante"
            :disabled="removendo === c.id_comprovante"
            @click="remover(c)"
          >
            <i class="bi bi-trash" style="color:var(--danger);"></i>
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ApiError } from '../../services/api'
import {
  enviarComprovantes, listarComprovantes, removerComprovante, type TipoPagamento,
} from '../../services/comprovantesApi'
import { pode } from '../../composables/useAcesso'
import type { Comprovante, Id } from '../../types/domain'
import { ACCEPT_INPUT, MAX_ARQUIVOS, TAMANHO_MAXIMO, mesLegivel, validarLote } from '../../utils/comprovantes'
import { formatarTamanho } from '../../utils/midias'

const props = defineProps<{
  tipo: TipoPagamento
  /** ID do pagamento (id_pgto_despesa / id_pgto_receita). Sem ele, o bloco fica oculto. */
  pagamentoId: Id | null | undefined
}>()

const emit = defineEmits<{
  (e: 'toast', t: { msg: string; type: 'success' | 'danger' | 'info' | 'warning' }): void
  (e: 'changed', total: number): void
}>()

const itens = ref<Comprovante[]>([])
const carregando = ref(false)
const enviando = ref(false)
const removendo = ref<Id | null>(null)
const arrastando = ref(false)
const erro = ref('')
const seletor = ref<HTMLInputElement | null>(null)

const podeEnviar = computed(() => pode('financeiro.edit', 'financeiro.create'))
const podeRemover = computed(() => pode('financeiro.delete'))

const mensagemDe = (e: unknown, padrao: string) =>
  e instanceof ApiError || e instanceof Error ? e.message : padrao

async function carregar() {
  erro.value = ''
  if (!props.pagamentoId) {
    itens.value = []
    return
  }
  carregando.value = true
  try {
    itens.value = await listarComprovantes(props.tipo, props.pagamentoId)
  } catch (e) {
    erro.value = mensagemDe(e, 'Não foi possível carregar os comprovantes.')
  } finally {
    carregando.value = false
  }
}

async function enviar(arquivos: File[]) {
  if (!props.pagamentoId || enviando.value) return
  erro.value = ''

  const invalido = validarLote(arquivos)
  if (invalido) {
    erro.value = invalido
    return
  }

  enviando.value = true
  try {
    const res = await enviarComprovantes(props.tipo, props.pagamentoId, arquivos)
    const falhas = res.erros ?? []
    emit('toast', {
      msg: res.message || 'Comprovantes enviados.',
      type: falhas.length ? 'warning' : 'success',
    })
    await carregar()
    // Depois de recarregar: carregar() limpa o erro anterior.
    if (falhas.length) {
      erro.value = falhas.map((f) => `${f.arquivo}: ${f.mensagem}`).join(' ')
    }
    emit('changed', itens.value.length)
  } catch (e) {
    erro.value = mensagemDe(e, 'Não foi possível enviar o comprovante.')
    emit('toast', { msg: erro.value, type: 'danger' })
  } finally {
    enviando.value = false
  }
}

function aoEscolher(evento: Event) {
  const campo = evento.target as HTMLInputElement
  const arquivos = Array.from(campo.files ?? [])
  campo.value = '' // permite escolher o mesmo arquivo de novo
  void enviar(arquivos)
}

function aoSoltar(evento: DragEvent) {
  arrastando.value = false
  if (!podeEnviar.value) return
  void enviar(Array.from(evento.dataTransfer?.files ?? []))
}

async function remover(comprovante: Comprovante) {
  if (!confirm(`Remover o comprovante "${comprovante.nome}"? O arquivo vai para a lixeira do Google Drive.`)) return

  removendo.value = comprovante.id_comprovante
  erro.value = ''
  try {
    await removerComprovante(comprovante.id_comprovante)
    itens.value = itens.value.filter((c) => c.id_comprovante !== comprovante.id_comprovante)
    emit('toast', { msg: 'Comprovante removido.', type: 'success' })
    emit('changed', itens.value.length)
  } catch (e) {
    erro.value = mensagemDe(e, 'Não foi possível remover o comprovante.')
    emit('toast', { msg: erro.value, type: 'danger' })
  } finally {
    removendo.value = null
  }
}

onMounted(carregar)
watch(() => [props.tipo, props.pagamentoId], carregar)
</script>

<style scoped>
.comprovantes-drop {
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  padding: 16px; border: 2px dashed var(--border-color); border-radius: 10px; text-align: center;
  color: var(--ink-muted); transition: border-color .15s, background .15s;
}
.comprovantes-drop i { font-size: 1.8rem; color: var(--accent); }
.comprovantes-drop p { margin: 0; font-size: .85rem; }
.comprovantes-drop small { font-size: .75rem; }
.comprovantes-drop.is-over { border-color: var(--accent); background: rgba(176, 142, 53, .08); }
.comprovantes-drop.is-disabled { opacity: .6; pointer-events: none; }
.comprovantes-erro { color: var(--danger); font-size: .82rem; margin: 10px 0 0; }
.comprovantes-vazio { color: var(--ink-muted); font-size: .85rem; margin: 12px 0 0; text-align: center; }
.comprovantes-lista { margin-top: 12px; }
.comprovantes-lista li { display: flex; align-items: center; gap: 8px; cursor: default; }
.comprovantes-nome { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .82rem; }
.comprovantes-meta { font-size: .75rem; color: var(--ink-muted); white-space: nowrap; }
</style>
