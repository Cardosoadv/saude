/**
 * Regras da tela de aparência (cores e fontes do template). Puro (sem Vue nem DOM) para testar em Node.
 * O backend valida de novo: cor hexadecimal (#RGB/#RRGGBB) e fonte da lista fechada.
 */
import type { SiteTema } from '../types/site'

const RE_HEX = /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/

export const hexValido = (v: string): boolean => RE_HEX.test(v.trim())

/** `#abc` → `#aabbcc` (o `<input type="color">` só aceita 6 dígitos). */
export function hexCompleto(v: string): string {
  const t = v.trim().toLowerCase()
  return /^#[0-9a-f]{3}$/.test(t) ? '#' + t.slice(1).split('').map((c) => c + c).join('') : t
}

export const mesmaCor = (a: string, b: string): boolean => hexCompleto(a) === hexCompleto(b)

function canal(c: number): number {
  const s = c / 255
  return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
}

/** Luminância relativa (WCAG 2.x) de uma cor hexadecimal válida. */
export function luminancia(hex: string): number {
  const h = hexCompleto(hex).slice(1)
  const [r, g, b] = [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16))
  return 0.2126 * canal(r) + 0.7152 * canal(g) + 0.0722 * canal(b)
}

/** Razão de contraste entre duas cores (1 a 21). */
export function contraste(a: string, b: string): number {
  const [la, lb] = [luminancia(a), luminancia(b)]
  const [claro, escuro] = la >= lb ? [la, lb] : [lb, la]
  return (claro + 0.05) / (escuro + 0.05)
}

export const MINIMO_TEXTO = 4.5 // WCAG AA, texto normal
export const MINIMO_DESTAQUE = 3 // WCAG AA, texto grande e componentes

/** Pares texto/fundo conferidos quando os dois tokens existem no template (convenção dos templates do sistema). */
export const PARES_CONTRASTE: ReadonlyArray<{ texto: string; fundo: string; minimo: number }> = [
  { texto: 'text-primary', fundo: 'navy-dark', minimo: MINIMO_TEXTO },
  { texto: 'text-primary', fundo: 'navy-medium', minimo: MINIMO_TEXTO },
  { texto: 'text-muted', fundo: 'navy-dark', minimo: MINIMO_TEXTO },
  { texto: 'text-dark', fundo: 'cream', minimo: MINIMO_TEXTO },
  { texto: 'text-dark', fundo: 'cream-dark', minimo: MINIMO_TEXTO },
  { texto: 'gold', fundo: 'navy-dark', minimo: MINIMO_DESTAQUE },
  { texto: 'gold-deep', fundo: 'cream', minimo: MINIMO_DESTAQUE },
]

export interface AvisoContraste {
  texto: string
  fundo: string
  razao: number
  minimo: number
  mensagem: string
}

/** Pares com contraste abaixo do mínimo, para as cores atuais. Tokens ausentes ou com cor inválida são ignorados. */
export function avisosDeContraste(cores: Record<string, string>): AvisoContraste[] {
  const avisos: AvisoContraste[] = []
  for (const par of PARES_CONTRASTE) {
    const [t, f] = [cores[par.texto], cores[par.fundo]]
    if (!t || !f || !hexValido(t) || !hexValido(f)) continue
    const razao = contraste(t, f)
    if (razao < par.minimo) {
      avisos.push({
        ...par,
        razao,
        mensagem: `"${par.texto}" sobre "${par.fundo}" tem contraste ${razao.toFixed(1).replace('.', ',')}:1; o mínimo recomendado (WCAG AA) é ${String(par.minimo).replace('.', ',')}:1.`,
      })
    }
  }
  return avisos
}

export interface EstadoTema {
  cores: Record<string, string>
  fontes: Record<string, string>
}

/** Valores atuais do tema (o que a página usa hoje). */
export function estadoDoTema(tema: SiteTema | null | undefined): EstadoTema {
  return {
    cores: Object.fromEntries(Object.entries(tema?.cores ?? {}).map(([k, v]) => [k, v.atual])),
    fontes: Object.fromEntries(Object.entries(tema?.fontes ?? {}).map(([k, v]) => [k, v.atual])),
  }
}

/**
 * Corpo do `PUT template/tema`: só o que mudou. Voltar ao valor padrão do template manda vazio, o que
 * remove a personalização em vez de gravar uma cópia do padrão.
 */
export function calcularCorpo(tema: SiteTema, atual: EstadoTema): { cores?: Record<string, string>; fontes?: Record<string, string> } {
  const cores: Record<string, string> = {}
  for (const [token, info] of Object.entries(tema.cores)) {
    const novo = atual.cores[token]
    if (novo === undefined || mesmaCor(novo, info.atual)) continue
    cores[token] = mesmaCor(novo, info.padrao) ? '' : hexCompleto(novo)
  }
  const fontes: Record<string, string> = {}
  for (const [token, info] of Object.entries(tema.fontes)) {
    const nova = atual.fontes[token]
    if (nova === undefined || nova === info.atual) continue
    fontes[token] = nova === info.padrao ? '' : nova
  }
  return { ...(Object.keys(cores).length ? { cores } : {}), ...(Object.keys(fontes).length ? { fontes } : {}) }
}

export const temAlteracoes = (tema: SiteTema, atual: EstadoTema): boolean =>
  Object.keys(calcularCorpo(tema, atual)).length > 0

/** Erros de cor feitos no cliente (campo digitado). */
export function errosLocais(atual: EstadoTema): Record<string, string> {
  const erros: Record<string, string> = {}
  for (const [token, v] of Object.entries(atual.cores)) {
    if (!hexValido(v)) erros[`cores.${token}`] = 'Cor deve ser hexadecimal (#RGB ou #RRGGBB).'
  }
  return erros
}

/** Famílias permitidas para um token de fonte: as da mesma categoria (`serif` → serifadas). */
export function familiasDoToken(token: string, disponiveis: Record<string, string>): string[] {
  const categoria = token.toLowerCase().includes('sans') ? 'sans' : 'serif'
  const daCategoria = Object.entries(disponiveis).filter(([, c]) => c === categoria).map(([f]) => f)
  return daCategoria.length ? daCategoria : Object.keys(disponiveis)
}

/** Endereço do Google Fonts para pré-visualizar famílias da lista permitida (nunca texto livre). */
export function urlFontes(familias: string[], disponiveis: Record<string, string>): string {
  const validas = [...new Set(familias)].filter((f) => f in disponiveis)
  if (!validas.length) return ''
  const partes = validas.map((f) => `family=${encodeURIComponent(f).replace(/%20/g, '+')}:wght@400;700`)
  return `https://fonts.googleapis.com/css2?${partes.join('&')}&display=swap`
}
