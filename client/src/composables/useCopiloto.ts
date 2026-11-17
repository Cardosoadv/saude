/**
 * Estado do Copiloto, compartilhado pela Topbar (botão e atalho) e pelo painel lateral.
 *
 * A conversa vive só em memória: some ao recarregar a página, ao sair ou ao trocar de escritório (`limpar()`).
 */
import { reactive, readonly } from 'vue'
import {
  confirmarAcao,
  enviarMensagem,
  mensagemDeErro,
  type AcaoPendenteCopiloto,
  type ContextoCopiloto,
  type ReferenciaCopiloto,
  type TurnoCopiloto,
} from '../services/copilotoApi'

export type EstadoAcao = 'pendente' | 'confirmando' | 'confirmada' | 'recusada' | 'expirada' | 'falhou'

export interface MensagemCopiloto {
  id: number
  papel: 'usuario' | 'assistente' | 'erro'
  texto: string
  referencias?: ReferenciaCopiloto[]
  acao?: AcaoPendenteCopiloto
  acaoEstado?: EstadoAcao
  acaoResultado?: string
}

/** Turnos enviados como histórico a cada pergunta (o backend corta de novo). */
export const MAX_TURNOS_HISTORICO = 10

const estado = reactive({
  aberto: false,
  carregando: false,
  mensagens: [] as MensagemCopiloto[],
})

let proximoId = 1
let controlador: AbortController | null = null

/** Notas que entram no histórico sem aparecer como balão (ex.: ação recusada). */
const notasHistorico = new Map<number, string>()

function adicionar(mensagem: Omit<MensagemCopiloto, 'id'>): MensagemCopiloto {
  const nova = { id: proximoId++, ...mensagem }
  estado.mensagens.push(nova)
  return estado.mensagens[estado.mensagens.length - 1]
}

export function historicoParaEnvio(mensagens: readonly MensagemCopiloto[] = estado.mensagens): TurnoCopiloto[] {
  const turnos: TurnoCopiloto[] = []
  for (const m of mensagens) {
    if (m.papel === 'usuario' || m.papel === 'assistente') {
      const nota = notasHistorico.get(m.id)
      const texto = [m.texto, nota].filter(Boolean).join('\n\n').trim()
      if (texto) turnos.push({ papel: m.papel, texto })
    }
  }
  return turnos.slice(-MAX_TURNOS_HISTORICO)
}

export function abrir(): void {
  estado.aberto = true
}

export function fechar(): void {
  estado.aberto = false
}

export function alternar(): void {
  estado.aberto = !estado.aberto
}

export function cancelar(): void {
  controlador?.abort()
}

/** Apaga a conversa e cancela a pergunta em andamento (logout, troca de escritório, "Nova conversa"). */
export function limpar(): void {
  cancelar()
  controlador = null
  estado.mensagens.splice(0)
  estado.carregando = false
  notasHistorico.clear()
}

export async function enviar(texto: string, contexto: ContextoCopiloto | null): Promise<void> {
  const mensagem = texto.trim()
  if (!mensagem || estado.carregando) return

  const historico = historicoParaEnvio()
  adicionar({ papel: 'usuario', texto: mensagem })
  estado.carregando = true
  const atual = (controlador = new AbortController())

  try {
    const resposta = await enviarMensagem(mensagem, historico, contexto, atual.signal)
    if (atual.signal.aborted) return
    adicionar({
      papel: 'assistente',
      texto: resposta.resposta,
      referencias: resposta.referencias,
      ...(resposta.acao_pendente ? { acao: resposta.acao_pendente, acaoEstado: 'pendente' as const } : {}),
    })
  } catch (erro) {
    adicionar({
      papel: 'erro',
      texto: atual.signal.aborted ? 'Pergunta cancelada.' : mensagemDeErro(erro),
    })
  } finally {
    if (controlador === atual) {
      controlador = null
      estado.carregando = false
    }
  }
}

function acharMensagem(id: number): MensagemCopiloto | undefined {
  return estado.mensagens.find((m) => m.id === id)
}

export function acaoExpirada(acao: AcaoPendenteCopiloto, agora = Date.now()): boolean {
  const expira = Date.parse(acao.expira_em)
  return Number.isFinite(expira) && expira <= agora
}

export async function confirmar(id: number): Promise<void> {
  const mensagem = acharMensagem(id)
  if (!mensagem?.acao || mensagem.acaoEstado !== 'pendente') return

  if (acaoExpirada(mensagem.acao)) {
    mensagem.acaoEstado = 'expirada'
    return
  }

  mensagem.acaoEstado = 'confirmando'
  try {
    const resultado = await confirmarAcao(mensagem.acao.token)
    mensagem.acaoEstado = 'confirmada'
    mensagem.acaoResultado = resultado.resultado
    mensagem.referencias = [...(mensagem.referencias ?? []), ...resultado.referencias]
    notasHistorico.set(id, `[Ação confirmada pelo usuário: ${mensagem.acao.resumo}. Resultado: ${resultado.resultado}]`)
  } catch (erro) {
    mensagem.acaoEstado = 'falhou'
    mensagem.acaoResultado = mensagemDeErro(erro)
    notasHistorico.set(id, `[A ação "${mensagem.acao.resumo}" falhou: ${mensagem.acaoResultado}]`)
  }
}

export function recusar(id: number): void {
  const mensagem = acharMensagem(id)
  if (!mensagem?.acao || mensagem.acaoEstado !== 'pendente') return
  mensagem.acaoEstado = 'recusada'
  notasHistorico.set(id, `[Ação recusada pelo usuário: ${mensagem.acao.resumo}. Nada foi gravado.]`)
}

export function useCopiloto() {
  return {
    estado: readonly(estado),
    abrir,
    fechar,
    alternar,
    enviar,
    cancelar,
    limpar,
    confirmar,
    recusar,
  }
}
