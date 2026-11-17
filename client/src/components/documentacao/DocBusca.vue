<template>
  <div class="doc-busca" @keydown="aoTeclar">
    <label class="visually-hidden" for="doc-busca-campo">Buscar na documentação</label>
    <div class="doc-busca-campo">
      <i class="bi bi-search" aria-hidden="true"></i>
      <input
        id="doc-busca-campo"
        ref="campo"
        v-model="consulta"
        type="search"
        placeholder="Buscar na documentação ( / )"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="list"
        aria-controls="doc-busca-resultados"
        :aria-expanded="aberta"
        :aria-activedescendant="ativo >= 0 ? `doc-busca-item-${ativo}` : undefined"
        data-busca
        @input="agendar"
        @focus="aberta = temConsulta"
        @blur="aberta = false"
      >
    </div>

    <div v-if="aberta" class="doc-busca-painel">
      <p v-if="buscando" class="doc-busca-estado" role="status">Buscando…</p>
      <p v-else-if="falha" class="doc-busca-estado" role="alert">{{ falha }}</p>
      <p v-else-if="!resultados.length" class="doc-busca-estado" role="status" data-sem-resultados>Nada encontrado para “{{ consulta.trim() }}”.</p>
      <ul v-else id="doc-busca-resultados" role="listbox" aria-label="Resultados da busca">
        <li
          v-for="(r, i) in resultados"
          :id="`doc-busca-item-${i}`"
          :key="r.slug"
          role="option"
          :aria-selected="i === ativo"
          :class="{ ativo: i === ativo }"
          :data-resultado="r.slug"
          @mousedown.prevent="escolher(i)"
          @mouseenter="ativo = i"
        >
          <span class="doc-busca-titulo" v-html="destacarTermos(r.titulo, consultaFeita)"></span>
          <span class="doc-busca-secao">{{ r.secao }}</span>
          <span class="doc-busca-trecho" v-html="destacarTermos(r.trecho, consultaFeita)"></span>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { debounce } from '../../services/api'
import { buscar } from '../../services/documentacaoApi'
import { destacarTermos, termosDaBusca } from '../../utils/buscaDocumentacao'
import type { DocResultadoBusca } from '../../types/documentacao'

const emit = defineEmits<{ (e: 'navegar', slug: string): void }>()

const consulta = ref('')
/** Consulta que gerou os resultados exibidos (o destaque usa esta, não a que está sendo digitada). */
const consultaFeita = ref('')
const resultados = ref<DocResultadoBusca[]>([])
const buscando = ref(false)
const falha = ref('')
const aberta = ref(false)
const ativo = ref(-1)
const campo = ref<HTMLInputElement | null>(null)
let controle: AbortController | null = null

const temConsulta = computed(() => termosDaBusca(consulta.value).length > 0)

async function executar() {
  controle?.abort()
  const texto = consulta.value.trim()
  if (!termosDaBusca(texto).length) {
    resultados.value = []
    aberta.value = false
    buscando.value = false
    return
  }

  controle = new AbortController()
  const meu = controle
  buscando.value = true
  falha.value = ''
  aberta.value = true
  try {
    const resposta = await buscar(texto, meu.signal)
    if (meu !== controle) return
    resultados.value = resposta.data
    consultaFeita.value = texto
    ativo.value = resposta.data.length ? 0 : -1
  } catch {
    if (meu !== controle || meu.signal.aborted) return
    resultados.value = []
    falha.value = 'Não foi possível buscar agora.'
  } finally {
    if (meu === controle) buscando.value = false
  }
}

const agendar = debounce(executar, 300)

function escolher(i: number) {
  const r = resultados.value[i]
  if (!r) return
  aberta.value = false
  consulta.value = ''
  resultados.value = []
  emit('navegar', r.slug)
}

function aoTeclar(evento: KeyboardEvent) {
  const total = resultados.value.length
  if (evento.key === 'ArrowDown' && total) {
    evento.preventDefault()
    aberta.value = true
    ativo.value = (ativo.value + 1) % total
  } else if (evento.key === 'ArrowUp' && total) {
    evento.preventDefault()
    ativo.value = (ativo.value - 1 + total) % total
  } else if (evento.key === 'Enter' && aberta.value && ativo.value >= 0) {
    evento.preventDefault()
    escolher(ativo.value)
  } else if (evento.key === 'Escape') {
    if (aberta.value) aberta.value = false
    else consulta.value = ''
  }
}

function focar() {
  campo.value?.focus()
  campo.value?.select()
}

/** `/` foca a busca, exceto quando o usuário já está digitando em algum campo. */
function atalho(evento: KeyboardEvent) {
  if (evento.key !== '/' || evento.ctrlKey || evento.metaKey || evento.altKey) return
  const alvo = evento.target as HTMLElement | null
  if (alvo && (alvo.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(alvo.tagName))) return
  evento.preventDefault()
  focar()
}

window.addEventListener('keydown', atalho)
onBeforeUnmount(() => {
  window.removeEventListener('keydown', atalho)
  controle?.abort()
})

// O clique num resultado usa mousedown.prevent: o campo não perde o foco antes da escolha.
defineExpose({ focar })
</script>

<style scoped>
.doc-busca { margin-bottom: 12px; }
.doc-busca-campo { position: relative; }
.doc-busca-campo i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--ink-muted); }
.doc-busca-campo input {
  width: 100%; padding: 8px 10px 8px 32px; border: 1px solid var(--border); border-radius: 8px;
  background: var(--card); color: var(--ink); font-size: .9rem;
}
.doc-busca-campo input:focus { outline: 2px solid var(--primary); outline-offset: 1px; }
/* Resultados no fluxo da coluna (e não sobrepostos): a coluna do índice rola e cortaria um painel flutuante. */
.doc-busca-painel {
  margin-top: 6px; background: var(--card); border: 1px solid var(--border); border-radius: 8px;
  box-shadow: var(--shadow); max-height: 60vh; overflow-y: auto;
}
.doc-busca-estado { margin: 0; padding: 12px; color: var(--ink-muted); font-size: .85rem; }
.doc-busca-painel ul { list-style: none; margin: 0; padding: 4px; }
.doc-busca-painel li { display: flex; flex-direction: column; gap: 2px; padding: 8px 10px; border-radius: 6px; cursor: pointer; }
.doc-busca-painel li.ativo { background: var(--surface); }
.doc-busca-titulo { font-weight: 600; color: var(--ink); }
.doc-busca-secao { font-size: .75rem; color: var(--ink-muted); text-transform: uppercase; letter-spacing: .03em; }
.doc-busca-trecho { font-size: .82rem; color: var(--ink-dim); }
.doc-busca-painel :deep(mark) { background: #fde68a; color: inherit; padding: 0 1px; border-radius: 2px; }
.visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
</style>
