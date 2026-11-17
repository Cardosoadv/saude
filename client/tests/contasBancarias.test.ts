import { describe, it, expect } from 'vitest'
import { contaCompleta, formVazio } from '../src/utils/contasBancarias'

describe('contasBancarias', () => {
  it('conta só com nome não serve para importar extrato', () => {
    expect(contaCompleta({ banco: 'Banco X', agencia: '', numero_conta: null })).toBe(false)
  })
  it('conta com banco, agência e número serve', () => {
    expect(contaCompleta({ banco: 'Banco X', agencia: '0001', numero_conta: '123-4' })).toBe(true)
  })
  it('espaços em branco não contam', () => {
    expect(contaCompleta({ banco: ' ', agencia: '0001', numero_conta: '1' })).toBe(false)
  })
  it('formulário vazio começa sem id', () => {
    expect(formVazio().id_conta).toBeNull()
  })
})
