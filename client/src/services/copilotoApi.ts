/**
 * API do Copiloto Jurídico & Financeiro (`/api/v2/copiloto`, ordojuris#891).
 *
 * O backend não guarda a conversa: a SPA envia os últimos turnos de texto a cada pergunta.
 * Gravações nunca acontecem no chat: voltam em `acao_pendente` e só rodam em `confirmarAcao()`.
 */
import { apiFetch, ApiError } from './api'

export type TipoEntidadeCopiloto = 'processo' | 'cliente' | 'contrato' | 'caso' | 'liquidacao' | 'intimacao' | 'tarefa'

export interface ContextoCopiloto {
  entidade_tipo?: TipoEntidadeCopiloto
  entidade_id?: number
  tela?: string
}

export interface TurnoCopiloto {
  papel: 'usuario' | 'assistente'
  texto: string
}

export interface ReferenciaCopiloto {
  tipo: TipoEntidadeCopiloto
  id: number
  rotulo: string
}

export interface AcaoPendenteCopiloto {
  ferramenta: string
  resumo: string
  campos: { campo: string; valor: string }[]
  argumentos: Record<string, unknown>
  token: string
  expira_em: string
}

export interface RespostaCopiloto {
  resposta: string
  referencias: ReferenciaCopiloto[]
  ferramentas_usadas: string[]
  acao_pendente: AcaoPendenteCopiloto | null
  interacao_id: number | null
}

export interface ResultadoAcaoCopiloto {
  resultado: string
  referencias: ReferenciaCopiloto[]
}

export interface AcaoRapidaCopiloto {
  id: string
  rotulo: string
  prompt: string
}

const RAIZ = 'copiloto'

/** O modelo pode fazer várias consultas por pergunta: o tempo limite é maior que o padrão. */
const TEMPO_CHAT_MS = 130_000

/** Mensagem legível do erro: a API manda o código em `error` e o texto em `message`. */
export function mensagemDeErro(erro: unknown): string {
  if (erro instanceof ApiError) {
    if (typeof erro.data?.message === 'string' && erro.data.message) return erro.data.message
    if (erro.status === 403) return 'Você não tem acesso ao Copiloto.'
    if (erro.status === 0) return erro.message
  }
  return 'Não foi possível falar com o Copiloto agora. Tente de novo.'
}

export async function enviarMensagem(
  mensagem: string,
  historico: TurnoCopiloto[],
  contexto: ContextoCopiloto | null,
  signal?: AbortSignal,
): Promise<RespostaCopiloto> {
  const res = await apiFetch<{ data: RespostaCopiloto }>(`${RAIZ}/chat`, {
    method: 'POST',
    body: { mensagem, historico, contexto },
    timeout: TEMPO_CHAT_MS,
    signal,
  })
  return {
    resposta: res.data?.resposta ?? '',
    referencias: res.data?.referencias ?? [],
    ferramentas_usadas: res.data?.ferramentas_usadas ?? [],
    acao_pendente: res.data?.acao_pendente ?? null,
    interacao_id: res.data?.interacao_id ?? null,
  }
}

export async function confirmarAcao(token: string): Promise<ResultadoAcaoCopiloto> {
  const res = await apiFetch<{ data: ResultadoAcaoCopiloto }>(`${RAIZ}/confirmar`, {
    method: 'POST',
    body: { token },
    timeout: TEMPO_CHAT_MS,
  })
  return { resultado: res.data?.resultado ?? '', referencias: res.data?.referencias ?? [] }
}

export async function listarAcoesRapidas(entidadeTipo?: TipoEntidadeCopiloto | null): Promise<AcaoRapidaCopiloto[]> {
  const params = entidadeTipo ? `?entidade_tipo=${encodeURIComponent(entidadeTipo)}` : ''
  const res = await apiFetch<{ data: AcaoRapidaCopiloto[] }>(`${RAIZ}/acoes-rapidas${params}`)
  return Array.isArray(res.data) ? res.data : []
}
