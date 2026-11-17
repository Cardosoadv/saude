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

/**
 * Balancete de 31/12/2025 (Pontual Gestão Empresarial e Contabilidade, emitido em 05/03/2026)
 * levado para o plano de contas Cardoso & Bruno.
 *
 * - Lucros Acumulados (3.2.01) = 517.705,44 de saldo + 149.305,25 de resultado de 2025 já transferido.
 * - Lucro distribuído fica acumulado nas contas 3.3.xx, como no balancete.
 * - Grupos sem saldo (Pró-Labore a Pagar, Simples Nacional) e contas de resultado não entram.
 */
export const BALANCETE_2025_CARDOSO_BRUNO: SaldoAbertura[] = [
  { code: '1.1.01', side: 'D', amount: 468.21 },
  { code: '1.1.02', side: 'D', amount: 3426.65 },
  { code: '1.1.03', side: 'D', amount: 4862.57 },
  { code: '1.1.04', side: 'D', amount: 12833.74 },
  { code: '1.3.01', side: 'D', amount: 20000 },
  { code: '2.1.02', side: 'C', amount: 462.83 },
  { code: '2.1.05', side: 'C', amount: 403208.8 },
  { code: '3.1.01', side: 'C', amount: 100000 },
  { code: '3.2.01', side: 'C', amount: 667010.69 },
  { code: '3.2.02', side: 'D', amount: 10000 },
  { code: '3.3.01', side: 'D', amount: 127368.17 },
  { code: '3.3.02', side: 'D', amount: 991722.98 },
]

export const DATA_ABERTURA_2026 = '2026-01-01'
