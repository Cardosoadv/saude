/**
 * Serviço de Integração do Módulo Contábil - Front-End SPA
 *
 * Conecta a SPA aos endpoints RESTful do OrdoJuris e do pacote cognitiotechny/contabil.
 *
 * @version 1.0.0
 */
import { apiFetch } from './api'
import type { SaldoAbertura } from '../utils/abertura'

/** De onde veio a conta sugerida: dicionário de categorias ou conta padrão do plano. */
export type OrigemSugestao = 'dicionario' | 'padrao'

export interface DivisaoIntegracao {
  valor: number
  debito_id?: number
  credito_id?: number
  descricao?: string
  /** Categoria financeira que originou a parte (divisão sugerida pelo rateio). */
  categoria_id?: number | null
  categoria?: string
  sugestao_origem?: OrigemSugestao
  /** Conta sugerida para a perna da categoria; usada para saber se o usuário a alterou. */
  sugestao_conta_id?: number
}

export interface CategoriaTransacao {
  categoria_id: number | null
  categoria: string
  /** Parte do valor pago que cabe à categoria. */
  valor: number
}

/** Associação do dicionário categoria -> conta (crédito em receitas, débito em despesas). */
export interface EntradaDicionarioCategoria {
  categoria_id: number
  categoria?: string
  tipo: 'receita' | 'despesa'
  account_id: number | null
  rotulo?: string | null
}

export interface TransacaoIntegracao {
  uid: string
  tipo: 'receita' | 'despesa' | 'transferencia'
  source_type: string
  source_id: number
  data: string
  descricao: string
  valor: number
  conta_origem: string
  status_contabil: 'pendente' | 'importado'
  entry_id?: number | null
  entry_number?: number | null
  /** Números de todos os lançamentos gerados (mais de um quando a operação foi dividida). */
  entry_numbers?: number[]
  /** Divisão em vários lançamentos; a soma dos valores deve fechar com `valor`. */
  divisoes?: DivisaoIntegracao[]
  /** Verdadeiro quando `divisoes` foi sugerida pelo rateio de categorias do financeiro. */
  divisao_sugerida?: boolean
  /** Categoria única da operação (nulo quando não tem ou tem várias). */
  categoria_id?: number | null
  categorias?: CategoriaTransacao[]
  sugestao_origem?: OrigemSugestao
  sugestao_conta_id?: number
  debito_id?: number
  debito_rotulo?: string
  credito_id?: number
  credito_rotulo?: string
}

export interface KpisIntegracao {
  total_geral: number
  total_pendentes: number
  total_importados: number
  valor_pendente: number
  valor_importado: number
}

export interface ContaContabil {
  id: number
  code: string
  name: string
  type: 'ASSET' | 'LIABILITY' | 'EQUITY' | 'REVENUE' | 'EXPENSE'
  rotulo?: string
  nature?: 'DEBIT' | 'CREDIT'
  level?: number
  parent_id?: number | null
  accepts_entries?: boolean
  is_cash_account?: boolean
  active?: boolean
  balance?: number
  children?: ContaContabil[]
}

export interface LinhaLancamento {
  account_id: number
  side: 'D' | 'C'
  amount: number
  account_code?: string
  account_name?: string
  description?: string
}

export interface LancamentoContabil {
  id: number
  number: number
  accrual_date: string
  cash_date?: string | null
  description: string
  status: 'DRAFT' | 'POSTED' | 'REVERSED'
  source_type?: string | null
  source_id?: string | null
  idempotency_key?: string | null
  total?: number
  total_amount?: number
  lines?: LinhaLancamento[]
}

export interface PeriodoFiscal {
  id: number
  fiscal_year_id: number
  start_date: string
  end_date: string
  status: 'OPEN' | 'CLOSED'
}

export interface ExercicioFiscal {
  id: number
  label: string
  start_date: string
  end_date: string
  status: 'OPEN' | 'CLOSED'
  periods?: PeriodoFiscal[]
}

export interface RelatorioItem {
  code: string
  name: string
  type: string
  debit: number
  credit: number
  balance: number
}

// ---------------------------------------------------------------------------
// INTEGRAÇÃO FINANCEIRA
// ---------------------------------------------------------------------------

export async function listarTransacoesIntegracao(filtros: {
  data_inicio?: string
  data_fim?: string
  tipo?: string
  status?: string
  entity_id?: number
} = {}): Promise<{ transacoes: TransacaoIntegracao[]; kpis: KpisIntegracao }> {
  const params = new URLSearchParams()
  if (filtros.data_inicio) params.set('data_inicio', filtros.data_inicio)
  if (filtros.data_fim) params.set('data_fim', filtros.data_fim)
  if (filtros.tipo) params.set('tipo', filtros.tipo)
  if (filtros.status) params.set('status', filtros.status)
  if (filtros.entity_id) params.set('entity_id', String(filtros.entity_id))

  const res = await apiFetch<{ success: boolean; data: TransacaoIntegracao[]; kpis: KpisIntegracao }>(
    `contabil/integracao/transacoes?${params.toString()}`
  )
  return {
    transacoes: res.data || [],
    kpis: res.kpis || { total_geral: 0, total_pendentes: 0, total_importados: 0, valor_pendente: 0, valor_importado: 0 },
  }
}

export async function listarContasIntegracao(entityId = 1): Promise<ContaContabil[]> {
  const res = await apiFetch<{ success: boolean; data: ContaContabil[] }>(
    `contabil/integracao/contas?entity_id=${entityId}`
  )
  return res.data || []
}

export async function listarDicionarioCategorias(entityId = 1): Promise<EntradaDicionarioCategoria[]> {
  const res = await apiFetch<{ success: boolean; data: EntradaDicionarioCategoria[] }>(
    `contabil/integracao/categorias-contas?entity_id=${entityId}`
  )
  return res.data || []
}

/** Grava associações categoria -> conta (`account_id` nulo remove) e devolve o dicionário atualizado. */
export async function salvarDicionarioCategorias(
  itens: EntradaDicionarioCategoria[],
  entityId = 1
): Promise<EntradaDicionarioCategoria[]> {
  const res = await apiFetch<{ success: boolean; data: EntradaDicionarioCategoria[] }>(
    'contabil/integracao/categorias-contas',
    {
      method: 'POST',
      body: {
        itens: itens.map(({ categoria_id, tipo, account_id }) => ({ categoria_id, tipo, account_id })),
        entity_id: entityId,
      },
    }
  )
  return res.data || []
}

export async function importarTransacoesFinanceiras(
  itens: Partial<TransacaoIntegracao>[],
  postar = true,
  entityId = 1
): Promise<{ sucesso: number; ignorados: number; erros: string[]; message?: string }> {
  const res = await apiFetch<{ success: boolean; resultado: { sucesso: number; ignorados: number; erros: string[] }; message?: string }>(
    'contabil/integracao/importar',
    {
      method: 'POST',
      body: { itens, postar, entity_id: entityId },
    }
  )
  return {
    ...res.resultado,
    message: res.message,
  }
}

// ---------------------------------------------------------------------------
// PLANO DE CONTAS
// ---------------------------------------------------------------------------

export async function listarContas(entityId = 1): Promise<ContaContabil[]> {
  const res = await apiFetch<{ data?: ContaContabil[] } | ContaContabil[]>(`contabil/accounts?entity_id=${entityId}`)
  if (Array.isArray(res)) return res
  return res.data || []
}

export async function listarArvoreContas(entityId = 1): Promise<ContaContabil[]> {
  const res = await apiFetch<{ data?: ContaContabil[] } | ContaContabil[]>(`contabil/accounts/tree?entity_id=${entityId}`)
  if (Array.isArray(res)) return res
  return res.data || []
}

export async function criarConta(
  conta: {
    code: string
    name: string
    type: string
    parent_id?: number | null
    is_cash_account?: boolean
  },
  entityId = 1
): Promise<ContaContabil> {
  const res = await apiFetch<{ data: ContaContabil }>('contabil/accounts', {
    method: 'POST',
    body: { ...conta, entity_id: entityId },
  })
  return res.data || (res as unknown as ContaContabil)
}

// ---------------------------------------------------------------------------
// LANÇAMENTOS (LIVRO DIÁRIO)
// ---------------------------------------------------------------------------

export async function listarLancamentos(filtros: {
  date_from?: string
  date_to?: string
  status?: string
  entity_id?: number
  /** Paginação por cursor: id do último lançamento da página anterior (lista do mais novo ao mais velho). */
  before_id?: number | null
  limit?: number
} = {}): Promise<LancamentoContabil[]> {
  const params = new URLSearchParams()
  if (filtros.date_from) params.set('from', filtros.date_from)
  if (filtros.date_to) params.set('to', filtros.date_to)
  if (filtros.status) params.set('status', filtros.status)
  if (filtros.entity_id) params.set('entity_id', String(filtros.entity_id))
  if (filtros.before_id) params.set('before_id', String(filtros.before_id))
  if (filtros.limit) params.set('limit', String(filtros.limit))

  const res = await apiFetch<{ data?: LancamentoContabil[] } | LancamentoContabil[]>(
    `contabil/entries?${params.toString()}`
  )
  if (Array.isArray(res)) return res
  return res.data || []
}

export async function criarLancamento(
  dados: {
    accrual_date: string
    description: string
    lines: Array<{ account_id: number; side: 'D' | 'C'; amount: number; description?: string }>
    cash_date?: string
    post?: boolean
  },
  entityId = 1
): Promise<LancamentoContabil> {
  const res = await apiFetch<{ data: LancamentoContabil }>('contabil/entries', {
    method: 'POST',
    body: { ...dados, entity_id: entityId },
  })
  return res.data || (res as unknown as LancamentoContabil)
}

export async function estornarLancamento(id: number, motivo: string, date?: string): Promise<void> {
  await apiFetch(`contabil/entries/${id}/reverse`, {
    method: 'POST',
    body: { reason: motivo, date },
  })
}

/**
 * Lança a abertura contábil: saldos iniciais em um único lançamento de várias linhas.
 * Repetir na mesma data não duplica; `created` indica se o lançamento foi gravado agora.
 */
export async function lancarAbertura(
  dados: { data: string; saldos: SaldoAbertura[]; descricao?: string; postar?: boolean },
  entityId = 1,
): Promise<{ created: boolean; entry: LancamentoContabil; message?: string }> {
  const res = await apiFetch<{
    resultado: { created: boolean; entry: LancamentoContabil }
    message?: string
  }>('contabil/integracao/abertura', {
    method: 'POST',
    body: { ...dados, postar: dados.postar ?? true, entity_id: entityId },
  })
  return { ...res.resultado, message: res.message }
}

// ---------------------------------------------------------------------------
// RELATÓRIOS E DEMONSTRAÇÕES CONTÁBEIS
// ---------------------------------------------------------------------------

/** Centro de custo (processo, caso, cliente, ramo_direito) dos relatórios; sem id, todo o tipo. */
export interface FiltroDimensao {
  tipo: string
  id?: number | string | null
}

function aplicarDimensao(params: URLSearchParams, filtro?: FiltroDimensao | null): void {
  if (!filtro?.tipo) return
  params.set('dimension_type', filtro.tipo)
  if (filtro.id) params.set('dimension_id', String(filtro.id))
}

export async function obterBalancete(dataInicio?: string, dataFim?: string, entityId = 1, centroCusto?: FiltroDimensao | null): Promise<any> {
  const params = new URLSearchParams()
  if (dataInicio) params.set('from', dataInicio)
  if (dataFim) params.set('to', dataFim)
  params.set('entity_id', String(entityId))
  aplicarDimensao(params, centroCusto)

  const res = await apiFetch(`contabil/reports/trial-balance?${params.toString()}`)
  return res.data || res
}

export interface ContaSecaoRazao {
  id: number
  code: string
  name: string
  nature: 'DEBIT' | 'CREDIT'
}

export interface LinhaLivroRazao {
  date: string
  entry_id: number
  entry_number: number
  description: string
  account_id: number
  account_code: string
  debit: number
  credit: number
  balance: number
}

export interface SecaoLivroRazao {
  account: ContaSecaoRazao
  opening: number
  rows: LinhaLivroRazao[]
  debits: number
  credits: number
  closing: number
}

export interface LivroRazao {
  sections: SecaoLivroRazao[]
}

export interface LinhaLancamentoDiario {
  id?: number
  account_id: number
  side: 'D' | 'C'
  amount: number
  description?: string | null
  position?: number
}

export interface LancamentoDiario {
  id: number
  number: number
  accrual_date: string
  description: string
  status?: string
  total?: number
  lines?: LinhaLancamentoDiario[]
  [chave: string]: unknown
}

export interface LivroDiario {
  entries: LancamentoDiario[]
  total: number
}

/** Limite de páginas, para não entrar em laço se a API repetir o cursor. */
const MAX_PAGINAS_DIARIO = 500

/**
 * Livro Razão: quando `contaId` é informado (> 0), busca a conta (analítica ou sintética)
 * seguindo `next_cursor` até reunir todas as linhas do período; sem `contaId` (0 ou nulo),
 * consulta todas as contas com movimento por intervalo de código (`from_code`..`to_code`).
 */
export async function obterLivroRazao(
  contaId?: number | null,
  dataInicio?: string,
  dataFim?: string,
  entityId = 1,
  faixaCodigos: { from_code?: string; to_code?: string } = {},
): Promise<LivroRazao> {
  if (!contaId || contaId <= 0) {
    const params = new URLSearchParams({
      from_code: faixaCodigos.from_code || '0',
      to_code: faixaCodigos.to_code || '999999999',
    })
    if (dataInicio) params.set('from', dataInicio)
    if (dataFim) params.set('to', dataFim)
    params.set('entity_id', String(entityId))

    const res = await apiFetch<any>(`contabil/reports/ledger?${params.toString()}`)
    const data = res?.data ?? res
    const sections: SecaoLivroRazao[] = Array.isArray(data) ? data : data?.sections || []
    return { sections }
  }

  let secoes: SecaoLivroRazao[] = []
  let cursor: string | null = null
  const vistos = new Set<string>()

  for (let pagina = 0; pagina < MAX_PAGINAS_DIARIO; pagina++) {
    const params = new URLSearchParams({ account_id: String(contaId) })
    if (dataInicio) params.set('from', dataInicio)
    if (dataFim) params.set('to', dataFim)
    params.set('entity_id', String(entityId))
    if (cursor) params.set('cursor', cursor)

    const res = await apiFetch<any>(`contabil/reports/ledger?${params.toString()}`)
    const data = res?.data ?? res
    const paginaSecoes: SecaoLivroRazao[] = Array.isArray(data) ? data : data?.sections || []

    if (pagina === 0) {
      secoes = paginaSecoes.map((s) => ({ ...s, rows: [...(s.rows || [])] }))
    } else if (secoes[0] && paginaSecoes[0]?.rows?.length) {
      secoes[0].rows.push(...paginaSecoes[0].rows)
    }

    const proximo: string | null = data?.next_cursor || null
    if (!proximo || vistos.has(proximo)) break
    vistos.add(proximo)
    cursor = proximo
  }

  return { sections: secoes }
}

/**
 * Livro Diário: a API devolve `data.entries` paginado por `next_cursor`;
 * aqui todas as páginas do período são buscadas e reunidas.
 */
export async function obterLivroDiario(dataInicio?: string, dataFim?: string, entityId = 1): Promise<LivroDiario> {
  const entries: LancamentoDiario[] = []
  let total = 0
  let cursor: string | null = null
  const vistos = new Set<string>()

  for (let pagina = 0; pagina < MAX_PAGINAS_DIARIO; pagina++) {
    const params = new URLSearchParams()
    if (dataInicio) params.set('from', dataInicio)
    if (dataFim) params.set('to', dataFim)
    params.set('entity_id', String(entityId))
    if (cursor) params.set('cursor', cursor)

    const res = await apiFetch<any>(`contabil/reports/journal?${params.toString()}`)
    const data = res?.data ?? res
    const pagina_entries: LancamentoDiario[] = Array.isArray(data) ? data : data?.entries || []
    entries.push(...pagina_entries)
    if (typeof data?.total === 'number') total = data.total

    const proximo: string | null = data?.next_cursor || null
    if (!proximo || vistos.has(proximo)) break
    vistos.add(proximo)
    cursor = proximo
  }

  return { entries, total }
}

export async function obterDreContabil(dataInicio?: string, dataFim?: string, entityId = 1, centroCusto?: FiltroDimensao | null): Promise<any> {
  const params = new URLSearchParams()
  if (dataInicio) params.set('from', dataInicio)
  if (dataFim) params.set('to', dataFim)
  params.set('entity_id', String(entityId))
  aplicarDimensao(params, centroCusto)

  const res = await apiFetch(`contabil/reports/income-statement?${params.toString()}`)
  return res.data || res
}

export async function obterBalancoPatrimonial(data?: string, entityId = 1): Promise<any> {
  const params = new URLSearchParams()
  if (data) params.set('as_of', data)
  params.set('entity_id', String(entityId))

  const res = await apiFetch(`contabil/reports/balance-sheet?${params.toString()}`)
  return res.data || res
}

// ---------------------------------------------------------------------------
// EXERCÍCIOS FISCAIS E PERÍODOS
// ---------------------------------------------------------------------------

export async function listarExerciciosFiscais(entityId = 1): Promise<ExercicioFiscal[]> {
  const res = await apiFetch<{ data?: ExercicioFiscal[] } | ExercicioFiscal[]>(`contabil/fiscal-years?entity_id=${entityId}`)
  if (Array.isArray(res)) return res
  return res.data || []
}

export async function fecharPeriodo(periodoId: number): Promise<void> {
  await apiFetch(`contabil/periods/${periodoId}/close`, { method: 'POST' })
}

export async function reabrirPeriodo(periodoId: number, motivo: string): Promise<void> {
  await apiFetch(`contabil/periods/${periodoId}/reopen`, {
    method: 'POST',
    body: { reason: motivo },
  })
}

// ---------------------------------------------------------------------------
// IMPORTAÇÃO DO PLANO DE CONTAS
// ---------------------------------------------------------------------------

export interface ModeloPlano {
  id: string
  rotulo: string
}

export interface ResultadoImportacaoPlano {
  created: number
  skipped: number
}

/** Planos de contas modelo que acompanham o pacote contábil. */
export async function listarModelosPlano(): Promise<ModeloPlano[]> {
  const res = await apiFetch<{ data?: ModeloPlano[] }>('contabil/integracao/planos-modelo')
  return res.data || []
}

/** Importa um plano modelo; cria a entidade se faltar e não duplica contas existentes. */
export async function importarPlanoPadrao(model: string, entityId = 1): Promise<ResultadoImportacaoPlano> {
  const res = await apiFetch<{ resultado: ResultadoImportacaoPlano }>('contabil/integracao/plano-padrao', {
    method: 'POST',
    body: { model, entity_id: entityId },
  })
  return res.resultado
}

/**
 * Importa um plano de contas de um arquivo CSV ou JSON (tudo ou nada: se uma linha
 * falhar, nada é gravado). Contas com código já existente são mantidas.
 */
export async function importarPlanoArquivo(
  conteudo: string,
  formato: 'csv' | 'json',
  entityId = 1,
): Promise<ResultadoImportacaoPlano> {
  const res = await apiFetch<{ data: ResultadoImportacaoPlano }>(
    `contabil/accounts/import?entity_id=${entityId}&format=${formato}&skip_existing=1`,
    {
      method: 'POST',
      body: conteudo,
      headers: { 'Content-Type': formato === 'csv' ? 'text/csv' : 'application/json' },
    },
  )
  return res.data
}

/** Texto amigável de um erro de importação, com as linhas rejeitadas quando a API as informa. */
export function mensagemErroImportacao(e: any): string {
  const erro = e?.data?.error
  const base =
    typeof erro?.message === 'string'
      ? erro.message
      : typeof e?.message === 'string'
        ? e.message
        : 'Falha ao importar o plano de contas.'
  const detalhes: { row: number; message: string }[] = Array.isArray(erro?.details) ? erro.details : []
  if (detalhes.length === 0) return base
  const linhas = detalhes
    .slice(0, 5)
    .map((d) => `linha ${d.row}: ${d.message}`)
    .join('; ')
  return `${base} ${linhas}${detalhes.length > 5 ? ` (+${detalhes.length - 5})` : ''}`
}
