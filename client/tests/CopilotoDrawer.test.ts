// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createApp, nextTick, reactive } from 'vue'

const api = vi.hoisted(() => ({
  enviarMensagem: vi.fn(),
  confirmarAcao: vi.fn(),
  listarAcoesRapidas: vi.fn(),
  mensagemDeErro: vi.fn(() => 'erro'),
}))
vi.mock('../src/services/copilotoApi', () => api)

import CopilotoDrawer from '../src/components/copiloto/CopilotoDrawer.vue'
import { useCopiloto, historicoParaEnvio } from '../src/composables/useCopiloto'

const espera = async () => { for (let i = 0; i < 8; i++) { await nextTick(); await Promise.resolve() } }

const ACAO = {
  ferramenta: 'criar_tarefa',
  resumo: 'Criar tarefa',
  campos: [{ campo: 'Tarefa', valor: 'Contestar' }],
  argumentos: { tarefa: 'Contestar' },
  token: 'tok.en',
  expira_em: new Date(Date.now() + 600_000).toISOString(),
}

function montar(contexto: Record<string, unknown> | null = { entidade_tipo: 'processo', entidade_id: 7, tela: 'detail' }) {
  const raiz = document.createElement('div')
  document.body.appendChild(raiz)
  const props = reactive({ contexto, onNavegar: vi.fn() })
  createApp(CopilotoDrawer, props).mount(raiz)
  return { raiz, props }
}

function teclar(key: string, extra: KeyboardEventInit = {}) {
  window.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...extra }))
}

async function perguntar(raiz: HTMLElement, texto: string) {
  const area = raiz.querySelector('textarea') as HTMLTextAreaElement
  area.value = texto
  area.dispatchEvent(new Event('input'))
  await nextTick();
  (raiz.querySelector('form') as HTMLFormElement).dispatchEvent(new Event('submit', { cancelable: true }))
  await espera()
}

describe('CopilotoDrawer', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    Object.values(api).forEach((f) => f.mockReset?.())
    api.listarAcoesRapidas.mockResolvedValue([{ id: 'resumir_processo', rotulo: 'Resumir processo', prompt: 'Resuma este processo.' }])
    api.mensagemDeErro.mockReturnValue('erro')
    const c = useCopiloto()
    c.limpar()
    c.fechar()
  })

  it('abre e fecha com Ctrl+K / Cmd+K e Esc', async () => {
    const { raiz } = montar()
    expect(raiz.querySelector('.copiloto-drawer')).toBeNull()

    teclar('k', { ctrlKey: true })
    await espera()
    expect(raiz.querySelector('.copiloto-drawer')).not.toBeNull()
    expect(raiz.textContent).toContain('Processo #7')

    teclar('Escape')
    await espera()
    // O painel sai com transição; o estado já fechou.
    expect(useCopiloto().estado.aberto).toBe(false)

    teclar('K', { metaKey: true })
    await espera()
    expect(useCopiloto().estado.aberto).toBe(true)

    teclar('k', { ctrlKey: true, shiftKey: true })
    expect(useCopiloto().estado.aberto).toBe(true)
  })

  it('mostra as ações rápidas da tela e envia o contexto', async () => {
    api.enviarMensagem.mockResolvedValue({ resposta: 'Resumo pronto', referencias: [], ferramentas_usadas: [], acao_pendente: null, interacao_id: 1 })
    const { raiz } = montar()
    useCopiloto().abrir()
    await espera()

    expect(api.listarAcoesRapidas).toHaveBeenCalledWith('processo')
    const chip = raiz.querySelector('.copiloto-chip') as HTMLButtonElement
    expect(chip.textContent).toContain('Resumir processo')
    chip.click()
    await espera()

    expect(api.enviarMensagem).toHaveBeenCalledWith('Resuma este processo.', [], { entidade_tipo: 'processo', entidade_id: 7, tela: 'detail' }, expect.anything())
    expect(raiz.textContent).toContain('Resumo pronto')
  })

  it('referência na resposta navega dentro da SPA e HTML do modelo não é interpretado', async () => {
    api.enviarMensagem.mockResolvedValue({
      resposta: 'Veja [o processo](ordo://processo/12) <img src=x onerror="window.hack=1">',
      referencias: [{ tipo: 'processo', id: 12, rotulo: 'o processo' }],
      ferramentas_usadas: [], acao_pendente: null, interacao_id: 1,
    })
    const { raiz, props } = montar()
    useCopiloto().abrir()
    await espera()
    await perguntar(raiz, 'Qual processo?')

    expect(raiz.querySelector('.copiloto-msg-assistente img')).toBeNull()
    const ref = raiz.querySelector('.copiloto-msg-assistente .copiloto-ref') as HTMLButtonElement
    ref.click()
    expect(props.onNavegar).toHaveBeenCalledWith({ tipo: 'processo', id: 12 })
    // Já aparece como link no texto: não repete abaixo.
    expect(raiz.querySelectorAll('.copiloto-refs').length).toBe(0)
  })

  it('ação proposta só grava ao confirmar, uma vez', async () => {
    api.enviarMensagem.mockResolvedValue({ resposta: 'Preparei a ação.', referencias: [], ferramentas_usadas: [], acao_pendente: ACAO, interacao_id: 1 })
    let resolver: (v: unknown) => void = () => {}
    api.confirmarAcao.mockReturnValue(new Promise((r) => { resolver = r }))
    const { raiz, props } = montar()
    useCopiloto().abrir()
    await espera()
    await perguntar(raiz, 'Crie a tarefa Contestar')

    expect(raiz.textContent).toContain('Nada foi gravado ainda')
    expect(api.confirmarAcao).not.toHaveBeenCalled()

    const botao = Array.from(raiz.querySelectorAll('.copiloto-acao button')).find((b) => b.textContent?.includes('Confirmar')) as HTMLButtonElement
    botao.click()
    await espera()
    expect((raiz.querySelector('.copiloto-acao button') as HTMLButtonElement).disabled).toBe(true)
    botao.click()
    expect(api.confirmarAcao).toHaveBeenCalledTimes(1)
    expect(api.confirmarAcao).toHaveBeenCalledWith('tok.en')

    resolver({ resultado: 'Tarefa criada.', referencias: [{ tipo: 'tarefa', id: 31, rotulo: 'Abrir tarefa' }] })
    await espera()
    expect(raiz.textContent).toContain('Tarefa criada.')
    ;(raiz.querySelector('.copiloto-refs .copiloto-ref') as HTMLButtonElement).click()
    expect(props.onNavegar).toHaveBeenCalledWith({ tipo: 'tarefa', id: 31 })
  })

  it('recusar não chama a API e entra no histórico', async () => {
    api.enviarMensagem.mockResolvedValue({ resposta: 'Preparei a ação.', referencias: [], ferramentas_usadas: [], acao_pendente: ACAO, interacao_id: 1 })
    const { raiz } = montar()
    useCopiloto().abrir()
    await espera()
    await perguntar(raiz, 'Crie')

    ;(Array.from(raiz.querySelectorAll('.copiloto-acao button')).find((b) => b.textContent?.includes('Recusar')) as HTMLButtonElement).click()
    await espera()

    expect(api.confirmarAcao).not.toHaveBeenCalled()
    expect(raiz.textContent).toContain('Ação recusada')
    expect(historicoParaEnvio().at(-1)?.texto).toContain('recusada pelo usuário')
  })

  it('ação expirada não chama a API', async () => {
    api.enviarMensagem.mockResolvedValue({ resposta: 'ok', referencias: [], ferramentas_usadas: [], acao_pendente: { ...ACAO, expira_em: new Date(Date.now() - 1000).toISOString() }, interacao_id: 1 })
    const { raiz } = montar()
    useCopiloto().abrir()
    await espera()
    await perguntar(raiz, 'Crie')

    ;(Array.from(raiz.querySelectorAll('.copiloto-acao button')).find((b) => b.textContent?.includes('Confirmar')) as HTMLButtonElement).click()
    await espera()

    expect(api.confirmarAcao).not.toHaveBeenCalled()
    expect(raiz.textContent).toContain('expirou')
  })

  it('erro da API aparece na conversa', async () => {
    api.enviarMensagem.mockRejectedValue(new Error('x'))
    api.mensagemDeErro.mockReturnValue('Muitas perguntas em pouco tempo.')
    const { raiz } = montar(null)
    useCopiloto().abrir()
    await espera()
    expect(api.listarAcoesRapidas).toHaveBeenCalledWith(null)
    await perguntar(raiz, 'Oi')

    expect(raiz.querySelector('.copiloto-msg-erro')?.textContent).toContain('Muitas perguntas')
  })
})
