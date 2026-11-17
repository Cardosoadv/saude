// @vitest-environment happy-dom
/**
 * Testes da listagem de processos (ordenação por último movimento, última intimação e filtro de responsável)
 * @version 1.1.0
 */
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createApp, nextTick } from 'vue'

const chamadasApi: string[] = []

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api')
  return {
    ...real,
    apiFetch: vi.fn(async (rota: string) => {
      chamadasApi.push(rota)
      if (rota.startsWith('processos?')) {
        return {
          success: true,
          data: [
            {
              id_processo: 10,
              numeroprocessocommascara: '5001111-22.2026.8.13.0024',
              titulo_processo: 'Ação Ordinária',
              cliente_nome: 'Maria da Silva',
              siglaTribunal: 'TJMG',
              dataDistribuicao: '2026-01-10',
              ultimo_movimento_em: '2026-11-05 14:30:00',
              ultima_intimacao_em: '2026-11-06',
              encerrado: 0,
              arquived_at: null,
              risco: 'Possível',
              etiquetas: []
            }
          ],
          pagination: {
            total_items: 1,
            total_pages: 1,
            per_page: 25
          }
        }
      }
      if (rota === 'processos/stats') {
        return {
          success: true,
          data: { total: 1, ativos: 1, arquivados: 0, encerrados: 0 }
        }
      }
      if (rota === 'processos/widgets-sidebar') {
        return {
          success: true,
          data: { movimentados: [], intimacoes: [] }
        }
      }
      if (rota === 'usuarios/responsaveis-tarefas') {
        return {
          success: true,
          data: [
            { id: 1, username: 'Ana Responsável', responsavel_tarefas: 1 },
            { id: 2, username: 'Carlos Sem Tarefa', responsavel_tarefas: 0 }
          ]
        }
      }
      if (rota === 'etiquetas') {
        return { success: true, data: [] }
      }
      return { success: true, data: [] }
    })
  }
})

import ProcessList from '../src/views/Processos/ProcessList.vue'

async function flushPromises() {
  for (let i = 0; i < 6; i++) {
    await Promise.resolve()
    await nextTick()
  }
}

describe('ProcessList.vue — ordenação por último movimento e última intimação', () => {
  beforeEach(() => {
    chamadasApi.length = 0
  })

  it('exibe as opções de ordenação por último movimento e última intimação e envia parâmetros corretos à API', async () => {
    const host = document.createElement('div')
    document.body.appendChild(host)
    const app = createApp(ProcessList, { searchQuery: '' })
    app.mount(host)
    await flushPromises()

    // Exibe datas de último movimento e última intimação no card
    expect(host.textContent).toContain('Últ. movimento: 05/11/2026')
    expect(host.textContent).toContain('Últ. intimação: 06/11/2026')

    // Abre o menu "Ordenar por"
    const botoes = Array.from(host.querySelectorAll('button'))
    const btnOrdenar = botoes.find(b => b.textContent?.includes('Ordenar por:'))
    expect(btnOrdenar).toBeTruthy()
    btnOrdenar!.click()
    await flushPromises()

    const itensMenu = Array.from(host.querySelectorAll('.dropdown-menu .dropdown-item'))
    const textosMenu = itensMenu.map(i => i.textContent?.trim())
    expect(textosMenu).toContain('Data do Último Movimento')
    expect(textosMenu).toContain('Data da Última Intimação')

    // Seleciona "Data do Último Movimento"
    const itemMovimento = itensMenu.find(i => i.textContent?.includes('Data do Último Movimento')) as HTMLElement
    chamadasApi.length = 0
    itemMovimento.click()
    await flushPromises()

    const chamadaMovimentoDesc = chamadasApi.find(r => r.startsWith('processos?'))
    expect(chamadaMovimentoDesc).toContain('sort=ultimo_movimento_em')
    expect(chamadaMovimentoDesc).toContain('order=desc')

    // Clicar novamente na mesma opção alterna para order=asc
    btnOrdenar!.click()
    await flushPromises()
    const itemMovimentoNovamente = Array.from(host.querySelectorAll('.dropdown-menu .dropdown-item'))
      .find(i => i.textContent?.includes('Data do Último Movimento')) as HTMLElement
    chamadasApi.length = 0
    itemMovimentoNovamente.click()
    await flushPromises()

    const chamadaMovimentoAsc = chamadasApi.find(r => r.startsWith('processos?'))
    expect(chamadaMovimentoAsc).toContain('sort=ultimo_movimento_em')
    expect(chamadaMovimentoAsc).toContain('order=asc')

    // Seleciona "Data da Última Intimação"
    btnOrdenar!.click()
    await flushPromises()
    const itemIntimacao = Array.from(host.querySelectorAll('.dropdown-menu .dropdown-item'))
      .find(i => i.textContent?.includes('Data da Última Intimação')) as HTMLElement
    chamadasApi.length = 0
    itemIntimacao.click()
    await flushPromises()

    const chamadaIntimacaoDesc = chamadasApi.find(r => r.startsWith('processos?'))
    expect(chamadaIntimacaoDesc).toContain('sort=ultima_intimacao_em')
    expect(chamadaIntimacaoDesc).toContain('order=desc')

    app.unmount()
    host.remove()
  })

  it('lista no select de responsável apenas usuários que podem ser responsáveis por tarefas', async () => {
    const host = document.createElement('div')
    document.body.appendChild(host)
    const app = createApp(ProcessList, { searchQuery: '' })
    app.mount(host)
    await flushPromises()

    expect(chamadasApi).toContain('usuarios/responsaveis-tarefas')

    const btnFiltros = Array.from(host.querySelectorAll('button')).find(b => b.textContent?.includes('Filtros Avançados'))
    btnFiltros!.click()
    await flushPromises()

    const selects = Array.from(host.querySelectorAll('.filters-panel select'))
    const selectResponsavel = selects[1] as HTMLSelectElement
    const opcoes = Array.from(selectResponsavel.querySelectorAll('option')).map(o => o.textContent?.trim())

    expect(opcoes).toEqual(['Todos', 'Ana Responsável'])
    expect(opcoes).not.toContain('Carlos Sem Tarefa')

    app.unmount()
    host.remove()
  })
})

