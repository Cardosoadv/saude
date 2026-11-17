import { describe, it, expect, vi, afterEach } from 'vitest'
import { buscarCep, formatarCep } from '../src/utils/cep'

afterEach(() => vi.unstubAllGlobals())

describe('formatarCep', () => {
  it('aplica a máscara e descarta excesso', () => {
    expect(formatarCep('74000')).toBe('74000')
    expect(formatarCep('74000123')).toBe('74000-123')
    expect(formatarCep('74.000-1239')).toBe('74000-123')
  })
})

describe('buscarCep', () => {
  it('não consulta CEP incompleto', async () => {
    const fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)
    expect(await buscarCep('7400')).toBeNull()
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('mapeia a resposta do ViaCEP', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ logradouro: 'Rua 1', bairro: 'Centro', localidade: 'Goiânia', uf: 'GO' })
    }))
    expect(await buscarCep('74000-123')).toEqual({ logradouro: 'Rua 1', bairro: 'Centro', cidade: 'Goiânia', uf: 'GO' })
  })

  it('retorna null quando o CEP não existe', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ erro: true }) }))
    expect(await buscarCep('99999999')).toBeNull()
  })
})
