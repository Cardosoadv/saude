import { describe, it, expect } from 'vitest'
import {
  centavosParaTexto, formatarReais, limparCriterio, parcelaAtrasada, resumirCriterio, saldoAReceber, textoParaCentavos, tomStatus,
} from '../src/utils/liquidacao'

describe('textoParaCentavos', () => {
  it.each([
    ['1.234,56', 123456],
    ['1234,56', 123456],
    ['1234.56', 123456],
    ['R$ 10,5', 1050],
    ['-5,00', -500],
    ['100', 10000],
  ])('converte %s', (texto, esperado) => {
    expect(textoParaCentavos(texto)).toBe(esperado)
  })

  it.each(['', '  ', 'abc', '1,234,56', '12,345', null, undefined])('recusa %s sem adivinhar', (texto) => {
    expect(textoParaCentavos(texto as string)).toBeNull()
  })

  it('ida e volta com centavosParaTexto', () => {
    expect(textoParaCentavos(centavosParaTexto(123456))).toBe(123456)
    expect(centavosParaTexto(null)).toBe('')
  })
})

describe('formatarReais / saldoAReceber', () => {
  it('formata centavos em reais', () => {
    expect(formatarReais(123456).replace(/\s/g, ' ')).toContain('1.234,56')
  })

  it('o saldo nunca fica negativo', () => {
    expect(saldoAReceber(1000, 400)).toBe(600)
    expect(saldoAReceber(1000, 1500)).toBe(0)
  })
})

describe('parcelaAtrasada', () => {
  it('pendente vencida está atrasada; paga não', () => {
    expect(parcelaAtrasada({ status_pagamento: 'pendente', data_vencimento: '2026-01-10' }, '2026-02-01')).toBe(true)
    expect(parcelaAtrasada({ status_pagamento: 'pendente', data_vencimento: '2026-03-10' }, '2026-02-01')).toBe(false)
    expect(parcelaAtrasada({ status_pagamento: 'vencida', data_vencimento: '2026-03-10' }, '2026-02-01')).toBe(true)
    expect(parcelaAtrasada({ status_pagamento: 'paga', data_vencimento: '2026-01-10' }, '2026-02-01')).toBe(false)
  })
})

describe('resumirCriterio', () => {
  it('critério vazio é valor manual', () => {
    expect(resumirCriterio({})).toBe('Valor informado manualmente')
    expect(resumirCriterio(null)).toBe('Valor informado manualmente')
  })

  it('descreve correção e juros', () => {
    const texto = resumirCriterio({
      correcao: { indice: 'IPCA-E' },
      juros: { tipo: 'simples', taxa_mensal: '1', data_inicial: 'vencimento' },
    })
    expect(texto).toContain('correção pelo IPCA-E')
    expect(texto).toContain('1% ao mês')
  })
})

describe('limparCriterio', () => {
  it('normaliza o índice e descarta juros sem tipo', () => {
    const c = limparCriterio({ correcao: { indice: ' ipca-e ' }, juros: { tipo: '' as never } })
    expect(c.correcao?.indice).toBe('IPCA-E')
    expect(c.juros).toBeUndefined()
  })

  it('juros "nenhum" não carrega taxa', () => {
    expect(limparCriterio({ juros: { tipo: 'nenhum', taxa_mensal: '1' } }).juros).toEqual({ tipo: 'nenhum' })
  })

  it('troca vírgula por ponto nas taxas', () => {
    const c = limparCriterio({ juros: { tipo: 'simples', taxa_mensal: '0,5' }, percentual: '10,5' })
    expect(c.juros?.taxa_mensal).toBe('0.5')
    expect(c.percentual).toBe('10.5')
  })
})

describe('tomStatus', () => {
  it('mapeia status para o tom do badge', () => {
    expect(tomStatus('quitada')).toBe('success')
    expect(tomStatus('impugnada')).toBe('danger')
  })
})
