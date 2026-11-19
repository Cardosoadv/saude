/**
 * Tipos do editor do site público (API `api/v2/site/admin/*`).
 * Contrato: docs/api-site-admin.md.
 */

export type SitePaginaTipo = 'home' | 'pagina' | 'area' | 'sistema'
export type SitePaginaStatus = 'rascunho' | 'publicado'

export interface SitePagina {
  id: number
  slug: string
  titulo: string
  tipo: SitePaginaTipo
  layout: string
  meta_title: string | null
  meta_description: string | null
  og_image_id: number | null
  status: SitePaginaStatus
  publicado_em: string | null
  ordem: number
  /** 0/1 vindos do banco. */
  exibir_sitemap: number | boolean
  prioridade_sitemap: number
  changefreq: string
  url: string
  created_at?: string
  updated_at?: string
  /** Só na listagem. */
  total_secoes?: number
  /** Só no detalhe. */
  secoes?: SiteSecao[]
}

export type SitePaginaEntrada = Partial<Pick<SitePagina,
  'slug' | 'titulo' | 'layout' | 'meta_title' | 'meta_description' | 'og_image_id'
  | 'exibir_sitemap' | 'prioridade_sitemap' | 'changefreq' | 'ordem'>> & { tipo?: 'pagina' | 'area' }

export interface SiteSecao {
  id: number
  pagina_id: number
  tipo: string
  ancora: string | null
  ordem: number
  visivel: number | boolean
  dados: Record<string, any>
  /** Só no detalhe da página: `false` quando o template ativo não tem o tipo. */
  compativel?: boolean
}

export interface SiteSecaoEntrada {
  tipo?: string
  dados?: Record<string, any>
  ancora?: string | null
  visivel?: boolean
}

export type SiteMenuItemTipo = 'pagina' | 'url' | 'ancora'

export interface SiteMenuItem {
  id?: number
  rotulo: string
  tipo: SiteMenuItemTipo
  pagina_id?: number | null
  url?: string | null
  ancora?: string | null
  destaque?: boolean
  filhos?: SiteMenuItem[]
}

export type SiteMenus = Record<string, SiteMenuItem[]>

export interface SiteMidia {
  id: number
  arquivo: string
  nome_original: string
  mime: string
  tamanho: number
  largura: number
  altura: number
  alt: string | null
  url: string
  miniatura_url: string
}

export interface SiteMidiaPager {
  page: number
  per_page: number
  total: number
  last_page: number
}

export type SiteMidiaUso =
  | { tipo: 'configuracao'; chave: string }
  | { tipo: 'pagina'; pagina_id: number; titulo: string }
  | { tipo: 'secao'; secao_id: number; secao_tipo: string; pagina_id: number; titulo: string }

export type SiteConfiguracaoTipo = 'string' | 'text' | 'json' | 'imagem' | 'cor' | 'bool'

export interface SiteConfiguracao {
  chave: string
  tipo: SiteConfiguracaoTipo
  grupo: string
  valor: any
}

export type SiteConfiguracoes = Record<string, SiteConfiguracao[]>

export interface SiteTemplate {
  slug: string
  nome: string
  versao: string
  autor: string | null
  descricao: string | null
  origem: 'core' | 'upload' | 'cli'
  ativo: boolean
  motor: 'php' | 'twig'
  removivel: boolean
  preview_url: string | null
}

export interface SiteTemplateResumo {
  slug: string
  nome: string
  versao: string
  origem: string
  ativo: boolean
}

export interface SiteTemplateAtivacao {
  template: string
  ativado: boolean
  secoes: Array<{
    id: number
    tipo: string
    pagina: string
    pagina_id: number
    acao: 'incompativel' | 'revisar'
    erros: Record<string, string> | never[]
  }>
  avisos: string[]
  seed: unknown | null
}

export interface SiteTema {
  cores: Record<string, { padrao: string; atual: string; personalizado: boolean }>
  fontes: Record<string, { padrao: string; atual: string; personalizado: boolean }>
  /** família → categoria (serif/sans). */
  fontes_disponiveis: Record<string, string>
}

export type CampoTipo =
  | 'texto' | 'texto_inline' | 'textarea' | 'rich_text' | 'imagem' | 'icone'
  | 'link' | 'opcao' | 'bool' | 'lista' | 'referencia_menu' | 'dinamico'

export interface CampoSchema {
  tipo: CampoTipo
  obrigatorio?: boolean
  max?: number
  padrao?: unknown
  /** Tipo `opcao`. */
  opcoes?: string[]
  /** Tipo `lista`: campos de cada item. */
  itens?: Record<string, CampoSchema>
  /** Tipo `dinamico`: `ultimas_noticias` ou `areas`. */
  fonte?: string
}

export type CamposSchema = Record<string, CampoSchema>

export interface SecaoSchema {
  rotulo: string
  campos: CamposSchema
}

export interface TemplateSchema {
  template: { slug: string; nome: string; versao: string }
  layouts: string[]
  menus: string[]
  secoes: Record<string, SecaoSchema>
  /** chave → texto padrão. */
  textos: Record<string, string>
  tema: { cores: Record<string, string>; fontes: Record<string, string> }
}

export type SiteLinkValor =
  | { tipo: 'pagina'; pagina_id: number | null }
  | { tipo: 'ancora'; ancora: string }
  | { tipo: 'url'; url: string }

/** Resposta padrão da API do site. */
export interface SiteResposta<T> {
  data: T
  avisos?: string[]
}

/** Categoria das notícias do site (campos com o nome das colunas, como a API de notícias). */
export interface SiteNoticiaCategoria {
  id: number
  name: string
  slug: string
  meta_description: string | null
  /** Notícias não excluídas nesta categoria; com alguma, a exclusão é recusada. */
  total_noticias: number
  created_at: string | null
  updated_at: string | null
}

export interface SiteNoticiaCategoriaEntrada {
  name?: string
  /** Vazio: gerado a partir do nome. */
  slug?: string
  meta_description?: string | null
}

/** Notícia do site (colunas de `site_news`). */
export interface SiteNoticia {
  id: number
  title: string
  slug: string
  status: 'draft' | 'published'
  category_id: number
  category_name?: string | null
  summary?: string | null
  content?: string | null
  meta_title?: string | null
  meta_description?: string | null
  published_at: string | null
}

export interface SiteNoticiaEntrada {
  title?: string
  category_id?: number | string
  status?: 'draft' | 'published'
  summary?: string
  content?: string
  meta_title?: string
  meta_description?: string
  /** Formato `YYYY-MM-DDTHH:mm`. */
  published_at?: string
}

export interface SiteNoticiaLista {
  data: SiteNoticia[]
  total: number
  per_page: number
  current_page: number
  last_page: number
}
