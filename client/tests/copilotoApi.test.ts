// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, vi } from 'vitest'

const api = vi.hoisted(() => ({ apiFetch: vi.fn() }))
vi.mock('../src/services/api', async (original) => ({ ...(await original<typeof import('../src/services/api')>()), apiFetch: api.apiFetch }))

import { ApiError } from '../src/services/api'
import { confirmarAcao, enviarMensagem, listarAcoesRapidas, mensagemDeErro } from '../src/services/copilotoApi'

describe('copilotoApi', () => {
  beforeEach(() => api.apiFetch.mockReset())

  it('envia mensagem, histórico e contexto e normaliza a resposta', async () => {
    api.apiFetch.mockResolvedValue({ success: true, data: { resposta: 'Olá', referencias: [{ tipo: 'processo', id: 1, rotulo: 'P' }] } })

    const resposta = await enviarMensagem('Oi', [{ papel: 'usuario', texto: 'a' }], { entidade_tipo: 'processo', entidade_id: 1 })

    expect(api.apiFetch).toHaveBeenCalledWith('copiloto/chat', expect.objectContaining({
      method: 'POST',
      body: { mensagem: 'Oi', historico: [{ papel: 'usuario', texto: 'a' }], contexto: { entidade_tipo: 'processo', entidade_id: 1 } },
    }))
    expect(api.apiFetch.mock.calls[0][1].timeout).toBeGreaterThan(30000)
    expect(resposta).toEqual({ resposta: 'Olá', referencias: [{ tipo: 'processo', id: 1, rotulo: 'P' }], ferramentas_usadas: [], acao_pendente: null, interacao_id: null })
  })

  it('confirma pelo token', async () => {
    api.apiFetch.mockResolvedValue({ data: { resultado: 'Tarefa criada', referencias: [] } })

    expect(await confirmarAcao('abc.def')).toEqual({ resultado: 'Tarefa criada', referencias: [] })
    expect(api.apiFetch).toHaveBeenCalledWith('copiloto/confirmar', expect.objectContaining({ method: 'POST', body: { token: 'abc.def' } }))
  })

  it('lista ações rápidas da tela', async () => {
    api.apiFetch.mockResolvedValue({ data: [{ id: 'resumir_processo', rotulo: 'Resumir', prompt: 'p' }] })

    expect(await listarAcoesRapidas('processo')).toHaveLength(1)
    expect(api.apiFetch).toHaveBeenCalledWith('copiloto/acoes-rapidas?entidade_tipo=processo')

    await listarAcoesRapidas(null)
    expect(api.apiFetch).toHaveBeenLastCalledWith('copiloto/acoes-rapidas')
  })

  it('usa a mensagem da API em vez do código do erro', () => {
    expect(mensagemDeErro(new ApiError('copiloto_limite', 429, { error: 'copiloto_limite', message: 'Aguarde um instante.' }))).toBe('Aguarde um instante.')
    expect(mensagemDeErro(new ApiError('HTTP 403', 403))).toBe('Você não tem acesso ao Copiloto.')
    expect(mensagemDeErro(new Error('x'))).toContain('Tente de novo')
  })
})
