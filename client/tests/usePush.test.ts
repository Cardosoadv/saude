// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';

vi.mock('../src/services/pushApi', () => ({
  pushApi: {
    config: vi.fn(),
    inscrever: vi.fn(),
    remover: vi.fn(),
    teste: vi.fn(),
  },
}));

import { pushApi } from '../src/services/pushApi';
import {
  usePush, carregarPush, ativarPush, desativarPush, salvarPreferenciasPush,
  sincronizarPush, encerrarPushAoSair, chaveParaBytes, _resetPushParaTestes,
} from '../src/composables/usePush';

const api = pushApi as unknown as Record<'config' | 'inscrever' | 'remover' | 'teste', ReturnType<typeof vi.fn>>;
const CHAVE = 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U';

function assinatura(endpoint = 'https://push.exemplo.com/abc', chave = CHAVE) {
  return {
    endpoint,
    options: { applicationServerKey: chaveParaBytes(chave).buffer },
    unsubscribe: vi.fn().mockResolvedValue(true),
    toJSON: () => ({ endpoint, keys: { p256dh: 'p', auth: 'a' } }),
  };
}

let sub: ReturnType<typeof assinatura> | null;
let pushManager: { getSubscription: ReturnType<typeof vi.fn>; subscribe: ReturnType<typeof vi.fn> };
let permissao: NotificationPermission;

beforeEach(() => {
  _resetPushParaTestes();
  vi.clearAllMocks();
  sub = null;
  permissao = 'default';
  pushManager = {
    getSubscription: vi.fn(async () => sub),
    subscribe: vi.fn(async () => { sub = assinatura(); return sub; }),
  };
  Object.defineProperty(window, 'isSecureContext', { value: true, configurable: true });
  Object.defineProperty(window, 'PushManager', { value: function PushManager() {}, configurable: true });
  Object.defineProperty(window, 'Notification', {
    configurable: true,
    value: {
      get permission() { return permissao; },
      requestPermission: vi.fn(async () => { permissao = 'granted'; return permissao; }),
    },
  });
  Object.defineProperty(navigator, 'serviceWorker', {
    configurable: true,
    value: { getRegistration: vi.fn(async () => ({ active: {}, pushManager })), ready: Promise.resolve({ pushManager }) },
  });
  api.config.mockResolvedValue({ data: { habilitado: true, chavePublica: CHAVE, inscricao: null } });
  api.inscrever.mockImplementation(async (_s: unknown, prefs?: object) => ({ data: { prazos: true, ...prefs } }));
  api.remover.mockResolvedValue({ success: true });
});

afterEach(() => {
  delete (navigator as any).serviceWorker;
  delete (window as any).PushManager;
  delete (window as any).Notification;
});

describe('usePush', () => {
  it('converte a chave VAPID base64url em 65 bytes', () => {
    const bytes = chaveParaBytes(CHAVE);
    expect(bytes.length).toBe(65);
    expect(bytes[0]).toBe(4);
  });

  it('sem chaves no servidor, mostra que não está configurado', async () => {
    api.config.mockResolvedValue({ data: { habilitado: false, chavePublica: null, inscricao: null } });
    await carregarPush();
    expect(usePush().situacao.value).toBe('servidor-off');
  });

  it('navegador sem Push API', async () => {
    delete (window as any).PushManager;
    await carregarPush();
    expect(usePush().situacao.value).toBe('sem-suporte');
  });

  it('permissão negada no navegador', async () => {
    permissao = 'denied';
    await carregarPush();
    expect(usePush().situacao.value).toBe('bloqueado');
  });

  it('falha de rede vira estado de erro (não "sem suporte")', async () => {
    api.config.mockRejectedValue(new Error('Sem conexão'));
    await carregarPush();
    expect(usePush().situacao.value).toBe('erro');
  });

  it('ativa: pede permissão, inscreve com a chave do servidor e salva as preferências', async () => {
    await carregarPush();
    expect(usePush().situacao.value).toBe('desativado');

    await ativarPush({ prazos: true });

    expect(window.Notification.requestPermission).toHaveBeenCalled();
    const opcoes = pushManager.subscribe.mock.calls[0][0];
    expect(opcoes.userVisibleOnly).toBe(true);
    expect(Array.from(opcoes.applicationServerKey)).toEqual(Array.from(chaveParaBytes(CHAVE)));
    expect(api.inscrever).toHaveBeenCalledWith(sub!.toJSON(), { prazos: true });
    expect(usePush().situacao.value).toBe('ativado');
    expect(usePush().preferencias.value).toEqual({ prazos: true });
  });

  it('não ativa se o usuário recusar a permissão', async () => {
    await carregarPush();
    (window.Notification.requestPermission as any).mockImplementation(async () => { permissao = 'denied'; return 'denied'; });
    await expect(ativarPush()).rejects.toThrow(/bloqueadas/);
    expect(pushManager.subscribe).not.toHaveBeenCalled();
    expect(usePush().situacao.value).toBe('bloqueado');
  });

  it('falha do serviço de push do navegador vira mensagem clara', async () => {
    await carregarPush();
    pushManager.subscribe.mockRejectedValue(new DOMException('Registration failed - permission denied', 'AbortError'));
    await expect(ativarPush()).rejects.toThrow(/não conseguiu se registrar/);
    expect(api.inscrever).not.toHaveBeenCalled();
    expect(usePush().situacao.value).toBe('desativado');
  });

  it('refaz a inscrição quando a chave do servidor mudou', async () => {
    const antiga = assinatura('https://push.exemplo.com/velha', 'B' + 'A'.repeat(86));
    sub = antiga;
    permissao = 'granted';
    await carregarPush();
    expect(usePush().situacao.value).toBe('desativado');

    await ativarPush();
    expect(antiga.unsubscribe).toHaveBeenCalled();
    expect(pushManager.subscribe).toHaveBeenCalled();
  });

  it('aparelho já inscrito carrega como ativado com as preferências do servidor', async () => {
    sub = assinatura();
    permissao = 'granted';
    api.config.mockResolvedValue({ data: { habilitado: true, chavePublica: CHAVE, inscricao: { prazos: false } } });

    await carregarPush();

    expect(api.config).toHaveBeenCalledWith(sub.endpoint);
    expect(usePush().situacao.value).toBe('ativado');
    expect(usePush().preferencias.value).toEqual({ prazos: false });
  });

  it('salva preferências e desativa no navegador e no servidor', async () => {
    sub = assinatura();
    permissao = 'granted';
    await salvarPreferenciasPush({ prazos: true });
    expect(api.inscrever).toHaveBeenCalledWith(sub.toJSON(), { prazos: true });

    const atual = sub;
    await desativarPush();
    expect(api.remover).toHaveBeenCalledWith(atual.endpoint);
    expect(atual.unsubscribe).toHaveBeenCalled();
    expect(usePush().situacao.value).toBe('desativado');
  });

  it('ao entrar, reafirma a inscrição do próprio usuário sem pedir permissão', async () => {
    sub = assinatura();
    permissao = 'granted';
    api.config.mockResolvedValue({ data: { habilitado: true, chavePublica: CHAVE, inscricao: { prazos: true } } });

    await sincronizarPush();

    expect(api.inscrever).toHaveBeenCalledWith(sub.toJSON());
    expect(window.Notification.requestPermission).not.toHaveBeenCalled();
  });

  it('ao entrar, cancela inscrição deixada por outra pessoa no mesmo navegador', async () => {
    sub = assinatura();
    permissao = 'granted';

    await sincronizarPush();

    expect(api.inscrever).not.toHaveBeenCalled();
    expect(sub!.unsubscribe).toHaveBeenCalled();
  });

  it('ao entrar sem permissão concedida, não faz nada', async () => {
    await sincronizarPush();
    expect(api.config).not.toHaveBeenCalled();
  });

  it('ao sair, desvincula o aparelho', async () => {
    sub = assinatura();
    await encerrarPushAoSair();
    expect(api.remover).toHaveBeenCalledWith(sub.endpoint, { timeout: 4000 });
    expect(sub.unsubscribe).toHaveBeenCalled();
  });
});
