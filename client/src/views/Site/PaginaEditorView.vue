<template>
  <div class="page-content animate-fade-in editor">
    <div class="breadcrumb">
      <a href="#" @click.prevent="sair">Páginas do Site</a>
      <i class="bi bi-chevron-right"></i>
      <span>{{ pagina?.titulo ?? 'Página' }}</span>
    </div>

    <div v-if="carregando" class="glass-panel" style="padding: 2rem;">Carregando…</div>
    <div v-else-if="falhaCarga" class="glass-panel" style="padding: 2rem;" role="alert">
      <p>{{ falhaCarga }}</p>
      <button class="btn btn-ghost btn-sm" @click="carregar">Tentar de novo</button>
    </div>

    <template v-else-if="pagina">
      <!-- Barra superior -->
      <div class="barra">
        <div class="barra-titulo">
          <h1>{{ pagina.titulo }}</h1>
          <span class="selo" :class="'selo--' + pagina.status" data-status>{{ ROTULO_STATUS[pagina.status] }}</span>
          <code>{{ caminhoPublico(pagina) }}</code>
          <span v-if="sujo" class="sujo" role="status"><i class="bi bi-pencil"></i> Alterações não salvas</span>
        </div>
        <div class="barra-acoes">
          <button class="btn btn-ghost" data-dados @click="abrirDados"><i class="bi bi-gear"></i> Dados e SEO</button>
          <button class="btn btn-ghost" data-previa-toggle @click="previaAberta = !previaAberta">
            <i class="bi bi-eye"></i> {{ previaAberta ? 'Ocultar prévia' : 'Mostrar prévia' }}
          </button>
          <button class="btn btn-accent" data-salvar-tudo :disabled="!sujo || salvando" @click="salvarTudo">
            {{ salvando ? 'Salvando…' : 'Salvar rascunho' }}
          </button>
          <template v-if="podePublicar(pagina) && pode('site.publicar')">
            <button v-if="pagina.status === 'rascunho'" class="btn btn-primary" data-publicar :disabled="salvando" @click="publicar">
              <i class="bi bi-cloud-arrow-up"></i> Publicar
            </button>
            <button v-else class="btn btn-ghost" data-despublicar :disabled="salvando" @click="despublicar">
              <i class="bi bi-cloud-slash"></i> Despublicar
            </button>
          </template>
        </div>
      </div>

      <div v-if="falhasPublicacao.length" class="glass-panel falhas" role="alert" data-falhas>
        <strong><i class="bi bi-exclamation-octagon"></i> Não foi possível publicar</strong>
        <ul>
          <li v-for="f in falhasPublicacao" :key="f.secaoId">
            <a href="#" @click.prevent="selecionar(String(f.secaoId))">Seção #{{ f.secaoId }}</a>: {{ f.mensagens.join('; ') }}
          </li>
        </ul>
      </div>

      <div v-if="pagina.tipo === 'sistema'" class="glass-panel" style="padding: 1.5rem;">
        Páginas de sistema (notícias, área do cliente) têm tela fixa no template. Só os dados e o SEO são editáveis: use <strong>Dados e SEO</strong>.
      </div>

      <div v-else class="colunas" :class="{ 'colunas--previa': previaAberta }">
        <!-- Esquerda: seções -->
        <aside class="lista-secoes glass-panel" aria-label="Seções da página">
          <div class="lista-topo">
            <strong>Seções</strong>
            <button class="btn btn-ghost btn-sm" data-adicionar @click="catalogo = true"><i class="bi bi-plus-lg"></i> Adicionar</button>
          </div>
          <p v-if="!itens.length" class="mudo">Esta página ainda não tem seções.</p>
          <ul class="secoes">
            <li
              v-for="(it, i) in itens"
              :key="it.chave"
              class="secao"
              :class="{ 'secao--ativa': it.chave === selecionada, 'secao--oculta': !it.visivel, 'secao--incompativel': !it.compativel, 'secao--alvo': alvo === it.chave }"
              :data-secao="it.chave"
              @dragover.prevent="alvo = it.chave"
              @drop.prevent="soltar(it)"
            >
              <span class="alca" :draggable="!ehNova(it)" title="Arraste para reordenar" @dragstart="arrastando = it.chave" @dragend="arrastando = null; alvo = null">
                <i class="bi bi-grip-vertical"></i>
              </span>
              <button type="button" class="secao-corpo" data-selecionar @click="selecionar(it.chave)">
                <i :class="['bi', iconeDoTipo(it.tipo)]" aria-hidden="true"></i>
                <span class="secao-textos">
                  <strong>{{ rotuloDoTipo(schema, it.tipo) }}</strong>
                  <small>{{ resumoDaSecao(it.dados, camposDe(it.tipo)) }}</small>
                  <small v-if="!it.compativel" class="aviso" data-incompativel>Tipo fora do template ativo</small>
                  <small v-else-if="ehNova(it)" class="aviso">Nova, ainda não salva</small>
                  <small v-else-if="alterada(it, camposDe(it.tipo))" class="aviso">Alterada</small>
                </span>
              </button>
              <span class="secao-acoes">
                <button type="button" class="btn btn-ghost btn-sm" :title="it.visivel ? 'Ocultar do site' : 'Mostrar no site'" data-visivel :disabled="ehNova(it)" @click="alternarVisivel(it)">
                  <i :class="['bi', it.visivel ? 'bi-eye' : 'bi-eye-slash']"></i>
                </button>
                <button type="button" class="btn btn-ghost btn-sm" title="Subir" data-subir :disabled="ehNova(it) || i === 0" @click="mover(i, i - 1)"><i class="bi bi-arrow-up"></i></button>
                <button type="button" class="btn btn-ghost btn-sm" title="Descer" data-descer :disabled="ehNova(it) || i >= ultimaSalva" @click="mover(i, i + 1)"><i class="bi bi-arrow-down"></i></button>
              </span>
            </li>
          </ul>
        </aside>

        <!-- Centro: formulário da seção -->
        <section class="formulario glass-panel" aria-label="Conteúdo da seção">
          <p v-if="!atual" class="mudo">Selecione ou adicione uma seção para editar.</p>

          <template v-else>
            <div class="form-topo">
              <h2><i :class="['bi', iconeDoTipo(atual.tipo)]"></i> {{ rotuloDoTipo(schema, atual.tipo) }}</h2>
              <span class="form-topo-acoes">
                <button v-if="!ehNova(atual) && atual.compativel" class="btn btn-ghost btn-sm" data-duplicar :disabled="alterada(atual, camposAtual)" :title="alterada(atual, camposAtual) ? 'Salve a seção antes de duplicar' : 'Duplicar seção'" @click="duplicar(atual)">
                  <i class="bi bi-files"></i> Duplicar
                </button>
                <button class="btn btn-danger btn-sm" data-excluir @click="excluir(atual)"><i class="bi bi-trash"></i> Excluir</button>
              </span>
            </div>

            <div v-if="!atual.compativel" class="glass-panel incompativel" role="alert" data-aviso-incompativel>
              <i class="bi bi-exclamation-triangle"></i>
              Esta seção é do tipo <strong>{{ atual.tipo }}</strong>, que o template ativo não tem. Ela não aparece no site e não pode ser editada aqui,
              mas o conteúdo foi preservado: se você voltar a um template com esse tipo, ela volta a funcionar.
            </div>

            <template v-else>
              <div class="form-group" :class="{ 'campo--erro': erroAncora }" data-campo-ancora>
                <label class="form-label" for="ed-ancora">Âncora (opcional)</label>
                <input id="ed-ancora" class="form-control" :value="atual.ancora" placeholder="contato" @input="definirAncora(($event.target as HTMLInputElement).value)" />
                <small class="mudo">Permite links de menu para esta seção (ex.: <code>#contato</code>).</small>
                <p v-if="erroAncora" class="erro" role="alert">{{ erroAncora }}</p>
              </div>

              <SchemaForm
                :campos="camposAtual"
                :model-value="atual.dados"
                :errors="errosCampos"
                :menus="schema?.menus ?? []"
                @update:model-value="atualizarDados"
              />

              <p v-for="o in errosOutros" :key="o" class="erro" role="alert">{{ o }}</p>

              <div class="form-rodape">
                <button class="btn btn-ghost" data-descartar :disabled="ehNova(atual) ? false : !alterada(atual, camposAtual) || salvando" @click="descartar(atual)">
                  {{ ehNova(atual) ? 'Cancelar seção' : 'Descartar alterações' }}
                </button>
                <button class="btn btn-accent" data-salvar-secao :disabled="!alterada(atual, camposAtual) || salvando" @click="salvarSecao(atual)">
                  {{ ehNova(atual) ? 'Criar seção' : 'Salvar seção' }}
                </button>
              </div>
            </template>
          </template>
        </section>

        <!-- Direita: prévia -->
        <aside v-if="previaAberta" class="previa glass-panel" aria-label="Pré-visualização">
          <div class="previa-topo">
            <strong>Prévia</strong>
            <span class="previa-botoes">
              <button class="btn btn-sm" :class="dispositivo === 'desktop' ? 'btn-primary' : 'btn-ghost'" data-desktop title="Computador" @click="dispositivo = 'desktop'"><i class="bi bi-display"></i></button>
              <button class="btn btn-sm" :class="dispositivo === 'celular' ? 'btn-primary' : 'btn-ghost'" data-celular title="Celular" @click="dispositivo = 'celular'"><i class="bi bi-phone"></i></button>
              <button class="btn btn-ghost btn-sm" data-recarregar-previa title="Recarregar" @click="recarregarPrevia"><i class="bi bi-arrow-clockwise"></i></button>
            </span>
          </div>
          <p v-if="erroPrevia" class="erro" role="alert">{{ erroPrevia }}</p>
          <div class="previa-moldura">
            <iframe
              v-if="previaUrl"
              :key="previaChave"
              :src="previaUrl"
              :style="{ width: LARGURA_PREVIA[dispositivo] }"
              title="Pré-visualização da página"
              sandbox="allow-same-origin allow-scripts allow-popups"
              data-iframe
            ></iframe>
          </div>
          <small class="mudo">A prévia mostra o que já foi salvo.</small>
        </aside>
      </div>
    </template>

    <!-- Catálogo de tipos -->
    <ModalSite v-if="catalogo" titulo="Adicionar seção" :largura="640" @fechar="catalogo = false">
      <ul class="catalogo">
        <li v-for="(def, tipo) in schema?.secoes ?? {}" :key="tipo">
          <button type="button" class="catalogo-item" :data-tipo="tipo" @click="adicionar(String(tipo))">
            <i :class="['bi', iconeDoTipo(String(tipo))]"></i>
            <span><strong>{{ def.rotulo }}</strong><small>{{ Object.keys(def.campos).length }} campos</small></span>
          </button>
        </li>
      </ul>
    </ModalSite>

    <!-- Dados e SEO -->
    <ModalSite v-if="dadosAberto && pagina" titulo="Dados e SEO da página" @fechar="fecharDados">
      <PaginaDadosForm
        v-model="formDados"
        modo="editar"
        :erros="errosDados"
        :layouts="schema?.layouts ?? []"
        :original="pagina"
        :slug-fixo="pagina.tipo === 'home' || pagina.tipo === 'sistema'"
      />
      <template #rodape>
        <button class="btn btn-ghost" :disabled="salvandoDados" @click="fecharDados">Cancelar</button>
        <button class="btn btn-accent" data-salvar-dados :disabled="salvandoDados" @click="salvarDados">{{ salvandoDados ? 'Salvando…' : 'Salvar' }}</button>
      </template>
    </ModalSite>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import ModalSite from '../../components/site/ModalSite.vue'
import PaginaDadosForm from '../../components/site/PaginaDadosForm.vue'
import SchemaForm from '../../components/site/SchemaForm.vue'
import { pode } from '../../composables/useAcesso'
import { marcarSujo } from '../../composables/useSaidaProtegida'
import {
  atualizarPagina, atualizarSecao, avisosDe, criarSecao, despublicarPagina, errosDeCampo, excluirSecao,
  obterPagina, obterSchema, publicarPagina, reordenarSecoes, tokenPreview,
} from '../../services/siteApi'
import type { CamposSchema, SitePagina, TemplateSchema } from '../../types/site'
import {
  LARGURA_PREVIA, alterada, corpoDaSecao, ehNova, erroDeAncora, iconeDoTipo, idsEmOrdem, itemDoServidor, itemNovo,
  ordemComCopiaApos, resumoDaSecao, rotuloDoTipo, separarErrosDaSecao, type Dispositivo, type ItemSecao,
} from '../../utils/editorPagina'
import {
  ROTULO_STATUS, avisoMudancaDeSlug, caminhoPublico, corpoEdicao, errosDePublicacao, formDaPagina, formVazio,
  podePublicar, validarForm, type FormPagina,
} from '../../utils/paginasSite'

const props = defineProps<{ paginaId: number | null }>()
const emit = defineEmits<{
  (e: 'toast', t: { msg: string; type: string }): void
  (e: 'set-view', view: string): void
}>()

const pagina = ref<SitePagina | null>(null)
const schema = ref<TemplateSchema | null>(null)
const itens = ref<ItemSecao[]>([])
const selecionada = ref<string | null>(null)
const carregando = ref(true)
const falhaCarga = ref('')
const salvando = ref(false)
const erros = ref<Record<string, Record<string, string>>>({})
const falhasPublicacao = ref<ReturnType<typeof errosDePublicacao>>([])
const catalogo = ref(false)
const contadorNovas = ref(0)
const arrastando = ref<string | null>(null)
const alvo = ref<string | null>(null)

const previaAberta = ref(true)
const dispositivo = ref<Dispositivo>('desktop')
const previaUrl = ref('')
const previaChave = ref(0)
const erroPrevia = ref('')

const dadosAberto = ref(false)
const formDados = ref<FormPagina>(formVazio())
const formDadosOriginal = ref<FormPagina>(formVazio())
const errosDados = ref<Record<string, string>>({})
const salvandoDados = ref(false)

const mensagemDe = (e: unknown) => Object.values(errosDeCampo(e))[0] ?? (e as Error)?.message ?? 'erro desconhecido'
const camposDe = (tipo: string): CamposSchema | null => schema.value?.secoes?.[tipo]?.campos ?? null

const atual = computed(() => itens.value.find((i) => i.chave === selecionada.value) ?? null)
const camposAtual = computed<CamposSchema>(() => (atual.value ? camposDe(atual.value.tipo) ?? {} : {}))
const sujo = computed(() => itens.value.some((i) => alterada(i, camposDe(i.tipo))))
const ultimaSalva = computed(() => itens.value.reduce((u, it, i) => (ehNova(it) ? u : i), 0))
const errosDaAtual = computed(() => separarErrosDaSecao(atual.value ? erros.value[atual.value.chave] ?? {} : {}))
const errosCampos = computed(() => errosDaAtual.value.campos)
const errosOutros = computed(() => errosDaAtual.value.outros)
const erroAncora = computed(() => (atual.value ? erroDeAncora(atual.value.ancora) ?? errosDaAtual.value.ancora ?? '' : ''))

watch(sujo, (v) => marcarSujo(v))

// ---- carga
async function carregar() {
  if (props.paginaId === null) {
    falhaCarga.value = 'Nenhuma página selecionada.'
    carregando.value = false
    return
  }
  carregando.value = true
  falhaCarga.value = ''
  try {
    const [p, s] = await Promise.all([obterPagina(props.paginaId), obterSchema().catch(() => null)])
    pagina.value = p.data
    schema.value = s?.data ?? null
    itens.value = (p.data.secoes ?? []).map((sec) => itemDoServidor(sec, camposDe(sec.tipo)))
    selecionada.value = itens.value.find((i) => i.compativel)?.chave ?? itens.value[0]?.chave ?? null
    erros.value = {}
    recarregarPrevia()
  } catch (e) {
    falhaCarga.value = 'Não foi possível carregar a página: ' + mensagemDe(e)
  } finally {
    carregando.value = false
  }
}

function selecionar(chave: string) {
  if (itens.value.some((i) => i.chave === chave)) selecionada.value = chave
}

// ---- prévia
async function recarregarPrevia() {
  erroPrevia.value = ''
  if (!pagina.value || pagina.value.tipo === 'sistema') {
    previaUrl.value = ''
    return
  }
  try {
    previaUrl.value = (await tokenPreview(pagina.value.id)).data.url
    previaChave.value += 1
  } catch (e) {
    previaUrl.value = ''
    erroPrevia.value = 'Não foi possível gerar a prévia: ' + mensagemDe(e)
  }
}

// ---- edição da seção
function substituir(chave: string, mudancas: Partial<ItemSecao>) {
  itens.value = itens.value.map((i) => (i.chave === chave ? { ...i, ...mudancas } : i))
}

function atualizarDados(dados: Record<string, any>) {
  if (!atual.value) return
  substituir(atual.value.chave, { dados })
  limparErros(atual.value.chave)
}

function definirAncora(valor: string) {
  if (!atual.value) return
  substituir(atual.value.chave, { ancora: valor })
}

function limparErros(chave: string) {
  if (!erros.value[chave]) return
  const { [chave]: _, ...resto } = erros.value
  erros.value = resto
}

function descartar(item: ItemSecao) {
  limparErros(item.chave)
  if (ehNova(item)) {
    itens.value = itens.value.filter((i) => i.chave !== item.chave)
    selecionada.value = itens.value[0]?.chave ?? null
    return
  }
  substituir(item.chave, { dados: JSON.parse(JSON.stringify(item.original!.dados)), ancora: item.original!.ancora })
}

/** Salva uma seção (cria ou atualiza). Devolve `true` se salvou. */
async function salvarSecao(item: ItemSecao, silencioso = false): Promise<boolean> {
  const campos = camposDe(item.tipo)
  if (!campos || erroDeAncora(item.ancora)) return false

  try {
    const corpo = corpoDaSecao(item, campos)
    const r = ehNova(item)
      ? await criarSecao(pagina.value!.id, { tipo: item.tipo, ...corpo, visivel: item.visivel })
      : await atualizarSecao(item.id!, corpo)
    const novo = itemDoServidor({ ...r.data, compativel: true }, campos)
    itens.value = itens.value.map((i) => (i.chave === item.chave ? novo : i))
    if (selecionada.value === item.chave) selecionada.value = novo.chave
    limparErros(item.chave)
    if (!silencioso) emit('toast', { msg: 'Seção salva.', type: 'success' })
    avisosDe(r).forEach((a) => emit('toast', { msg: a, type: 'warning' }))
    return true
  } catch (e) {
    const doServidor = errosDeCampo(e)
    if (Object.keys(doServidor).length) {
      erros.value = { ...erros.value, [item.chave]: doServidor }
      selecionada.value = item.chave
      emit('toast', { msg: 'Corrija os campos destacados na seção.', type: 'danger' })
    } else {
      emit('toast', { msg: 'Erro ao salvar a seção: ' + mensagemDe(e), type: 'danger' })
    }
    return false
  }
}

/** Salva todas as seções alteradas, em ordem; para na primeira que falhar. */
async function salvarTudo(): Promise<boolean> {
  salvando.value = true
  try {
    for (const item of [...itens.value]) {
      if (alterada(item, camposDe(item.tipo)) && !(await salvarSecao(item, true))) return false
    }
    emit('toast', { msg: 'Rascunho salvo.', type: 'success' })
    await recarregarPrevia()
    return true
  } finally {
    salvando.value = false
  }
}

// ---- estrutura: adicionar, ocultar, ordenar, duplicar, excluir
function adicionar(tipo: string) {
  const campos = camposDe(tipo)
  if (!campos) return
  contadorNovas.value += 1
  const novo = itemNovo(contadorNovas.value, tipo, campos)
  itens.value = [...itens.value, novo]
  selecionada.value = novo.chave
  catalogo.value = false
}

async function alternarVisivel(item: ItemSecao) {
  const novo = !item.visivel
  substituir(item.chave, { visivel: novo })
  try {
    await atualizarSecao(item.id!, { visivel: novo })
    recarregarPrevia()
  } catch (e) {
    substituir(item.chave, { visivel: !novo })
    emit('toast', { msg: mensagemDe(e), type: 'danger' })
  }
}

async function aplicarOrdem(nova: ItemSecao[]) {
  const anterior = itens.value
  itens.value = nova
  try {
    await reordenarSecoes(pagina.value!.id, idsEmOrdem(nova))
    recarregarPrevia()
  } catch (e) {
    itens.value = anterior
    emit('toast', { msg: 'Não foi possível reordenar: ' + mensagemDe(e), type: 'danger' })
  }
}

function mover(de: number, para: number) {
  if (para < 0 || para > ultimaSalva.value || de === para) return
  const copia = [...itens.value]
  const [m] = copia.splice(de, 1)
  copia.splice(para, 0, m)
  return aplicarOrdem(copia)
}

function soltar(destino: ItemSecao) {
  const origem = arrastando.value
  arrastando.value = null
  alvo.value = null
  if (!origem || origem === destino.chave || ehNova(destino)) return
  const de = itens.value.findIndex((i) => i.chave === origem)
  const para = itens.value.findIndex((i) => i.chave === destino.chave)
  if (de >= 0 && para >= 0) return mover(de, para)
}

async function duplicar(item: ItemSecao) {
  const campos = camposDe(item.tipo)
  if (!campos || item.id === null) return
  try {
    // A âncora é única na página: a cópia nasce sem ela.
    const r = await criarSecao(pagina.value!.id, { tipo: item.tipo, dados: corpoDaSecao(item, campos).dados, ancora: null, visivel: item.visivel })
    const copia = itemDoServidor({ ...r.data, compativel: true }, campos)
    const ordem = ordemComCopiaApos(idsEmOrdem(itens.value), item.id, r.data.id)
    const porId = new Map<number, ItemSecao>([...itens.value, copia].filter((i) => i.id !== null).map((i) => [i.id as number, i]))
    const reordenadas = ordem.map((id) => porId.get(id)!).concat(itens.value.filter(ehNova))
    itens.value = reordenadas
    selecionada.value = copia.chave
    await reordenarSecoes(pagina.value!.id, ordem)
    emit('toast', { msg: 'Seção duplicada.', type: 'success' })
    recarregarPrevia()
  } catch (e) {
    emit('toast', { msg: 'Não foi possível duplicar: ' + mensagemDe(e), type: 'danger' })
    await carregar()
  }
}

async function excluir(item: ItemSecao) {
  if (ehNova(item)) return descartar(item)
  if (!window.confirm(`Excluir esta seção (${rotuloDoTipo(schema.value, item.tipo)})? Esta ação não pode ser desfeita.`)) return
  try {
    await excluirSecao(item.id!)
    itens.value = itens.value.filter((i) => i.chave !== item.chave)
    if (selecionada.value === item.chave) selecionada.value = itens.value[0]?.chave ?? null
    limparErros(item.chave)
    emit('toast', { msg: 'Seção excluída.', type: 'success' })
    recarregarPrevia()
  } catch (e) {
    emit('toast', { msg: mensagemDe(e), type: 'danger' })
  }
}

// ---- publicar
async function publicar() {
  falhasPublicacao.value = []
  if (sujo.value && !(await salvarTudo())) return
  try {
    const r = await publicarPagina(pagina.value!.id)
    pagina.value = { ...pagina.value!, ...r.data, secoes: pagina.value!.secoes }
    emit('toast', { msg: 'Página publicada.', type: 'success' })
    avisosDe(r).forEach((a) => emit('toast', { msg: a, type: 'warning' }))
  } catch (e) {
    falhasPublicacao.value = errosDePublicacao(errosDeCampo(e))
    emit('toast', { msg: 'Não foi possível publicar: ' + mensagemDe(e), type: 'danger' })
  }
}

async function despublicar() {
  if (!window.confirm('Despublicar a página? Ela deixa de aparecer no site e volta a rascunho.')) return
  try {
    const r = await despublicarPagina(pagina.value!.id)
    pagina.value = { ...pagina.value!, ...r.data, secoes: pagina.value!.secoes }
    emit('toast', { msg: 'A página voltou a rascunho.', type: 'success' })
  } catch (e) {
    emit('toast', { msg: mensagemDe(e), type: 'danger' })
  }
}

// ---- dados e SEO
function abrirDados() {
  formDados.value = formDaPagina(pagina.value!)
  formDadosOriginal.value = formDaPagina(pagina.value!)
  errosDados.value = {}
  dadosAberto.value = true
}
const fecharDados = () => { if (!salvandoDados.value) dadosAberto.value = false }

async function salvarDados() {
  const locais = validarForm(formDados.value)
  if (Object.keys(locais).length) {
    errosDados.value = locais
    return
  }
  const corpo = corpoEdicao(formDadosOriginal.value, formDados.value)
  if (!Object.keys(corpo).length) {
    dadosAberto.value = false
    return
  }
  const aviso = avisoMudancaDeSlug(pagina.value!, formDados.value.slug)
  if (aviso && !window.confirm(aviso + '\n\nSalvar mesmo assim?')) return

  salvandoDados.value = true
  try {
    const r = await atualizarPagina(pagina.value!.id, corpo)
    pagina.value = { ...pagina.value!, ...r.data, secoes: pagina.value!.secoes }
    dadosAberto.value = false
    emit('toast', { msg: 'Dados da página salvos.', type: 'success' })
    avisosDe(r).forEach((a) => emit('toast', { msg: a, type: 'warning' }))
    recarregarPrevia()
  } catch (e) {
    const doServidor = errosDeCampo(e)
    if (Object.keys(doServidor).length) errosDados.value = doServidor
    else emit('toast', { msg: mensagemDe(e), type: 'danger' })
  } finally {
    salvandoDados.value = false
  }
}

function sair() {
  emit('set-view', 'site-paginas')
}

function aoFechar(e: BeforeUnloadEvent) {
  if (sujo.value) {
    e.preventDefault()
    e.returnValue = ''
  }
}

watch(() => props.paginaId, carregar)
onMounted(() => {
  window.addEventListener('beforeunload', aoFechar)
  carregar()
})
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', aoFechar)
  marcarSujo(false)
})
</script>

<style scoped>
.barra { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; }
.barra-titulo { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
.barra-titulo h1 { margin: 0; font-size: 1.4rem; }
.barra-acoes { display: flex; flex-wrap: wrap; gap: 8px; }
.sujo { color: var(--accent); font-size: .85rem; }
.selo { padding: 2px 10px; border-radius: 999px; font-size: .75rem; font-weight: 600; }
.selo--publicado { background: #f0fdf4; color: var(--success); }
.selo--rascunho { background: #fff7ed; color: var(--accent); }
.falhas { padding: 12px 16px; margin-bottom: 12px; border-left: 4px solid var(--danger); }
.colunas { display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 14px; align-items: start; }
.colunas--previa { grid-template-columns: 280px minmax(0, 1fr) minmax(320px, 0.9fr); }
@media (max-width: 1100px) { .colunas, .colunas--previa { grid-template-columns: 1fr; } }
.lista-secoes, .formulario, .previa { padding: 12px; }
.lista-topo, .previa-topo, .form-topo { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; gap: 8px; }
.form-topo h2 { margin: 0; font-size: 1.1rem; }
.form-topo-acoes, .previa-botoes { display: inline-flex; gap: 6px; }
.secoes { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.secao { display: flex; align-items: center; gap: 4px; border: 1px solid var(--border); border-radius: 8px; padding: 6px; background: var(--surface); }
.secao--ativa { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(1, 81, 110, .15); }
.secao--oculta { opacity: .6; }
.secao--incompativel { border-style: dashed; }
.secao--alvo { border-color: var(--accent); }
.alca { cursor: grab; color: var(--ink-dim); }
.secao-corpo { flex: 1; min-width: 0; display: flex; gap: 8px; align-items: flex-start; text-align: left; border: 0; background: transparent; cursor: pointer; padding: 2px; }
.secao-textos { display: flex; flex-direction: column; min-width: 0; }
.secao-textos small { color: var(--ink-dim); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.secao-textos .aviso { color: var(--accent); }
.secao-acoes { display: flex; flex-direction: column; gap: 2px; }
.mudo { color: var(--ink-dim); }
.erro { margin: 4px 0 0; font-size: .85rem; color: var(--danger); }
.campo--erro .form-control { border-color: var(--danger); }
.incompativel { padding: 12px 14px; border-left: 4px solid var(--accent); }
.form-rodape { display: flex; justify-content: flex-end; gap: 8px; margin-top: 12px; position: sticky; bottom: 0; background: var(--surface); padding: 10px 0; }
.previa-moldura { background: #e9e6df; border-radius: 8px; padding: 6px; display: flex; justify-content: center; min-height: 420px; }
.previa-moldura iframe { border: 0; background: #fff; height: 640px; max-width: 100%; border-radius: 6px; }
.catalogo { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; }
.catalogo-item { width: 100%; display: flex; align-items: center; gap: 10px; text-align: left; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); cursor: pointer; }
.catalogo-item:hover { border-color: var(--primary); }
.catalogo-item span { display: flex; flex-direction: column; }
.catalogo-item small { color: var(--ink-dim); }
</style>
