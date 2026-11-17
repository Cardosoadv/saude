/**
 * Importação de extratos bancários (OFX). A prévia não grava nada; a importação grava só os
 * movimentos novos. Valores em centavos (negativo = saída), datas em AAAA-MM-DD.
 */
import { ApiError, apiFetch } from './api'
import type { Id } from '../types/domain'

export interface AlertaExtrato {
  codigo: string
  mensagem: string
  total?: number
  meses?: string[]
  enviado_em?: string[]
  /** Só a reemissão de FITID exige `confirmar=true`. */
  exige_confirmacao?: boolean
}

export interface MovimentoPrevia {
  situacao: 'novo' | 'duplicado' | 'conflito'
  data: string
  valor_centavos: number
  fitid: string | null
  memo?: string
  nome_contraparte?: string
}

export interface ResultadoExtrato {
  /** Nulo na prévia (nada foi gravado). */
  importacao_id: Id | null
  lidos: number
  novos: number
  duplicados: number
  conflitos: number
  periodo: [string | null, string | null]
  alertas: AlertaExtrato[]
  conflitos_detalhe: MovimentoPrevia[]
  /** Só na prévia. */
  movimentos?: MovimentoPrevia[]
}

export interface ImportacaoExtrato {
  id_importacao: Id
  conta_id: Id
  arquivo_nome: string
  periodo_inicio: string | null
  periodo_fim: string | null
  total_lidos: number
  total_novos: number
  total_duplicados: number
  total_conflitos: number
  status: string
  enviado_por: Id | null
  enviado_por_nome?: string | null
  created_at?: string | null
}

export interface PaginaImportacoes {
  itens: ImportacaoExtrato[]
  total: number
  page: number
}

/** Erro da API com os alertas que pedem confirmação (HTTP 409 com `requer_confirmacao`). */
export class ConfirmacaoNecessaria extends Error {
  alertas: AlertaExtrato[]

  constructor(message: string, alertas: AlertaExtrato[]) {
    super(message)
    this.name = 'ConfirmacaoNecessaria'
    this.alertas = alertas
  }
}

function corpo(contaId: Id, arquivo: File, confirmar = false): FormData {
  const form = new FormData()
  form.append('conta_id', String(contaId))
  form.append('arquivo', arquivo)
  if (confirmar) form.append('confirmar', 'true')
  return form
}

/** Mostra o que seria importado, sem gravar nada. */
export async function previaExtrato(contaId: Id, arquivo: File): Promise<ResultadoExtrato> {
  const res = await apiFetch<{ data: ResultadoExtrato }>('financeiro/extratos/previa', {
    method: 'POST',
    body: corpo(contaId, arquivo),
    timeout: 120000,
  })
  return res.data
}

/**
 * Grava os movimentos novos. Se a API pedir confirmação (409 `requer_confirmacao`), lança
 * `ConfirmacaoNecessaria`; reenvie com `confirmar = true` depois que a pessoa confirmar.
 */
export async function importarExtrato(contaId: Id, arquivo: File, confirmar = false): Promise<ResultadoExtrato> {
  try {
    const res = await apiFetch<{ data: ResultadoExtrato }>('financeiro/extratos/importar', {
      method: 'POST',
      body: corpo(contaId, arquivo, confirmar),
      timeout: 120000,
    })
    return res.data
  } catch (erro) {
    if (erro instanceof ApiError && erro.status === 409 && erro.data?.requer_confirmacao) {
      throw new ConfirmacaoNecessaria(erro.message, erro.data.alertas ?? [])
    }
    throw erro
  }
}

export async function listarImportacoes(contaId: Id | null, page = 1, perPage = 10): Promise<PaginaImportacoes> {
  const params = new URLSearchParams({ page: String(page), per_page: String(perPage) })
  if (contaId) params.set('conta_id', String(contaId))
  const res = await apiFetch<{ data?: ImportacaoExtrato[]; total?: number; page?: number }>(
    `financeiro/extratos/importacoes?${params.toString()}`,
  )
  return { itens: res?.data ?? [], total: res?.total ?? 0, page: res?.page ?? page }
}

// ---------------------------------------------------------------------------------------------
// Movimentos, conciliação, lançamento, regras e conferência (sistema#239)
// ---------------------------------------------------------------------------------------------

export type StatusMovimento = 'pendente' | 'conciliado' | 'lancado' | 'ignorado'
export type TipoPagamentoExtrato = 'despesa' | 'receita' | 'transferencia'

export interface MovimentoExtrato {
  id_movimento: Id
  conta_id: Id
  importacao_id?: Id
  data: string
  valor_centavos: number
  memo: string | null
  nome_contraparte: string | null
  status: StatusMovimento
  pagamento_tipo: TipoPagamentoExtrato | null
  pagamento_id: Id | null
  motivo_ignorado: string | null
}

export interface FiltrosMovimentos {
  conta_id?: Id | null
  data_inicio?: string
  data_fim?: string
  status?: StatusMovimento | ''
  q?: string
}

export interface PaginaMovimentos {
  itens: MovimentoExtrato[]
  total: number
  page: number
}

function query(params: Record<string, string | number | null | undefined>): string {
  const q = new URLSearchParams()
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && String(v) !== '') q.set(k, String(v))
  }
  const texto = q.toString()
  return texto ? `?${texto}` : ''
}

const BASE = 'financeiro/extratos'

export async function listarMovimentos(filtros: FiltrosMovimentos, page = 1, perPage = 50): Promise<PaginaMovimentos> {
  const res = await apiFetch<{ data?: MovimentoExtrato[]; total?: number; page?: number }>(
    `${BASE}/movimentos${query({ ...filtros, page, per_page: perPage })}`,
  )
  return { itens: res?.data ?? [], total: res?.total ?? 0, page: res?.page ?? page }
}

export interface CandidatoPagamento {
  tipo: string
  id: Id
  data: string
  valor_centavos: number
  descricao: string | null
  contraparte: string | null
  dias: number
  nome_confere: boolean
  pontos: number
}

export interface ParTransferencia {
  id_movimento: Id
  conta_id: Id
  data: string
  valor_centavos: number
  dias: number
}

export interface SugestoesConciliacao {
  movimento: MovimentoExtrato
  janela_dias: number
  pagamentos: CandidatoPagamento[]
  em_aberto: CandidatoPagamento[]
  pares_transferencia: ParTransferencia[]
  confianca: 'alta' | 'media' | null
}

export async function sugestoesConciliacao(movimentoId: Id, janela?: number): Promise<SugestoesConciliacao> {
  const res = await apiFetch<{ data: SugestoesConciliacao }>(`${BASE}/movimentos/${movimentoId}/sugestoes${query({ janela })}`)
  return res.data
}

export async function conciliarMovimento(
  movimentoId: Id, pagamentoTipo: TipoPagamentoExtrato, pagamentoId: Id, movimentoParId?: Id | null,
): Promise<MovimentoExtrato> {
  const res = await apiFetch<{ data: MovimentoExtrato }>(`${BASE}/movimentos/${movimentoId}/conciliar`, {
    method: 'POST',
    body: { pagamento_tipo: pagamentoTipo, pagamento_id: pagamentoId, ...(movimentoParId ? { movimento_par_id: movimentoParId } : {}) },
  })
  return res.data
}

export async function desconciliarMovimento(movimentoId: Id): Promise<MovimentoExtrato> {
  const res = await apiFetch<{ data: MovimentoExtrato }>(`${BASE}/movimentos/${movimentoId}/desconciliar`, { method: 'POST', body: {} })
  return res.data
}

export async function ignorarMovimento(movimentoId: Id, motivo: string): Promise<MovimentoExtrato> {
  const res = await apiFetch<{ data: MovimentoExtrato }>(`${BASE}/movimentos/${movimentoId}/ignorar`, { method: 'POST', body: { motivo } })
  return res.data
}

export interface SugestaoLote {
  movimento: MovimentoExtrato
  pagamento_tipo: TipoPagamentoExtrato
  pagamento_id: Id
  pagamento: CandidatoPagamento
}

export async function previaConciliacaoLote(contaId: Id, janela?: number): Promise<SugestaoLote[]> {
  const res = await apiFetch<{ data?: SugestaoLote[] }>(`${BASE}/conciliacao/lote${query({ conta_id: contaId, janela })}`)
  return res?.data ?? []
}

export interface ResultadoConciliacaoLote {
  conciliados: Id[]
  rejeitados: Array<{ movimento_id: Id; motivo: string }>
}

export async function conciliarLote(
  itens: Array<{ movimento_id: Id; pagamento_tipo: TipoPagamentoExtrato; pagamento_id: Id }>,
): Promise<ResultadoConciliacaoLote> {
  const res = await apiFetch<{ data: ResultadoConciliacaoLote }>(`${BASE}/conciliacao/lote`, {
    method: 'POST',
    body: { confirmar: true, itens },
  })
  return res.data
}

export interface RegraExtrato {
  id_regra: Id
  nome: string
  conta_id: Id | null
  campo: string
  padrao: string
  sinal: 'entrada' | 'saida' | 'ambos'
  fornecedor_id: Id | null
  cliente_id: Id | null
  categoria_id: Id | null
  rateio: Array<{ id: Id; percentual: number }>
  ativo: boolean
}

export interface ContraparteSugerida {
  id: Id
  nome: string
}

export interface SugestaoLancamento {
  movimento: MovimentoExtrato
  tipo: 'despesa' | 'receita'
  pode_lancar: boolean
  descricao_sugerida: string
  regra: RegraExtrato | null
  contrapartes: ContraparteSugerida[]
}

export async function sugestaoLancamento(movimentoId: Id): Promise<SugestaoLancamento> {
  const res = await apiFetch<{ data: SugestaoLancamento }>(`${BASE}/movimentos/${movimentoId}/sugestao-lancamento`)
  return res.data
}

export interface DadosLancamento {
  fornecedor_id?: Id
  novo_fornecedor?: { nome: string; documento?: string }
  cliente_id?: Id
  categoria_id?: Id
  categorias?: Array<{ categoria_id: Id; valor?: number }>
  rateio?: Array<{ id: Id; percentual: number }>
  descricao?: string
  comentario?: string
  criar_regra?: boolean
  quitar?: { tipo: 'despesa' | 'receita'; id: Id }
  movimento_par_id?: Id
}

export interface ResultadoLancamento {
  movimento: MovimentoExtrato
  pagamento_tipo: TipoPagamentoExtrato
  pagamento_id: Id
  regra?: RegraExtrato
  regra_erro?: string
}

export async function lancarMovimento(movimentoId: Id, dados: DadosLancamento): Promise<ResultadoLancamento> {
  const res = await apiFetch<{ data: ResultadoLancamento }>(`${BASE}/movimentos/${movimentoId}/lancar`, { method: 'POST', body: dados })
  return res.data
}

export interface ResultadoLancamentoLote {
  total: number
  lancados: number
  rejeitados: number
  itens: Array<{ movimento_id: Id; ok: boolean; pagamento_tipo: string | null; pagamento_id: Id | null; motivo: string | null }>
}

export async function lancarLote(itens: Array<DadosLancamento & { movimento_id: Id }>): Promise<ResultadoLancamentoLote> {
  const res = await apiFetch<{ data: ResultadoLancamentoLote }>(`${BASE}/movimentos/lancar-lote`, { method: 'POST', body: { itens } })
  return res.data
}

export async function listarRegras(contaId?: Id | null): Promise<RegraExtrato[]> {
  const res = await apiFetch<{ data?: RegraExtrato[] }>(`${BASE}/regras${query({ conta_id: contaId })}`)
  return res?.data ?? []
}

export async function excluirRegra(id: Id): Promise<void> {
  await apiFetch(`${BASE}/regras/${id}`, { method: 'DELETE' })
}

export async function criarRegra(dados: Partial<Omit<RegraExtrato, 'id_regra' | 'ativo'>>): Promise<RegraExtrato> {
  const res = await apiFetch<{ data: RegraExtrato }>(`${BASE}/regras`, { method: 'POST', body: dados })
  return res.data
}

export interface ConferenciaSaldo {
  conta_id: Id
  ano: number
  mes: number
  situacao: string
  mensagem: string
  data_saldo: string | null
  /** Centavos. */
  saldo_banco: number | null
  saldo_sistema: number | null
  diferenca: number | null
  total_pendentes: number
  soma_pendentes: number
  diferenca_nao_explicada: number | null
  pendentes: Array<Pick<MovimentoExtrato, 'id_movimento' | 'data' | 'valor_centavos' | 'memo' | 'nome_contraparte'>>
}

export async function conferirSaldo(contaId: Id, ano: number, mes: number): Promise<ConferenciaSaldo> {
  const res = await apiFetch<{ data: ConferenciaSaldo }>(`${BASE}/conferencia${query({ conta_id: contaId, ano, mes })}`)
  return res.data
}

export interface ResumoMesExtrato {
  ano: number
  mes: number
  pendentes: number
  ignorados: number
  conciliados: number
  lancados: number
  contas: Array<{ conta_id: Id; pendentes: number; ignorados: number; conciliados: number; lancados: number }>
}

export async function resumoMesExtrato(ano: number, mes: number): Promise<ResumoMesExtrato> {
  const res = await apiFetch<{ data: ResumoMesExtrato }>(`${BASE}/resumo-mes${query({ ano, mes })}`)
  return res.data
}

/** Anexa comprovantes ao pagamento que o movimento conciliou ou lançou (mesmos limites dos comprovantes). */
export async function anexarComprovantesAoMovimento(movimentoId: Id, arquivos: File[]): Promise<{ message?: string; erros?: Array<{ arquivo: string; mensagem: string }> }> {
  const corpo = new FormData()
  arquivos.forEach((a) => corpo.append('arquivos[]', a))
  return apiFetch(`${BASE}/movimentos/${movimentoId}/comprovantes`, { method: 'POST', body: corpo, timeout: 120000 })
}
