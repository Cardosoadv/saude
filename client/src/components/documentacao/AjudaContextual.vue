<template>
  <button
    v-if="pagina"
    class="topbar-icon-btn"
    title="Ajuda desta tela (F1)"
    aria-label="Abrir a ajuda desta tela"
    :aria-expanded="aberto"
    aria-controls="ajuda-contextual"
    data-ajuda
    @click="alternar"
  >
    <i class="bi bi-question-circle"></i>
  </button>

  <Teleport to="body">
    <div v-if="aberto && pagina" class="ajuda-fundo" data-fechar-ajuda @click="fechar"></div>
    <aside
      v-if="aberto && pagina"
      id="ajuda-contextual"
      ref="painel"
      class="ajuda-painel"
      role="dialog"
      aria-labelledby="ajuda-contextual-titulo"
      tabindex="-1"
      @keydown.esc.stop="fechar"
    >
      <header class="ajuda-cabecalho">
        <p class="ajuda-rotulo">Ajuda</p>
        <button class="ajuda-fechar" aria-label="Fechar a ajuda" title="Fechar (Esc)" @click="fechar">
          <i class="bi bi-x-lg"></i>
        </button>
      </header>
      <h2 id="ajuda-contextual-titulo">{{ pagina.titulo }}</h2>
      <p v-if="pagina.resumo" class="doc-resumo">{{ pagina.resumo }}</p>
      <div class="doc-corpo" v-html="pagina.html" @click="aoClicarNoConteudo"></div>
      <footer class="ajuda-rodape">
        <a :href="hashDaDocumentacao(pagina.slug)" data-abrir-documentacao @click.prevent="abrirNaDocumentacao(pagina.slug, null)">
          <i class="bi bi-book"></i> Abrir na documentação
        </a>
        <button class="btn btn-ghost btn-sm" data-fechar-rodape @click="fechar">Fechar</button>
      </footer>
    </aside>
  </Teleport>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { ApiError } from '../../services/api'
import { obterPaginaDaTela } from '../../services/documentacaoApi'
import { telaLiberada } from '../../utils/acessoTelas'
import { hashDaDocumentacao, lerHashDaDocumentacao } from '../../utils/rotasDocumentacao'
import type { DocPagina } from '../../types/documentacao'
import './documentacao.css'

const props = defineProps<{ currentView: string }>()

const emit = defineEmits<{
  (e: 'abrir-documentacao', destino: { slug: string | null; ancora: string | null }): void
}>()

const pagina = ref<DocPagina | null>(null)
const aberto = ref(false)
const painel = ref<HTMLElement | null>(null)

// Telas sem ajuda (404) ficam guardadas como `null` durante a sessão; falha de rede não é guardada.
const cache = new Map<string, DocPagina | null>()

let pedido = 0
async function carregar(tela: string) {
  aberto.value = false
  const meu = ++pedido

  if (tela === 'documentacao' || !telaLiberada('documentacao')) {
    pagina.value = null
    return
  }
  if (cache.has(tela)) {
    pagina.value = cache.get(tela) ?? null
    return
  }

  pagina.value = null
  try {
    const encontrada = (await obterPaginaDaTela(tela)).data
    cache.set(tela, encontrada)
    if (meu === pedido) pagina.value = encontrada
  } catch (erro) {
    if (erro instanceof ApiError && erro.status === 404) cache.set(tela, null)
  }
}

async function abrir() {
  if (!pagina.value) return
  aberto.value = true
  await nextTick()
  painel.value?.focus()
}

function fechar() {
  aberto.value = false
}

function alternar() {
  if (aberto.value) fechar()
  else abrir()
}

function abrirNaDocumentacao(slug: string | null, ancora: string | null) {
  fechar()
  emit('abrir-documentacao', { slug, ancora })
}

/** Links para a documentação dentro da ajuda abrem a tela Documentação; os demais seguem normais. */
function aoClicarNoConteudo(evento: MouseEvent) {
  if (evento.defaultPrevented || evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey) return
  const link = (evento.target as HTMLElement | null)?.closest?.('a')
  const href = link?.getAttribute('href') ?? ''
  const rota = lerHashDaDocumentacao(href)
  if (rota) {
    evento.preventDefault()
    abrirNaDocumentacao(rota.slug, rota.ancora)
  } else if (href.startsWith('#/')) {
    fechar() // link para outra tela do sistema
  }
}

/** F1 ou `?` (Shift+/) abrem a ajuda, exceto quando o usuário está digitando. */
function atalho(evento: KeyboardEvent) {
  if (evento.key === 'Escape' && aberto.value) {
    fechar()
    return
  }
  const ehAtalho = evento.key === 'F1' || (evento.key === '?' && !evento.ctrlKey && !evento.metaKey && !evento.altKey)
  if (!ehAtalho || !pagina.value) return
  const alvo = evento.target as HTMLElement | null
  if (evento.key === '?' && alvo && (alvo.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(alvo.tagName))) return
  evento.preventDefault()
  alternar()
}

watch(() => props.currentView, carregar, { immediate: true })
// Módulo ligado/desligado com a sessão aberta.
watch(() => telaLiberada('documentacao'), () => carregar(props.currentView))

window.addEventListener('keydown', atalho)
onBeforeUnmount(() => window.removeEventListener('keydown', atalho))
</script>

<style>
.ajuda-fundo { position: fixed; inset: 0; z-index: 1190; background: rgba(0, 0, 0, .25); }
.ajuda-painel {
  position: fixed; z-index: 1200; top: 0; right: 0; bottom: 0; width: min(480px, 100vw);
  background: var(--card); box-shadow: var(--shadow-lg); overflow-y: auto; padding: 16px 24px 24px;
  outline: none;
}
.ajuda-cabecalho { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
.ajuda-rotulo { margin: 0; font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: var(--ink-muted); font-weight: 600; }
.ajuda-fechar { background: none; border: 0; cursor: pointer; color: var(--ink-dim); font-size: 1rem; padding: 6px; border-radius: 6px; }
.ajuda-fechar:hover { background: var(--surface); }
.ajuda-painel h2 { font-family: var(--font-head); font-size: 1.35rem; margin: 0 0 4px; color: var(--ink); }
.ajuda-painel .doc-resumo { color: var(--ink-muted); margin: 0 0 16px; }
.ajuda-painel .doc-corpo { font-size: .92rem; }
.ajuda-rodape { margin-top: 20px; padding-top: 12px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.ajuda-rodape a { color: var(--primary); text-decoration: none; font-weight: 600; }
.ajuda-rodape a:hover { text-decoration: underline; }
@media print { .ajuda-fundo, .ajuda-painel { display: none !important; } }
</style>
