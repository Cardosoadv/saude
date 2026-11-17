/**
 * API de liquidações (cálculo do crédito executado, parcelas, documentos) e de índices econômicos.
 * Valores monetários trafegam em centavos inteiros.
 */
import { apiFetch, BASE_URL } from './api'
import type { Id } from '../types/domain'
import type {
  Cenario, Criterio, HistoricoCalculo, IndiceEconomico, ItemLiquidacao, Liquidacao, LiquidacaoDetalhe,
  MemoriaCalculo, Pagamento, ResultadoCenario, SugestaoIa, TipoDocumento,
} from '../types/liquidacao'

export interface Paginacao { currentPage: number; pageCount: number; total: number; perPage: number }

export interface FiltrosLiquidacoes {
  status?: string
  tipo_liquidacao?: string
  processo_id?: Id
  cliente_id?: Id
  search?: string
  page?: number
}

const RAIZ = 'liquidacoes'

export async function listarLiquidacoes(filtros: FiltrosLiquidacoes = {}): Promise<{ data: Liquidacao[]; pager: Paginacao | null }> {
  const params = new URLSearchParams()
  Object.entries(filtros).forEach(([k, v]) => { if (v !== undefined && v !== null && v !== '') params.set(k, String(v)) })
  const res = await apiFetch<{ data: Liquidacao[]; pager: Paginacao | null }>(`${RAIZ}?${params}`)
  return { data: res.data ?? [], pager: res.pager ?? null }
}

export async function listarPorProcesso(processoId: Id): Promise<Liquidacao[]> {
  const res = await apiFetch<{ data: Liquidacao[] }>(`${RAIZ}/processo/${processoId}`)
  return res.data ?? []
}

export async function obterLiquidacao(id: Id): Promise<LiquidacaoDetalhe> {
  const res = await apiFetch<{ data: LiquidacaoDetalhe }>(`${RAIZ}/show/${id}`)
  return res.data
}

export async function criarLiquidacao(dados: Partial<Liquidacao> & { processo_id: Id }): Promise<Id> {
  const res = await apiFetch<{ id: Id }>(`${RAIZ}/store`, { method: 'POST', body: dados })
  return res.id
}

export async function atualizarLiquidacao(id: Id, dados: Record<string, unknown>): Promise<void> {
  await apiFetch(`${RAIZ}/update/${id}`, { method: 'PUT', body: dados })
}

export async function excluirLiquidacao(id: Id): Promise<void> {
  await apiFetch(`${RAIZ}/delete/${id}`, { method: 'DELETE' })
}

export async function historicoLiquidacao(id: Id): Promise<HistoricoCalculo[]> {
  const res = await apiFetch<{ data: HistoricoCalculo[] }>(`${RAIZ}/historico/${id}`)
  return res.data ?? []
}

// ---- Cálculo -------------------------------------------------------

/** Recalcula com os critérios atuais; `atualizarAteHoje` move a data-base para hoje. */
export async function recalcular(id: Id, opcoes: { atualizarAteHoje?: boolean; dataBase?: string; motivo?: string } = {}): Promise<void> {
  await apiFetch(`${RAIZ}/recalcular/${id}`, {
    method: 'POST',
    body: { atualizar_ate_hoje: opcoes.atualizarAteHoje ?? false, data_base: opcoes.dataBase, motivo: opcoes.motivo },
    timeout: 120000,
  })
}

export async function memoriaDoItem(itemId: Id): Promise<MemoriaCalculo | null> {
  const res = await apiFetch<{ data: { disponivel: boolean; memoria: MemoriaCalculo | null } }>(`${RAIZ}/memoria/${itemId}`)
  return res.data.disponivel ? res.data.memoria : null
}

/** Calcula sem gravar. Com `cenarios`, compara critérios diferentes para o mesmo item. */
export async function simular(corpo: {
  item_id?: Id
  item?: Partial<ItemLiquidacao>
  criterio_padrao?: Criterio
  data_base?: string
  cenarios?: Cenario[]
}): Promise<{ data_base: string; cenarios: ResultadoCenario[] }> {
  const res = await apiFetch<{ data: { data_base: string; cenarios: ResultadoCenario[] } }>(`${RAIZ}/simular`, {
    method: 'POST', body: corpo, timeout: 60000,
  })
  return res.data
}

export async function sugerirCriterio(liquidacaoId: Id, texto: string): Promise<SugestaoIa> {
  return apiFetch<SugestaoIa>(`${RAIZ}/sugerirCriterio/${liquidacaoId}`, { method: 'POST', body: { texto }, timeout: 90000 })
}

// ---- Itens ---------------------------------------------------------

export async function salvarItem(liquidacaoId: Id, item: Partial<ItemLiquidacao>, itemId?: Id): Promise<void> {
  if (itemId) {
    await apiFetch(`${RAIZ}/updateItem/${itemId}`, { method: 'PUT', body: item, timeout: 60000 })
  } else {
    await apiFetch(`${RAIZ}/storeItem`, { method: 'POST', body: { ...item, liquidacao_id: liquidacaoId }, timeout: 60000 })
  }
}

export async function excluirItem(itemId: Id): Promise<void> {
  await apiFetch(`${RAIZ}/deleteItem/${itemId}`, { method: 'DELETE' })
}

// ---- Parcelas e pagamentos -----------------------------------------

export interface PlanoParcelas {
  numero_parcelas: number
  primeiro_vencimento: string
  periodicidade: string
  /** Centavos. */
  entrada?: number
  data_entrada?: string
  substituir?: boolean
}

export async function gerarParcelas(liquidacaoId: Id, plano: PlanoParcelas): Promise<Pagamento[]> {
  const res = await apiFetch<{ parcelas: Pagamento[] }>(`${RAIZ}/gerarParcelas/${liquidacaoId}`, { method: 'POST', body: plano })
  return res.parcelas
}

export interface DadosBaixa {
  data_pagamento: string
  forma_pagamento?: string
  observacao?: string
  conta_id?: Id
}

/** Baixa a parcela; comprovantes (opcionais) vão ao Google Drive. */
export async function baixarParcela(pagamentoId: Id, dados: DadosBaixa, comprovantes: File[] = []): Promise<{ quitada: boolean; erros_upload: Array<{ arquivo: string; mensagem: string }>; message?: string }> {
  const corpo = new FormData()
  Object.entries(dados).forEach(([k, v]) => { if (v !== undefined && v !== '') corpo.append(k, String(v)) })
  comprovantes.forEach((arquivo) => corpo.append('arquivos[]', arquivo))
  return apiFetch(`${RAIZ}/baixarPagamento/${pagamentoId}`, { method: 'POST', body: corpo, timeout: 120000 })
}

export async function cancelarParcela(pagamentoId: Id, motivo?: string): Promise<void> {
  await apiFetch(`${RAIZ}/cancelarPagamento/${pagamentoId}`, { method: 'POST', body: { motivo } })
}

// ---- Documentos ----------------------------------------------------

export async function enviarDocumentos(liquidacaoId: Id, tipo: TipoDocumento, arquivos: File[]): Promise<{ erros: Array<{ arquivo: string; mensagem: string }> }> {
  const corpo = new FormData()
  corpo.append('tipo_documento', tipo)
  arquivos.forEach((arquivo) => corpo.append('arquivos[]', arquivo))
  return apiFetch(`${RAIZ}/uploadDocumento/${liquidacaoId}`, { method: 'POST', body: corpo, timeout: 120000 })
}

export async function excluirDocumento(documentoId: Id): Promise<void> {
  await apiFetch(`${RAIZ}/deleteDocumento/${documentoId}`, { method: 'DELETE' })
}

// ---- Relatórios (abrem em nova aba; a sessão vai por cookie) ----------

export const urlPdf = (id: Id): string => `${BASE_URL}${RAIZ}/pdf/${id}`
export const urlPlanilha = (id: Id, formato: 'csv' | 'xlsx'): string => `${BASE_URL}${RAIZ}/exportar/${id}?formato=${formato}`

// ---- Índices econômicos ----------------------------------------------

export async function listarIndices(): Promise<IndiceEconomico[]> {
  const res = await apiFetch<{ data: IndiceEconomico[] }>('indices')
  return res.data ?? []
}

export async function lacunasDoIndice(codigo: string): Promise<string[]> {
  const res = await apiFetch<{ data: string[] }>(`indices/${encodeURIComponent(codigo)}/lacunas`)
  return res.data ?? []
}

export async function importarCsvIndice(codigo: string, arquivo: File): Promise<{ novos?: number; atualizados?: number }> {
  const corpo = new FormData()
  corpo.append('arquivo', arquivo)
  const res = await apiFetch<{ data: { novos?: number; atualizados?: number } }>(`indices/${encodeURIComponent(codigo)}/csv`, { method: 'POST', body: corpo, timeout: 60000 })
  return res.data
}

export async function atualizarIndicesSgs(codigo?: string, historico = false): Promise<Record<string, { status: string; novos: number; mensagem?: string }>> {
  const res = await apiFetch<{ data: Record<string, { status: string; novos: number; mensagem?: string }> }>('indices/atualizar', {
    method: 'POST', body: { codigo, historico }, timeout: 120000,
  })
  return res.data
}

export async function salvarIndice(dados: Partial<IndiceEconomico>, id?: Id): Promise<void> {
  await apiFetch(id ? `indices/${id}` : 'indices', { method: id ? 'PUT' : 'POST', body: dados })
}
