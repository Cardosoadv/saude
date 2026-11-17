/**
 * PWA — registro do service worker, aviso de nova versão, instalação e estado
 * de conexão. Estado em módulo (singleton): qualquer componente que chame
 * usePwa() enxerga os mesmos valores.
 */
import { ref, readonly } from 'vue'

/** Evento não padronizado do Chromium para oferecer a instalação. */
export interface BeforeInstallPromptEvent extends Event {
  prompt(): Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

/** Intervalo para procurar versão nova enquanto o app fica aberto (1 h). */
export const INTERVALO_VERIFICACAO_MS = 60 * 60 * 1000

const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine !== false)
const atualizacaoDisponivel = ref(false)
const podeInstalar = ref(false)
const instalado = ref(false)

let swEmEspera: ServiceWorker | null = null
let promptInstalacao: BeforeInstallPromptEvent | null = null
let iniciado = false
let recarregando = false

/** Roda como app instalado (janela própria, sem barra do navegador)? */
export function emModoApp(): boolean {
  if (typeof window === 'undefined') return false
  const standalone = typeof window.matchMedia === 'function'
    && window.matchMedia('(display-mode: standalone)').matches
  return standalone || (navigator as Navigator & { standalone?: boolean }).standalone === true
}

/** iPhone/iPad no Safari: não há prompt de instalação, só "Adicionar à Tela de Início". */
export function ehIosSafari(): boolean {
  if (typeof navigator === 'undefined') return false
  const ua = navigator.userAgent || ''
  const ios = /iPad|iPhone|iPod/.test(ua) || (ua.includes('Macintosh') && (navigator.maxTouchPoints ?? 0) > 1)
  return ios && /Safari/.test(ua) && !/CriOS|FxiOS|EdgiOS/.test(ua)
}

/** O service worker só funciona em contexto seguro (https ou localhost), nunca em file://. */
export function swSuportado(): boolean {
  return typeof window !== 'undefined'
    && 'serviceWorker' in navigator
    && window.isSecureContext === true
    && /^https?:$/.test(window.location.protocol)
}

function marcarEmEspera(sw: ServiceWorker | null) {
  // Só avisa quando já havia uma versão controlando a página (não na 1ª instalação).
  if (sw && navigator.serviceWorker.controller) {
    swEmEspera = sw
    atualizacaoDisponivel.value = true
  }
}

function observarRegistro(reg: ServiceWorkerRegistration) {
  if (reg.waiting) marcarEmEspera(reg.waiting)
  reg.addEventListener('updatefound', () => {
    const novo = reg.installing
    if (!novo) return
    novo.addEventListener('statechange', () => {
      if (novo.state === 'installed') marcarEmEspera(novo)
    })
  })
}

/** Registra o service worker e liga os ouvintes. Chamar uma vez, no main.ts. */
export async function iniciarPwa(opts: { registrar?: boolean } = {}): Promise<ServiceWorkerRegistration | null> {
  if (iniciado || typeof window === 'undefined') return null
  iniciado = true

  instalado.value = emModoApp()
  window.addEventListener('online', () => { online.value = true })
  window.addEventListener('offline', () => { online.value = false })

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault()
    promptInstalacao = e as BeforeInstallPromptEvent
    podeInstalar.value = true
  })
  window.addEventListener('appinstalled', () => {
    promptInstalacao = null
    podeInstalar.value = false
    instalado.value = true
  })

  if (opts.registrar === false || !swSuportado()) return null

  // Clique numa notificação push com o painel já aberto: o SW pede a troca de rota.
  navigator.serviceWorker.addEventListener('message', (evento: MessageEvent) => {
    const dados = evento.data as { type?: string; url?: string } | null
    if (dados?.type === 'NAVEGAR' && typeof dados.url === 'string') navegarPara(dados.url)
  })

  // A versão nova assumiu (após "Atualizar"): recarrega uma única vez.
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (recarregando || !swEmEspera) return
    recarregando = true
    window.location.reload()
  })

  try {
    const reg = await navigator.serviceWorker.register('./sw.js', { scope: './' })
    observarRegistro(reg)
    // SPA não navega: procura versão nova periodicamente e ao voltar para a aba.
    const verificar = () => { reg.update().catch(() => undefined) }
    setInterval(verificar, INTERVALO_VERIFICACAO_MS)
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') verificar()
    })
    return reg
  } catch (erro) {
    console.warn('[PWA] Falha ao registrar o service worker:', erro)
    return null
  }
}

/** Aplica a rota (hash) de um endereço do próprio painel; ignora qualquer outro destino. */
export function navegarPara(endereco: string) {
  try {
    const destino = new URL(endereco, window.location.href)
    const atual = new URL(window.location.href)
    if (destino.origin !== atual.origin || destino.pathname.replace(/vue\.html$/, '') !== atual.pathname.replace(/vue\.html$/, '')) return
    if (destino.hash.startsWith('#/') && destino.hash !== window.location.hash) window.location.hash = destino.hash
  } catch {
    // endereço inválido: ignora
  }
}

/** Ativa a versão em espera; a página recarrega quando ela assumir. */
export function aplicarAtualizacao() {
  if (!swEmEspera) {
    window.location.reload()
    return
  }
  swEmEspera.postMessage({ type: 'SKIP_WAITING' })
}

/** Abre o diálogo nativo de instalação (Chrome/Edge/Android). */
export async function instalarApp(): Promise<boolean> {
  if (!promptInstalacao) return false
  const evento = promptInstalacao
  promptInstalacao = null
  podeInstalar.value = false
  await evento.prompt()
  const { outcome } = await evento.userChoice
  return outcome === 'accepted'
}

export function usePwa() {
  return {
    online: readonly(online),
    atualizacaoDisponivel: readonly(atualizacaoDisponivel),
    podeInstalar: readonly(podeInstalar),
    instalado: readonly(instalado),
    ehIosSafari,
    aplicarAtualizacao,
    instalarApp,
  }
}

/** Somente para testes: volta ao estado inicial. */
export function _resetPwaParaTestes() {
  online.value = true
  atualizacaoDisponivel.value = false
  podeInstalar.value = false
  instalado.value = false
  swEmEspera = null
  promptInstalacao = null
  iniciado = false
  recarregando = false
}
