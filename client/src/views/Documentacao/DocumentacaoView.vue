<template>
  <div ref="raiz" class="page-content animate-fade-in documentacao">
    <div class="page-header">
      <div>
        <h1>Documentação</h1>
        <p>Manual do Ordo Juris: como usar cada tela do sistema.</p>
      </div>
      <div class="page-header-actions doc-acoes">
        <button
          class="btn btn-ghost"
          data-alternar-arvore
          :aria-expanded="arvoreVisivel"
          aria-controls="doc-lateral"
          @click="alternarArvore"
        >
          <i class="bi bi-list-ul"></i> Índice
        </button>
        <button v-if="pagina" class="btn btn-ghost" data-imprimir @click="imprimir">
          <i class="bi bi-printer"></i> Imprimir
        </button>
        <button v-if="podeEditar && pagina" class="btn btn-ghost" data-editar-pagina :disabled="preparandoEdicao" @click="editarPagina">
          <i class="bi bi-pencil"></i> {{ preparandoEdicao ? 'Preparando…' : 'Editar esta página' }}
        </button>
        <button v-if="podeEditar" class="btn btn-ghost" data-edicao @click="emit('abrir-edicao', null)">
          <i class="bi bi-pencil-square"></i> Editar documentação
        </button>
      </div>
    </div>

    <div
      class="doc-layout"
      :class="{
        'sem-sumario': !pagina || !pagina.sumario.length,
        'arvore-recolhida': arvoreRecolhida,
        'gaveta-aberta': gavetaAberta,
      }"
    >
      <div v-if="gavetaAberta" class="doc-gaveta-fundo" data-fechar-gaveta @click="gavetaAberta = false"></div>
      <aside id="doc-lateral" class="doc-lateral">
        <p v-if="falhaIndice" class="doc-falha-indice" role="alert">{{ falhaIndice }}</p>
        <DocArvore :indice="indice" :slug-atual="slug ?? null" @navegar="(s) => navegar(s)">
          <template #topo><DocBusca @navegar="(s) => navegar(s)" /></template>
        </DocArvore>
      </aside>

      <!-- Carregando / erro -->
      <div v-if="carregando" class="glass-panel doc-artigo" role="status">Carregando…</div>
      <div v-else-if="falha" class="glass-panel doc-artigo" role="alert" data-falha>
        <h1>{{ naoEncontrada ? 'Página não encontrada' : 'Algo deu errado' }}</h1>
        <p class="doc-resumo">{{ falha }}</p>
        <button v-if="!naoEncontrada" class="btn btn-ghost btn-sm" @click="carregarPagina">Tentar de novo</button>
        <button class="btn btn-ghost btn-sm" data-voltar-inicio @click="navegar(null)">Ir para o início da documentação</button>
      </div>

      <!-- Página -->
      <article v-else-if="pagina" class="glass-panel doc-artigo" data-pagina>
        <h1>{{ pagina.titulo }}<span v-if="pagina.status === 'rascunho'" class="selo-rascunho" data-rascunho title="Só quem edita vê esta versão">Rascunho</span></h1>
        <p v-if="pagina.resumo" class="doc-resumo">{{ pagina.resumo }}</p>
        <div ref="corpo" class="doc-corpo" v-html="pagina.html" @click="aoClicarNoConteudo"></div>

        <nav v-if="pagina.anterior || pagina.proxima" class="doc-vizinhas" aria-label="Páginas vizinhas">
          <a
            v-if="pagina.anterior"
            class="anterior"
            :href="hashDaDocumentacao(pagina.anterior.slug)"
            data-anterior
            @click.prevent="navegar(pagina.anterior.slug)"
          >
            <small><i class="bi bi-arrow-left"></i> Anterior</small>{{ pagina.anterior.titulo }}
          </a>
          <a
            v-if="pagina.proxima"
            class="proxima"
            :href="hashDaDocumentacao(pagina.proxima.slug)"
            data-proxima
            @click.prevent="navegar(pagina.proxima.slug)"
          >
            <small>Próxima <i class="bi bi-arrow-right"></i></small>{{ pagina.proxima.titulo }}
          </a>
        </nav>
        <p class="doc-atualizado">Atualizada em {{ dataAtualizacao }}</p>
      </article>

      <!-- Início: seções e páginas -->
      <div v-else class="doc-inicio" data-inicio>
        <p v-if="!indice.length && !falhaIndice" class="glass-panel doc-artigo">Nenhuma página disponível para o seu perfil.</p>
        <div class="secoes">
          <section v-for="secao in indice" :key="secao.slug" class="glass-panel secao" :data-secao="secao.slug">
            <h2>
              <a v-if="secao.capa" :href="hashDaDocumentacao(secao.slug)" @click.prevent="navegar(secao.slug)">{{ secao.titulo }}</a>
              <span v-else>{{ secao.titulo }}</span>
            </h2>
            <p v-if="secao.resumo" class="resumo">{{ secao.resumo }}</p>
            <ul>
              <li v-for="p in secao.paginas" :key="p.slug">
                <a :href="hashDaDocumentacao(p.slug)" :data-link="p.slug" @click.prevent="navegar(p.slug)">{{ p.titulo }}</a>
              </li>
            </ul>
          </section>
        </div>
      </div>

      <DocSumario
        v-if="pagina && pagina.sumario.length"
        :itens="pagina.sumario"
        :slug="pagina.slug"
        :ativa="ancoraAtiva"
        @ir="(a) => navegar(pagina!.slug, a)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ApiError } from '../../services/api'
import { copiarDoArquivo, obterIndice, obterPagina } from '../../services/documentacaoApi'
import { liberado } from '../../composables/useAcesso'
import { hashDaDocumentacao, lerHashDaDocumentacao } from '../../utils/rotasDocumentacao'
import DocArvore from '../../components/documentacao/DocArvore.vue'
import DocBusca from '../../components/documentacao/DocBusca.vue'
import DocSumario from '../../components/documentacao/DocSumario.vue'
import '../../components/documentacao/documentacao.css'
import type { DocPagina, DocSecao } from '../../types/documentacao'

const props = defineProps<{
  slug?: string | null
  ancora?: string | null
}>()

const emit = defineEmits<{
  (e: 'navegar', destino: { slug: string | null; ancora: string | null }): void
  /** Edição (ordojuris#711): id da página no banco, ou null para a lista. */
  (e: 'abrir-edicao', id: number | null): void
  (e: 'toast', toast: { msg: string; type: string }): void
}>()

const podeEditar = computed(() => liberado('documentacao', 'documentacao.editar'))
const preparandoEdicao = ref(false)

/** Página do banco: abre o editor. Página de arquivo: cria a cópia editável (rascunho) e abre. */
async function editarPagina() {
  if (!pagina.value) return
  if (pagina.value.id) {
    emit('abrir-edicao', pagina.value.id)
    return
  }
  preparandoEdicao.value = true
  try {
    const { data } = await copiarDoArquivo(pagina.value.slug)
    emit('toast', { msg: 'Cópia criada como rascunho. A versão oficial continua no ar até você publicar.', type: 'success' })
    emit('abrir-edicao', data.id)
  } catch (erro: any) {
    emit('toast', { msg: erro?.message || 'Não foi possível preparar a edição.', type: 'danger' })
  } finally {
    preparandoEdicao.value = false
  }
}

const indice = ref<DocSecao[]>([])
const falhaIndice = ref('')
const pagina = ref<DocPagina | null>(null)
const carregando = ref(false)
const falha = ref('')
const naoEncontrada = ref(false)
const ancoraAtiva = ref<string | null>(null)
const arvoreRecolhida = ref(false)
const gavetaAberta = ref(false)
const raiz = ref<HTMLElement | null>(null)
const corpo = ref<HTMLElement | null>(null)

const telaPequena = () => typeof window.matchMedia === 'function' && window.matchMedia('(max-width: 768px)').matches
const arvoreVisivel = computed(() => (telaPequena() ? gavetaAberta.value : !arvoreRecolhida.value))

const dataAtualizacao = computed(() => {
  const data = pagina.value ? new Date(pagina.value.atualizado_em) : null
  return data && !isNaN(data.getTime()) ? data.toLocaleDateString('pt-BR') : ''
})

/** `[id="…"]` com aspas e barras escapadas (o id vem do backend, mas não confiamos no formato). */
const seletorDoId = (id: string) => `[id="${id.replace(/["\\]/g, '\\$&')}"]`

function navegar(slug: string | null, ancora: string | null = null) {
  gavetaAberta.value = false
  emit('navegar', { slug, ancora })
}

function alternarArvore() {
  if (telaPequena()) gavetaAberta.value = !gavetaAberta.value
  else arvoreRecolhida.value = !arvoreRecolhida.value
}

function imprimir() {
  window.print()
}

/** Links da página para a documentação navegam sem recarregar; os demais seguem o padrão. */
function aoClicarNoConteudo(evento: MouseEvent) {
  if (evento.defaultPrevented || evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey) return
  const link = (evento.target as HTMLElement | null)?.closest?.('a')
  const rota = link ? lerHashDaDocumentacao(link.getAttribute('href') ?? '') : null
  if (!rota) return
  evento.preventDefault()
  navegar(rota.slug, rota.ancora)
}

async function carregarIndice() {
  falhaIndice.value = ''
  try {
    indice.value = (await obterIndice()).data
  } catch {
    falhaIndice.value = 'Não foi possível carregar o índice.'
  }
}

async function carregarPagina() {
  observador?.disconnect()
  falha.value = ''
  naoEncontrada.value = false
  ancoraAtiva.value = null
  if (!props.slug) {
    pagina.value = null
    return
  }

  carregando.value = true
  try {
    pagina.value = (await obterPagina(props.slug)).data
  } catch (erro) {
    pagina.value = null
    naoEncontrada.value = erro instanceof ApiError && erro.status === 404
    falha.value = naoEncontrada.value
      ? 'Ela pode ter sido removida ou não estar disponível para o seu perfil.'
      : 'Não foi possível carregar a página.'
  } finally {
    carregando.value = false
  }

  await nextTick()
  observarTitulos()
  rolarParaAncora(true)
}

/** Rola até a âncora atual; sem âncora, volta ao topo da página (só ao trocar de página). */
function rolarParaAncora(trocouDePagina = false) {
  const alvo = props.ancora ? corpo.value?.querySelector<HTMLElement>(seletorDoId(props.ancora)) : null
  if (alvo) {
    alvo.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
    ancoraAtiva.value = props.ancora ?? null
  } else if (trocouDePagina) {
    // Volta ao topo da tela (cabeçalho com "Índice" e "Imprimir" visível).
    raiz.value?.scrollIntoView?.({ block: 'start' })
  }
}

// Sumário acompanha a leitura: destaca o último título que passou do topo.
let observador: IntersectionObserver | null = null
function observarTitulos() {
  if (!pagina.value?.sumario.length || !corpo.value || typeof IntersectionObserver === 'undefined') return
  const titulos = pagina.value.sumario
    .map((item) => corpo.value!.querySelector<HTMLElement>(seletorDoId(item.ancora)))
    .filter((el): el is HTMLElement => el !== null)

  observador = new IntersectionObserver((entradas) => {
    const visiveis = entradas.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)
    if (visiveis.length) ancoraAtiva.value = (visiveis[0].target as HTMLElement).id
  }, { rootMargin: '0px 0px -70% 0px' })
  titulos.forEach((t) => observador!.observe(t))
}

watch(() => props.slug, carregarPagina)
watch(() => props.ancora, () => rolarParaAncora())

onMounted(() => {
  carregarIndice()
  carregarPagina()
})
onBeforeUnmount(() => observador?.disconnect())
</script>

<style scoped>
.secoes {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
}
.secao { padding: 20px 24px; }
.secao h2 { font-size: 1.1rem; margin: 0 0 6px; }
.secao h2 a { color: var(--ink); text-decoration: none; }
.secao h2 a:hover { color: var(--primary); }
.secao ul { margin: 8px 0 0; padding-left: 1.1rem; }
.secao li { margin: 4px 0; }
.secao li a { color: var(--primary); text-decoration: none; }
.secao li a:hover { text-decoration: underline; }
.resumo { color: var(--ink-muted); font-size: .9rem; margin: 0; }
.doc-falha-indice { color: var(--danger); font-size: .85rem; }
[data-falha] .btn { margin-right: 8px; }
.selo-rascunho { display: inline-block; vertical-align: middle; margin-left: 8px; padding: 2px 8px; border-radius: 999px; font-size: .75rem; font-family: var(--font-body); font-weight: 500; background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
</style>
