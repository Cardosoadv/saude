import { describe, it, expect, beforeEach, vi } from 'vitest'

const api = vi.hoisted(() => ({ enviarMensagem: vi.fn(), confirmarAcao: vi.fn(), mensagemDeErro: vi.fn(() => 'erro') }))
vi.mock('../src/services/copilotoApi', () => api)

import { enviar, historicoParaEnvio, limpar, useCopiloto, MAX_TURNOS_HISTORICO } from '../src/composables/useCopiloto'

describe('useCopiloto', () => {
  beforeEach(() => { limpar(); api.enviarMensagem.mockReset() })

  it('envia o histórico anterior sem erros e limitado', async () => {
    api.enviarMensagem.mockResolvedValue({ resposta: 'r', referencias: [], ferramentas_usadas: [], acao_pendente: null, interacao_id: null })
    for (let i = 0; i < 8; i++) await enviar(`p${i}`, null)
    api.enviarMensagem.mockRejectedValueOnce(new Error('falhou'))
    await enviar('com erro', null)

    const historico = historicoParaEnvio()
    expect(historico.length).toBe(MAX_TURNOS_HISTORICO)
    expect(historico.every((t) => t.papel === 'usuario' || t.papel === 'assistente')).toBe(true)
    expect(api.enviarMensagem.mock.calls[1][1]).toEqual([{ papel: 'usuario', texto: 'p0' }, { papel: 'assistente', texto: 'r' }])
  })

  it('ignora mensagem vazia e limpa a conversa', async () => {
    await enviar('   ', null)
    expect(api.enviarMensagem).not.toHaveBeenCalled()

    api.enviarMensagem.mockResolvedValue({ resposta: 'r', referencias: [], ferramentas_usadas: [], acao_pendente: null, interacao_id: null })
    await enviar('oi', null)
    expect(useCopiloto().estado.mensagens.length).toBe(2)
    limpar()
    expect(useCopiloto().estado.mensagens.length).toBe(0)
  })
})
