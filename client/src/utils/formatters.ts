/**
 * Formata input de moeda (dinheiro) adicionando vírgulas e pontos
 */
export function formatMoneyInput(value: string | number): string {
  let val = String(value).replace(/\D/g, '')
  if (!val) {
    return ''
  }
  val = (parseFloat(val) / 100).toFixed(2)
  val = val.replace('.', ',')
  val = val.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.')
  return val
}

/**
 * Formata um valor inicial numérico para string de moeda
 */
export function formatInitialMoney(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return ''
  const parsed = parseFloat(String(value))
  if (isNaN(parsed)) return ''
  return parsed.toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.')
}

/**
 * Formata uma string de números para o formato CNJ (0000000-00.0000.0.00.0000)
 */
export function formatCNJ(numero?: string | null): string {
  if (!numero) return ''
  const limpo = numero.replace(/\D/g, '')
  if (limpo.length !== 20) return numero
  return limpo.replace(/^(\d{7})(\d{2})(\d{4})(\d)(\d{2})(\d{4})$/, '$1-$2.$3.$4.$5.$6')
}

/**
 * Aplica máscara parcial CNJ durante a digitação
 */
export function applyCNJMask(value: string): string {
  let limpo = value.replace(/\D/g, '').substring(0, 20)
  let formatted = ''
  for (let i = 0; i < limpo.length; i++) {
    if (i === 7) formatted += '-'
    if (i === 9) formatted += '.'
    if (i === 13) formatted += '.'
    if (i === 14) formatted += '.'
    if (i === 16) formatted += '.'
    formatted += limpo[i]
  }
  return formatted
}
