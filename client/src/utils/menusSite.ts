/**
 * Regras da tela de menus do site. Puro (sem Vue nem DOM) para testar em Node.
 * A árvore tem um nível de submenu e até 50 itens por menu (limites do backend).
 */
import type { SiteMenuItem, SiteMenuItemTipo } from '../types/site'

export const MAX_ITENS = 50

export interface ItemMenu {
  /** Identificador local, estável durante a edição (não vai para a API). */
  uid: string
  rotulo: string
  tipo: SiteMenuItemTipo
  pagina_id: number | null
  url: string
  ancora: string
  destaque: boolean
  filhos: ItemMenu[]
}

export const ROTULO_LOCAL: Record<string, string> = {
  cabecalho: 'Cabeçalho',
  rodape: 'Rodapé',
  lateral_areas: 'Lateral (outras áreas)',
}
export const rotuloDoLocal = (local: string): string =>
  ROTULO_LOCAL[local] ?? local.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase())

let contador = 0
export const novoUid = (): string => `m${++contador}`

export function itemVazio(tipo: SiteMenuItemTipo = 'pagina'): ItemMenu {
  return { uid: novoUid(), rotulo: '', tipo, pagina_id: null, url: '', ancora: '', destaque: false, filhos: [] }
}

/** Árvore da API → árvore de edição. */
export function arvoreDaApi(itens: SiteMenuItem[] | undefined): ItemMenu[] {
  return (itens ?? []).map((i) => ({
    uid: novoUid(),
    rotulo: i.rotulo ?? '',
    tipo: i.tipo,
    pagina_id: i.pagina_id === null || i.pagina_id === undefined ? null : Number(i.pagina_id),
    url: i.url ?? '',
    ancora: i.ancora ?? '',
    destaque: !!i.destaque,
    filhos: arvoreDaApi(i.filhos),
  }))
}

/** Árvore de edição → corpo do `PUT menus/{local}` (só o destino do tipo escolhido). */
export function arvoreParaEnvio(itens: ItemMenu[]): SiteMenuItem[] {
  return itens.map((i) => {
    const base: SiteMenuItem = { rotulo: i.rotulo.trim(), tipo: i.tipo, destaque: i.destaque }
    if (i.tipo === 'pagina') base.pagina_id = i.pagina_id
    else if (i.tipo === 'url') base.url = i.url.trim()
    else base.ancora = i.ancora.trim()
    if (i.filhos.length) base.filhos = arvoreParaEnvio(i.filhos)
    return base
  })
}

export const alterada = (inicial: ItemMenu[], atual: ItemMenu[]): boolean =>
  JSON.stringify(arvoreParaEnvio(inicial)) !== JSON.stringify(arvoreParaEnvio(atual))

export function contarItens(itens: ItemMenu[]): number {
  return itens.reduce((n, i) => n + 1 + i.filhos.length, 0)
}

// ---- edição da árvore (sempre devolve cópia)

/** Move o item de `de` para `para` dentro da lista `pai` (índice do item pai, ou `null` para o primeiro nível). */
export function moverNaLista(itens: ItemMenu[], pai: number | null, de: number, para: number): ItemMenu[] {
  const mover = (lista: ItemMenu[]) => {
    if (de === para || de < 0 || para < 0 || de >= lista.length || para >= lista.length) return lista
    const copia = [...lista]
    const [m] = copia.splice(de, 1)
    copia.splice(para, 0, m)
    return copia
  }
  return pai === null ? mover(itens) : itens.map((i, idx) => (idx === pai ? { ...i, filhos: mover(i.filhos) } : i))
}

/** Torna o item do primeiro nível um subitem do item anterior. Só vale se ele não tem filhos e há um item antes. */
export function tornarSubitem(itens: ItemMenu[], indice: number): ItemMenu[] {
  const item = itens[indice]
  if (!item || indice === 0 || item.filhos.length) return itens
  return itens
    .map((i, idx) => (idx === indice - 1 ? { ...i, filhos: [...i.filhos, item] } : i))
    .filter((_, idx) => idx !== indice)
}

/** Tira um subitem do submenu, colocando-o logo depois do item pai. */
export function tirarDoSubmenu(itens: ItemMenu[], pai: number, indice: number): ItemMenu[] {
  const dono = itens[pai]
  const filho = dono?.filhos[indice]
  if (!filho) return itens
  const semFilho = itens.map((i, idx) => (idx === pai ? { ...i, filhos: i.filhos.filter((_, j) => j !== indice) } : i))
  return [...semFilho.slice(0, pai + 1), filho, ...semFilho.slice(pai + 1)]
}

export function removerItem(itens: ItemMenu[], pai: number | null, indice: number): ItemMenu[] {
  return pai === null
    ? itens.filter((_, i) => i !== indice)
    : itens.map((i, idx) => (idx === pai ? { ...i, filhos: i.filhos.filter((_, j) => j !== indice) } : i))
}

export function atualizarItem(itens: ItemMenu[], pai: number | null, indice: number, novo: ItemMenu): ItemMenu[] {
  return pai === null
    ? itens.map((i, idx) => (idx === indice ? { ...novo, filhos: i.filhos } : i))
    : itens.map((i, idx) => (idx === pai ? { ...i, filhos: i.filhos.map((f, j) => (j === indice ? novo : f)) } : i))
}

// ---- validação e avisos

const RE_ANCORA = /^[a-z0-9_-]+$/
const RE_URL = /^(https?:\/\/\S+|mailto:\S+|tel:\S+|\/\S*)$/i

/** Erro local de um item, por campo (`rotulo`, `pagina_id`, `url`, `ancora`). */
export function errosDoItem(i: ItemMenu): Record<string, string> {
  const erros: Record<string, string> = {}
  if (!i.rotulo.trim()) erros.rotulo = 'Informe o rótulo (até 100 caracteres).'
  else if (i.rotulo.trim().length > 100) erros.rotulo = 'Máximo de 100 caracteres.'
  if (i.tipo === 'pagina' && i.pagina_id === null) erros.pagina_id = 'Escolha a página de destino.'
  if (i.tipo === 'url' && !RE_URL.test(i.url.trim())) erros.url = 'URL inválida (use http, https, mailto, tel ou um caminho iniciado por /).'
  if (i.tipo === 'ancora' && !RE_ANCORA.test(i.ancora.trim())) erros.ancora = 'Âncora inválida (letras minúsculas, números, hífen e sublinhado).'
  return erros
}

/** Erros de toda a árvore, com os mesmos caminhos da API (`0.rotulo`, `1.filhos.0.url`; sem o prefixo `itens.`). */
export function errosDaArvore(itens: ItemMenu[]): Record<string, string> {
  const saida: Record<string, string> = {}
  itens.forEach((i, idx) => {
    for (const [c, m] of Object.entries(errosDoItem(i))) saida[`${idx}.${c}`] = m
    i.filhos.forEach((f, j) => {
      for (const [c, m] of Object.entries(errosDoItem(f))) saida[`${idx}.filhos.${j}.${c}`] = m
    })
  })
  return saida
}

export interface PaginaResumo { id: number; titulo: string; status: string; tipo?: string }

/** Aviso sobre o destino de um item de página: rascunho (não aparece no site) ou inexistente. */
export function avisoDoDestino(i: Pick<ItemMenu, 'tipo' | 'pagina_id'>, paginas: PaginaResumo[]): string | null {
  if (i.tipo !== 'pagina' || i.pagina_id === null) return null
  const pagina = paginas.find((p) => p.id === i.pagina_id)
  if (!pagina) return 'A página de destino não existe mais (foi excluída). O link ficará quebrado.'
  if (pagina.status !== 'publicado') return `A página "${pagina.titulo}" está em rascunho: o link só funcionará depois de publicá-la.`
  return null
}

/** Todos os avisos de destino da árvore, com o rótulo do item. */
export function avisosDaArvore(itens: ItemMenu[], paginas: PaginaResumo[]): string[] {
  const avisos: string[] = []
  const ver = (i: ItemMenu) => {
    const a = avisoDoDestino(i, paginas)
    if (a) avisos.push(`"${i.rotulo.trim() || 'Sem rótulo'}": ${a}`)
  }
  itens.forEach((i) => { ver(i); i.filhos.forEach(ver) })
  return avisos
}

/** Erros da API (`itens.0.filhos.1.url`) → caminhos da árvore (`0.filhos.1.url`); o que não casa vai em `outros`. */
export function errosDaApi(erros: Record<string, string>): { campos: Record<string, string>; outros: string[] } {
  const campos: Record<string, string> = {}
  const outros: string[] = []
  for (const [c, m] of Object.entries(erros)) {
    if (c.startsWith('itens.')) campos[c.slice(6)] = m
    else outros.push(m)
  }
  return { campos, outros }
}
