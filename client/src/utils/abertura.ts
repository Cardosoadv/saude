/**
 * Abertura contábil - utilitários puros (sem acesso à API).
 *
 * Cada saldo informa débito OU crédito de uma conta patrimonial; a abertura só é
 * enviada quando total de débitos e de créditos fecham, em centavos.
 *
 * @version 1.0.0
 */

export interface SaldoAbertura {
  /** Código da conta no plano (ex.: 1.1.02). */
  code: string
  side: 'D' | 'C'
  /** Valor em reais. */
  amount: number
}

export interface LinhaAbertura {
  code: string
  debito: number | null
  credito: number | null
}

/** Classes que recebem saldo de abertura; receitas e despesas começam zeradas. */
export const TIPOS_ABERTURA = ['ASSET', 'LIABILITY', 'EQUITY']

const centavos = (v: number | null | undefined) => Math.round((Number(v) || 0) * 100)

/** Converte as linhas da tela em saldos, descartando contas sem valor. */
export function saldosDasLinhas(linhas: LinhaAbertura[]): SaldoAbertura[] {
  const saldos: SaldoAbertura[] = []
  for (const l of linhas) {
    if (centavos(l.debito) > 0) saldos.push({ code: l.code, side: 'D', amount: centavos(l.debito) / 100 })
    if (centavos(l.credito) > 0) saldos.push({ code: l.code, side: 'C', amount: centavos(l.credito) / 100 })
  }
  return saldos
}

export function totaisAbertura(linhas: LinhaAbertura[]) {
  const debitos = linhas.reduce((a, l) => a + centavos(l.debito), 0)
  const creditos = linhas.reduce((a, l) => a + centavos(l.credito), 0)
  return {
    debitos: debitos / 100,
    creditos: creditos / 100,
    diferenca: (debitos - creditos) / 100,
    fecha: debitos > 0 && debitos === creditos,
  }
}

/** Primeiro dia do ano corrente (data sugerida para a abertura contábil). */
export const primeiroDiaDoAno = (ano = new Date().getFullYear()) => `${ano}-01-01`
