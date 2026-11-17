<template>
  <div class="modal-backdrop" @click.self="fechar">
    <div class="modal-box">
      <div class="modal-header">
        <h2><i class="bi bi-paperclip"></i> Anexar comprovante</h2>
        <button type="button" class="modal-close" :disabled="enviando" @click="fechar"><i class="bi bi-x-lg"></i></button>
      </div>

      <div class="modal-body" style="padding: 1.5rem;">
        <p class="lote-resumo">{{ resumoLote(pagamentos.length) }}. Os mesmos arquivos serão anexados a todos eles.</p>

        <input ref="seletor" type="file" multiple :accept="ACCEPT_INPUT" hidden @change="aoEscolher" />
        <button type="button" class="btn btn-outline-primary" :disabled="enviando" @click="seletor?.click()">
          <i class="bi bi-cloud-arrow-up"></i> Escolher arquivos
        </button>
        <small class="lote-ajuda">PDF, JPG, PNG ou WEBP · até {{ formatarTamanho(TAMANHO_MAXIMO) }} · {{ MAX_ARQUIVOS }} por vez</small>

        <ul v-if="arquivos.length" class="lote-arquivos">
          <li v-for="(a, i) in arquivos" :key="i">
            <i :class="a.type === 'application/pdf' ? 'bi bi-file-earmark-pdf' : 'bi bi-file-earmark-image'"></i>
            <span class="lote-nome" :title="a.name">{{ a.name }}</span>
            <span class="lote-meta">{{ formatarTamanho(a.size) }}</span>
            <button type="button" class="btn btn-ghost btn-sm" title="Tirar da lista" :disabled="enviando" @click="arquivos.splice(i, 1)">
              <i class="bi bi-x"></i>
            </button>
          </li>
        </ul>

        <p v-if="erro" class="lote-erro" role="alert">{{ erro }}</p>
      </div>

      <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem;">
        <button type="button" class="btn btn-outline-secondary" :disabled="enviando" @click="fechar">Cancelar</button>
        <button type="button" class="btn btn-primary" :disabled="enviando || !arquivos.length" @click="enviar">
          {{ enviando ? 'Enviando...' : 'Anexar' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { ApiError } from '../../services/api'
import { enviarComprovantesLote, type PagamentoLote } from '../../services/comprovantesApi'
import {
  ACCEPT_INPUT, MAX_ARQUIVOS, MAX_PAGAMENTOS_LOTE, TAMANHO_MAXIMO, resumoLote, validarLote,
} from '../../utils/comprovantes'
import { formatarTamanho } from '../../utils/midias'

const props = defineProps<{ pagamentos: PagamentoLote[] }>()

const emit = defineEmits<{
  (e: 'close'): void
  /** Envio concluído (total ou parcial): a tela deve recarregar a listagem. */
  (e: 'enviado'): void
  (e: 'toast', t: { msg: string; type: 'success' | 'danger' | 'info' | 'warning' }): void
}>()

const arquivos = ref<File[]>([])
const enviando = ref(false)
const erro = ref('')
const seletor = ref<HTMLInputElement | null>(null)

function aoEscolher(evento: Event) {
  const campo = evento.target as HTMLInputElement
  const novos = Array.from(campo.files ?? [])
  campo.value = '' // permite escolher o mesmo arquivo de novo
  arquivos.value = [...arquivos.value, ...novos]
  erro.value = validarLote(arquivos.value) ?? ''
}

function fechar() {
  if (!enviando.value) emit('close')
}

async function enviar() {
  if (enviando.value) return

  if (props.pagamentos.length > MAX_PAGAMENTOS_LOTE) {
    erro.value = `Selecione no máximo ${MAX_PAGAMENTOS_LOTE} pagamentos por vez.`
    return
  }
  const invalido = validarLote(arquivos.value)
  if (invalido) {
    erro.value = invalido
    return
  }

  enviando.value = true
  erro.value = ''
  try {
    const res = await enviarComprovantesLote(props.pagamentos, arquivos.value)
    const falhas = res.erros ?? []
    emit('toast', { msg: res.message || 'Comprovantes anexados.', type: falhas.length ? 'warning' : 'success' })
    emit('enviado')
    emit('close')
  } catch (e) {
    erro.value = e instanceof ApiError || e instanceof Error ? e.message : 'Não foi possível anexar os comprovantes.'
  } finally {
    enviando.value = false
  }
}
</script>

<style scoped>
.lote-resumo { margin: 0 0 12px; font-size: .9rem; }
.lote-ajuda { display: block; margin-top: 6px; color: var(--ink-muted); }
.lote-arquivos { list-style: none; margin: 12px 0 0; padding: 0; }
.lote-arquivos li { display: flex; align-items: center; gap: 8px; padding: 4px 0; }
.lote-nome { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .85rem; }
.lote-meta { font-size: .75rem; color: var(--ink-muted); white-space: nowrap; }
.lote-erro { color: var(--danger); font-size: .85rem; margin: 12px 0 0; }
</style>
