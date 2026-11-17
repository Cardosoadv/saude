/** Auxiliares de exibição de prazos e da memória de cálculo. */
import type { MotivoDesconto } from '../services/calendarioForenseApi'

export const ROTULO_MOTIVO: Record<MotivoDesconto, string> = {
  fim_de_semana: 'Fim de semana',
  feriado: 'Feriado',
  recesso: 'Recesso forense',
  suspensao: 'Suspensão de expediente',
}

/** O servidor marca `prazo_a_conferir` (0/1) nas tarefas cujo prazo não veio de uma contagem. */
export function prazoAConferir(tarefa: { prazo_a_conferir?: number | string | boolean | null } | null | undefined): boolean {
  const v = tarefa?.prazo_a_conferir
  return v === true || v === 1 || v === '1'
}

/**
 * Uma intimação tem prazo a conferir quando a IA estimou uma data, mas não identificou
 * o número de dias (sem ele o servidor não consegue contar).
 */
export function intimacaoComPrazoAConferir(item: { ai_data_critica?: string | null; ai_categoria?: string | null; ai_detalhes_extracao?: string | null } | null | undefined): boolean {
  if (!item?.ai_data_critica || item.ai_categoria === 'audiencia') return false
  try {
    const detalhes = item.ai_detalhes_extracao ? JSON.parse(item.ai_detalhes_extracao) : {}
    return !(Number(detalhes?.prazo_dias) > 0)
  } catch {
    return true
  }
}
