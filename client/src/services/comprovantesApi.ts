/**
 * Comprovantes de pagamento (financeiro). Os arquivos ficam no Google Drive, na pasta do
 * financeiro, em subpastas mensais; a API só guarda o vínculo com o pagamento.
 */
import { apiFetch } from './api'
import type { Comprovante, Id } from '../types/domain'

export type TipoPagamento = 'despesa' | 'receita'

export interface RespostaEnvio {
  success: boolean
  message?: string
  data: Comprovante[]
  /** Falhas por arquivo (envio parcial). */
  erros?: Array<{ arquivo: string; mensagem: string }>
}

const RAIZ: Record<TipoPagamento, string> = {
  despesa: 'financeiro/pagamentoDespesas',
  receita: 'financeiro/pagamentoReceitas',
}

export async function listarComprovantes(tipo: TipoPagamento, pagamentoId: Id): Promise<Comprovante[]> {
  const res = await apiFetch<{ data?: Comprovante[] }>(`${RAIZ[tipo]}/${pagamentoId}/comprovantes`)
  return res?.data ?? []
}

/** Envia um ou mais arquivos (campo `arquivos[]`). Envio de arquivo demora mais que o padrão da API. */
export async function enviarComprovantes(tipo: TipoPagamento, pagamentoId: Id, arquivos: File[]): Promise<RespostaEnvio> {
  const corpo = new FormData()
  arquivos.forEach((arquivo) => corpo.append('arquivos[]', arquivo))

  return apiFetch<RespostaEnvio>(`${RAIZ[tipo]}/${pagamentoId}/comprovantes`, {
    method: 'POST',
    body: corpo,
    timeout: 120000,
  })
}

/** Move o arquivo para a lixeira do Drive e remove o comprovante da lista. */
export async function removerComprovante(id: Id): Promise<void> {
  await apiFetch(`financeiro/comprovantes/${id}`, { method: 'DELETE' })
}

export interface PagamentoLote {
  tipo: TipoPagamento
  id: Id
}

/** Anexa os mesmos arquivos a vários pagamentos (campos `pagamentos[]` = "despesa:12" e `arquivos[]`). */
export async function enviarComprovantesLote(pagamentos: PagamentoLote[], arquivos: File[]): Promise<RespostaEnvio> {
  const corpo = new FormData()
  pagamentos.forEach((p) => corpo.append('pagamentos[]', `${p.tipo}:${p.id}`))
  arquivos.forEach((arquivo) => corpo.append('arquivos[]', arquivo))

  return apiFetch<RespostaEnvio>('financeiro/comprovantes/lote', {
    method: 'POST',
    body: corpo,
    timeout: 120000,
  })
}

export interface PendenciasDoMes {
  ano: number
  mes: number
  total: number
  despesas: number
  receitas: number
  valor_despesas: number
  valor_receitas: number
  contas: Array<{ conta_id: Id; conta: string; despesas: number; receitas: number }>
}

/** Pagamentos do mês sem comprovante (informativo: não impede o fechamento). */
export async function pendenciasDoMes(ano: number, mes: number): Promise<PendenciasDoMes> {
  const res = await apiFetch<{ data: PendenciasDoMes }>(`financeiro/comprovantes/pendencias?ano=${ano}&mes=${mes}`)
  return res.data
}
