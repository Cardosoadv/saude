<template>
  <div class="page-content animate-fade-in">
    <div class="page-header">
      <div>
        <h1>Menus do Site</h1>
        <p>Cabeçalho, rodapé e menus laterais. Cada menu é salvo inteiro, de uma vez.</p>
      </div>
      <div class="page-header-actions">
        <span v-if="sujoAtual" class="sujo" role="status"><i class="bi bi-pencil"></i> Alterações não salvas</span>
        <button class="btn btn-ghost" :disabled="!sujoAtual || salvando" data-descartar @click="descartar">Descartar</button>
        <button class="btn btn-accent" :disabled="!sujoAtual || salvando" data-salvar @click="salvar">
          {{ salvando ? 'Salvando…' : 'Salvar menu' }}
        </button>
      </div>
    </div>

    <div v-if="carregando" class="glass-panel" style="padding: 2rem;">Carregando…</div>
    <div v-else-if="falhaCarga" class="glass-panel" style="padding: 2rem;" role="alert">
      <p>{{ falhaCarga }}</p>
      <button class="btn btn-ghost btn-sm" @click="carregar">Tentar de novo</button>
    </div>

    <div v-else class="form-container">
      <div class="abas" role="tablist">
        <button
          v-for="local in locais"
          :key="local"
          type="button"
          role="tab"
          class="aba"
          :class="{ ativa: local === localAtivo }"
          :aria-selected="local === localAtivo"
          :data-aba="local"
          @click="localAtivo = local"
        >
          {{ rotuloDoLocal(local) }}
          <span v-if="sujo(local)" class="ponto" title="Alterações não salvas">•</span>
        </button>
      </div>

      <div class="glass-panel corpo" role="tabpanel">
        <p v-if="!locais.length" class="mudo">O template ativo não declara menus.</p>

        <template v-else>
          <p v-for="m in errosApi.outros" :key="m" class="erro" role="alert">{{ m }}</p>

          <div v-if="avisos.length" class="avisos" role="status" data-avisos>
            <strong><i class="bi bi-exclamation-triangle"></i> Atenção</strong>
            <ul><li v-for="a in avisos" :key="a">{{ a }}</li></ul>
          </div>

          <p v-if="!arvore.length" class="mudo">Este menu está vazio.</p>

          <div class="itens">
            <template v-for="(item, i) in arvore" :key="item.uid">
              <MenuItemLinha
                :item="item"
                :id="`${i}`"
                :paginas="paginas"
                :ancoras="ancoras"
                :erros="errosDoItemPorCaminho(`${i}`)"
                :aviso="avisoDoDestino(item, paginas)"
                :primeiro="i === 0"
                :ultimo="i === arvore.length - 1"
                :pode-subitem="i > 0 && !item.filhos.length"
                :class="{ 'alvo': alvo === `|${i}` }"
                @update:item="(n) => editar(atualizarItem(arvore, null, i, n))"
                @subir="editar(moverNaLista(arvore, null, i, i - 1))"
                @descer="editar(moverNaLista(arvore, null, i, i + 1))"
                @remover="editar(removerItem(arvore, null, i))"
                @tornar-subitem="editar(tornarSubitem(arvore, i))"
                @arrastar="arrastando = { pai: null, indice: i }"
                @soltar-fim="arrastando = null; alvo = null"
                @dragover.prevent="alvo = `|${i}`"
                @drop.prevent="soltar(null, i)"
              />
              <MenuItemLinha
                v-for="(filho, j) in item.filhos"
                :key="filho.uid"
                subitem
                :item="filho"
                :id="`${i}.filhos.${j}`"
                :paginas="paginas"
                :ancoras="ancoras"
                :erros="errosDoItemPorCaminho(`${i}.filhos.${j}`)"
                :aviso="avisoDoDestino(filho, paginas)"
                :primeiro="j === 0"
                :ultimo="j === item.filhos.length - 1"
                :class="{ 'alvo': alvo === `${i}|${j}` }"
                @update:item="(n) => editar(atualizarItem(arvore, i, j, n))"
                @subir="editar(moverNaLista(arvore, i, j, j - 1))"
                @descer="editar(moverNaLista(arvore, i, j, j + 1))"
                @remover="editar(removerItem(arvore, i, j))"
                @tirar-do-submenu="editar(tirarDoSubmenu(arvore, i, j))"
                @arrastar="arrastando = { pai: i, indice: j }"
                @soltar-fim="arrastando = null; alvo = null"
                @dragover.prevent="alvo = `${i}|${j}`"
                @drop.prevent="soltar(i, j)"
              />
            </template>
          </div>

          <div class="rodape-lista">
            <button class="btn btn-ghost btn-sm" data-adicionar :disabled="contarItens(arvore) >= MAX_ITENS" @click="adicionar">
              <i class="bi bi-plus-lg"></i> Adicionar item
            </button>
            <small class="mudo">{{ contarItens(arvore) }} de {{ MAX_ITENS }} itens. O menu aceita um nível de submenu.</small>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import MenuItemLinha from '../../components/site/MenuItemLinha.vue'
import { marcarSujo } from '../../composables/useSaidaProtegida'
import { avisosDe, errosDeCampo, listarMenus, listarPaginas, obterPagina, obterSchema, salvarMenu } from '../../services/siteApi'
import {
  MAX_ITENS, alterada, arvoreDaApi, arvoreParaEnvio, atualizarItem, avisoDoDestino, avisosDaArvore, contarItens,
  errosDaApi, errosDaArvore, itemVazio, moverNaLista, removerItem, rotuloDoLocal, tirarDoSubmenu, tornarSubitem,
  type ItemMenu, type PaginaResumo,
} from '../../utils/menusSite'

const emit = defineEmits<{ (e: 'toast', t: { msg: string; type: string }): void }>()

const carregando = ref(true)
const falhaCarga = ref('')
const salvando = ref(false)
const locais = ref<string[]>([])
const localAtivo = ref('')
const iniciais = ref<Record<string, ItemMenu[]>>({})
const atuais = ref<Record<string, ItemMenu[]>>({})
const paginas = ref<PaginaResumo[]>([])
const ancoras = ref<string[]>([])
const errosDoServidor = ref<Record<string, { campos: Record<string, string>; outros: string[] }>>({})
const errosLocais = ref<Record<string, Record<string, string>>>({})
const arrastando = ref<{ pai: number | null; indice: number } | null>(null)
const alvo = ref<string | null>(null)

const arvore = computed(() => atuais.value[localAtivo.value] ?? [])
const avisos = computed(() => avisosDaArvore(arvore.value, paginas.value))
const sujo = (local: string) => alterada(iniciais.value[local] ?? [], atuais.value[local] ?? [])
const sujoAtual = computed(() => !!localAtivo.value && sujo(localAtivo.value))
const sujoGeral = computed(() => locais.value.some(sujo))
const errosApi = computed(() => errosDoServidor.value[localAtivo.value] ?? { campos: {}, outros: [] })
const mensagemDe = (e: unknown) => Object.values(errosDeCampo(e))[0] ?? (e as Error)?.message ?? 'erro desconhecido'

watch(sujoGeral, (v) => marcarSujo(v))

/** Erros do item (locais e do servidor) por campo, a partir do caminho `0` ou `1.filhos.0`. */
function errosDoItemPorCaminho(caminho: string): Record<string, string> {
  const saida: Record<string, string> = {}
  const junta = (origem: Record<string, string>) => {
    for (const [c, m] of Object.entries(origem)) {
      if (c.startsWith(caminho + '.') && !c.slice(caminho.length + 1).includes('.')) saida[c.slice(caminho.length + 1)] = m
    }
  }
  junta(errosLocais.value[localAtivo.value] ?? {})
  junta(errosApi.value.campos)
  return saida
}

function editar(nova: ItemMenu[]) {
  atuais.value = { ...atuais.value, [localAtivo.value]: nova }
  errosDoServidor.value = { ...errosDoServidor.value, [localAtivo.value]: { campos: {}, outros: [] } }
  errosLocais.value = { ...errosLocais.value, [localAtivo.value]: {} }
}

const adicionar = () => editar([...arvore.value, itemVazio()])

function soltar(pai: number | null, indice: number) {
  const origem = arrastando.value
  arrastando.value = null
  alvo.value = null
  if (!origem || origem.pai !== pai) return
  editar(moverNaLista(arvore.value, pai, origem.indice, indice))
}

function descartar() {
  atuais.value = { ...atuais.value, [localAtivo.value]: arvoreDaApi(arvoreParaEnvio(iniciais.value[localAtivo.value] ?? [])) }
  errosLocais.value = { ...errosLocais.value, [localAtivo.value]: {} }
  errosDoServidor.value = { ...errosDoServidor.value, [localAtivo.value]: { campos: {}, outros: [] } }
}

async function salvar() {
  const local = localAtivo.value
  const locais_ = errosDaArvore(arvore.value)
  if (Object.keys(locais_).length) {
    errosLocais.value = { ...errosLocais.value, [local]: locais_ }
    emit('toast', { msg: 'Corrija os itens destacados antes de salvar.', type: 'warning' })
    return
  }

  salvando.value = true
  try {
    const r = await salvarMenu(local, arvoreParaEnvio(arvore.value))
    const nova = arvoreDaApi(r.data)
    iniciais.value = { ...iniciais.value, [local]: arvoreDaApi(r.data) }
    atuais.value = { ...atuais.value, [local]: nova }
    emit('toast', { msg: `Menu "${rotuloDoLocal(local)}" salvo.`, type: 'success' })
    avisosDe(r).forEach((a) => emit('toast', { msg: a, type: 'warning' }))
  } catch (e) {
    const doServidor = errosDeCampo(e)
    if (Object.keys(doServidor).length) {
      errosDoServidor.value = { ...errosDoServidor.value, [local]: errosDaApi(doServidor) }
      emit('toast', { msg: 'O menu não foi salvo: corrija os itens destacados.', type: 'danger' })
    } else {
      emit('toast', { msg: 'Erro ao salvar o menu: ' + mensagemDe(e), type: 'danger' })
    }
  } finally {
    salvando.value = false
  }
}

async function carregar() {
  carregando.value = true
  falhaCarga.value = ''
  try {
    const [menus, schema, lista] = await Promise.all([
      listarMenus(),
      obterSchema().catch(() => null),
      listarPaginas(),
    ])
    locais.value = schema?.data.menus ?? Object.keys(menus.data)
    iniciais.value = {}
    atuais.value = {}
    for (const l of locais.value) {
      iniciais.value[l] = arvoreDaApi(menus.data[l])
      atuais.value[l] = arvoreDaApi(menus.data[l])
    }
    if (!locais.value.includes(localAtivo.value)) localAtivo.value = locais.value[0] ?? ''
    paginas.value = lista.data.map((p) => ({ id: p.id, titulo: p.titulo, status: p.status, tipo: p.tipo }))
    carregarAncoras(lista.data.find((p) => p.tipo === 'home')?.id)
  } catch (e) {
    falhaCarga.value = 'Não foi possível carregar os menus: ' + mensagemDe(e)
  } finally {
    carregando.value = false
  }
}

/** Âncoras das seções da página inicial, sugeridas nos itens do tipo âncora. Falhar aqui não impede a edição. */
async function carregarAncoras(idHome: number | undefined) {
  if (!idHome) return
  try {
    const home = await obterPagina(idHome)
    ancoras.value = (home.data.secoes ?? []).map((s) => s.ancora).filter((a): a is string => !!a)
  } catch {
    ancoras.value = []
  }
}

function aoFechar(e: BeforeUnloadEvent) {
  if (sujoGeral.value) {
    e.preventDefault()
    e.returnValue = ''
  }
}

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
.abas { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 12px; border-bottom: 1px solid var(--border); }
.aba { border: 0; background: transparent; padding: 10px 14px; cursor: pointer; color: var(--ink-dim); border-bottom: 2px solid transparent; }
.aba.ativa { color: var(--primary); border-bottom-color: var(--primary); font-weight: 600; }
.ponto { color: var(--accent); margin-left: 4px; }
.sujo { align-self: center; font-size: .85rem; color: var(--accent); }
.corpo { padding: 1.25rem; }
.itens { display: flex; flex-direction: column; gap: 8px; }
.itens :deep(.alvo) { border-color: var(--accent); }
.rodape-lista { display: flex; align-items: center; gap: 12px; margin-top: 14px; }
.mudo { color: var(--ink-dim); }
.erro { color: var(--danger); font-size: .85rem; }
.avisos { border-left: 4px solid var(--accent); padding: 8px 12px; margin-bottom: 12px; font-size: .88rem; }
.avisos ul { margin: 4px 0 0; padding-left: 18px; }
</style>
