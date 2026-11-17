// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({ enviarComprovantesLote: vi.fn() }));

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn() };
});
vi.mock('../src/services/comprovantesApi', () => api);

import ComprovanteLoteModal from '../src/components/Financeiro/ComprovanteLoteModal.vue';

const espera = async () => { for (let i = 0; i < 6; i++) { await nextTick(); await Promise.resolve(); } };
const pagamentos = [{ tipo: 'despesa', id: 12 }, { tipo: 'receita', id: 7 }];

function montar(props: Record<string, any> = { pagamentos }) {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const eventos = { toasts: [] as any[], fechou: 0, enviou: 0 };
  const app = createApp(ComprovanteLoteModal, {
    ...props,
    onToast: (t: any) => eventos.toasts.push(t),
    onClose: () => { eventos.fechou++; },
    onEnviado: () => { eventos.enviou++; },
  });
  app.mount(raiz);
  return { raiz, ...eventos, eventos, desmontar: () => { app.unmount(); raiz.remove(); } };
}

function escolher(raiz: HTMLElement, arquivos: File[]) {
  const campo = raiz.querySelector('input[type=file]') as HTMLInputElement;
  Object.defineProperty(campo, 'files', { value: arquivos, configurable: true });
  campo.dispatchEvent(new Event('change'));
}

const botaoAnexar = (raiz: HTMLElement) =>
  Array.from(raiz.querySelectorAll('button')).find((b) => /Anexar$|Enviando/.test(b.textContent?.trim() ?? '')) as HTMLButtonElement;

describe('ComprovanteLoteModal', () => {
  let montado: ReturnType<typeof montar> | null = null;
  beforeEach(() => { api.enviarComprovantesLote.mockReset(); });
  afterEach(() => { montado?.desmontar(); montado = null; });

  it('mostra quantos pagamentos receberão o comprovante e só habilita com arquivo', async () => {
    montado = montar();
    await espera();

    expect(montado.raiz.textContent).toContain('2 pagamentos selecionados');
    expect(botaoAnexar(montado.raiz).disabled).toBe(true);

    escolher(montado.raiz, [new File(['%PDF'], 'pix.pdf', { type: 'application/pdf' })]);
    await espera();
    expect(montado.raiz.textContent).toContain('pix.pdf');
    expect(botaoAnexar(montado.raiz).disabled).toBe(false);
  });

  it('envia o lote, avisa e fecha', async () => {
    api.enviarComprovantesLote.mockResolvedValue({ success: true, message: 'Comprovante anexado a 2 pagamentos.', data: [] });
    montado = montar();
    const pdf = new File(['%PDF'], 'pix.pdf', { type: 'application/pdf' });
    escolher(montado.raiz, [pdf]);
    await espera();

    botaoAnexar(montado.raiz).click();
    await espera();

    expect(api.enviarComprovantesLote).toHaveBeenCalledWith(pagamentos, [pdf]);
    expect(montado.eventos.toasts.at(-1)).toEqual({ msg: 'Comprovante anexado a 2 pagamentos.', type: 'success' });
    expect(montado.eventos.enviou).toBe(1);
    expect(montado.eventos.fechou).toBe(1);
  });

  it('mantém o modal aberto e mostra o erro do servidor (ex.: mês fechado)', async () => {
    api.enviarComprovantesLote.mockRejectedValue(new Error('O período 09/2026 já está fechado financeiramente.'));
    montado = montar();
    escolher(montado.raiz, [new File(['%PDF'], 'pix.pdf', { type: 'application/pdf' })]);
    await espera();

    botaoAnexar(montado.raiz).click();
    await espera();

    expect(montado.raiz.querySelector('[role=alert]')?.textContent).toContain('já está fechado');
    expect(montado.eventos.fechou).toBe(0);
  });

  it('recusa arquivo inválido sem chamar a API', async () => {
    montado = montar();
    escolher(montado.raiz, [new File(['x'], 'virus.exe', { type: 'application/x-msdownload' })]);
    await espera();

    botaoAnexar(montado.raiz).click();
    await espera();

    expect(api.enviarComprovantesLote).not.toHaveBeenCalled();
    expect(montado.raiz.querySelector('[role=alert]')?.textContent).toContain('virus.exe');
  });

  it('recusa mais de 50 pagamentos', async () => {
    const muitos = Array.from({ length: 51 }, (_, i) => ({ tipo: 'despesa', id: i + 1 }));
    montado = montar({ pagamentos: muitos });
    escolher(montado.raiz, [new File(['%PDF'], 'pix.pdf', { type: 'application/pdf' })]);
    await espera();

    botaoAnexar(montado.raiz).click();
    await espera();

    expect(api.enviarComprovantesLote).not.toHaveBeenCalled();
    expect(montado.raiz.querySelector('[role=alert]')?.textContent).toContain('no máximo 50');
  });
});
