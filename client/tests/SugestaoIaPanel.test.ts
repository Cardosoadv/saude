// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createApp, nextTick } from 'vue'

const api = vi.hoisted(() => ({ sugerirCriterio: vi.fn() }))
vi.mock('../src/services/liquidacoesApi', () => api)

import SugestaoIaPanel from '../src/components/Liquidacoes/SugestaoIaPanel.vue'

const SUGESTAO = {
  criterio: { correcao: { indice: 'IPCA-E' }, juros: { tipo: 'simples', taxa_mensal: '1' } },
  sugestao: { honorarios_percentual: '10', criterio: {} },
  trechos: ['corrigidas pelo IPCA-E'],
  observacoes: 'Confira a data de início dos juros.',
  avisos: [],
  requer_confirmacao: true,
}
const espera = async () => { for (let i = 0; i < 6; i++) { await nextTick(); await Promise.resolve() } }

function montar(onAplicar = vi.fn(), podeEditar = true) {
  const raiz = document.createElement('div')
  document.body.appendChild(raiz)
  createApp(SugestaoIaPanel, { liquidacaoId: 7, podeEditar, onAplicar }).mount(raiz)
  return { raiz, onAplicar }
}

async function sugerir(raiz: HTMLElement) {
  (raiz.querySelector('.section-header button') as HTMLButtonElement).click()
  await nextTick()
  const area = raiz.querySelector('textarea') as HTMLTextAreaElement
  area.value = 'condeno ao pagamento corrigido pelo IPCA-E'
  area.dispatchEvent(new Event('input'))
  await nextTick();
  (raiz.querySelector('.ia-acoes button') as HTMLButtonElement).click()
  await espera()
}

describe('SugestaoIaPanel', () => {
  beforeEach(() => { document.body.innerHTML = ''; api.sugerirCriterio.mockReset() })

  it('mostra o selo de IA e só aplica depois da confirmação', async () => {
    api.sugerirCriterio.mockResolvedValue(SUGESTAO)
    const { raiz, onAplicar } = montar()
    await sugerir(raiz)

    expect(api.sugerirCriterio).toHaveBeenCalledWith(7, 'condeno ao pagamento corrigido pelo IPCA-E')
    expect(raiz.textContent).toContain('Sugerido por IA')
    expect(raiz.textContent).toContain('corrigidas pelo IPCA-E')

    const aplicar = Array.from(raiz.querySelectorAll('button')).find((b) => b.textContent?.includes('Aplicar')) as HTMLButtonElement
    expect(aplicar.disabled).toBe(true)
    aplicar.click()
    expect(onAplicar).not.toHaveBeenCalled()

    const caixa = raiz.querySelector('input[type=checkbox]') as HTMLInputElement
    caixa.checked = true
    caixa.dispatchEvent(new Event('change'))
    await nextTick()
    expect(aplicar.disabled).toBe(false)
    aplicar.click()
    expect(onAplicar).toHaveBeenCalledWith(SUGESTAO.criterio, expect.stringContaining('IPCA-E'))
  })

  it('sem permissão de edição não oferece aplicar', async () => {
    api.sugerirCriterio.mockResolvedValue(SUGESTAO)
    const { raiz } = montar(vi.fn(), false)
    await sugerir(raiz)
    expect(raiz.querySelector('input[type=checkbox]')).toBeNull()
  })

  it('mostra o erro do servidor', async () => {
    api.sugerirCriterio.mockRejectedValue(new Error('IA indisponível'))
    const { raiz } = montar()
    await sugerir(raiz)
    expect(raiz.querySelector('[role=alert]')?.textContent).toContain('IA indisponível')
  })
})
