<template>
  <div class="page-content animate-fade-in">
    <div class="page-header">
      <div>
        <h1>Páginas do Site</h1>
        <p>Crie, edite e publique as páginas do site público.</p>
      </div>
      <div class="page-header-actions">
        <button class="btn btn-primary" data-nova @click="abrirCriar"><i class="bi bi-plus-circle"></i> Nova página</button>
      </div>
    </div>

    <div class="filtros">
      <input type="search" class="form-control" v-model="busca" placeholder="Buscar por título ou endereço" aria-label="Buscar páginas" />
      <select class="form-control" v-model="status" aria-label="Filtrar por status">
        <option value="">Todos os status</option>
        <option value="publicado">Publicado</option>
        <option value="rascunho">Rascunho</option>
      </select>
      <select class="form-control" v-model="tipo" aria-label="Filtrar por tipo">
        <option value="">Todos os tipos</option>
        <option v-for="(rotulo, valor) in ROTULO_TIPO" :key="valor" :value="valor">{{ rotulo }}</option>
      </select>
    </div>

    <p v-if="erroLista" class="erro" role="alert">{{ erroLista }} <button class="btn btn-ghost btn-sm" @click="carregar">Tentar de novo</button></p>

    <div v-if="falhasPublicacao" class="glass-panel falhas" role="alert" data-falhas>
      <strong><i class="bi bi-exclamation-octagon"></i> "{{ falhasPublicacao.titulo }}" não foi publicada</strong>
      <p>Corrija o conteúdo das seções abaixo e tente de novo.</p>
      <ul>
        <li v-for="f in falhasPublicacao.secoes" :key="f.secaoId">
          Seção #{{ f.secaoId }}: {{ f.mensagens.join('; ') }}
        </li>
        <li v-for="m in falhasPublicacao.outras" :key="m">{{ m }}</li>
      </ul>
      <button class="btn btn-ghost btn-sm" data-corrigir @click="emit('abrir-pagina', falhasPublicacao.id)">Abrir editor da página</button>
      <button class="btn btn-ghost btn-sm" @click="falhasPublicacao = null">Fechar</button>
    </div>

    <div class="glass-panel tabela-caixa">
      <div v-if="carregando" class="vazio">Carregando…</div>
      <div v-else-if="!paginas.length" class="vazio">Nenhuma página encontrada.</div>
      <table v-else class="tabela">
        <thead>
          <tr><th>Título</th><th>Endereço</th><th>Tipo</th><th>Status</th><th>Alterada em</th><th class="acoes-col">Ações</th></tr>
        </thead>
        <tbody>
          <tr v-for="p in paginas" :key="p.id" :data-pagina="p.id">
            <td>
              <a href="#" class="titulo" @click.prevent="emit('abrir-pagina', p.id)">{{ p.titulo }}</a>
              <small v-if="p.total_secoes !== undefined" class="mudo">{{ p.total_secoes }} {{ p.total_secoes === 1 ? 'seção' : 'seções' }}</small>
            </td>
            <td><code>{{ caminhoPublico(p) }}</code></td>
            <td>{{ ROTULO_TIPO[p.tipo] ?? p.tipo }}</td>
            <td><span class="selo" :class="'selo--' + p.status" data-status>{{ ROTULO_STATUS[p.status] ?? p.status }}</span></td>
            <td>{{ formatDate(p.updated_at, true) }}</td>
            <td class="acoes-col">
              <button class="btn btn-ghost btn-sm" title="Editar seções" data-editor @click="emit('abrir-pagina', p.id)"><i class="bi bi-layout-text-window"></i></button>
              <button class="btn btn-ghost btn-sm" title="Editar dados e SEO" data-dados @click="abrirEditar(p)"><i class="bi bi-pencil"></i></button>
              <button v-if="podeDuplicar(p)" class="btn btn-ghost btn-sm" title="Duplicar" data-duplicar @click="duplicar(p)"><i class="bi bi-files"></i></button>
              <button class="btn btn-ghost btn-sm" title="Abrir no site" data-abrir-site @click="abrirNoSite(p)"><i class="bi bi-box-arrow-up-right"></i></button>
              <template v-if="podePublicar(p) && pode('site.publicar')">
                <button v-if="p.status === 'rascunho'" class="btn btn-success-soft btn-sm" title="Publicar" data-publicar @click="publicar(p)"><i class="bi bi-cloud-arrow-up"></i></button>
                <button v-else class="btn btn-ghost btn-sm" title="Despublicar" data-despublicar @click="despublicar(p)"><i class="bi bi-cloud-slash"></i></button>
              </template>
              <button v-if="podeExcluir(p)" class="btn btn-danger btn-sm" title="Excluir" data-excluir @click="excluir(p)"><i class="bi bi-trash"></i></button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <ModalSite v-if="modal" :titulo="modal.modo === 'criar' ? 'Nova página' : 'Dados da página'" @fechar="fecharModal">
      <PaginaDadosForm
        v-model="form"
        :modo="modal.modo"
        :erros="errosForm"
        :layouts="layouts"
        :original="modal.pagina ?? null"
        :slug-fixo="modal.pagina?.tipo === 'home' || modal.pagina?.tipo === 'sistema'"
      />
      <template #rodape>
        <button class="btn btn-ghost" :disabled="salvando" @click="fecharModal">Cancelar</button>
        <button class="btn btn-accent" :disabled="salvando" data-salvar @click="salvar">
          {{ salvando ? 'Salvando…' : modal.modo === 'criar' ? 'Criar e editar seções' : 'Salvar' }}
        </button>
      </template>
    </ModalSite>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import ModalSite from '../../components/site/ModalSite.vue'
import PaginaDadosForm from '../../components/site/PaginaDadosForm.vue'
import { pode } from '../../composables/useAcesso'
import { formatDate } from '../../services/api'
import {
  atualizarPagina, avisosDe, criarPagina, despublicarPagina, duplicarPagina, errosDeCampo, excluirPagina,
  listarPaginas, obterSchema, publicarPagina, tokenPreview,
} from '../../services/siteApi'
import type { SitePagina } from '../../types/site'
import {
  ROTULO_STATUS, ROTULO_TIPO, caminhoPublico, avisoMudancaDeSlug, corpoCriacao, corpoEdicao, errosDePublicacao,
  formDaPagina, formVazio, podeDuplicar, podeExcluir, podePublicar, validarForm, type FormPagina,
} from '../../utils/paginasSite'

const emit = defineEmits<{
  (e: 'toast', t: { msg: string; type: string }): void
  (e: 'abrir-pagina', id: number): void
}>()

const paginas = ref<SitePagina[]>([])
const carregando = ref(true)
const erroLista = ref('')
const busca = ref('')
const status = ref('')
const tipo = ref('')
const layouts = ref<string[]>([])

const modal = ref<{ modo: 'criar' | 'editar'; pagina?: SitePagina } | null>(null)
const form = ref<FormPagina>(formVazio())
const formOriginal = ref<FormPagina>(formVazio())
const errosForm = ref<Record<string, string>>({})
const salvando = ref(false)
const falhasPublicacao = ref<{ id: number; titulo: string; secoes: ReturnType<typeof errosDePublicacao>; outras: string[] } | null>(null)

const mensagemDe = (e: unknown) => Object.values(errosDeCampo(e))[0] ?? (e as Error)?.message ?? 'erro desconhecido'

async function carregar() {
  carregando.value = true
  erroLista.value = ''
  try {
    paginas.value = (await listarPaginas({ status: status.value, tipo: tipo.value, busca: busca.value.trim() })).data
  } catch (e) {
    erroLista.value = 'Não foi possível carregar as páginas: ' + mensagemDe(e)
  } finally {
    carregando.value = false
  }
}

let temporizador: ReturnType<typeof setTimeout> | undefined
watch(busca, () => { clearTimeout(temporizador); temporizador = setTimeout(carregar, 300) })
watch([status, tipo], carregar)

onMounted(async () => {
  carregar()
  try {
    layouts.value = (await obterSchema()).data.layouts
  } catch {
    layouts.value = [] // sem template ativo: a página usa o layout padrão do tipo
  }
})
onBeforeUnmount(() => clearTimeout(temporizador))

// ---- criar / editar dados
function abrirCriar() {
  form.value = formVazio()
  formOriginal.value = formVazio()
  errosForm.value = {}
  modal.value = { modo: 'criar' }
}

function abrirEditar(p: SitePagina) {
  form.value = formDaPagina(p)
  formOriginal.value = formDaPagina(p)
  errosForm.value = {}
  modal.value = { modo: 'editar', pagina: p }
}

const fecharModal = () => { if (!salvando.value) modal.value = null }

async function salvar() {
  if (!modal.value) return
  const locais = validarForm(form.value)
  if (Object.keys(locais).length) {
    errosForm.value = locais
    return
  }

  salvando.value = true
  errosForm.value = {}
  try {
    if (modal.value.modo === 'criar') {
      const r = await criarPagina(corpoCriacao(form.value))
      modal.value = null
      emit('toast', { msg: 'Página criada como rascunho.', type: 'success' })
      emit('abrir-pagina', r.data.id)
      return
    }

    const pagina = modal.value.pagina!
    const corpo = corpoEdicao(formOriginal.value, form.value)
    if (!Object.keys(corpo).length) {
      modal.value = null
      return
    }
    const aviso = avisoMudancaDeSlug(pagina, form.value.slug)
    if (aviso && !window.confirm(aviso + '\n\nSalvar mesmo assim?')) return

    const r = await atualizarPagina(pagina.id, corpo)
    modal.value = null
    emit('toast', { msg: 'Página atualizada.', type: 'success' })
    avisosDe(r).forEach((a) => emit('toast', { msg: a, type: 'warning' }))
    await carregar()
  } catch (e) {
    const doServidor = errosDeCampo(e)
    if (Object.keys(doServidor).length) errosForm.value = doServidor
    else emit('toast', { msg: 'Erro ao salvar: ' + mensagemDe(e), type: 'danger' })
  } finally {
    salvando.value = false
  }
}

// ---- ações da lista
async function duplicar(p: SitePagina) {
  try {
    const r = await duplicarPagina(p.id)
    emit('toast', { msg: `Página duplicada: "${r.data.titulo}" (rascunho).`, type: 'success' })
    await carregar()
  } catch (e) {
    emit('toast', { msg: mensagemDe(e), type: 'danger' })
  }
}

async function publicar(p: SitePagina) {
  falhasPublicacao.value = null
  try {
    const r = await publicarPagina(p.id)
    emit('toast', { msg: `"${p.titulo}" publicada.`, type: 'success' })
    avisosDe(r).forEach((a) => emit('toast', { msg: a, type: 'warning' }))
    await carregar()
  } catch (e) {
    const erros = errosDeCampo(e)
    const secoes = errosDePublicacao(erros)
    const outras = Object.entries(erros).filter(([c]) => !c.startsWith('secoes.')).map(([, m]) => m)
    if (secoes.length || outras.length) {
      falhasPublicacao.value = { id: p.id, titulo: p.titulo, secoes, outras }
    }
    emit('toast', { msg: 'Não foi possível publicar: ' + mensagemDe(e), type: 'danger' })
  }
}

async function despublicar(p: SitePagina) {
  if (!window.confirm(`Despublicar "${p.titulo}"? Ela deixa de aparecer no site e volta a rascunho.`)) return
  try {
    await despublicarPagina(p.id)
    emit('toast', { msg: `"${p.titulo}" voltou a rascunho.`, type: 'success' })
    await carregar()
  } catch (e) {
    emit('toast', { msg: mensagemDe(e), type: 'danger' })
  }
}

async function excluir(p: SitePagina) {
  if (!window.confirm(`Excluir "${p.titulo}" e todas as suas seções? Esta ação não pode ser desfeita.`)) return
  try {
    await excluirPagina(p.id)
    emit('toast', { msg: 'Página excluída.', type: 'success' })
    await carregar()
  } catch (e) {
    emit('toast', { msg: mensagemDe(e), type: 'danger' })
  }
}

/** Publicadas abrem a URL pública; rascunhos abrem a prévia (endereço com token temporário). */
async function abrirNoSite(p: SitePagina) {
  try {
    const url = p.status === 'publicado' || p.tipo === 'sistema' ? p.url : (await tokenPreview(p.id)).data.url
    window.open(url, '_blank', 'noopener')
  } catch (e) {
    emit('toast', { msg: mensagemDe(e), type: 'danger' })
  }
}
</script>

<style scoped>
.filtros { display: flex; flex-wrap: wrap; gap: 10px; margin: 14px 0; }
.filtros .form-control { max-width: 260px; }
.erro { color: var(--danger); }
.falhas { padding: 12px 16px; margin-bottom: 12px; border-left: 4px solid var(--danger); }
.tabela-caixa { padding: 0; overflow-x: auto; }
.vazio { padding: 2rem; color: var(--ink-dim); text-align: center; }
.tabela { width: 100%; border-collapse: collapse; }
.tabela th, .tabela td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--border); vertical-align: middle; }
.tabela th { font-size: .8rem; color: var(--ink-dim); text-transform: uppercase; }
.titulo { font-weight: 600; display: block; }
.mudo { color: var(--ink-dim); }
.selo { padding: 2px 10px; border-radius: 999px; font-size: .75rem; font-weight: 600; }
.selo--publicado { background: #f0fdf4; color: var(--success); }
.selo--rascunho { background: #fff7ed; color: var(--accent); }
.acoes-col { white-space: nowrap; text-align: right !important; }
.acoes-col .btn { margin-left: 4px; }
</style>
