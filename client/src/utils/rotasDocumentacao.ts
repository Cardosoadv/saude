/**
 * Hash da documentação: `#/documentacao`, `#/documentacao/{slug}` e
 * `#/documentacao/{slug}#{ancora}` (dois `#`: o segundo separa a âncora).
 * É o formato dos links que o backend gera dentro das páginas.
 */

export const PREFIXO = '#/documentacao'

export interface RotaDocumentacao {
  slug: string | null
  ancora: string | null
}

export function ehHashDaDocumentacao(hash: string): boolean {
  return hash === PREFIXO || hash.startsWith(`${PREFIXO}/`) || hash.startsWith(`${PREFIXO}#`)
}

/** Lê o hash; `null` quando não é da documentação. */
export function lerHashDaDocumentacao(hash: string): RotaDocumentacao | null {
  if (!ehHashDaDocumentacao(hash)) return null

  const resto = hash.slice(PREFIXO.length).replace(/^\//, '')
  const corte = resto.indexOf('#')
  const slug = decodificar(corte === -1 ? resto : resto.slice(0, corte)).replace(/\/+$/, '')
  const ancora = corte === -1 ? '' : decodificar(resto.slice(corte + 1))

  return { slug: slug || null, ancora: ancora || null }
}

export function hashDaDocumentacao(slug?: string | null, ancora?: string | null): string {
  let hash = PREFIXO
  if (slug) hash += `/${slug.split('/').map(encodeURIComponent).join('/')}`
  if (slug && ancora) hash += `#${encodeURIComponent(ancora)}`
  return hash
}

function decodificar(texto: string): string {
  try {
    return decodeURIComponent(texto)
  } catch {
    return texto
  }
}

// ---------------------------------------------------------------- edição

/** Prefixo próprio (e não `#/documentacao/admin`), para não colidir com o slug de uma página. */
export const PREFIXO_EDICAO = '#/documentacao-admin'

export type RotaEdicao = { tela: 'lista' } | { tela: 'editor'; id: number | null }

/** `#/documentacao-admin` (lista), `#/documentacao-admin/nova` e `#/documentacao-admin/{id}` (editor). */
export function lerHashDaEdicao(hash: string): RotaEdicao | null {
  if (hash === PREFIXO_EDICAO || hash === `${PREFIXO_EDICAO}/`) return { tela: 'lista' }
  if (!hash.startsWith(`${PREFIXO_EDICAO}/`)) return null
  const resto = hash.slice(PREFIXO_EDICAO.length + 1)
  if (resto === 'nova') return { tela: 'editor', id: null }
  return /^\d+$/.test(resto) ? { tela: 'editor', id: Number(resto) } : { tela: 'lista' }
}

export function hashDaEdicao(rota: RotaEdicao): string {
  if (rota.tela === 'lista') return PREFIXO_EDICAO
  return `${PREFIXO_EDICAO}/${rota.id ?? 'nova'}`
}
