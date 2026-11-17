/**
 * Notificações push (PWA): permissão do navegador, inscrição deste aparelho e preferências.
 *
 * Estado em módulo (singleton). Depende do service worker registrado pelo usePwa, ou seja,
 * só funciona no build de produção servido por https. No iPhone/iPad, só com o app instalado
 * na Tela de Início (iOS 16.4+).
 */
import { ref, readonly } from 'vue'
import { pushApi, type PreferenciasPush } from '../services/pushApi'
import { ehIosSafari, emModoApp } from './usePwa'

export type SituacaoPush =
  | 'carregando'
  | 'sem-suporte'      // navegador sem Push API ou sem service worker ativo
  | 'instalar-ios'     // iPhone/iPad fora do app instalado
  | 'servidor-off'     // backend sem chaves VAPID
  | 'bloqueado'        // usuário negou a permissão no navegador
  | 'desativado'       // pode ativar
  | 'ativado'
  | 'erro'             // falha ao consultar o servidor (ex.: sem conexão)

const PREFERENCIAS_PADRAO: PreferenciasPush = { intimacoes: true, prazos: true }

const situacao = ref<SituacaoPush>('carregando')
const preferencias = ref<PreferenciasPush>({ ...PREFERENCIAS_PADRAO })
const ocupado = ref(false)
const horario = ref<{ inicio: number; fim: number } | null>(null)
let chavePublica: string | null = null

/** Converte a chave VAPID (base64url) no formato aceito por pushManager.subscribe. */
export function chaveParaBytes(base64url: string): Uint8Array<ArrayBuffer> {
  const preenchido = base64url + '='.repeat((4 - (base64url.length % 4)) % 4)
  const binario = atob(preenchido.replace(/-/g, '+').replace(/_/g, '/'))
  const bytes = new Uint8Array(new ArrayBuffer(binario.length))
  for (let i = 0; i < binario.length; i++) bytes[i] = binario.charCodeAt(i)
  return bytes
}

function mesmaChave(sub: PushSubscription, chave: string): boolean {
  const atual = sub.options?.applicationServerKey
  if (!atual) return true // navegador não informa: assume a mesma
  const a = new Uint8Array(atual)
  const b = chaveParaBytes(chave)
  return a.length === b.length && a.every((v, i) => v === b[i])
}

export function pushSuportado(): boolean {
  return typeof window !== 'undefined'
    && 'serviceWorker' in navigator
    && 'PushManager' in window
    && 'Notification' in window
    && window.isSecureContext === true
}

/** Registro do SW já ativo (não espera indefinidamente: em dev não há SW). */
async function registroAtivo(): Promise<ServiceWorkerRegistration | null> {
  if (!('serviceWorker' in navigator)) return null
  const reg = await navigator.serviceWorker.getRegistration()
  if (!reg) return null
  if (reg.active) return reg
  return Promise.race([
    navigator.serviceWorker.ready,
    new Promise<null>((resolve) => setTimeout(() => resolve(null), 5000)),
  ])
}

async function assinaturaAtual(): Promise<PushSubscription | null> {
  const reg = await registroAtivo()
  return reg ? reg.pushManager.getSubscription() : null
}

/** Lê a situação deste aparelho (chamar ao abrir a tela de notificações). */
export async function carregarPush(): Promise<void> {
  situacao.value = 'carregando'
  try {
    if (!pushSuportado()) {
      situacao.value = ehIosSafari() && !emModoApp() ? 'instalar-ios' : 'sem-suporte'
      return
    }
    const reg = await registroAtivo()
    if (!reg) {
      situacao.value = 'sem-suporte'
      return
    }

    const sub = await reg.pushManager.getSubscription()
    const { data } = await pushApi.config(sub?.endpoint)
    chavePublica = data?.chavePublica ?? null
    horario.value = data?.horario ?? null

    if (!data?.habilitado || !chavePublica) {
      situacao.value = 'servidor-off'
      return
    }
    if (Notification.permission === 'denied') {
      situacao.value = 'bloqueado'
      return
    }

    if (sub && data.inscricao && Notification.permission === 'granted' && mesmaChave(sub, chavePublica)) {
      preferencias.value = { ...data.inscricao }
      situacao.value = 'ativado'
    } else {
      preferencias.value = { ...PREFERENCIAS_PADRAO }
      situacao.value = 'desativado'
    }
  } catch (e) {
    console.warn('[Push] Falha ao carregar a situação das notificações:', e)
    situacao.value = 'erro'
  }
}

/** Pede a permissão, inscreve o aparelho e salva no servidor. */
export async function ativarPush(prefs: PreferenciasPush = preferencias.value): Promise<void> {
  if (!chavePublica) throw new Error('Notificações não estão configuradas no servidor.')
  ocupado.value = true
  try {
    const permissao = await Notification.requestPermission()
    if (permissao !== 'granted') {
      situacao.value = permissao === 'denied' ? 'bloqueado' : 'desativado'
      throw new Error(permissao === 'denied'
        ? 'As notificações foram bloqueadas no navegador. Libere nas configurações do site para ativar.'
        : 'Permissão de notificação não concedida.')
    }

    const reg = await registroAtivo()
    if (!reg) throw new Error('O app ainda não está pronto para notificações. Recarregue a página e tente de novo.')

    let sub = await reg.pushManager.getSubscription()
    if (sub && !mesmaChave(sub, chavePublica)) {
      await sub.unsubscribe() // chave do servidor mudou: refaz a inscrição
      sub = null
    }
    if (!sub) {
      try {
        sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: chaveParaBytes(chavePublica) })
      } catch (e) {
        console.warn('[Push] Falha ao inscrever no serviço de push do navegador:', e)
        throw new Error('O navegador não conseguiu se registrar no serviço de notificações. '
          + 'Verifique se ele está atualizado e com acesso à internet (redes corporativas podem bloquear) e tente de novo.')
      }
    }

    const { data } = await pushApi.inscrever(sub.toJSON(), prefs)
    preferencias.value = data ? { ...data } : { ...prefs }
    situacao.value = 'ativado'
  } finally {
    ocupado.value = false
  }
}

/** Cancela a inscrição deste aparelho (navegador e servidor). */
export async function desativarPush(): Promise<void> {
  ocupado.value = true
  try {
    const sub = await assinaturaAtual()
    if (sub) {
      await pushApi.remover(sub.endpoint).catch(() => undefined)
      await sub.unsubscribe()
    }
    situacao.value = Notification.permission === 'denied' ? 'bloqueado' : 'desativado'
  } finally {
    ocupado.value = false
  }
}

/** Salva as preferências (intimações / prazos) deste aparelho. */
export async function salvarPreferenciasPush(prefs: PreferenciasPush): Promise<void> {
  const sub = await assinaturaAtual()
  if (!sub) throw new Error('Este aparelho não está inscrito.')
  ocupado.value = true
  try {
    const { data } = await pushApi.inscrever(sub.toJSON(), prefs)
    preferencias.value = data ? { ...data } : { ...prefs }
  } finally {
    ocupado.value = false
  }
}

export async function testarPush(): Promise<string> {
  ocupado.value = true
  try {
    const r = await pushApi.teste()
    return r.message || 'Notificação de teste enviada.'
  } finally {
    ocupado.value = false
  }
}

/**
 * Ao entrar no sistema: se o aparelho já tem inscrição e permissão, reenvia ao servidor
 * (mantém o dono correto e recupera inscrições renovadas pelo navegador). Silencioso.
 */
export async function sincronizarPush(): Promise<void> {
  try {
    if (!pushSuportado() || Notification.permission !== 'granted') return
    const sub = await assinaturaAtual()
    if (!sub) return
    const { data } = await pushApi.config(sub.endpoint)
    if (!data?.habilitado || !data.chavePublica || !mesmaChave(sub, data.chavePublica)) return
    if (data.inscricao) {
      // Reafirma a inscrição deste usuário; nunca liga notificações por conta própria.
      await pushApi.inscrever(sub.toJSON())
    } else {
      // Inscrição deixada por outra pessoa neste navegador (saiu sem clicar em "Sair"):
      // cancela no navegador; o servidor descarta o endereço no próximo envio (410).
      await sub.unsubscribe()
    }
  } catch {
    // silencioso: notificações não podem atrapalhar o login
  }
}

/**
 * Ao sair: desvincula este aparelho do usuário, para que a próxima pessoa a usar o
 * navegador não receba os avisos de outra. Rápido e silencioso.
 */
export async function encerrarPushAoSair(): Promise<void> {
  try {
    if (!pushSuportado()) return
    const sub = await assinaturaAtual()
    if (!sub) return
    await pushApi.remover(sub.endpoint, { timeout: 4000 }).catch(() => undefined)
    await sub.unsubscribe()
  } catch {
    // silencioso
  }
}

export function usePush() {
  return {
    situacao: readonly(situacao),
    preferencias: readonly(preferencias),
    ocupado: readonly(ocupado),
    horario: readonly(horario),
    carregarPush,
    ativarPush,
    desativarPush,
    salvarPreferenciasPush,
    testarPush,
  }
}

/** Somente para testes. */
export function _resetPushParaTestes() {
  situacao.value = 'carregando'
  preferencias.value = { ...PREFERENCIAS_PADRAO }
  ocupado.value = false
  horario.value = null
  chavePublica = null
}
