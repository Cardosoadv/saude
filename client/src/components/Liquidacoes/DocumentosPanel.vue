<template>
  <div class="section-card">
    <div class="section-header">
      <span><i class="bi bi-folder2-open"></i> Documentos</span>
      <button v-if="podeEditar" type="button" class="btn btn-ghost btn-sm" @click="seletor?.click()" :disabled="enviando">
        <i class="bi bi-cloud-arrow-up"></i> {{ enviando ? 'Enviando...' : 'Enviar arquivos' }}
      </button>
    </div>
    <div class="section-body">
      <div v-if="podeEditar" class="doc-envio">
        <label class="doc-tipo">Tipo
          <select class="form-control form-select" v-model="tipo" :disabled="enviando" aria-label="Tipo do documento">
            <option v-for="(r, k) in tiposEnvio" :key="k" :value="k">{{ r }}</option>
          </select>
        </label>
        <small class="doc-ajuda">PDF, Word, JPG, PNG ou WEBP · até {{ formatarTamanho(TAMANHO_MAXIMO) }} · {{ MAX_ARQUIVOS }} por vez. Os arquivos vão para a pasta do cliente no Google Drive.</small>
        <input ref="seletor" type="file" multiple :accept="ACCEPT_INPUT" hidden @change="enviar" />
      </div>
      <p v-if="erro" class="doc-erro" role="alert">{{ erro }}</p>

      <div v-if="!documentos.length" class="doc-vazio">Nenhum documento anexado.</div>
      <ul v-else class="doc-lista">
        <li v-for="d in documentos" :key="d.documento_id">
          <i class="bi bi-file-earmark-text"></i>
          <a :href="d.url_arquivo" target="_blank" rel="noopener" class="doc-nome" :title="d.nome_arquivo">{{ d.nome_arquivo }}</a>
          <span class="badge badge-secondary">{{ TIPOS_DOCUMENTO[d.tipo_documento] ?? d.tipo_documento }}</span>
          <small class="doc-data">{{ formatDate(d.data_upload) }}</small>
          <button v-if="podeEditar" type="button" class="btn btn-ghost btn-sm" :aria-label="`Excluir ${d.nome_arquivo}`" @click="excluir(d)"><i class="bi bi-trash"></i></button>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { ApiError, formatDate } from '../../services/api'
import { enviarDocumentos, excluirDocumento } from '../../services/liquidacoesApi'
import type { DocumentoLiquidacao, TipoDocumento } from '../../types/liquidacao'
import type { Id } from '../../types/domain'
import { ACCEPT_INPUT, MAX_ARQUIVOS, TAMANHO_MAXIMO, validarLote } from '../../utils/contratosArquivos'
import { TIPOS_DOCUMENTO } from '../../utils/liquidacao'
import { formatarTamanho } from '../../utils/midias'

const props = defineProps<{ liquidacaoId: Id; documentos: DocumentoLiquidacao[]; podeEditar: boolean }>()
const emit = defineEmits<{
  (e: 'atualizar'): void
  (e: 'toast', t: { msg: string; type: 'success' | 'danger' | 'info' | 'warning' }): void
}>()

// Comprovantes entram pela baixa da parcela; aqui só os demais tipos.
const tiposEnvio: Record<string, string> = { sentenca: TIPOS_DOCUMENTO.sentenca, calculos: TIPOS_DOCUMENTO.calculos, outros: TIPOS_DOCUMENTO.outros }
const tipo = ref<TipoDocumento>('sentenca')
const seletor = ref<HTMLInputElement | null>(null)
const enviando = ref(false)
const erro = ref('')

async function enviar(evento: Event) {
  const campo = evento.target as HTMLInputElement
  const arquivos = Array.from(campo.files ?? [])
  campo.value = ''
  const invalido = validarLote(arquivos)
  if (invalido) { erro.value = invalido; return }

  enviando.value = true
  erro.value = ''
  try {
    const r = await enviarDocumentos(props.liquidacaoId, tipo.value, arquivos)
    const falhas = r.erros?.length ?? 0
    emit('toast', { msg: falhas ? `Enviado com ${falhas} falha(s): ${r.erros[0].mensagem}` : 'Documento(s) enviado(s).', type: falhas ? 'warning' : 'success' })
    emit('atualizar')
  } catch (e) {
    erro.value = e instanceof ApiError || e instanceof Error ? e.message : 'Não foi possível enviar os arquivos.'
  } finally {
    enviando.value = false
  }
}

async function excluir(d: DocumentoLiquidacao) {
  if (!confirm(`Excluir "${d.nome_arquivo}"? O arquivo vai para a lixeira do Google Drive.`)) return
  try {
    await excluirDocumento(d.documento_id)
    emit('toast', { msg: 'Documento excluído.', type: 'success' })
    emit('atualizar')
  } catch (e) {
    emit('toast', { msg: e instanceof Error ? e.message : 'Não foi possível excluir o documento.', type: 'danger' })
  }
}
</script>

<style scoped>
.doc-envio { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; }
.doc-tipo { display: flex; align-items: center; gap: 6px; font-size: .82rem; }
.doc-ajuda { color: var(--ink-muted); font-size: .76rem; flex: 1; min-width: 200px; }
.doc-erro { color: var(--danger); font-size: .85rem; margin: 0 0 8px; }
.doc-vazio { text-align: center; color: var(--ink-muted); padding: 14px; font-size: .88rem; }
.doc-lista { list-style: none; margin: 0; padding: 0; }
.doc-lista li { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid var(--border); }
.doc-nome { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .85rem; }
.doc-data { color: var(--ink-muted); font-size: .75rem; white-space: nowrap; }
</style>
