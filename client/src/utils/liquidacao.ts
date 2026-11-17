/**
 * Regras de apresentação e conversão do módulo de liquidação. Todos os valores da API estão em
 * centavos inteiros; o usuário digita e vê reais.
 */
import type {
  Criterio, CriterioJuros, ItemLiquidacao, Pagamento, StatusLiquidacao, StatusParcela, TipoDocumento, TipoItem, TipoLiquidacao,
} from '../types/liquidacao'

export const STATUS_LIQUIDACAO: Record<StatusLiquidacao, string> = {
  rascunho: 'Rascunho',
  em_calculo: 'Em cálculo',
  apresentada: 'Apresentada',
  impugnada: 'Impugnada',
  homologada: 'Homologada',
  em_pagamento: 'Em pagamento',
  quitada: 'Quitada',
}

export const TIPOS_LIQUIDACAO: Record<TipoLiquidacao, string> = {
  por_calculo: 'Por cálculo',
  por_arbitramento: 'Por arbitramento',
  procedimento_comum: 'Pelo procedimento comum',
}

export const TIPOS_ITEM: Record<TipoItem, string> = {
  principal: 'Principal',
  juros: 'Juros',
  multa: 'Multa',
  honorarios: 'Honorários',
  custas: 'Custas',
  outros: 'Outros',
}

export const STATUS_PARCELA: Record<StatusParcela, string> = {
  pendente: 'Pendente',
  paga: 'Paga',
  vencida: 'Vencida',
  cancelada: 'Cancelada',
}

export const TIPOS_DOCUMENTO: Record<TipoDocumento, string> = {
  sentenca: 'Sentença',
  calculos: 'Cálculos',
  comprovante: 'Comprovante',
  outros: 'Outros',
}

export const PERIODICIDADES: Record<string, string> = {
  semanal: 'Semanal',
  quinzenal: 'Quinzenal',
  mensal: 'Mensal',
  bimestral: 'Bimestral',
  trimestral: 'Trimestral',
  semestral: 'Semestral',
  anual: 'Anual',
}

/** Itens cujo valor vem de um percentual sobre os demais (não de valor base + índice). */
export const ITENS_PERCENTUAIS: TipoItem[] = ['multa', 'honorarios']

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })

/** 123456 → "R$ 1.234,56". */
export function formatarReais(centavos: number | string | null | undefined): string {
  return BRL.format(Number(centavos ?? 0) / 100)
}

/** 123456 → "1.234,56" (sem símbolo, para campos de edição). */
export function centavosParaTexto(centavos: number | string | null | undefined): string {
  if (centavos === null || centavos === undefined || centavos === '') return ''
  return (Number(centavos) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

/**
 * "1.234,56" / "1234,56" / "1234.56" → 123456. Devolve null se o texto não é um valor monetário
 * válido (nunca adivinha). Aceita sinal negativo.
 */
export function textoParaCentavos(texto: string | number | null | undefined): number | null {
  if (texto === null || texto === undefined) return null
  const bruto = String(texto).trim().replace(/^R\$\s*/, '')
  if (bruto === '') return null

  let normal: string
  if (/^-?\d{1,3}(\.\d{3})*(,\d{1,2})?$/.test(bruto)) normal = bruto.replace(/\./g, '').replace(',', '.')
  else if (/^-?\d+(,\d{1,2})?$/.test(bruto)) normal = bruto.replace(',', '.')
  else if (/^-?\d+(\.\d{1,2})?$/.test(bruto)) normal = bruto
  else return null

  const [inteiro, decimais = ''] = normal.split('.')
  const sinal = inteiro.startsWith('-') ? -1 : 1
  return sinal * (Math.abs(parseInt(inteiro, 10)) * 100 + parseInt(decimais.padEnd(2, '0'), 10))
}

export type TomBadge = 'success' | 'warning' | 'danger' | 'info' | 'muted' | 'secondary'

export function tomStatus(status: StatusLiquidacao | string): TomBadge {
  switch (status) {
    case 'quitada': return 'success'
    case 'homologada': return 'info'
    case 'em_pagamento': return 'info'
    case 'impugnada': return 'danger'
    case 'apresentada': return 'warning'
    case 'em_calculo': return 'warning'
    default: return 'muted'
  }
}

export function tomParcela(status: StatusParcela | string): TomBadge {
  switch (status) {
    case 'paga': return 'success'
    case 'vencida': return 'danger'
    case 'cancelada': return 'muted'
    default: return 'warning'
  }
}

/** Parcela em aberto com vencimento anterior a `hoje` (AAAA-MM-DD), mesmo que o aviso diário ainda não rodou. */
export function parcelaAtrasada(p: Pick<Pagamento, 'status_pagamento' | 'data_vencimento'>, hoje: string): boolean {
  return p.status_pagamento === 'vencida' || (p.status_pagamento === 'pendente' && p.data_vencimento < hoje)
}

export function hojeISO(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const REGRA_MES: Record<string, string> = { cheio: 'mês cheio', pro_rata: 'pro rata', excluir: 'excluído' }

function dataBr(iso: string): string {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso)
  return m ? `${m[3]}/${m[2]}/${m[1]}` : iso
}

function descreverJuros(j: CriterioJuros): string {
  if (j.tipo === 'nenhum') return 'sem juros'
  const taxa = j.indice ? `pelo ${j.indice}` : `${String(j.taxa_mensal ?? '0').replace('.', ',')}% ao mês`
  const inicio = j.data_inicial && j.data_inicial !== 'vencimento' ? `desde ${dataBr(j.data_inicial)}` : 'desde o vencimento'
  return `juros ${j.tipo === 'compostos' ? 'compostos' : 'simples'} ${taxa}, ${inicio}`
}

/** Resumo de uma linha do critério, para a tabela de itens. */
export function resumirCriterio(c: Criterio | null | undefined, item?: Pick<ItemLiquidacao, 'tipo_item'>): string {
  if (!c || Object.keys(c).length === 0) return 'Valor informado manualmente'
  const partes: string[] = []
  if (c.usar_padrao) partes.push('critério padrão da liquidação')
  if (c.percentual) {
    const base = { principal: 'principal corrigido', principal_juros: 'principal com juros', total: 'total' }[c.base_percentual ?? 'principal']
    partes.push(`${c.percentual.replace('.', ',')}% sobre ${base}`)
  }
  if (c.correcao) {
    const meses = [c.correcao.mes_inicial, c.correcao.mes_final].some((r) => r && r !== 'cheio')
      ? ` (início: ${REGRA_MES[c.correcao.mes_inicial ?? 'cheio']}, fim: ${REGRA_MES[c.correcao.mes_final ?? 'cheio']})` : ''
    partes.push(`correção pelo ${c.correcao.indice}${meses}`)
  }
  if (c.juros) partes.push(descreverJuros(c.juros))
  if (c.mudancas?.length) partes.push(`${c.mudancas.length} troca${c.mudancas.length > 1 ? 's' : ''} de critério por data`)
  if (c.parcelas?.length) partes.push(`${c.parcelas.length} competência${c.parcelas.length > 1 ? 's' : ''}`)
  if (c.abatimentos?.length) partes.push(`${c.abatimentos.length} abatimento${c.abatimentos.length > 1 ? 's' : ''}`)
  if (!partes.length && item) partes.push(TIPOS_ITEM[item.tipo_item] ?? 'critério vazio')
  return partes.join('; ')
}

/** Remove chaves vazias para o backend (que recusa chaves desconhecidas, mas aceita critério vazio). */
export function limparCriterio(c: Criterio): Criterio {
  const saida: Criterio = {}
  if (c.usar_padrao) saida.usar_padrao = true
  if (c.correcao?.indice) saida.correcao = { ...c.correcao, indice: c.correcao.indice.trim().toUpperCase() }
  if (c.juros && c.juros.tipo) {
    const j: CriterioJuros = { tipo: c.juros.tipo }
    if (j.tipo !== 'nenhum') {
      if (c.juros.indice) j.indice = c.juros.indice
      else if (c.juros.taxa_mensal) j.taxa_mensal = String(c.juros.taxa_mensal).replace(',', '.')
      if (c.juros.data_inicial) j.data_inicial = c.juros.data_inicial
      if (c.juros.base) j.base = c.juros.base
    }
    saida.juros = j
  }
  if (c.mudancas?.length) saida.mudancas = c.mudancas
  if (c.parcelas?.length) saida.parcelas = c.parcelas
  if (c.abatimentos?.length) saida.abatimentos = c.abatimentos
  if (c.percentual) {
    saida.percentual = String(c.percentual).replace(',', '.')
    if (c.base_percentual) saida.base_percentual = c.base_percentual
  }
  return saida
}

/** Total exibido no painel de saldo: o que falta receber de uma liquidação. */
export function saldoAReceber(valorTotal: number, totalPago: number): number {
  return Math.max(Number(valorTotal) - Number(totalPago), 0)
}
