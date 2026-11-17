// @vitest-environment happy-dom
/**
 * Testes de UI e navegação por links href em Clientes (ClientDetail e ClientList)
 * @version 1.0.0
 */
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createApp, nextTick } from 'vue'

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api')
  return {
    ...real,
    apiFetch: vi.fn(async (rota: string) => {
      if (rota === 'clientes/detalhes/42') {
        return {
          success: true,
          data: {
            id_cliente: 42,
            nome: 'Maria da Silva',
            documento: '12345678900',
            tipo_cliente: 'F',
            email: 'maria@exemplo.com',
            telefone: '31999990000'
          },
          origens: [],
          processos: [
            {
              id_processo: 101,
              numeroprocessocommascara: '5117757-44.2025.8.13.0024',
              titulo_processo: 'Ação de Indenização por Danos Morais',
              ativo: 1
            }
          ],
          casos: [
            {
              id_caso: 202,
              titulo: 'Consultoria Trabalhista',
              tipo: 'Consultivo',
              descricao: 'Análise de contrato',
              status: 'Em andamento'
            }
          ]
        }
      }
      if (rota === 'clientes/estatisticasCliente/42') {
        return {
          success: true,
          data: { processos: 1, casos: 1, receitas: 4000, tarefas: 3 }
        }
      }
      if (rota === 'clientes/anotacoes/42') {
        return {
          success: true,
          data: [
            {
              id: 505,
              titulo: 'Reunião de alinhamento',
              anotacao: 'Cliente enviou documentos pendentes',
              created_at: '2026-11-06 10:00:00',
              tipo: 'processo',
              entidade_id: 101,
              referencia: '5117757-44.2025.8.13.0024'
            }
          ]
        }
      }
      if (rota === 'clientes/movimentacoes/42') {
        return {
          success: true,
          data: [
            {
              id_receita: 303,
              id_pgto_receita: null,
              descricao: 'Honorários Iniciais',
              data: '2026-11-01',
              valor: 2500,
              pago: 0
            },
            {
              id_receita: 304,
              id_pgto_receita: 88,
              descricao: 'Parcela 1 Recebida',
              data: '2026-10-01',
              valor: 1500,
              pago: 1
            }
          ]
        }
      }
      if (rota === 'clientes/intimacoes/42') {
        return {
          success: true,
          data: [
            {
              id_intimacao: 404,
              data_publicacao: '2026-11-05',
              diario: 'DJEN',
              conteudo: 'Intimação para manifestação sobre laudo pericial',
              id_processo: 101,
              numeroprocessocommascara: '5117757-44.2025.8.13.0024'
            }
          ]
        }
      }
      if (rota.includes('advogados')) {
        return {
          success: true,
          data: []
        }
      }
      if (rota.includes('acessos')) {
        return {
          success: true,
          data: { responsavel: null, usuarios: [], pode_gerenciar: false }
        }
      }
      if (rota === 'clientes/select-list') {
        return []
      }
      if (rota === 'clientes/estatisticas') {
        return { success: true, data: { total: 1, pf: 1, pj: 0 } }
      }
      if (rota === 'clientes/recentes') {
        return {
          success: true,
          data: [{ id_cliente: 42, nome: 'Maria da Silva', created_at: '2026-11-01' }]
        }
      }
      if (rota === 'clientes/aniversariantes') {
        return {
          success: true,
          data: [{ id_cliente: 42, nome: 'Maria da Silva', data_nascimento: '1990-11-15' }]
        }
      }
      if (rota.startsWith('clientes?')) {
        return {
          success: true,
          data: [
            {
              id_cliente: 42,
              nome: 'Maria da Silva',
              documento: '123.456.789-00',
              tipo_pessoa: 1,
              email: 'maria@exemplo.com',
              telefone: '31999990000',
              cidade: 'Belo Horizonte',
              uf: 'MG',
              status_crm: 'Ativo'
            }
          ],
          pagination: { page: 1, total_pages: 1, total_items: 1 }
        }
      }
      return { success: true, data: [] }
    })
  }
})

vi.mock('../src/composables/useAcesso', () => ({
  pode: vi.fn(() => true),
  liberado: vi.fn(() => true),
  moduloAtivo: vi.fn(() => true),
  acesso: { modulos: {}, permissoes: [], isSuperuser: true, carregado: true }
}))

import ClientDetail from '../src/views/Clientes/ClientDetail.vue'
import ClientList from '../src/views/Clientes/ClientList.vue'

const espera = async () => {
  for (let i = 0; i < 10; i++) {
    await nextTick()
    await Promise.resolve()
  }
}

describe('Módulo de Clientes - Padrão Visual e Links href', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('ClientDetail renderiza tabela no padrão data-table, exibe titulo_processo e links href reais em todas as abas', async () => {
    const raiz = document.createElement('div')
    document.body.appendChild(raiz)
    const app = createApp(ClientDetail, { clientId: 42 })
    app.mount(raiz)
    await espera()

    // Verifica container de abas no padrão card
    expect(raiz.querySelector('.card.tabs-card')).not.toBeNull()
    expect(raiz.querySelector('table.data-table')).not.toBeNull()

    // Verifica que nenhum link usa href="#"
    const linksInvalidos = Array.from(raiz.querySelectorAll('a')).filter(
      (a) => a.getAttribute('href') === '#'
    )
    expect(linksInvalidos).toHaveLength(0)

    // Aba Processos: exibe titulo_processo e link href="#/processos/detalhes/101"
    expect(raiz.textContent).toContain('Ação de Indenização por Danos Morais')
    const linkProcesso = raiz.querySelector('a[href="#/processos/detalhes/101"]')
    expect(linkProcesso).not.toBeNull()
    expect(linkProcesso?.textContent).toContain('5117757-44.2025.8.13.0024')

    // Aba Casos Extrajudiciais
    const botoesAba = Array.from(raiz.querySelectorAll<HTMLButtonElement>('button.tab-btn'))
    botoesAba[1].click()
    await espera()
    const linkCaso = raiz.querySelector('a[href="#/casos/detalhes/202"]')
    expect(linkCaso).not.toBeNull()
    expect(linkCaso?.textContent).toContain('Consultoria Trabalhista')

    // Aba Financeiro
    botoesAba[2].click()
    await espera()
    const linkReceitaAberta = raiz.querySelector('a[href="#/receitas/detalhes/303"]')
    const linkReceitaPaga = raiz.querySelector('a[href="#/receitas/recebidas/detalhes/88"]')
    expect(linkReceitaAberta).not.toBeNull()
    expect(linkReceitaPaga).not.toBeNull()

    // Aba Intimações
    botoesAba[3].click()
    await espera()
    const linkIntimacao = raiz.querySelector('a[href="#/intimacoes/detalhes/404"]')
    const linkProcIntimacao = raiz.querySelector('a[href="#/processos/detalhes/101"]')
    expect(linkIntimacao).not.toBeNull()
    expect(linkProcIntimacao).not.toBeNull()

    // Aba CRM / Histórico
    botoesAba[4].click()
    await espera()
    const linkOrigemNota = raiz.querySelector('a[href="#/processos/detalhes/101"]')
    expect(linkOrigemNota).not.toBeNull()

    app.unmount()
    raiz.remove()
  })

  it('ClientList renderiza links href reais para edição, criação e widgets laterais sem usar href="#"', async () => {
    const raiz = document.createElement('div')
    document.body.appendChild(raiz)
    const app = createApp(ClientList, { searchQuery: '' })
    app.mount(raiz)
    await espera()

    const linksInvalidos = Array.from(raiz.querySelectorAll('a')).filter(
      (a) => a.getAttribute('href') === '#'
    )
    expect(linksInvalidos).toHaveLength(0)

    expect(raiz.querySelector('a[href="#/clientes/novo"]')).not.toBeNull()
    const linksCliente = raiz.querySelectorAll('a[href="#/clientes/editar/42"]')
    expect(linksCliente.length).toBeGreaterThanOrEqual(3) // card principal, botão editar, recentes e aniversariantes

    app.unmount()
    raiz.remove()
  })
})
