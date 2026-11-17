/** Regras do cliente para a importação de extratos OFX (o backend revalida tudo). */
import { formatarTamanho } from './midias'
import type { AlertaExtrato } from '../services/extratosApi'

/** Padrão do backend (EXTRATOS_MAX_MB); a API recusa acima do configurado. */
export const TAMANHO_MAXIMO_OFX = 5 * 1024 * 1024
export const ACCEPT_OFX = '.ofx,.OFX'

type ArquivoBasico = { name: string; size: number }

/** Mensagem de erro, ou null se o arquivo pode ser enviado. */
export function validarArquivoOfx(arquivo: ArquivoBasico): string | null {
  if (!/\.ofx$/i.test(arquivo.name)) return `"${arquivo.name}": envie um arquivo no formato OFX (.ofx).`
  if (arquivo.size === 0) return `"${arquivo.name}": o arquivo está vazio.`
  if (arquivo.size > TAMANHO_MAXIMO_OFX) {
    return `"${arquivo.name}": o arquivo tem ${formatarTamanho(arquivo.size)}; o máximo é ${formatarTamanho(TAMANHO_MAXIMO_OFX)}.`
  }
  return null
}

const CODIGOS_CONFIRMACAO = ['possivel_reemissao_de_fitid']

export function exigeConfirmacao(alerta: AlertaExtrato): boolean {
  return alerta.exige_confirmacao === true || CODIGOS_CONFIRMACAO.includes(alerta.codigo)
}

export function alertasQueExigemConfirmacao(alertas: AlertaExtrato[]): AlertaExtrato[] {
  return alertas.filter(exigeConfirmacao)
}

/** Período do arquivo em dd/mm/aaaa, ou "—" se ausente. */
export function periodoLegivel(periodo: [string | null, string | null] | null | undefined): string {
  const [inicio, fim] = periodo ?? [null, null]
  const fmt = (d: string | null) => (d && /^\d{4}-\d{2}-\d{2}/.test(d) ? d.slice(0, 10).split('-').reverse().join('/') : null)
  const a = fmt(inicio)
  const b = fmt(fim)
  if (a && b) return a === b ? a : `${a} a ${b}`
  return a ?? b ?? '—'
}

/** Valor em centavos para exibição em reais (centavos inteiros, sinal preservado). */
export function centavosParaReais(centavos: number): number {
  return centavos / 100
}

/** Resumo de uma prévia/resultado quando nada é novo. */
export function nadaNovo(r: { novos: number; duplicados: number }): boolean {
  return r.novos === 0 && r.duplicados > 0
}

// --- Movimentos (sistema#239) ---------------------------------------------------------------

export const LIMITE_LOTE = 50

export const ROTULO_STATUS: Record<string, string> = {
  pendente: 'Pendente',
  conciliado: 'Conciliado',
  lancado: 'Lançado',
  ignorado: 'Ignorado',
}

/** Centavos em R$ (ex.: -1500 → "-R$ 15,00"). */
export function formatarCentavos(centavos: number | null | undefined): string {
  if (centavos === null || centavos === undefined) return '—'
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(centavos / 100)
}

/** "12,50" ou "12.50" → 1250 centavos; vazio ou inválido → null. */
export function reaisParaCentavos(texto: string): number | null {
  const limpo = texto.trim().replace(/\./g, '').replace(',', '.')
  if (limpo === '' || Number.isNaN(Number(limpo))) return null
  return Math.round(Number(limpo) * 100)
}

/** Soma dos percentuais do rateio; null se estiver vazio (rateio é opcional). */
export function validarRateio(rateio: Array<{ id: unknown; percentual: number }>): string | null {
  if (rateio.length === 0) return null
  const soma = rateio.reduce((t, r) => t + Number(r.percentual || 0), 0)
  return Math.abs(soma - 100) < 0.005 ? null : `O rateio soma ${soma.toFixed(2).replace('.', ',')}%; precisa fechar 100%.`
}

/** Limita a seleção ao máximo do lote; devolve se houve corte. */
export function limitarSelecao<T>(ids: T[], limite = LIMITE_LOTE): { ids: T[]; cortou: boolean } {
  return { ids: ids.slice(0, limite), cortou: ids.length > limite }
}

/** Texto curto de um movimento para listas e diálogos. */
export function descricaoMovimento(m: { memo?: string | null; nome_contraparte?: string | null }): string {
  return (m.nome_contraparte || m.memo || 'Sem descrição').trim()
}

/** Diferença de saldo explicada em linguagem simples. */
export function textoSituacaoSaldo(situacao: string): string {
  switch (situacao) {
    case 'conferido': return 'Confere'
    case 'explicada_por_pendentes': return 'Diferença explicada pelos pendentes'
    case 'divergente': return 'Divergente'
    default: return 'Sem saldo do banco'
  }
}

/** Mensagem do resumo do mês para o fechamento. */
export function textoPendenciasExtrato(pendentes: number): string {
  if (pendentes <= 0) return 'Nenhum movimento do extrato pendente de conciliação.'
  return pendentes === 1
    ? '1 movimento do extrato ainda está pendente de conciliação.'
    : `${pendentes} movimentos do extrato ainda estão pendentes de conciliação.`
}

/**
 * Dados do lançamento em lote a partir da regra que casou com o movimento. Sem regra, ou com regra
 * incompleta (despesa sem fornecedor ou categoria), devolve o motivo para o item não entrar no lote.
 */
export function dadosPorRegra(s: {
  tipo: 'despesa' | 'receita'
  pode_lancar: boolean
  descricao_sugerida: string
  regra: { fornecedor_id: unknown; cliente_id: unknown; categoria_id: unknown; rateio: Array<{ id: unknown; percentual: number }> } | null
}): { dados: Record<string, unknown> } | { motivo: string } {
  if (!s.pode_lancar) return { motivo: 'O movimento não está mais pendente.' }
  const r = s.regra
  if (!r) return { motivo: 'Nenhuma regra casa com este movimento; lance individualmente.' }
  const dados: Record<string, unknown> = { descricao: s.descricao_sugerida }
  if (s.tipo === 'despesa') {
    if (!r.fornecedor_id || !r.categoria_id) return { motivo: 'A regra não define fornecedor e categoria; lance individualmente.' }
    dados.fornecedor_id = r.fornecedor_id
  } else if (r.cliente_id) {
    dados.cliente_id = r.cliente_id
  }
  if (r.categoria_id) dados.categoria_id = r.categoria_id
  if (r.rateio?.length) dados.rateio = r.rateio
  return { dados }
}
