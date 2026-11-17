import { describe, it, expect } from 'vitest'
import {
  BALANCETE_2025_CARDOSO_BRUNO,
  saldosDasLinhas,
  totaisAbertura,
  type LinhaAbertura,
} from '../src/utils/abertura'

describe('Abertura contábil', () => {
  it('o balancete de 31/12/2025 fecha em partidas dobradas', () => {
    const linhas: LinhaAbertura[] = BALANCETE_2025_CARDOSO_BRUNO.map((s) => ({
      code: s.code,
      debito: s.side === 'D' ? s.amount : null,
      credito: s.side === 'C' ? s.amount : null,
    }))
    const t = totaisAbertura(linhas)
    expect(t.debitos).toBe(1170682.32)
    expect(t.creditos).toBe(1170682.32)
    expect(t.fecha).toBe(true)
  })

  it('o ativo do preset é o ativo do balancete (41.591,17)', () => {
    const ativo = BALANCETE_2025_CARDOSO_BRUNO.filter((s) => s.code.startsWith('1.')).reduce((a, s) => a + s.amount, 0)
    expect(Math.round(ativo * 100)).toBe(4159117)
  })

  it('só aceita contas patrimoniais no preset', () => {
    expect(BALANCETE_2025_CARDOSO_BRUNO.every((s) => /^[123]\./.test(s.code))).toBe(true)
  })

  it('ignora linhas sem valor e separa débito e crédito', () => {
    const saldos = saldosDasLinhas([
      { code: '1.1.01', debito: 10.5, credito: null },
      { code: '2.1.01', debito: null, credito: 10.5 },
      { code: '3.1.01', debito: 0, credito: null },
    ])
    expect(saldos).toEqual([
      { code: '1.1.01', side: 'D', amount: 10.5 },
      { code: '2.1.01', side: 'C', amount: 10.5 },
    ])
  })

  it('não fecha com diferença de um centavo nem sem valores', () => {
    expect(totaisAbertura([{ code: 'a', debito: 100, credito: 99.99 }]).fecha).toBe(false)
    expect(totaisAbertura([{ code: 'a', debito: null, credito: null }]).fecha).toBe(false)
  })
})
