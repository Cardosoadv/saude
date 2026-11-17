// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createApp, nextTick } from 'vue'

vi.mock('../src/services/liquidacoesApi', () => ({ gerarParcelas: vi.fn(), baixarParcela: vi.fn(), cancelarParcela: vi.fn() }))

import ParcelasPanel from '../src/components/Liquidacoes/ParcelasPanel.vue'

const parcela = (id: number, numero: number, vencimento: string, status: string) => ({
  pagamento_id: id, liquidacao_id: 1, numero_parcela: numero, valor_parcela: 50000, data_vencimento: vencimento,
  data_pagamento: null, status_pagamento: status, forma_pagamento: null, comprovante_url: null,
})
const RESUMO = {
  total_parcelas: 3, valor_parcelado: 150000, total_pago: 0, total_vencido: 50000, saldo_a_receber: 150000,
  proxima_parcela: null, diferenca_parcelamento: null,
}

function montar(pagamentos: unknown[], podeEditar = true) {
  const raiz = document.createElement('div')
  document.body.appendChild(raiz)
  createApp(ParcelasPanel, { liquidacao: { liquidacao_id: 1, valor_total: 150000, pagamentos, resumo_pagamentos: RESUMO }, podeEditar }).mount(raiz)
  return raiz
}

describe('ParcelasPanel', () => {
  beforeEach(() => { document.body.innerHTML = '' })

  it('destaca a parcela pendente já vencida', async () => {
    const raiz = montar([parcela(1, 1, '2020-01-10', 'pendente'), parcela(2, 2, '2999-01-10', 'pendente')])
    await nextTick()
    const linhas = raiz.querySelectorAll('tbody tr')
    expect(linhas[0].classList.contains('atrasada')).toBe(true)
    expect(linhas[1].classList.contains('atrasada')).toBe(false)
    expect(linhas[0].textContent).toContain('Vencida')
  })

  it('sem parcelas mostra o estado vazio e oferece gerar o plano', async () => {
    const raiz = montar([])
    await nextTick()
    expect(raiz.textContent).toContain('Nenhuma parcela gerada')
    expect(raiz.querySelector('.section-header button')?.textContent).toContain('Gerar parcelamento')
  })

  it('sem permissão de edição esconde o botão de parcelamento', async () => {
    const raiz = montar([], false)
    await nextTick()
    expect(raiz.querySelector('.section-header button')).toBeNull()
  })
})
