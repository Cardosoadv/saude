<template>
  <div class="page-content animate-fade-in editor-docs">
    <div class="page-header">
      <div>
        <p class="migalha"><a href="#/documentacao-admin" @click.prevent="emit('voltar')">Edição da documentação</a></p>
        <h1>
          {{ id ? (original?.titulo || 'Página') : 'Nova página' }}
          <span v-if="original" class="selo" :class="original.status === 'rascunho' ? 'selo-rascunho' : 'selo-publicado'" data-status>
            {{ original.status === 'rascunho' ? 'Rascunho' : 'Publicado' }}
          </span>
        </h1>
        <p v-if="original?.tem_arquivo" class="nota">
          Cópia de uma página do manual oficial. Enquanto for rascunho, os leitores continuam vendo a versão oficial.
        </p>
      </div>
      <div class="page-header-actions">
        <span v-if="sujo" class="sujo" role="status"><i class="bi bi-pencil"></i> Alterações não salvas</span>
        <button v-if="original" class="btn btn-ghost" data-ver @click="emit('abrir-documentacao', { slug: original.slug, ancora: null })">
          <i class="bi bi-eye"></i> Ver
        </button>
        <button v-if="original" class="btn btn-ghost perigo" data-excluir :disabled="ocupado" @click="excluir">
          <i class="bi bi-trash"></i> Excluir
        </button>
        <button class="btn btn-ghost" data-salvar :disabled="ocupado || (!!id && !sujo)" title="Salvar (Ctrl+S)" @click="salvar">
          {{ salvando ? 'Salvando…' : 'Salvar rascunho' }}
        </button>
        <button v-if="original?.status === 'publicado'" class="btn btn-ghost" data-despublicar :disabled="ocupado" @click="mudarStatus('rascunho')">
          Despublicar
        </button>
        <button v-else class="btn btn-accent" data-publicar :disabled="ocupado || !id" :title="!id ? 'Salve antes de publicar' : ''" @click="mudarStatus('publicado')">
          {{ sujo ? 'Salvar e publicar' : 'Publicar' }}
        </button>
      </div>
    </div>

    <div v-if="carregando" class="glass-panel estado" role="status">Carregando…</div>
    <div v-else-if="falhaCarga" class="glass-panel estado" role="alert">
      <p>{{ falhaCarga }}</p>
      <button class="btn btn-ghost btn-sm" @click="carregar">Tentar de novo</button>
    </div>

    <template v-else>
      <p v-for="m in errosGerais" :key="m" class="erro" role="alert">{{ m }}</p>
      <div v-if="avisos.length" class="avisos" role="status" data-avisos>
        <strong><i class="bi bi-exclamation-triangle"></i> Atenção</strong>
        <ul><li v-for="a in avisos" :key="a">{{ a }}</li></ul>
      </div>

      <details class="glass-panel dados" :open="!id">
        <summary>Dados da página</summary>
        <div class="grade">
          <label class="campo largo" :class="{ invalido: erros.titulo }">
            <span>Título *</span>
            <input v-model="form.titulo" type="text" maxlength="120" data-campo="titulo">
            <small v-if="erros.titulo" class="erro">{{ erros.titulo }}</small>
          </label>
          <label class="campo" :class="{ invalido: erros.slug }">
            <span>Endereço *</span>
            <input v-model="form.slug" type="text" placeholder="secao/pagina" data-campo="slug" @blur="form.slug = form.slug.trim().toLowerCase()">
            <small v-if="erros.slug" class="erro">{{ erros.slug }}</small>
            <small v-else class="ajuda">"secao" para a capa, "secao/pagina" para uma página. Ex.: processos/prazos</small>
          </label>
          <label class="campo largo" :class="{ invalido: erros.resumo }">
            <span>Resumo</span>
            <input v-model="form.resumo" type="text" maxlength="300" data-campo="resumo">
            <small v-if="erros.resumo" class="erro">{{ erros.resumo }}</small>
          </label>
          <label class="campo curto" :class="{ invalido: erros.ordem }">
            <span>Ordem</span>
            <input v-model.number="form.ordem" type="number" min="0" max="9999" placeholder="100" data-campo="ordem">
            <small v-if="erros.ordem" class="erro">{{ erros.ordem }}</small>
          </label>
          <label class="campo" :class="{ invalido: erros.modulo }">
            <span>Módulo</span>
            <input v-model="form.modulo" type="text" placeholder="ex.: processos" data-campo="modulo">
            <small v-if="erros.modulo" class="erro">{{ erros.modulo }}</small>
            <small v-else class="ajuda">A página some com o módulo desativado.</small>
          </label>
          <label class="campo" :class="{ invalido: erros.permissao }">
            <span>Permissão</span>
            <input v-model="form.permissao" type="text" placeholder="ex.: processos.access" data-campo="permissao">
            <small v-if="erros.permissao" class="erro">{{ erros.permissao }}</small>
          </label>
          <label class="campo" :class="{ invalido: erroDeLista('telas') }">
            <span>Telas da ajuda contextual</span>
            <input v-model="telasTexto" type="text" placeholder="ex.: list, process-form" data-campo="telas">
            <small v-if="erroDeLista('telas')" class="erro">{{ erroDeLista('telas') }}</small>
            <small v-else class="ajuda">Separe por vírgula. O botão "?" dessas telas abre esta página.</small>
          </label>
          <label class="campo" :class="{ invalido: erroDeLista('palavras_chave') }">
            <span>Palavras-chave</span>
            <input v-model="palavrasTexto" type="text" placeholder="ex.: prazo, contagem" data-campo="palavras_chave">
            <small v-if="erroDeLista('palavras_chave')" class="erro">{{ erroDeLista('palavras_chave') }}</small>
          </label>
        </div>
      </details>

      <div class="area">
        <section class="glass-panel escrita">
          <div class="barra">
            <strong>Texto (Markdown)</strong>
            <span class="espaco"></span>
            <button class="btn btn-ghost btn-sm" type="button" title="Aviso" @click="inserir('\n> [!DICA]\n> ')">Dica</button>
            <button class="btn btn-ghost btn-sm" type="button" title="Aviso" @click="inserir('\n> [!ATENCAO]\n> ')">Atenção</button>
            <label class="btn btn-ghost btn-sm" :class="{ desativado: !secao || enviandoImagem }" :title="secao ? 'Inserir imagem' : 'Preencha o endereço antes'">
              <i class="bi bi-image"></i> {{ enviandoImagem ? 'Enviando…' : 'Imagem' }}
              <input type="file" accept="image/png,image/jpeg,image/gif,image/webp" hidden :disabled="!secao || enviandoImagem" data-imagem @change="aoEscolherImagem">
            </label>
          </div>
          <textarea
            ref="texto"
            v-model="form.markdown"
            class="markdown"
            spellcheck="true"
            placeholder="## Título da seção&#10;&#10;Texto da página…"
            data-campo="markdown"
            @keydown="aoTeclar"
          ></textarea>
          <small v-if="erros.markdown" class="erro">{{ erros.markdown }}</small>
          <small class="ajuda">
            Use ## e ### para os títulos. Links para outras páginas: <code>[texto](../secao/pagina.md)</code>.
            Formato completo em "Documentação › Administração".
          </small>
        </section>

        <section class="glass-panel previa" aria-label="Pré-visualização">
          <div class="barra"><strong>Pré-visualização</strong><span class="espaco"></span><small v-if="previsualizando">Atualizando…</small></div>
          <h2 class="previa-titulo">{{ form.titulo || 'Sem título' }}</h2>
          <p v-if="form.resumo" class="doc-resumo">{{ form.resumo }}</p>
          <div class="doc-corpo" data-previa v-html="previa.html"></div>
          <ul v-if="previa.avisos.length" class="avisos-previa">
            <li v-for="a in previa.avisos" :key="a">{{ a }}</li>
          </ul>
        </section>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { ApiError, debounce } from '../../services/api'
import {
  criarPagina, despublicarPagina, enviarImagem, excluirPagina, obterParaEdicao, previsualizar, publicarPagina, salvarPagina,
} from '../../services/documentacaoApi'
import { errosDeCampo, avisosDe } from '../../services/siteApi'
import { marcarSujo } from '../../composables/useSaidaProtegida'
import type { DocEntradaPagina, DocPaginaEdicao, DocPreview } from '../../types/documentacao'
import '../../components/documentacao/documentacao.css'

const props = defineProps<{ id: number | null }>()

const emit = defineEmits<{
  (e: 'voltar'): void
  (e: 'criada', id: number): void
  (e: 'excluida'): void
  (e: 'abrir-documentacao', destino: { slug: string | null; ancora: string | null }): void
  (e: 'toast', toast: { msg: string; type: string }): void
}>()

const vazio = (): DocEntradaPagina => ({
  slug: '', titulo: '', resumo: '', ordem: null, modulo: '', permissao: '', telas: [], palavras_chave: [], markdown: '',
})

const form = reactive<DocEntradaPagina>(vazio())
const original = ref<DocPaginaEdicao | null>(null)
const referencia = ref(JSON.stringify(vazio()))
const carregando = ref(false)
const falhaCarga = ref('')
const salvando = ref(false)
const mudandoStatus = ref(false)
const enviandoImagem = ref(false)
const previsualizando = ref(false)
const erros = ref<Record<string, string>>({})
const errosGerais = ref<string[]>([])
const avisos = ref<string[]>([])
const previa = ref<DocPreview>({ html: '', sumario: [], avisos: [] })
const texto = ref<HTMLTextAreaElement | null>(null)

const ocupado = computed(() => salvando.value || mudandoStatus.value)
const secao = computed(() => {
  const s = form.slug.trim().toLowerCase().split('/')[0]
  return /^[a-z0-9]+(-[a-z0-9]+)*$/.test(s) ? s : ''
})

// Listas editadas como texto separado por vírgula.
const lista = (t: string) => t.split(',').map((v) => v.trim()).filter(Boolean)
const telasTexto = computed({ get: () => form.telas.join(', '), set: (t: string) => { form.telas = lista(t) } })
const palavrasTexto = computed({ get: () => form.palavras_chave.join(', '), set: (t: string) => { form.palavras_chave = lista(t) } })

const sujo = computed(() => JSON.stringify(normalizado()) !== referencia.value)
watch(sujo, (v) => marcarSujo(v))

function normalizado(): DocEntradaPagina {
  return {
    ...form,
    slug: form.slug.trim().toLowerCase(),
    ordem: form.ordem === null || (form.ordem as unknown) === '' ? null : Number(form.ordem),
    telas: [...form.telas],
    palavras_chave: [...form.palavras_chave],
  }
}

function preencher(p: DocPaginaEdicao | null) {
  original.value = p
  Object.assign(form, p
    ? {
        slug: p.slug, titulo: p.titulo, resumo: p.resumo || '', ordem: p.ordem, modulo: p.modulo || '', permissao: p.permissao || '',
        telas: [...p.telas], palavras_chave: [...p.palavras_chave], markdown: p.markdown,
      }
    : vazio())
  referencia.value = JSON.stringify(normalizado())
}

function erroDeLista(campo: string): string {
  return erros.value[campo] || Object.entries(erros.value).find(([k]) => k.startsWith(`${campo}.`))?.[1] || ''
}

async function carregar() {
  erros.value = {}
  errosGerais.value = []
  avisos.value = []
  falhaCarga.value = ''
  if (!props.id) {
    preencher(null)
    atualizarPrevia()
    return
  }
  carregando.value = true
  try {
    preencher((await obterParaEdicao(props.id)).data)
    atualizarPrevia()
  } catch (erro) {
    falhaCarga.value = erro instanceof ApiError && erro.status === 404 ? 'Página não encontrada.' : 'Não foi possível carregar a página.'
  } finally {
    carregando.value = false
  }
}

function tratarErro(erro: unknown, padrao: string) {
  const porCampo = errosDeCampo(erro)
  erros.value = porCampo
  avisos.value = avisosDe(erro)
  const conhecidos = ['slug', 'titulo', 'resumo', 'ordem', 'modulo', 'permissao', 'markdown', 'telas', 'palavras_chave']
  errosGerais.value = Object.entries(porCampo)
    .filter(([k]) => !conhecidos.some((c) => k === c || k.startsWith(`${c}.`)))
    .map(([, m]) => m)
  if (!Object.keys(porCampo).length) errosGerais.value = [(erro as any)?.message || padrao]
}

/** Grava (cria ou atualiza). Devolve falso se a validação recusou. */
async function salvar(): Promise<boolean> {
  salvando.value = true
  erros.value = {}
  errosGerais.value = []
  try {
    const r = props.id ? await salvarPagina(props.id, normalizado()) : await criarPagina(normalizado())
    preencher(r.data)
    avisos.value = r.avisos || []
    emit('toast', { msg: 'Rascunho salvo.', type: 'success' })
    if (!props.id) emit('criada', r.data.id)
    return true
  } catch (erro) {
    tratarErro(erro, 'Não foi possível salvar.')
    return false
  } finally {
    salvando.value = false
  }
}

async function mudarStatus(status: 'publicado' | 'rascunho') {
  if (!props.id) return
  if (sujo.value && !(await salvar())) return
  mudandoStatus.value = true
  try {
    const r = status === 'publicado' ? await publicarPagina(props.id) : await despublicarPagina(props.id)
    preencher(r.data)
    emit('toast', {
      msg: status === 'publicado' ? 'Página publicada: já aparece para os leitores.' : 'Página voltou a ser rascunho.',
      type: 'success',
    })
  } catch (erro) {
    tratarErro(erro, 'Não foi possível mudar o status.')
  } finally {
    mudandoStatus.value = false
  }
}

async function excluir() {
  if (!props.id || !original.value) return
  const aviso = original.value.tem_arquivo
    ? 'Excluir esta cópia? A página volta a mostrar a versão do manual oficial.'
    : 'Excluir esta página? Ela some da documentação.'
  if (!window.confirm(aviso)) return
  try {
    await excluirPagina(props.id)
    marcarSujo(false)
    referencia.value = JSON.stringify(normalizado())
    emit('toast', { msg: 'Página excluída.', type: 'success' })
    emit('excluida')
  } catch (erro) {
    tratarErro(erro, 'Não foi possível excluir.')
  }
}

// ---------------------------------------------------------------- pré-visualização

let controle: AbortController | null = null
async function atualizarPrevia() {
  controle?.abort()
  controle = new AbortController()
  const meu = controle
  previsualizando.value = true
  try {
    const r = await previsualizar(form.markdown, form.slug.trim().toLowerCase() || 'rascunho', meu.signal)
    if (meu === controle) previa.value = r.data
  } catch {
    // Mantém a última prévia; o erro de verdade aparece ao salvar.
  } finally {
    if (meu === controle) previsualizando.value = false
  }
}
const agendarPrevia = debounce(atualizarPrevia, 600)
watch(() => [form.markdown, form.slug], () => agendarPrevia())

// ---------------------------------------------------------------- texto

async function inserir(trecho: string) {
  const area = texto.value
  const inicio = area?.selectionStart ?? form.markdown.length
  const fim = area?.selectionEnd ?? inicio
  form.markdown = form.markdown.slice(0, inicio) + trecho + form.markdown.slice(fim)
  await nextTick()
  area?.focus()
  area?.setSelectionRange(inicio + trecho.length, inicio + trecho.length)
}

async function aoEscolherImagem(evento: Event) {
  const campo = evento.target as HTMLInputElement
  const arquivo = campo.files?.[0]
  campo.value = ''
  if (!arquivo || !secao.value) return
  enviandoImagem.value = true
  try {
    const { data } = await enviarImagem(arquivo, secao.value)
    await inserir(`\n${data.markdown}\n`)
    emit('toast', { msg: 'Imagem enviada. Troque "Descreva a imagem" por uma descrição.', type: 'info' })
  } catch (erro) {
    const porCampo = errosDeCampo(erro)
    emit('toast', { msg: porCampo.arquivo || porCampo.secao || (erro as any)?.message || 'Não foi possível enviar a imagem.', type: 'danger' })
  } finally {
    enviandoImagem.value = false
  }
}

function aoTeclar(evento: KeyboardEvent) {
  if ((evento.ctrlKey || evento.metaKey) && evento.key.toLowerCase() === 's') {
    evento.preventDefault()
    if (!ocupado.value) salvar()
  }
}

function aoFechar(e: BeforeUnloadEvent) {
  if (sujo.value) {
    e.preventDefault()
    e.returnValue = ''
  }
}

watch(() => props.id, (novo, antigo) => {
  // Depois de criar, o id muda de null para o novo: os dados já estão no formulário.
  if (antigo === null && novo && original.value?.id === novo) return
  carregar()
})
onMounted(() => {
  window.addEventListener('beforeunload', aoFechar)
  carregar()
})
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', aoFechar)
  controle?.abort()
  marcarSujo(false)
})
</script>

<style scoped>
.migalha { margin: 0 0 4px; font-size: .85rem; }
.migalha a { color: var(--ink-muted); text-decoration: none; }
.migalha a:hover { color: var(--primary); }
.page-header h1 { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.nota { color: var(--ink-muted); font-size: .9rem; }
.sujo { color: var(--warning); font-size: .85rem; align-self: center; }
.estado { padding: 1.5rem; }
.erro { color: var(--danger); font-size: .85rem; }
.ajuda { color: var(--ink-muted); font-size: .8rem; }
.avisos { border-left: 4px solid var(--warning); background: var(--surface); padding: 10px 14px; border-radius: 0 6px 6px 0; margin-bottom: 12px; }
.avisos ul { margin: 6px 0 0; padding-left: 1.2rem; }
.dados { padding: 12px 18px; margin-bottom: 16px; }
.dados summary { cursor: pointer; font-weight: 600; }
.grade { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px 16px; margin-top: 12px; }
.campo { display: flex; flex-direction: column; gap: 4px; font-size: .88rem; }
.campo > span { font-weight: 600; color: var(--ink); }
.campo input { padding: 7px 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--card); color: var(--ink); }
.campo.invalido input { border-color: var(--danger); }
.campo.largo { grid-column: span 2; }
.campo.curto { max-width: 140px; }
.area { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 16px; align-items: start; }
.escrita, .previa { padding: 14px 16px; }
.barra { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; }
.espaco { flex: 1; }
.desativado { opacity: .5; cursor: not-allowed; }
.markdown {
  width: 100%; min-height: 60vh; resize: vertical; padding: 12px; border: 1px solid var(--border); border-radius: 6px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .88rem; line-height: 1.55;
  background: var(--card); color: var(--ink);
}
.previa { max-height: 80vh; overflow-y: auto; }
.previa-titulo { font-family: var(--font-head); font-size: 1.5rem; margin: 4px 0; }
.avisos-previa { color: var(--warning); font-size: .82rem; margin-top: 12px; }
.selo { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: .75rem; font-family: var(--font-body); font-weight: 500; border: 1px solid var(--border); }
.selo-rascunho { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
.selo-publicado { background: #dcfce7; color: #166534; border-color: #86efac; }
.perigo { color: var(--danger); }
@media (max-width: 1024px) { .area { grid-template-columns: minmax(0, 1fr); } .campo.largo { grid-column: auto; } }
</style>
