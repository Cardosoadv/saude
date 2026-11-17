// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import {
  iniciarPwa, usePwa, aplicarAtualizacao, instalarApp, swSuportado, _resetPwaParaTestes,
} from '../src/composables/usePwa';

/** EventTarget simples para simular ServiceWorker / Registration / Container. */
class Alvo {
  ouvintes: Record<string, Array<() => void>> = {};
  addEventListener(tipo: string, fn: () => void) { (this.ouvintes[tipo] ||= []).push(fn); }
  disparar(tipo: string) { (this.ouvintes[tipo] || []).forEach((fn) => fn()); }
}

class SwFalso extends Alvo {
  state = 'installing';
  postMessage = vi.fn();
}

function montarServiceWorker({ controlado = true } = {}) {
  const reg = Object.assign(new Alvo(), {
    installing: null as SwFalso | null,
    waiting: null as SwFalso | null,
    update: vi.fn().mockResolvedValue(undefined),
  });
  const container = Object.assign(new Alvo(), {
    controller: controlado ? {} : null,
    register: vi.fn().mockResolvedValue(reg),
  });
  Object.defineProperty(navigator, 'serviceWorker', { value: container, configurable: true });
  Object.defineProperty(window, 'isSecureContext', { value: true, configurable: true });
  return { reg, container };
}

/** Simula o navegador encontrando um sw.js novo e terminando de instalá-lo. */
function novaVersaoInstalada(reg: ReturnType<typeof montarServiceWorker>['reg']) {
  const novo = new SwFalso();
  reg.installing = novo;
  reg.disparar('updatefound');
  novo.state = 'installed';
  novo.disparar('statechange');
  return novo;
}

describe('usePwa', () => {
  beforeEach(() => {
    _resetPwaParaTestes();
  });

  afterEach(() => {
    vi.restoreAllMocks();
    delete (navigator as any).serviceWorker;
  });

  it('não registra o service worker quando registrar=false (dev)', async () => {
    const { container } = montarServiceWorker();
    const reg = await iniciarPwa({ registrar: false });
    expect(reg).toBeNull();
    expect(container.register).not.toHaveBeenCalled();
  });

  it('registra ./sw.js com escopo da pasta do app', async () => {
    const { container } = montarServiceWorker();
    await iniciarPwa();
    expect(container.register).toHaveBeenCalledWith('./sw.js', { scope: './' });
  });

  it('não suporta SW fora de contexto seguro (ex.: vue.html aberto via file://)', () => {
    montarServiceWorker();
    Object.defineProperty(window, 'isSecureContext', { value: false, configurable: true });
    expect(swSuportado()).toBe(false);
  });

  it('avisa nova versão quando um SW novo termina de instalar e a página já era controlada', async () => {
    const { reg } = montarServiceWorker({ controlado: true });
    await iniciarPwa();
    const { atualizacaoDisponivel } = usePwa();
    expect(atualizacaoDisponivel.value).toBe(false);

    const novo = novaVersaoInstalada(reg);
    expect(atualizacaoDisponivel.value).toBe(true);

    aplicarAtualizacao();
    expect(novo.postMessage).toHaveBeenCalledWith({ type: 'SKIP_WAITING' });
  });

  it('não avisa nova versão na primeira instalação (sem controlador)', async () => {
    const { reg } = montarServiceWorker({ controlado: false });
    await iniciarPwa();
    novaVersaoInstalada(reg);
    expect(usePwa().atualizacaoDisponivel.value).toBe(false);
  });

  it('avisa quando já existe um SW em espera ao abrir o app', async () => {
    const { reg } = montarServiceWorker();
    reg.waiting = new SwFalso();
    await iniciarPwa();
    expect(usePwa().atualizacaoDisponivel.value).toBe(true);
  });

  it('acompanha o estado da conexão', async () => {
    await iniciarPwa({ registrar: false });
    const { online } = usePwa();
    window.dispatchEvent(new Event('offline'));
    expect(online.value).toBe(false);
    window.dispatchEvent(new Event('online'));
    expect(online.value).toBe(true);
  });

  it('guarda o beforeinstallprompt e abre o diálogo nativo ao instalar', async () => {
    await iniciarPwa({ registrar: false });
    const { podeInstalar } = usePwa();
    expect(podeInstalar.value).toBe(false);

    const evento = Object.assign(new Event('beforeinstallprompt', { cancelable: true }), {
      prompt: vi.fn().mockResolvedValue(undefined),
      userChoice: Promise.resolve({ outcome: 'accepted' as const }),
    });
    window.dispatchEvent(evento);
    expect(evento.defaultPrevented).toBe(true);
    expect(podeInstalar.value).toBe(true);

    await expect(instalarApp()).resolves.toBe(true);
    expect(evento.prompt).toHaveBeenCalled();
    expect(podeInstalar.value).toBe(false);
  });
});

describe('navegarPara (clique em notificação com o painel aberto)', () => {
  it('troca só a rota do próprio painel', async () => {
    const { navegarPara } = await import('../src/composables/usePwa');
    window.location.hash = '#/dashboard';
    const base = window.location.href.split('#')[0];

    navegarPara(base + '#/tarefas');
    expect(window.location.hash).toBe('#/tarefas');

    navegarPara('https://outro-site.com/#/tarefas');
    expect(window.location.hash).toBe('#/tarefas');
  });
});
