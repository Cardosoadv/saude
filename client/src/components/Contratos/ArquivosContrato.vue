<template>
  <div class="card arquivos-contrato mb-3">
    <div class="card-header bg-light">
      <h3><i class="bi bi-folder2-open"></i> Arquivos do Contrato</h3>
    </div>

    <div class="card-body">
      <div v-if="podeEnviar" class="form-group" style="margin-bottom:10px;">
        <label for="arquivo_categoria" class="form-label">Categoria</label>
        <select id="arquivo_categoria" v-model="categoria" class="form-control" :disabled="enviando">
          <option v-for="c in CATEGORIAS_ARQUIVO" :key="c" :value="c">{{ c }}</option>
        </select>
      </div>

      <div
        v-if="podeEnviar"
        class="arquivos-drop"
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
        <small>PDF, Word, JPG, PNG ou WEBP · até {{ formatarTamanho(TAMANHO_MAXIMO) }} · {{ MAX_ARQUIVOS }} por vez</small>
        <input ref="seletor" type="file" multiple :accept="ACCEPT_INPUT" hidden @change="aoEscolher" />
      </div>

      <p v-if="erro" class="arquivos-erro" role="alert">{{ erro }}</p>

      <p v-if="carregando" class="arquivos-vazio">Carregando...</p>
      <p v-else-if="!itens.length" class="arquivos-vazio">Nenhum arquivo anexado.</p>

      <ul v-else class="widget-list arquivos-lista">
        <li v-for="a in itens" :key="String(a.id_contrato_arquivo)">
          <a :href="a.drive_link" target="_blank" rel="noopener noreferrer" class="arquivos-nome" :title="a.nome">
            <i :class="iconeDoArquivo(a.mime_type)"></i>
            {{ a.nome }}
          </a>
          <span class="arquivos-meta">
            {{ a.categoria }}<template v-if="a.tamanho"> · {{ formatarTamanho(a.tamanho) }}</template>
          </span>
          <button
            v-if="podeRemover"
            type="button"
            class="btn btn-ghost btn-sm"
            title="Remover arquivo"
            :disabled="removendo === a.id_contrato_arquivo"
            @click="remover(a)"
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
  enviarArquivosContrato, listarArquivosContrato, removerArquivoContrato,
} from '../../services/contratosArquivosApi'
import { pode } from '../../composables/useAcesso'
import type { ContratoArquivo, Id } from '../../types/domain'
import {
  ACCEPT_INPUT, CATEGORIAS_ARQUIVO, MAX_ARQUIVOS, TAMANHO_MAXIMO, iconeDoArquivo, validarLote,
} from '../../utils/contratosArquivos'
import { formatarTamanho } from '../../utils/midias'

const props = defineProps<{
  /** ID do contrato. Sem ele, o bloco fica vazio. */
  contratoId: Id | null | undefined
}>()

const emit = defineEmits<{
  (e: 'toast', t: { msg: string; type: 'success' | 'danger' | 'info' | 'warning' }): void
  (e: 'changed', total: number): void
}>()

const itens = ref<ContratoArquivo[]>([])
const categoria = ref<string>(CATEGORIAS_ARQUIVO[0])
const carregando = ref(false)
const enviando = ref(false)
const removendo = ref<Id | null>(null)
const arrastando = ref(false)
const erro = ref('')
const seletor = ref<HTMLInputElement | null>(null)

const podeEnviar = computed(() => pode('contratos.edit'))
const podeRemover = computed(() => pode('contratos.delete'))

const mensagemDe = (e: unknown, padrao: string) =>
  e instanceof ApiError || e instanceof Error ? e.message : padrao

async function carregar() {
  erro.value = ''
  if (!props.contratoId) {
    itens.value = []
    return
  }
  carregando.value = true
  try {
    itens.value = await listarArquivosContrato(props.contratoId)
  } catch (e) {
    erro.value = mensagemDe(e, 'Não foi possível carregar os arquivos do contrato.')
  } finally {
    carregando.value = false
  }
}

async function enviar(arquivos: File[]) {
  if (!props.contratoId || enviando.value) return
  erro.value = ''

  const invalido = validarLote(arquivos)
  if (invalido) {
    erro.value = invalido
    return
  }

  enviando.value = true
  try {
    const res = await enviarArquivosContrato(props.contratoId, arquivos, categoria.value)
    const falhas = res.erros ?? []
    emit('toast', {
      msg: res.message || 'Arquivos enviados.',
      type: falhas.length ? 'warning' : 'success',
    })
    await carregar()
    // Depois de recarregar: carregar() limpa o erro anterior.
    if (falhas.length) {
      erro.value = falhas.map((f) => `${f.arquivo}: ${f.mensagem}`).join(' ')
    }
    emit('changed', itens.value.length)
  } catch (e) {
    erro.value = mensagemDe(e, 'Não foi possível enviar o arquivo.')
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

async function remover(arquivo: ContratoArquivo) {
  if (!confirm(`Remover o arquivo "${arquivo.nome}"? Ele vai para a lixeira do Google Drive.`)) return

  removendo.value = arquivo.id_contrato_arquivo
  erro.value = ''
  try {
    await removerArquivoContrato(arquivo.id_contrato_arquivo)
    itens.value = itens.value.filter((a) => a.id_contrato_arquivo !== arquivo.id_contrato_arquivo)
    emit('toast', { msg: 'Arquivo removido.', type: 'success' })
    emit('changed', itens.value.length)
  } catch (e) {
    erro.value = mensagemDe(e, 'Não foi possível remover o arquivo.')
    emit('toast', { msg: erro.value, type: 'danger' })
  } finally {
    removendo.value = null
  }
}

onMounted(carregar)
watch(() => props.contratoId, carregar)
</script>

<style scoped>
.arquivos-drop {
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  padding: 16px; border: 2px dashed var(--border-color); border-radius: 10px; text-align: center;
  color: var(--ink-muted); transition: border-color .15s, background .15s;
}
.arquivos-drop i { font-size: 1.8rem; color: var(--accent); }
.arquivos-drop p { margin: 0; font-size: .85rem; }
.arquivos-drop small { font-size: .75rem; }
.arquivos-drop.is-over { border-color: var(--accent); background: rgba(176, 142, 53, .08); }
.arquivos-drop.is-disabled { opacity: .6; pointer-events: none; }
.arquivos-erro { color: var(--danger); font-size: .82rem; margin: 10px 0 0; }
.arquivos-vazio { color: var(--ink-muted); font-size: .85rem; margin: 12px 0 0; text-align: center; }
.arquivos-lista { margin-top: 12px; }
.arquivos-lista li { display: flex; align-items: center; gap: 8px; cursor: default; }
.arquivos-nome { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .82rem; }
.arquivos-meta { font-size: .75rem; color: var(--ink-muted); white-space: nowrap; }
</style>
