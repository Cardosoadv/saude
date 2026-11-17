/**
 * Regras do cliente para comprovantes de pagamento. O backend revalida tudo
 * (tipo pelo conteúdo, tamanho e quantidade); aqui só se evita o envio inútil.
 */
import { formatarTamanho } from './midias'

export const TIPOS_ACEITOS = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'] as const
export const TAMANHO_MAXIMO = 10 * 1024 * 1024
export const MAX_ARQUIVOS = 5
/** Máximo de pagamentos por envio em lote (igual ao limite do backend). */
export const MAX_PAGAMENTOS_LOTE = 50

export const ACCEPT_INPUT = [...TIPOS_ACEITOS, '.pdf', '.jpg', '.jpeg', '.png', '.webp'].join(',')

type ArquivoBasico = { name: string; type: string; size: number }

/** Mensagem de erro, ou null se o arquivo pode ser enviado. */
export function validarArquivo(arquivo: ArquivoBasico): string | null {
  if (!(TIPOS_ACEITOS as readonly string[]).includes(arquivo.type)) {
    return `"${arquivo.name}": envie PDF, JPG, PNG ou WEBP.`
  }
  if (arquivo.size === 0) {
    return `"${arquivo.name}": o arquivo está vazio.`
  }
  if (arquivo.size > TAMANHO_MAXIMO) {
    return `"${arquivo.name}": o arquivo tem ${formatarTamanho(arquivo.size)}; o máximo é ${formatarTamanho(TAMANHO_MAXIMO)}.`
  }
  return null
}

/** Valida o lote inteiro (quantidade e cada arquivo). Devolve a primeira mensagem de erro, ou null. */
export function validarLote(arquivos: ArquivoBasico[]): string | null {
  if (arquivos.length === 0) return 'Selecione ao menos um arquivo.'
  if (arquivos.length > MAX_ARQUIVOS) return `Envie no máximo ${MAX_ARQUIVOS} arquivos por vez.`
  for (const arquivo of arquivos) {
    const erro = validarArquivo(arquivo)
    if (erro) return erro
  }
  return null
}

/** Pasta mensal (AAAA-MM) em texto legível: "10/2026". */
export function mesLegivel(pastaMes: string | null | undefined): string {
  const m = /^(\d{4})-(\d{2})$/.exec(pastaMes ?? '')
  return m ? `${m[2]}/${m[1]}` : (pastaMes ?? '')
}

/** Texto do resumo do lote: "3 pagamentos selecionados". */
export function resumoLote(total: number): string {
  return `${total} ${total === 1 ? 'pagamento selecionado' : 'pagamentos selecionados'}`
}

/** Frase do aviso de fechamento: "3 despesas e 1 receita sem comprovante." */
export function textoPendencias(p: { despesas: number; receitas: number }): string {
  const partes: string[] = []
  if (p.despesas > 0) partes.push(`${p.despesas} ${p.despesas === 1 ? 'despesa' : 'despesas'}`)
  if (p.receitas > 0) partes.push(`${p.receitas} ${p.receitas === 1 ? 'receita' : 'receitas'}`)
  const lista = partes.join(' e ')
  const total = p.despesas + p.receitas
  return total === 0 ? 'Nenhum pagamento sem comprovante.' : `${lista} sem comprovante neste mês.`
}
