/**
 * Cliente da API de leitura da documentação interna (`api/v2/documentacao/*`).
 * Respostas `{ success, data }`; página inexistente ou invisível para o usuário
 * responde 404. Contrato: docs/api-documentacao.md (ordojuris).
 */
import { apiFetch } from './api'
import type {
  DocEntradaPagina, DocGravacao, DocImagemEnviada, DocItemEdicao, DocPagina, DocPaginaEdicao, DocPreview,
  DocResposta, DocResultadoBusca, DocSecao,
} from '../types/documentacao'

const RAIZ = 'documentacao'

/** `processos/cadastro` → `processos/cadastro`, com cada segmento codificado. */
const caminho = (slug: string) => slug.split('/').map(encodeURIComponent).join('/')

export const obterIndice = () =>
  apiFetch<DocResposta<DocSecao[]>>(RAIZ)

export const obterPagina = (slug: string) =>
  apiFetch<DocResposta<DocPagina>>(`${RAIZ}/paginas/${caminho(slug)}`)

export const buscar = (termo: string, signal?: AbortSignal) =>
  apiFetch<DocResposta<DocResultadoBusca[]>>(`${RAIZ}/busca?${new URLSearchParams({ q: termo })}`, signal ? { signal } : {})

/** Página de ajuda contextual de uma tela (`currentView`); 404 quando a tela não tem. */
export const obterPaginaDaTela = (tela: string) =>
  apiFetch<DocResposta<DocPagina>>(`${RAIZ}/tela/${encodeURIComponent(tela)}`)

// ---------------------------------------------------------------- edição (documentacao.editar)

const ADMIN = `${RAIZ}/admin`
const json = (method: string, body?: unknown) => ({ method, body: body as any })

export const listarParaEdicao = () =>
  apiFetch<{ data: DocItemEdicao[] }>(`${ADMIN}/paginas`)

export const obterParaEdicao = (id: number) =>
  apiFetch<{ data: DocPaginaEdicao }>(`${ADMIN}/paginas/${id}`)

export const criarPagina = (entrada: DocEntradaPagina) =>
  apiFetch<DocGravacao>(`${ADMIN}/paginas`, json('POST', entrada))

export const salvarPagina = (id: number, entrada: DocEntradaPagina) =>
  apiFetch<DocGravacao>(`${ADMIN}/paginas/${id}`, json('PUT', entrada))

export const publicarPagina = (id: number) =>
  apiFetch<{ data: DocPaginaEdicao }>(`${ADMIN}/paginas/${id}/publicar`, json('POST'))

export const despublicarPagina = (id: number) =>
  apiFetch<{ data: DocPaginaEdicao }>(`${ADMIN}/paginas/${id}/despublicar`, json('POST'))

export const excluirPagina = (id: number) =>
  apiFetch(`${ADMIN}/paginas/${id}`, json('DELETE'))

/** Cópia editável (rascunho) de uma página de arquivo. */
export const copiarDoArquivo = (slug: string) =>
  apiFetch<DocGravacao>(`${ADMIN}/paginas/de-arquivo/${caminho(slug)}`, json('POST'))

export const previsualizar = (markdown: string, slug: string, signal?: AbortSignal) =>
  apiFetch<{ data: DocPreview }>(`${ADMIN}/preview`, { ...json('POST', { markdown, slug }), ...(signal ? { signal } : {}) })

export function enviarImagem(arquivo: File, secao: string) {
  const corpo = new FormData()
  corpo.append('arquivo', arquivo)
  corpo.append('secao', secao)
  return apiFetch<{ data: DocImagemEnviada }>(`${ADMIN}/imagens`, { method: 'POST', body: corpo, timeout: 120000 })
}
