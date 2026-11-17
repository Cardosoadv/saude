/**
 * Regras do editor de página (lista de seções, rascunhos por seção, salvamento).
 * Puro (sem Vue nem DOM) para testar em Node.
 */
import type { CamposSchema, SiteSecao, TemplateSchema } from '../types/site'
import { completarDados, dadosParaEnvio, type Dados } from './schemaForm'

/** Seção em edição: o que o servidor tem (`original`) e o rascunho do usuário. */
export interface ItemSecao {
  /** `id` da seção, ou `novo-N` enquanto ela ainda não foi criada no servidor. */
  chave: string
  id: number | null
  tipo: string
  /** `false` quando o template ativo não tem o tipo (a seção é preservada, mas não editável). */
  compativel: boolean
  visivel: boolean
  ancora: string
  dados: Dados
  original: { dados: Dados; ancora: string } | null
}

const RE_ANCORA = /^[a-z0-9_-]+$/

export const ehNova = (i: Pick<ItemSecao, 'id'>): boolean => i.id === null

export function itemDoServidor(secao: SiteSecao, campos: CamposSchema | null): ItemSecao {
  const dados = campos ? completarDados(campos, secao.dados) : { ...(secao.dados ?? {}) }
  return {
    chave: String(secao.id),
    id: secao.id,
    tipo: secao.tipo,
    compativel: campos !== null && secao.compativel !== false,
    visivel: !!Number(secao.visivel),
    ancora: secao.ancora ?? '',
    dados,
    original: { dados: JSON.parse(JSON.stringify(dados)), ancora: secao.ancora ?? '' },
  }
}

export function itemNovo(numero: number, tipo: string, campos: CamposSchema): ItemSecao {
  return { chave: `novo-${numero}`, id: null, tipo, compativel: true, visivel: true, ancora: '', dados: completarDados(campos, {}), original: null }
}

/** O rascunho difere do servidor? Seção nova sempre conta como alteração pendente. */
export function alterada(item: ItemSecao, campos: CamposSchema | null): boolean {
  if (!item.compativel) return false
  if (item.original === null) return true
  if (item.ancora.trim() !== item.original.ancora) return true
  const atual = campos ? dadosParaEnvio(campos, item.dados) : item.dados
  const base = campos ? dadosParaEnvio(campos, item.original.dados) : item.original.dados
  return JSON.stringify(atual) !== JSON.stringify(base)
}

/** Âncora: minúsculas, números, hífen e sublinhado (vazio = sem âncora). */
export function erroDeAncora(ancora: string): string | null {
  const a = ancora.trim()
  return a === '' || RE_ANCORA.test(a) ? null : 'Use apenas letras minúsculas, números, hífen e sublinhado.'
}

/** Corpo do `PUT secoes/{id}` / `POST secoes`: dados sem campos dinâmicos e âncora (vazia = nenhuma). */
export function corpoDaSecao(item: ItemSecao, campos: CamposSchema): { dados: Dados; ancora: string | null } {
  return { dados: dadosParaEnvio(campos, item.dados), ancora: item.ancora.trim() === '' ? null : item.ancora.trim() }
}

const TIPOS_DE_TEXTO = new Set(['texto', 'texto_inline', 'textarea', 'rich_text'])

const limpar = (texto: string) => texto.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim()
const encurtar = (texto: string, limite: number) => (texto.length > limite ? texto.slice(0, limite - 1).trimEnd() + '…' : texto)

function primeiroTexto(dados: Dados, campos: CamposSchema | null): string {
  const nomes = campos ? Object.keys(campos) : Object.keys(dados)
  for (const nome of nomes) {
    const tipo = campos?.[nome]?.tipo
    const valor = dados[nome]
    if (typeof valor === 'string' && (!tipo || TIPOS_DE_TEXTO.has(tipo))) {
      const limpo = limpar(valor)
      if (limpo) return limpo
    }
  }
  // Sem texto próprio (ex.: só listas): usa o primeiro texto dos itens.
  for (const nome of nomes) {
    const valor = dados[nome]
    const itens = campos?.[nome]?.itens
    if (Array.isArray(valor) && (!campos || campos[nome]?.tipo === 'lista')) {
      for (const item of valor) {
        const achado = item && typeof item === 'object' ? primeiroTexto(item as Dados, itens ?? null) : ''
        if (achado) return achado
      }
    }
  }
  return ''
}

/** Texto curto que identifica a seção na lista: o primeiro texto preenchido, sem HTML. */
export function resumoDaSecao(dados: Dados, campos: CamposSchema | null, limite = 80): string {
  const texto = primeiroTexto(dados, campos)
  return texto ? encurtar(texto, limite) : 'Sem conteúdo'
}

const ICONES: Record<string, string> = {
  hero: 'bi-window', hero_area: 'bi-window', pilares: 'bi-columns-gap', cards_areas: 'bi-grid-3x2-gap',
  ultimas_noticias: 'bi-newspaper', contato: 'bi-telephone', intro_area: 'bi-text-paragraph',
  lista_destaque: 'bi-list-stars', lista_simples: 'bi-list-ul', cta_box: 'bi-megaphone', rich_text: 'bi-file-richtext',
  lateral_outras_areas: 'bi-layout-sidebar-inset',
}
export const iconeDoTipo = (tipo: string): string => ICONES[tipo] ?? 'bi-layout-text-window'

export function rotuloDoTipo(schema: TemplateSchema | null, tipo: string): string {
  return schema?.secoes?.[tipo]?.rotulo ?? tipo
}

/** Ids das seções já salvas, na ordem da lista (o que o `PUT ordem` espera). */
export const idsEmOrdem = (itens: ItemSecao[]): number[] => itens.filter((i) => i.id !== null).map((i) => i.id as number)

/** Ordem de ids com `idCopia` logo depois de `idOriginal`. */
export function ordemComCopiaApos(ids: number[], idOriginal: number, idCopia: number): number[] {
  const semCopia = ids.filter((i) => i !== idCopia)
  const pos = semCopia.indexOf(idOriginal)
  return pos < 0 ? semCopia.concat(idCopia) : [...semCopia.slice(0, pos + 1), idCopia, ...semCopia.slice(pos + 1)]
}

/** Erros de campo da seção (prefixo `dados.`) separados do erro da âncora e dos demais. */
export function separarErrosDaSecao(erros: Record<string, string>): { campos: Record<string, string>; ancora?: string; outros: string[] } {
  const campos: Record<string, string> = {}
  const outros: string[] = []
  let ancora: string | undefined
  for (const [chave, texto] of Object.entries(erros)) {
    if (chave.startsWith('dados.')) campos[chave.slice(6)] = texto
    else if (chave === 'ancora') ancora = texto
    else outros.push(texto)
  }
  return { campos, ancora, outros }
}

export type Dispositivo = 'desktop' | 'celular'
export const LARGURA_PREVIA: Record<Dispositivo, string> = { desktop: '100%', celular: '390px' }
