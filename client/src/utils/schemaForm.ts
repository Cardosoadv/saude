/**
 * Regras do formulário gerado pelo schema do template (`SchemaForm.vue`).
 * Tudo aqui é puro (sem Vue nem DOM) para ser testado em Node.
 *
 * Os dados de uma seção são um objeto `{ campo: valor }`; o valor de cada campo
 * segue o tipo do schema (ver docs/api-site-admin.md): string, int (id de mídia),
 * `{tipo, …}` (link), boolean, array de objetos (lista).
 */
import type { CampoSchema, CamposSchema, SiteLinkValor } from '../types/site'

export type Dados = Record<string, any>

/** Valor inicial de um campo, respeitando `padrao`. */
export function valorPadrao(campo: CampoSchema): unknown {
  if (campo.padrao !== undefined) return clonar(campo.padrao)

  switch (campo.tipo) {
    case 'bool': return false
    case 'imagem': return null
    case 'lista': return []
    case 'link': return { tipo: 'pagina', pagina_id: null } satisfies SiteLinkValor
    case 'dinamico': return undefined
    case 'opcao': return campo.opcoes?.[0] ?? ''
    default: return ''
  }
}

function clonar<T>(valor: T): T {
  return valor === undefined ? valor : JSON.parse(JSON.stringify(valor))
}

/** Dados completos: o que já existe, mais o padrão dos campos ausentes. Não altera a entrada. */
export function completarDados(campos: CamposSchema, dados: Dados | null | undefined): Dados {
  const base: Dados = { ...(dados ?? {}) }
  for (const [nome, campo] of Object.entries(campos)) {
    if (campo.tipo === 'dinamico') continue
    if (base[nome] === undefined) {
      base[nome] = valorPadrao(campo)
    } else if (campo.tipo === 'lista' && Array.isArray(base[nome]) && campo.itens) {
      base[nome] = (base[nome] as Dados[]).map((item) => completarDados(campo.itens!, item))
    }
  }
  return base
}

/** Um item novo para uma lista. */
export function novoItem(campo: CampoSchema): Dados {
  return completarDados(campo.itens ?? {}, {})
}

/**
 * Corpo a enviar à API: sem campos `dinamico` (não são gravados), inclusive dentro de listas.
 * A API substitui o conteúdo inteiro da seção, então todos os demais campos seguem.
 */
export function dadosParaEnvio(campos: CamposSchema, dados: Dados): Dados {
  const saida: Dados = {}
  for (const [nome, campo] of Object.entries(campos)) {
    if (campo.tipo === 'dinamico' || dados[nome] === undefined) continue
    saida[nome] = campo.tipo === 'lista' && campo.itens && Array.isArray(dados[nome])
      ? (dados[nome] as Dados[]).map((item) => dadosParaEnvio(campo.itens!, item))
      : dados[nome]
  }
  return saida
}

/** Cópia da lista com o item de `de` movido para `para`. Índices fora da lista não alteram nada. */
export function moverItem<T>(lista: readonly T[], de: number, para: number): T[] {
  const copia = [...lista]
  if (de === para || de < 0 || para < 0 || de >= copia.length || para >= copia.length) return copia
  const [item] = copia.splice(de, 1)
  copia.splice(para, 0, item)
  return copia
}

export function removerItem<T>(lista: readonly T[], indice: number): T[] {
  return lista.filter((_, i) => i !== indice)
}

/** Caminho de um campo, como a API o devolve nos erros: `botoes.1.rotulo`. */
export function caminhoDe(prefixo: string, ...partes: Array<string | number>): string {
  return [prefixo, ...partes].filter((p) => p !== '' && p !== undefined).join('.')
}

/**
 * Tira o prefixo (`dados.`) das chaves de erro de uma seção, mantendo só as que o têm.
 * `{ 'dados.botoes.1.rotulo': 'x' }` → `{ 'botoes.1.rotulo': 'x' }`.
 */
export function errosSemPrefixo(erros: Record<string, string>, prefixo: string): Record<string, string> {
  const saida: Record<string, string> = {}
  for (const [chave, texto] of Object.entries(erros)) {
    if (chave.startsWith(prefixo)) saida[chave.slice(prefixo.length)] = texto
  }
  return saida
}

/** Verdadeiro se existe erro em `caminho` ou dentro dele (ex.: a lista `botoes` quando `botoes.1.rotulo` falhou). */
export function temErroEm(erros: Record<string, string>, caminho: string): boolean {
  return Object.keys(erros).some((c) => c === caminho || c.startsWith(`${caminho}.`))
}

/** Erros que não casam com nenhum campo do schema (para mostrar num aviso geral). */
export function errosOrfaos(campos: CamposSchema, erros: Record<string, string>): Record<string, string> {
  const saida: Record<string, string> = {}
  for (const [caminho, texto] of Object.entries(erros)) {
    if (!caminhoExiste(campos, caminho.split('.'))) saida[caminho] = texto
  }
  return saida
}

function caminhoExiste(campos: CamposSchema, partes: string[]): boolean {
  const [nome, ...resto] = partes
  const campo = campos[nome]
  if (!campo) return false
  if (resto.length === 0) return true
  if (campo.tipo !== 'lista' || !campo.itens) return false
  // resto = [indice, campo, ...]
  return /^\d+$/.test(resto[0]) && (resto.length === 1 || caminhoExiste(campo.itens, resto.slice(1)))
}

/** Valor de `link` coerente com o tipo escolhido, descartando o resto do anterior. */
export function linkComTipo(tipo: SiteLinkValor['tipo']): SiteLinkValor {
  switch (tipo) {
    case 'ancora': return { tipo: 'ancora', ancora: '' }
    case 'url': return { tipo: 'url', url: '' }
    default: return { tipo: 'pagina', pagina_id: null }
  }
}

const RE_ICONE = /^bi-[a-z0-9-]+$/

export function iconeValido(valor: string): boolean {
  return RE_ICONE.test(valor)
}

/** Rótulo legível a partir do nome do campo no schema (`sobretitulo` → `Sobretitulo`, `link_texto` → `Link texto`). */
export function rotuloDoCampo(nome: string): string {
  const texto = nome.replace(/_/g, ' ').trim()
  return texto.charAt(0).toUpperCase() + texto.slice(1)
}

/** Nomes comuns de Bootstrap Icons oferecidos na busca do seletor (o campo aceita qualquer `bi-…` válido). */
export const ICONES_SUGERIDOS: readonly string[] = [
  'bi-award', 'bi-bank', 'bi-briefcase', 'bi-building', 'bi-buildings', 'bi-calendar-check', 'bi-calculator',
  'bi-chat-dots', 'bi-check-circle', 'bi-clipboard-check', 'bi-clock', 'bi-cash-coin', 'bi-cash-stack',
  'bi-currency-dollar', 'bi-diagram-3', 'bi-envelope', 'bi-file-earmark-text', 'bi-file-text', 'bi-geo-alt',
  'bi-globe', 'bi-graph-up', 'bi-handshake', 'bi-heart', 'bi-house', 'bi-house-door', 'bi-journal-text',
  'bi-key', 'bi-laptop', 'bi-lightbulb', 'bi-lock', 'bi-mortarboard', 'bi-people', 'bi-person',
  'bi-person-badge', 'bi-person-check', 'bi-person-workspace', 'bi-phone', 'bi-pie-chart', 'bi-receipt',
  'bi-scale', 'bi-shield-check', 'bi-shield-lock', 'bi-star', 'bi-telephone', 'bi-trophy', 'bi-whatsapp',
  'bi-hammer', 'bi-book', 'bi-bookmark', 'bi-box-seam', 'bi-card-checklist', 'bi-card-text', 'bi-cart',
  'bi-compass', 'bi-eye', 'bi-flag', 'bi-folder', 'bi-gem', 'bi-gear', 'bi-hand-thumbs-up', 'bi-hourglass-split',
  'bi-info-circle', 'bi-link-45deg', 'bi-list-check', 'bi-megaphone', 'bi-patch-check', 'bi-pencil-square',
  'bi-search', 'bi-send', 'bi-speedometer2', 'bi-stars', 'bi-tag', 'bi-tools', 'bi-truck', 'bi-wallet2',
]

/** Sugestões que contêm o termo (sem `bi-` e sem diferenciar maiúsculas); até `limite`. */
export function buscarIcones(termo: string, limite = 24): string[] {
  const t = termo.trim().toLowerCase().replace(/^bi-/, '')
  const achados = t === '' ? ICONES_SUGERIDOS : ICONES_SUGERIDOS.filter((i) => i.slice(3).includes(t))
  return achados.slice(0, limite)
}
