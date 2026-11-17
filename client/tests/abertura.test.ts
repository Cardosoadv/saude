import { describe, it, expect } from 'vitest'
import {
  primeiroDiaDoAno,
  saldosDasLinhas,
  totaisAbertura,
  type LinhaAbertura,
} from '../src/utils/abertura'

describe('Abertura contábil', () => {
  it('fecha em partidas dobradas quando débitos e créditos coincidem', () => {
    const linhas: LinhaAbertura[] = [
      { code: '1.1.01', debito: 1500.5, credito: null },
      { code: '3.1.01', debito: null, credito: 1500.5 },
    ]
    const t = totaisAbertura(linhas)
    expect(t.debitos).toBe(1500.5)
    expect(t.creditos).toBe(1500.5)
    expect(t.fecha).toBe(true)
  })

  it('sugere o primeiro dia do ano para a abertura', () => {
    expect(primeiroDiaDoAno(2027)).toBe('2027-01-01')
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
