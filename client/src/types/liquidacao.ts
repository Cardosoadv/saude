import type { Id } from './domain'

export type StatusLiquidacao =
  | 'rascunho' | 'em_calculo' | 'apresentada' | 'impugnada' | 'homologada' | 'em_pagamento' | 'quitada'
export type TipoLiquidacao = 'por_calculo' | 'por_arbitramento' | 'procedimento_comum'
export type TipoItem = 'principal' | 'juros' | 'multa' | 'honorarios' | 'custas' | 'outros'
export type StatusParcela = 'pendente' | 'paga' | 'vencida' | 'cancelada'
export type TipoDocumento = 'sentenca' | 'calculos' | 'comprovante' | 'outros'
export type RegraMes = 'cheio' | 'pro_rata' | 'excluir'

/** Todos os valores monetários da API de liquidação estão em centavos inteiros. */
export interface CriterioCorrecao {
  indice: string
  data_inicial?: string
  mes_inicial?: RegraMes
  mes_final?: RegraMes
}

export interface CriterioJuros {
  tipo: 'simples' | 'compostos' | 'nenhum'
  taxa_mensal?: string
  indice?: string
  /** Data (AAAA-MM-DD) ou "vencimento". */
  data_inicial?: string
  base?: 'corrigido' | 'nominal'
}

export interface Lancamento { data: string; valor: number }

export interface Criterio {
  usar_padrao?: boolean
  correcao?: CriterioCorrecao
  juros?: CriterioJuros
  mudancas?: Array<{ a_partir_de: string; correcao?: { indice: string }; juros?: CriterioJuros }>
  parcelas?: Lancamento[]
  abatimentos?: Lancamento[]
  percentual?: string
  base_percentual?: 'principal' | 'principal_juros' | 'total'
}

export interface ItemLiquidacao {
  item_id: Id
  liquidacao_id: Id
  tipo_item: TipoItem
  descricao: string
  valor_base: number
  valor_calculado: number
  criterio: Criterio
  tem_memoria?: boolean
}

export interface Pagamento {
  pagamento_id: Id
  liquidacao_id: Id
  numero_parcela: number
  valor_parcela: number
  data_vencimento: string
  data_pagamento: string | null
  status_pagamento: StatusParcela
  forma_pagamento: string | null
  comprovante_url: string | null
  observacao?: string | null
}

export interface DocumentoLiquidacao {
  documento_id: Id
  tipo_documento: TipoDocumento
  nome_arquivo: string
  url_arquivo: string
  pagamento_id?: Id | null
  data_upload: string
}

export interface ResumoPagamentos {
  total_parcelas: number
  valor_parcelado: number
  total_pago: number
  total_vencido: number
  saldo_a_receber: number
  proxima_parcela: { numero_parcela: number; data_vencimento: string; valor_parcela: number } | null
  diferenca_parcelamento: number | null
}

export interface HistoricoCalculo {
  historico_id: Id
  item_id: Id
  item_descricao?: string
  data_calculo: string
  valor_anterior: number
  valor_novo: number
  motivo_alteracao: string | null
}

export interface Liquidacao {
  liquidacao_id: Id
  processo_id: Id
  processo_numero?: string
  tipo_liquidacao: TipoLiquidacao
  status: StatusLiquidacao
  data_liquidacao: string | null
  data_homologacao: string | null
  data_base: string | null
  valor_total: number
  observacoes: string | null
  criterio_padrao?: Criterio
  lancar_honorarios?: number | string
  honorarios_percentual?: string | null
  calculado_em?: string | null
  /** Só na listagem. */
  total_pago?: number | string
  proxima_parcela_vencimento?: string | null
}

export interface LiquidacaoDetalhe extends Liquidacao {
  processo?: { numero_processo?: string; numeroprocessocommascara?: string; titulo_processo?: string } | null
  itens: ItemLiquidacao[]
  pagamentos: Pagamento[]
  documentos: DocumentoLiquidacao[]
  historico: HistoricoCalculo[]
  resumo_pagamentos: ResumoPagamentos
}

export interface ResumoLiquidacaoProcesso {
  liquidacao_id: Id
  status: StatusLiquidacao
  data_base: string | null
  valor_atualizado: number
  total_pago: number
  saldo_a_receber: number
  proxima_parcela_vencimento: string | null
}

/** Passo a passo de um item: ver CalculoLiquidacaoService. */
export interface MemoriaCalculo {
  tipo: 'manual' | 'calculado' | 'percentual' | 'nenhum'
  data_base: string
  valor: number
  total_corrigido?: number
  total_juros?: number
  parcelas?: Array<{
    data: string
    valor: number
    abatimento: boolean
    fator_correcao: string
    corrigido: number
    juros: { tipo?: string; valor: number; segmentos?: Array<Record<string, any>> }
    subtotal: number
    correcao: Array<{ indice: string; de: string; ate: string; fator: string; passos: Array<Record<string, any>> }>
  }>
  percentual?: string
  base_percentual?: string
  base_valor?: number
  avisos?: string[]
  criterio?: Criterio
}

export interface Cenario { nome: string; criterio: Criterio }
export interface ResultadoCenario { nome: string; valor: number; diferenca: number; memoria: MemoriaCalculo }

export interface IndiceEconomico {
  id_indice: Id
  codigo: string
  nome: string
  periodicidade: 'mensal' | 'diaria'
  fonte: 'sgs' | 'manual' | 'csv'
  codigo_serie: number | null
  ativo: number | string
  ultima_competencia?: string | null
  lacunas?: number
  atrasado?: boolean
}

export interface SugestaoIa {
  criterio: Criterio
  sugestao: { multa_percentual?: string; honorarios_percentual?: string; criterio: Criterio }
  trechos: string[]
  observacoes: string
  avisos: string[]
  requer_confirmacao: true
}
