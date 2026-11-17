/**
 * Aviso de alterações não salvas ao trocar de tela. A tela de edição marca o estado
 * (`marcarSujo`); a navegação principal (`App.vue#setView`) pergunta antes de sair.
 */
let sujo = false

export function marcarSujo(valor: boolean): void {
  sujo = valor
}

export function temAlteracoesNaoSalvas(): boolean {
  return sujo
}

export const MENSAGEM_SAIDA = 'Há alterações não salvas. Sair mesmo assim?'

/** Verdadeiro se pode sair: não há alterações, ou o usuário confirmou descartá-las. */
export function confirmarSaida(perguntar: (mensagem: string) => boolean = (m) => window.confirm(m)): boolean {
  if (!sujo) return true
  const sair = perguntar(MENSAGEM_SAIDA)
  if (sair) sujo = false
  return sair
}
