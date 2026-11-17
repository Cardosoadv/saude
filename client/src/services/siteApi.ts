/**
 * Cliente tipado da API administrativa do site (`api/v2/site/admin/*`).
 * Respostas: `{ data, avisos? }`; erros de validação (422) trazem `messages` por
 * caminho de campo (ver `errosDeCampo`). Contrato: docs/api-site-admin.md (ordojuris).
 */
import { apiFetch, ApiError, BASE_URL } from './api'
import { cabecalhosDeTenant } from './tenant'
import type {
  SiteConfiguracoes, SiteMenuItem, SiteMenus, SiteMidia, SiteMidiaPager, SiteMidiaUso,
  SiteNoticiaCategoria, SiteNoticiaCategoriaEntrada,
  SitePagina, SitePaginaEntrada, SiteResposta, SiteSecao, SiteSecaoEntrada, SiteTema,
  SiteTemplate, SiteTemplateAtivacao, SiteTemplateResumo, TemplateSchema,
} from '../types/site'


const RAIZ = 'site/admin'

const json = (method: string, body?: unknown) => ({ method, body: body as any })

// ---------------------------------------------------------------- erros

/**
 * Erros 422 por campo: `{ "dados.botoes.1.rotulo": "Campo obrigatório." }`.
 * Qualquer outro erro vira `{}` (a mensagem fica em `erro.message`).
 */
export function errosDeCampo(erro: unknown): Record<string, string> {
  if (!(erro instanceof ApiError) || erro.status !== 422) return {}
  const mensagens = erro.data?.messages
  if (!mensagens || typeof mensagens !== 'object' || Array.isArray(mensagens)) return {}

  const saida: Record<string, string> = {}
  for (const [campo, texto] of Object.entries(mensagens)) {
    saida[campo] = String(texto)
  }
  return saida
}

/** Avisos (não bloqueiam) de uma resposta de erro 422 ou de sucesso. */
export function avisosDe(origem: unknown): string[] {
  const lista = origem instanceof ApiError ? origem.data?.avisos : (origem as any)?.avisos
  return Array.isArray(lista) ? lista.map(String) : []
}

// ---------------------------------------------------------------- schema, tema, configurações

export const obterSchema = () =>
  apiFetch<SiteResposta<TemplateSchema>>(`${RAIZ}/template/schema`)

export const obterTema = () =>
  apiFetch<SiteResposta<SiteTema>>(`${RAIZ}/template/tema`)

/** `{ cores: { gold: '#B08E35' }, fontes: { serif: 'Lora' } }`; valor vazio restaura o padrão. */
export const salvarTema = (entrada: { cores?: Record<string, string>; fontes?: Record<string, string> }) =>
  apiFetch<SiteResposta<SiteTema>>(`${RAIZ}/template/tema`, json('PUT', entrada))

export const restaurarTema = () =>
  apiFetch<SiteResposta<SiteTema>>(`${RAIZ}/template/tema`, json('DELETE'))

export const obterConfiguracoes = () =>
  apiFetch<SiteResposta<SiteConfiguracoes>>(`${RAIZ}/configuracoes`)

/** `{ 'contato.telefone': '…' }`; chave nova usa `{ valor, tipo }`. Só as chaves enviadas mudam. */
export const salvarConfiguracoes = (valores: Record<string, unknown>) =>
  apiFetch<SiteResposta<SiteConfiguracoes>>(`${RAIZ}/configuracoes`, json('PUT', valores))

// ---------------------------------------------------------------- páginas

export interface FiltrosPaginas {
  status?: string
  tipo?: string
  busca?: string
}

function consulta(parametros: Record<string, string | number | undefined | null>): string {
  const q = new URLSearchParams()
  for (const [k, v] of Object.entries(parametros)) {
    if (v !== undefined && v !== null && v !== '') q.set(k, String(v))
  }
  const texto = q.toString()
  return texto ? `?${texto}` : ''
}

export const listarPaginas = (filtros: FiltrosPaginas = {}) =>
  apiFetch<SiteResposta<SitePagina[]>>(`${RAIZ}/paginas${consulta({ ...filtros })}`)

export const obterPagina = (id: number) =>
  apiFetch<SiteResposta<SitePagina>>(`${RAIZ}/paginas/${id}`)

export const criarPagina = (entrada: SitePaginaEntrada) =>
  apiFetch<SiteResposta<SitePagina>>(`${RAIZ}/paginas`, json('POST', entrada))

export const atualizarPagina = (id: number, entrada: SitePaginaEntrada) =>
  apiFetch<SiteResposta<SitePagina>>(`${RAIZ}/paginas/${id}`, json('PUT', entrada))

export const excluirPagina = (id: number) =>
  apiFetch<{ id: number; message: string }>(`${RAIZ}/paginas/${id}`, json('DELETE'))

export const publicarPagina = (id: number) =>
  apiFetch<SiteResposta<SitePagina>>(`${RAIZ}/paginas/${id}/publicar`, json('POST'))

export const despublicarPagina = (id: number) =>
  apiFetch<SiteResposta<SitePagina>>(`${RAIZ}/paginas/${id}/despublicar`, json('POST'))

export const duplicarPagina = (id: number) =>
  apiFetch<SiteResposta<SitePagina>>(`${RAIZ}/paginas/${id}/duplicar`, json('POST'))

/** Token de prévia do rascunho (validade 1 h): abrir `url` num iframe. */
export const tokenPreview = (id: number) =>
  apiFetch<SiteResposta<{ token: string; url: string; expira_em: string }>>(`${RAIZ}/paginas/${id}/preview-token`)

// ---------------------------------------------------------------- categorias de notícias

export const listarCategoriasNoticias = (busca?: string) =>
  apiFetch<SiteResposta<SiteNoticiaCategoria[]>>(`${RAIZ}/noticias/categorias${consulta({ busca })}`)

/** Sugere meta título e meta descrição pela IA a partir do conteúdo da notícia (nada é gravado). */
export const gerarMetatagsNoticia = (entrada: { title: string; content: string }) =>
  apiFetch<SiteResposta<{ summary: string; meta_title: string; meta_description: string }>>(`${RAIZ}/noticias/metatags`, json('POST', entrada))

export const criarCategoriaNoticia = (entrada: SiteNoticiaCategoriaEntrada) =>
  apiFetch<SiteResposta<SiteNoticiaCategoria>>(`${RAIZ}/noticias/categorias`, json('POST', entrada))

/** Só os campos enviados mudam. */
export const atualizarCategoriaNoticia = (id: number, entrada: SiteNoticiaCategoriaEntrada) =>
  apiFetch<SiteResposta<SiteNoticiaCategoria>>(`${RAIZ}/noticias/categorias/${id}`, json('PUT', entrada))

/** 422 em `categoria` se ainda houver notícias nela. */
export const excluirCategoriaNoticia = (id: number) =>
  apiFetch<{ id: number; message: string }>(`${RAIZ}/noticias/categorias/${id}`, json('DELETE'))

// ---------------------------------------------------------------- seções

export const criarSecao = (paginaId: number, entrada: SiteSecaoEntrada) =>
  apiFetch<SiteResposta<SiteSecao>>(`${RAIZ}/paginas/${paginaId}/secoes`, json('POST', entrada))

/** `dados`, se enviado, substitui o conteúdo inteiro da seção. */
export const atualizarSecao = (id: number, entrada: Omit<SiteSecaoEntrada, 'tipo'>) =>
  apiFetch<SiteResposta<SiteSecao>>(`${RAIZ}/secoes/${id}`, json('PUT', entrada))

export const excluirSecao = (id: number) =>
  apiFetch<{ id: number; message: string }>(`${RAIZ}/secoes/${id}`, json('DELETE'))

/** `ids`: todas as seções da página, na nova ordem. */
export const reordenarSecoes = (paginaId: number, ids: number[]) =>
  apiFetch<SiteResposta<SiteSecao[]>>(`${RAIZ}/paginas/${paginaId}/secoes/ordem`, json('PUT', { ids }))

// ---------------------------------------------------------------- menus

export const listarMenus = () =>
  apiFetch<SiteResposta<SiteMenus>>(`${RAIZ}/menus`)

/** Substitui a árvore inteira do local (um nível de submenu). */
export const salvarMenu = (local: string, itens: SiteMenuItem[]) =>
  apiFetch<SiteResposta<SiteMenuItem[]>>(`${RAIZ}/menus/${encodeURIComponent(local)}`, json('PUT', { itens }))

// ---------------------------------------------------------------- mídias

export interface RespostaMidias {
  data: SiteMidia[]
  pager: SiteMidiaPager
}

export const listarMidias = (parametros: { page?: number; per_page?: number; busca?: string } = {}) =>
  apiFetch<RespostaMidias>(`${RAIZ}/midias${consulta({ ...parametros })}`)

export const obterMidia = (id: number) =>
  apiFetch<SiteResposta<SiteMidia>>(`${RAIZ}/midias/${id}`)

export function enviarMidia(arquivo: File | Blob, alt?: string) {
  const form = new FormData()
  form.append('arquivo', arquivo)
  if (alt) form.append('alt', alt)
  return apiFetch<SiteResposta<SiteMidia>>(`${RAIZ}/midias`, { method: 'POST', body: form })
}

export const atualizarAltMidia = (id: number, alt: string | null) =>
  apiFetch<SiteResposta<SiteMidia>>(`${RAIZ}/midias/${id}`, json('PUT', { alt }))

export const usosMidia = (id: number) =>
  apiFetch<SiteResposta<SiteMidiaUso[]>>(`${RAIZ}/midias/${id}/usos`)

export const excluirMidia = (id: number) =>
  apiFetch<{ id: number; message: string }>(`${RAIZ}/midias/${id}`, json('DELETE'))

// ---------------------------------------------------------------- templates

export const listarTemplates = () =>
  apiFetch<SiteResposta<SiteTemplate[]>>(`${RAIZ}/templates`)

/** Envia o `.zip` (campo `arquivo`). Não ativa. 422 traz erros por arquivo e `avisos`. */
export function instalarTemplate(zip: File | Blob) {
  const form = new FormData()
  form.append('arquivo', zip)
  return apiFetch<{ data: SiteTemplateResumo; atualizado: boolean; avisos: string[] }>(
    `${RAIZ}/templates`, { method: 'POST', body: form, timeout: 120000 },
  )
}

export interface RespostaInstalacao {
  data: SiteTemplateResumo
  atualizado: boolean
  avisos: string[]
}

/**
 * Como `instalarTemplate`, mas com progresso do envio (0 a 1). `fetch` não informa o progresso de upload,
 * por isso aqui se usa `XMLHttpRequest`, com os mesmos cabeçalhos, CSRF e erros do `apiFetch`.
 */
export function instalarTemplateComProgresso(zip: File | Blob, aoProgredir: (fracao: number) => void): Promise<RespostaInstalacao> {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open('POST', `${BASE_URL}${RAIZ}/templates`)
    xhr.withCredentials = true
    xhr.timeout = 120000
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest')
    xhr.setRequestHeader('Accept', 'application/json')
    for (const [nome, valor] of Object.entries(cabecalhosDeTenant())) xhr.setRequestHeader(nome, valor)
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
    if (csrf) xhr.setRequestHeader('X-CSRF-TOKEN', csrf)

    xhr.upload.onprogress = (e) => { if (e.lengthComputable && e.total > 0) aoProgredir(e.loaded / e.total) }
    xhr.onerror = () => reject(new ApiError('Falha de conexão ao enviar o template.', 0))
    xhr.ontimeout = () => reject(new ApiError('Tempo de conexão esgotado. Verifique sua conexão e tente novamente.', 0))
    xhr.onload = () => {
      let corpo: any = null
      try { corpo = JSON.parse(xhr.responseText) } catch { /* resposta não é JSON */ }
      if (xhr.status >= 200 && xhr.status < 300 && corpo) {
        aoProgredir(1)
        resolve(corpo as RespostaInstalacao)
        return
      }
      reject(new ApiError(corpo?.messages?.error || corpo?.message || `HTTP ${xhr.status}`, xhr.status, corpo ?? undefined))
    }

    const form = new FormData()
    form.append('arquivo', zip)
    xhr.send(form)
  })
}

export const ativarTemplate = (slug: string) =>
  apiFetch<SiteResposta<SiteTemplateAtivacao>>(`${RAIZ}/templates/${encodeURIComponent(slug)}/ativar`, json('POST'))

export const paginasExemploTemplate = (slug: string) =>
  apiFetch<SiteResposta<{ paginas: string[]; paginas_ignoradas: string[]; erros: Record<string, string> }>>(
    `${RAIZ}/templates/${encodeURIComponent(slug)}/paginas-exemplo`, json('POST'))

/** O que as páginas de exemplo criariam (não grava nada). */
export const paginasExemploPrevia = (slug: string) =>
  apiFetch<SiteResposta<{ criar: Array<{ slug: string; titulo: string; secoes: number }>; ignoradas: string[] }>>(
    `${RAIZ}/templates/${encodeURIComponent(slug)}/paginas-exemplo`)

export const removerTemplate = (slug: string) =>
  apiFetch<{ slug: string; message: string }>(`${RAIZ}/templates/${encodeURIComponent(slug)}`, json('DELETE'))

/** Link de download do `.zip` (a sessão vai pelo cookie); `comConteudo` inclui o conteúdo atual do site. */
export const urlExportarTemplate = (slug: string, comConteudo = false) =>
  `${BASE_URL}${RAIZ}/templates/${encodeURIComponent(slug)}/exportar${comConteudo ? '?conteudo=1' : ''}`
