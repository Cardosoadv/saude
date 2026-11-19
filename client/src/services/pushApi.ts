/**
 * @version 1.0.0
 * Notificações push (PWA): configuração, inscrição do aparelho e teste.
 */
import { apiFetch } from './api'

export interface PreferenciasPush {
  prazos: boolean
}

export interface ConfigPush {
  habilitado: boolean
  chavePublica: string | null
  /** Preferências deste aparelho, quando ele já está inscrito para o usuário logado. */
  inscricao: PreferenciasPush | null
  /** Janela de envio (hora local da clínica). Ausente em backends antigos. */
  horario?: { inicio: number; fim: number }
}

interface Resposta<T> {
  success?: boolean
  message?: string
  data?: T
}

export const pushApi = {
  config: (endpoint?: string) =>
    apiFetch<Resposta<ConfigPush>>('push/config' + (endpoint ? '?endpoint=' + encodeURIComponent(endpoint) : '')),

  inscrever: (subscription: PushSubscriptionJSON, preferencias?: Partial<PreferenciasPush>) =>
    apiFetch<Resposta<PreferenciasPush>>('push/inscricao', { method: 'POST', body: { subscription, preferencias } }),

  remover: (endpoint: string, opts: { timeout?: number } = {}) =>
    apiFetch<Resposta<null>>('push/inscricao/remover', { method: 'POST', body: { endpoint }, ...opts }),

  teste: () => apiFetch<Resposta<{ enviadas: number }>>('push/teste', { method: 'POST' }),
}
