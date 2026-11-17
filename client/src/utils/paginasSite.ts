/**
 * Regras da lista e do formulário de páginas do site. Puro (sem Vue nem DOM) para testar em Node.
 * O backend valida tudo de novo (slug único, layout do template, mídia existente etc.).
 */
import type { SitePagina, SitePaginaEntrada } from '../types/site'

export const CHANGEFREQ = [
  { valor: 'always', rotulo: 'Sempre' },
  { valor: 'hourly', rotulo: 'A cada hora' },
  { valor: 'daily', rotulo: 'Diária' },
  { valor: 'weekly', rotulo: 'Semanal' },
  { valor: 'monthly', rotulo: 'Mensal' },
  { valor: 'yearly', rotulo: 'Anual' },
  { valor: 'never', rotulo: 'Nunca' },
] as const

export const TIPOS_CRIAVEIS = [
  { valor: 'pagina', rotulo: 'Página comum' },
  { valor: 'area', rotulo: 'Área de atuação' },
] as const

export const ROTULO_TIPO: Record<string, string> = {
  home: 'Página inicial',
  pagina: 'Página',
  area: 'Área de atuação',
  sistema: 'Sistema',
}

export const ROTULO_STATUS: Record<string, string> = { rascunho: 'Rascunho', publicado: 'Publicado' }

const RE_SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/

/** `Direito de Família & Sucessões` → `direito-de-familia-sucessoes` (até 120 caracteres). */
export function gerarSlug(titulo: string): string {
  return titulo
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 120)
    .replace(/-+$/g, '')
}

export const slugValido = (slug: string): boolean => slug.length <= 120 && RE_SLUG.test(slug)

/** Caminho público (`/` para a home, `/pagina/slug` nas demais), a partir da URL absoluta da API. */
export function caminhoPublico(pagina: Pick<SitePagina, 'url' | 'tipo' | 'slug'>): string {
  if (pagina.tipo === 'home') return '/'
  try {
    const { pathname } = new URL(pagina.url)
    const i = pathname.indexOf('/pagina/')
    if (i >= 0) return pathname.slice(i)
    const sistema = pathname.match(/\/(noticias|area-cliente|painel-cliente)\/?$/)
    return sistema ? `/${sistema[1]}` : pathname
  } catch {
    return `/pagina/${pagina.slug}`
  }
}

/** Páginas que não podem ser excluídas. */
export const podeExcluir = (p: Pick<SitePagina, 'tipo'>): boolean => p.tipo !== 'home' && p.tipo !== 'sistema'
/** Páginas de sistema não têm rascunho e não se duplicam; a home só se edita. */
export const podeDuplicar = (p: Pick<SitePagina, 'tipo'>): boolean => p.tipo !== 'sistema'
export const podePublicar = (p: Pick<SitePagina, 'tipo'>): boolean => p.tipo !== 'sistema' && p.tipo !== 'home'

export interface FormPagina {
  titulo: string
  slug: string
  tipo: 'pagina' | 'area'
  layout: string
  meta_title: string
  meta_description: string
  og_image_id: number | null
  exibir_sitemap: boolean
  prioridade_sitemap: number
  changefreq: string
}

export const formVazio = (): FormPagina => ({
  titulo: '', slug: '', tipo: 'pagina', layout: '', meta_title: '', meta_description: '',
  og_image_id: null, exibir_sitemap: true, prioridade_sitemap: 0.8, changefreq: 'monthly',
})

export function formDaPagina(p: SitePagina): FormPagina {
  return {
    titulo: p.titulo,
    slug: p.slug,
    tipo: p.tipo === 'area' ? 'area' : 'pagina',
    layout: p.layout ?? '',
    meta_title: p.meta_title ?? '',
    meta_description: p.meta_description ?? '',
    og_image_id: p.og_image_id === null || p.og_image_id === undefined ? null : Number(p.og_image_id),
    exibir_sitemap: !!Number(p.exibir_sitemap),
    prioridade_sitemap: Number(p.prioridade_sitemap ?? 0.8),
    changefreq: p.changefreq || 'monthly',
  }
}

export function validarForm(form: FormPagina): Record<string, string> {
  const erros: Record<string, string> = {}
  if (!form.titulo.trim()) erros.titulo = 'Informe o título da página.'
  if (!form.slug) erros.slug = 'Informe o endereço da página.'
  else if (!slugValido(form.slug)) erros.slug = 'Use apenas letras minúsculas, números e hífen (sem hífen no começo ou no fim).'
  if (form.meta_description.length > 160) erros.meta_description = 'Máximo de 160 caracteres.'
  if (form.meta_title.length > 255) erros.meta_title = 'Máximo de 255 caracteres.'
  const p = Number(form.prioridade_sitemap)
  if (Number.isNaN(p) || p < 0 || p > 1) erros.prioridade_sitemap = 'A prioridade vai de 0 a 1.'
  return erros
}

/** Corpo da criação: campos vazios opcionais não vão. */
export function corpoCriacao(form: FormPagina): SitePaginaEntrada {
  const corpo: SitePaginaEntrada = {
    titulo: form.titulo.trim(), slug: form.slug, tipo: form.tipo,
    exibir_sitemap: form.exibir_sitemap as any, prioridade_sitemap: Number(form.prioridade_sitemap), changefreq: form.changefreq,
  }
  if (form.layout) corpo.layout = form.layout
  if (form.meta_title.trim()) corpo.meta_title = form.meta_title.trim()
  if (form.meta_description.trim()) corpo.meta_description = form.meta_description.trim()
  if (form.og_image_id) corpo.og_image_id = form.og_image_id
  return corpo
}

/** Corpo da edição: só o que mudou. Campos de SEO limpos vão vazios (a API grava nulo). */
export function corpoEdicao(original: FormPagina, atual: FormPagina): SitePaginaEntrada {
  const corpo: Record<string, unknown> = {}
  const texto = (k: 'titulo' | 'slug' | 'layout' | 'meta_title' | 'meta_description' | 'changefreq') => {
    const novo = atual[k].trim()
    if (novo !== original[k].trim()) corpo[k] = novo
  }
  ;(['titulo', 'slug', 'layout', 'meta_title', 'meta_description', 'changefreq'] as const).forEach(texto)
  if (atual.og_image_id !== original.og_image_id) corpo.og_image_id = atual.og_image_id
  if (atual.exibir_sitemap !== original.exibir_sitemap) corpo.exibir_sitemap = atual.exibir_sitemap
  if (Number(atual.prioridade_sitemap) !== Number(original.prioridade_sitemap)) corpo.prioridade_sitemap = Number(atual.prioridade_sitemap)
  return corpo as SitePaginaEntrada
}

/** Aviso antes de salvar quando o slug de uma página publicada muda (a API devolve o mesmo aviso). */
export function avisoMudancaDeSlug(pagina: Pick<SitePagina, 'status' | 'slug'>, novoSlug: string): string | null {
  if (pagina.status !== 'publicado' || novoSlug === pagina.slug) return null
  return `O endereço antigo (/pagina/${pagina.slug}) deixará de funcionar; não há redirecionamento. Links e buscas que apontam para ele darão erro 404.`
}

/** Erros de publicação (`secoes.<id>.<campo>`) agrupados por seção, para listar ao usuário. */
export function errosDePublicacao(erros: Record<string, string>): Array<{ secaoId: number; mensagens: string[] }> {
  const porSecao = new Map<number, string[]>()
  for (const [caminho, mensagem] of Object.entries(erros)) {
    const m = caminho.match(/^secoes\.(\d+)\.(.+)$/)
    if (!m) continue
    const id = Number(m[1])
    porSecao.set(id, [...(porSecao.get(id) ?? []), `${m[2]}: ${mensagem}`])
  }
  return [...porSecao.entries()].map(([secaoId, mensagens]) => ({ secaoId, mensagens }))
}
