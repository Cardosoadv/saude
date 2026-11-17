<template>
  <Transition name="copiloto-slide">
    <aside
      v-if="estado.aberto"
      ref="painel"
      class="copiloto-drawer"
      role="dialog"
      aria-modal="false"
      aria-labelledby="copiloto-titulo"
      @keydown.esc.stop="fecharPainel"
    >
      <header class="copiloto-header">
        <div>
          <h2 id="copiloto-titulo"><i class="bi bi-stars" aria-hidden="true"></i> Copiloto</h2>
          <p class="copiloto-contexto">{{ rotuloContexto }}</p>
        </div>
        <div class="copiloto-header-acoes">
          <button
            v-if="estado.mensagens.length"
            type="button"
            class="topbar-icon-btn"
            title="Nova conversa"
            aria-label="Nova conversa"
            :disabled="estado.carregando"
            @click="limpar"
          ><i class="bi bi-plus-square"></i></button>
          <button type="button" class="topbar-icon-btn" title="Fechar (Esc)" aria-label="Fechar Copiloto" @click="fecharPainel">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      </header>

      <div ref="lista" class="copiloto-mensagens" aria-live="polite" @click="aoClicarNaResposta">
        <div v-if="!estado.mensagens.length" class="copiloto-vazio">
          <p>Pergunte sobre processos, clientes, prazos e liquidações. As respostas usam os dados que você já pode ver no sistema.</p>
          <p class="copiloto-vazio-nota">Para gravar algo, o Copiloto prepara a ação e você confirma antes.</p>
        </div>

        <div v-for="m in estado.mensagens" :key="m.id" :class="['copiloto-msg', `copiloto-msg-${m.papel}`]">
          <div v-if="m.papel === 'assistente'" class="copiloto-texto" v-html="renderizarMarkdown(m.texto)"></div>
          <div v-else class="copiloto-texto">{{ m.texto }}</div>

          <CopilotoAcaoCard
            v-if="m.acao && m.acaoEstado"
            :acao="m.acao"
            :estado="m.acaoEstado"
            :resultado="m.acaoResultado"
            @confirmar="confirmarAcaoDaMensagem(m.id)"
            @recusar="recusar(m.id)"
          />

          <div v-if="referenciasExtras(m).length" class="copiloto-refs">
            <button
              v-for="r in referenciasExtras(m)"
              :key="`${r.tipo}-${r.id}`"
              type="button"
              class="copiloto-ref"
              :data-tipo="r.tipo"
              :data-id="r.id"
            >{{ r.rotulo }}</button>
          </div>
        </div>

        <div v-if="estado.carregando" class="copiloto-msg copiloto-msg-assistente copiloto-carregando" role="status">
          <span class="copiloto-pontos" aria-hidden="true"><i></i><i></i><i></i></span>
          Consultando...
          <button type="button" class="copiloto-cancelar" @click="cancelar">Cancelar</button>
        </div>
      </div>

      <div v-if="acoesRapidas.length && !estado.carregando" class="copiloto-chips" aria-label="Sugestões">
        <button v-for="a in acoesRapidas" :key="a.id" type="button" class="copiloto-chip" @click="perguntar(a.prompt)">
          {{ a.rotulo }}
        </button>
      </div>

      <form class="copiloto-form" @submit.prevent="perguntar(texto)">
        <label for="copiloto-entrada" class="visually-hidden">Mensagem para o Copiloto</label>
        <textarea
          id="copiloto-entrada"
          ref="entrada"
          v-model="texto"
          rows="2"
          maxlength="4000"
          placeholder="Pergunte ao Copiloto..."
          :disabled="estado.carregando"
          @keydown.enter.exact.prevent="perguntar(texto)"
        ></textarea>
        <button type="submit" class="btn btn-primary btn-sm" :disabled="!texto.trim() || estado.carregando" aria-label="Enviar">
          <i class="bi bi-send"></i>
        </button>
      </form>
      <p class="copiloto-rodape">Respostas geradas por IA. Confira antes de usar.</p>
    </aside>
  </Transition>
</template>

<script setup lang="ts">
/**
 * Painel lateral do Copiloto Jurídico & Financeiro (sistema#277).
 *
 * Abre pela Topbar ou por Ctrl+K / Cmd+K, envia o registro aberto na tela como contexto, mostra ações
 * rápidas da tela e transforma as referências `ordo://` em botões que navegam dentro da SPA (`navegar`).
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch, type DeepReadonly } from 'vue'
import CopilotoAcaoCard from './CopilotoAcaoCard.vue'
import { renderizarMarkdown, TIPOS_REFERENCIA } from '../../utils/markdownSeguro'
import { useCopiloto, type MensagemCopiloto } from '../../composables/useCopiloto'
import {
  listarAcoesRapidas,
  type AcaoRapidaCopiloto,
  type ContextoCopiloto,
  type ReferenciaCopiloto,
  type TipoEntidadeCopiloto,
} from '../../services/copilotoApi'

const props = defineProps<{ contexto: ContextoCopiloto | null }>()
const emit = defineEmits<{ navegar: [referencia: { tipo: TipoEntidadeCopiloto; id: number }] }>()

const { estado, alternar, fechar, enviar, cancelar, limpar, confirmar, recusar } = useCopiloto()

const texto = ref('')
const acoesRapidas = ref<AcaoRapidaCopiloto[]>([])
const painel = ref<HTMLElement | null>(null)
const lista = ref<HTMLElement | null>(null)
const entrada = ref<HTMLTextAreaElement | null>(null)
let focoAnterior: HTMLElement | null = null

const NOMES: Record<TipoEntidadeCopiloto, string> = {
  processo: 'Processo',
  cliente: 'Cliente',
  contrato: 'Contrato',
  caso: 'Caso',
  liquidacao: 'Liquidação',
  intimacao: 'Intimação',
  tarefa: 'Tarefa',
}

const rotuloContexto = computed(() => {
  const c = props.contexto
  return c?.entidade_tipo && c.entidade_id
    ? `Contexto: ${NOMES[c.entidade_tipo]} #${c.entidade_id}`
    : 'Jurídico & Financeiro'
})

/** Referências que não aparecem como link no texto (ex.: registro criado após confirmar). */
function referenciasExtras(m: DeepReadonly<MensagemCopiloto>): DeepReadonly<ReferenciaCopiloto>[] {
  return (m.referencias ?? []).filter((r) => !m.texto.includes(`ordo://${r.tipo}/${r.id})`))
}

async function carregarAcoes(): Promise<void> {
  try {
    acoesRapidas.value = await listarAcoesRapidas(props.contexto?.entidade_tipo ?? null)
  } catch {
    acoesRapidas.value = []
  }
}

async function rolarParaFim(): Promise<void> {
  await nextTick()
  if (lista.value) lista.value.scrollTop = lista.value.scrollHeight
}

function perguntar(pergunta: string): void {
  const limpo = pergunta.trim()
  if (!limpo || estado.carregando) return
  texto.value = ''
  void enviar(limpo, props.contexto)
  void rolarParaFim()
}

function confirmarAcaoDaMensagem(id: number): void {
  void confirmar(id)
}

function fecharPainel(): void {
  fechar()
}

function aoClicarNaResposta(evento: MouseEvent): void {
  const alvo = (evento.target as HTMLElement | null)?.closest<HTMLElement>('.copiloto-ref')
  if (!alvo) return
  const tipo = alvo.dataset.tipo as TipoEntidadeCopiloto | undefined
  const id = Number(alvo.dataset.id)
  if (tipo && (TIPOS_REFERENCIA as readonly string[]).includes(tipo) && Number.isInteger(id) && id > 0) {
    emit('navegar', { tipo, id })
  }
}

function aoTeclar(evento: KeyboardEvent): void {
  if ((evento.ctrlKey || evento.metaKey) && !evento.altKey && !evento.shiftKey && evento.key.toLowerCase() === 'k') {
    evento.preventDefault()
    alternar()
  } else if (evento.key === 'Escape' && estado.aberto && !evento.defaultPrevented) {
    fechar()
  }
}

watch(() => estado.aberto, async (aberto) => {
  if (aberto) {
    focoAnterior = document.activeElement as HTMLElement | null
    void carregarAcoes()
    await nextTick()
    entrada.value?.focus()
    void rolarParaFim()
  } else {
    focoAnterior?.focus?.()
    focoAnterior = null
  }
})

watch(() => [props.contexto?.entidade_tipo, props.contexto?.entidade_id], () => {
  if (estado.aberto) void carregarAcoes()
})

watch(() => estado.mensagens.length, () => void rolarParaFim())

onMounted(() => window.addEventListener('keydown', aoTeclar))
onBeforeUnmount(() => window.removeEventListener('keydown', aoTeclar))
</script>

<style scoped>
.copiloto-drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: min(420px, 100vw);
  background: var(--card);
  border-left: 1px solid var(--border);
  box-shadow: var(--shadow-lg);
  z-index: 1050;
  display: flex;
  flex-direction: column;
}
.copiloto-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 14px 16px 10px;
  border-bottom: 1px solid var(--border);
}
.copiloto-header h2 { font-family: var(--font-head); font-size: 1.15rem; margin: 0; color: var(--primary); }
.copiloto-header h2 i { color: var(--accent); }
.copiloto-contexto { margin: 2px 0 0; font-size: .78rem; color: var(--ink-muted); }
.copiloto-header-acoes { display: flex; gap: 6px; }
.copiloto-mensagens { flex: 1; overflow-y: auto; padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; }
.copiloto-vazio { color: var(--ink-dim); font-size: .9rem; }
.copiloto-vazio-nota { color: var(--ink-muted); font-size: .8rem; }
.copiloto-msg { max-width: 92%; font-size: .9rem; line-height: 1.45; }
.copiloto-msg-usuario { align-self: flex-end; background: var(--primary); color: #fff; padding: 8px 12px; border-radius: 12px 12px 2px 12px; white-space: pre-wrap; overflow-wrap: anywhere; }
.copiloto-msg-assistente { align-self: flex-start; background: var(--surface); color: var(--ink); padding: 8px 12px; border-radius: 12px 12px 12px 2px; overflow-wrap: anywhere; }
.copiloto-msg-erro { align-self: flex-start; background: #fef2f2; color: var(--danger); padding: 8px 12px; border-radius: 10px; }
.copiloto-texto :deep(p) { margin: 0 0 6px; }
.copiloto-texto :deep(p:last-child) { margin-bottom: 0; }
.copiloto-texto :deep(ul), .copiloto-texto :deep(ol) { margin: 4px 0 6px; padding-left: 20px; }
.copiloto-texto :deep(code) { background: #ece9e2; padding: 0 4px; border-radius: 4px; font-size: .85em; }
.copiloto-mensagens :deep(.copiloto-ref) {
  background: none; border: none; padding: 0; color: var(--primary); text-decoration: underline;
  cursor: pointer; font: inherit; text-align: left;
}
.copiloto-refs { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; font-size: .85rem; }
.copiloto-carregando { display: flex; align-items: center; gap: 8px; color: var(--ink-muted); }
.copiloto-cancelar { background: none; border: none; color: var(--ink-muted); text-decoration: underline; cursor: pointer; font-size: .8rem; }
.copiloto-pontos { display: inline-flex; gap: 3px; }
.copiloto-pontos i { width: 6px; height: 6px; border-radius: 50%; background: var(--ink-muted); animation: pulsar 1s infinite ease-in-out; }
.copiloto-pontos i:nth-child(2) { animation-delay: .15s; }
.copiloto-pontos i:nth-child(3) { animation-delay: .3s; }
@keyframes pulsar { 0%, 100% { opacity: .3; } 50% { opacity: 1; } }
.copiloto-chips { display: flex; flex-wrap: wrap; gap: 6px; padding: 8px 16px 0; }
.copiloto-chip {
  border: 1px solid var(--border); background: var(--card); color: var(--ink-dim);
  border-radius: 999px; padding: 4px 10px; font-size: .78rem; cursor: pointer;
}
.copiloto-chip:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-lt); }
.copiloto-form { display: flex; gap: 8px; align-items: flex-end; padding: 10px 16px 4px; }
.copiloto-form textarea {
  flex: 1; resize: none; border: 1px solid var(--border); border-radius: 10px; padding: 8px 10px;
  font: inherit; font-size: .9rem; color: var(--ink); background: var(--card);
}
.copiloto-form textarea:focus { outline: 2px solid var(--primary-lt); border-color: var(--primary); }
.copiloto-rodape { margin: 0; padding: 0 16px 10px; font-size: .72rem; color: var(--ink-muted); }
.visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
.copiloto-slide-enter-active, .copiloto-slide-leave-active { transition: transform .2s ease; }
.copiloto-slide-enter-from, .copiloto-slide-leave-to { transform: translateX(100%); }
</style>
