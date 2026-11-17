/**
 * Notas fiscais de serviço (NFS-e) das receitas, emitidas pela API Brasil por meio da API do ordojuris.
 * A emissão é real (não há homologação): cada nota autorizada custa R$ 0,50 e tem validade fiscal.
 */
import { apiFetch, ApiError, BASE_URL } from './api'
import { cabecalhosDeTenant } from './tenant'

export type StatusNota =
  | 'enviando' | 'indeterminado' | 'criado' | 'em_processamento' | 'autorizado'
  | 'rejeitado' | 'falha' | 'cancelando' | 'cancelado'

export interface NotaFiscal {
  id_nota_fiscal: number
  receita_id: number
  status: StatusNota
  status_rotulo: string
  chave_nfe: string | null
  numero: string | null
  serie: string | null
  numero_nfse: string | null
  codigo_verificacao: string | null
  valor_servicos: number
  valor_liquido: number | null
  discriminacao: string
  tomador_documento: string
  tomador_nome: string
  pdf_url: string | null
  ultimo_erro: string | null
  emitida_em: string | null
  cancelamento_motivo: string | null
  created_at: string
  updated_at: string
  pode_atualizar: boolean
  pode_cancelar: boolean
  pode_descartar: boolean
  tem_documento: boolean
}

export interface PadroesFiscais {
  codigo_municipio: string | null
  servico_codigo: string | null
  codigo_nacional: string | null
  codigo_nbs: string | null
  codigo_cnae: string | null
  codigo_tributacao_municipio: string | null
  aliquota_iss: string | number | null
  regime_tributacao: string | null
  regime_apuracao: string | null
  percentual_simples: string | number | null
  cst: string | null
  discriminacao: string | null
}

export interface ConfiguracaoNfse {
  emitente: {
    id: string | null
    cnpj: string | null
    razao: string | null
    crt: string | null
    certificado_validade: string | null
  }
  padroes: PadroesFiscais
  pronto: boolean
  pendencias: string[]
  codigos_cancelamento: Record<string, string>
  /** Só para quem tem financeiro.nfse_configurar. */
  webhook_url?: string | null
}

export interface DadosEmitente {
  razao: string
  nome?: string
  cnpj: string
  cnae: string
  crt: number | string
  im?: string
  telefone: string
  email?: string
  rua: string
  numero: string
  complemento?: string
  bairro: string
  municipio: string
  cmun: string
  uf: string
  cep: string
}

export interface CamposEmissao extends PadroesFiscais {
  valor: number | string | null
  iss_retido: boolean
  data_competencia: string
}

export interface PreviaNota {
  receita: { id_receita: number; receita: string; valor: number; vencimento_dt: string; cliente_id: number | null }
  tomador: {
    cpf?: string
    cnpj?: string
    razao_social: string
    email?: string
    endereco: Record<string, string>
  } | null
  campos: CamposEmissao
  pendencias: string[]
  nota_ativa: NotaFiscal | null
}

interface Resposta<T> { success: boolean; message?: string; data: T }

export async function obterConfiguracao(): Promise<ConfiguracaoNfse> {
  return (await apiFetch<Resposta<ConfiguracaoNfse>>('financeiro/notas-fiscais/configuracao')).data
}

export async function salvarPadroes(padroes: Partial<PadroesFiscais>): Promise<Resposta<PadroesFiscais>> {
  return apiFetch('financeiro/notas-fiscais/configuracao', { method: 'PUT', body: padroes })
}

/** Cadastro atual do emitente na API Brasil (consulta gratuita). */
export async function obterEmitente(): Promise<Partial<Record<keyof DadosEmitente, string>> | null> {
  return (await apiFetch<Resposta<Partial<Record<keyof DadosEmitente, string>> | null>>('financeiro/notas-fiscais/emitente')).data
}

/** Cadastra (R$ 19,00 na API Brasil) ou atualiza o emitente. */
export async function salvarEmitente(dados: DadosEmitente): Promise<Resposta<{ acao: 'cadastrado' | 'vinculado' | 'atualizado' }>> {
  return apiFetch('financeiro/notas-fiscais/emitente', { method: 'POST', body: dados, timeout: 90000 })
}

export async function enviarCertificado(arquivo: File, senha: string): Promise<Resposta<{ validade: string | null; proprietario: string | null }>> {
  const corpo = new FormData()
  corpo.append('certificado', arquivo)
  corpo.append('senha', senha)
  return apiFetch('financeiro/notas-fiscais/certificado', { method: 'POST', body: corpo, timeout: 90000 })
}

export async function listarNotas(receitaId: number | string): Promise<NotaFiscal[]> {
  return (await apiFetch<Resposta<NotaFiscal[]>>(`financeiro/receitas/${receitaId}/notas-fiscais`)).data ?? []
}

export async function previaNota(receitaId: number | string, ajustes: Partial<CamposEmissao> = {}): Promise<PreviaNota> {
  return (await apiFetch<Resposta<PreviaNota>>(`financeiro/receitas/${receitaId}/notas-fiscais/previa`, { method: 'POST', body: ajustes })).data
}

export async function emitirNota(receitaId: number | string, campos: Partial<CamposEmissao>): Promise<Resposta<NotaFiscal>> {
  return apiFetch(`financeiro/receitas/${receitaId}/notas-fiscais`, { method: 'POST', body: campos, timeout: 90000 })
}

export async function atualizarNota(id: number): Promise<Resposta<NotaFiscal>> {
  return apiFetch(`financeiro/notas-fiscais/${id}/atualizar`, { method: 'POST', timeout: 60000 })
}

export async function cancelarNota(id: number, codigo: string, motivo: string): Promise<Resposta<NotaFiscal>> {
  return apiFetch(`financeiro/notas-fiscais/${id}/cancelar`, { method: 'POST', body: { codigo, motivo }, timeout: 60000 })
}

export async function descartarNota(id: number): Promise<Resposta<NotaFiscal>> {
  return apiFetch(`financeiro/notas-fiscais/${id}/descartar`, { method: 'POST' })
}

/** Baixa o PDF ou XML da nota e abre numa nova aba. */
export async function abrirDocumento(id: number, formato: 'pdf' | 'xml' = 'pdf'): Promise<void> {
  // Abre a aba já no clique (bloqueadores de pop-up barram janelas abertas depois de um await).
  const aba = window.open('', '_blank')
  try {
    const res = await fetch(`${BASE_URL}financeiro/notas-fiscais/${id}/documento?formato=${formato}`, {
      credentials: 'include',
      headers: { 'X-Requested-With': 'XMLHttpRequest', ...cabecalhosDeTenant() },
      signal: AbortSignal.timeout(60000),
    })
    if (!res.ok) {
      let mensagem = `HTTP ${res.status}`
      try { mensagem = (await res.json()).message || mensagem } catch { /* corpo não JSON */ }
      throw new ApiError(mensagem, res.status)
    }
    const url = URL.createObjectURL(await res.blob())
    if (aba) aba.location.href = url
    else window.location.href = url
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (e) {
    aba?.close()
    throw e
  }
}

export const STATUS_CLASSE: Record<StatusNota, string> = {
  enviando: 'badge-info',
  indeterminado: 'badge-warning',
  criado: 'badge-info',
  em_processamento: 'badge-info',
  autorizado: 'badge-success',
  rejeitado: 'badge-danger',
  falha: 'badge-danger',
  cancelando: 'badge-warning',
  cancelado: 'badge-secondary',
}
