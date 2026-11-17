// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({
  listarComprovantes: vi.fn(), enviarComprovantes: vi.fn(), removerComprovante: vi.fn(),
}));

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn() };
});
vi.mock('../src/services/comprovantesApi', () => api);

import ComprovantesPagamento from '../src/components/Financeiro/ComprovantesPagamento.vue';
import { aplicarAcesso, limparAcesso } from '../src/composables/useAcesso';

const comprovante = (id: number, extra: Record<string, unknown> = {}) => ({
  id_comprovante: id, tipo: 'despesa', pagamento_id: 15, nome: `2026-10-05_BB_150-50_D15_${id}.pdf`,
  mime_type: 'application/pdf', tamanho: 2048, drive_id: `d${id}`, drive_link: `https://drive.google.com/file/d/d${id}/view`,
  pasta_mes: '2026-10', ...extra,
});
const espera = async () => { for (let i = 0; i < 6; i++) { await nextTick(); await Promise.resolve(); } };

function montar(props: Record<string, any> = { tipo: 'despesa', pagamentoId: 15 }) {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const toasts: any[] = [];
  const mudancas: number[] = [];
  const app = createApp(ComprovantesPagamento, {
    ...props,
    onToast: (t: any) => toasts.push(t),
    onChanged: (n: number) => mudancas.push(n),
  });
  app.mount(raiz);
  return { raiz, toasts, mudancas, desmontar: () => { app.unmount(); raiz.remove(); } };
}

function escolher(raiz: HTMLElement, arquivos: File[]) {
  const campo = raiz.querySelector('input[type=file]') as HTMLInputElement;
  Object.defineProperty(campo, 'files', { value: arquivos, configurable: true });
  campo.dispatchEvent(new Event('change'));
}

describe('ComprovantesPagamento', () => {
  let montado: ReturnType<typeof montar> | null = null;

  beforeEach(() => {
    Object.values(api).forEach((f) => f.mockReset());
    api.listarComprovantes.mockResolvedValue([]);
    limparAcesso();
  });
  afterEach(() => { montado?.desmontar(); montado = null; limparAcesso(); });

  it('lista os comprovantes com link para o Drive', async () => {
    api.listarComprovantes.mockResolvedValue([comprovante(1), comprovante(2)]);
    montado = montar();
    await espera();

    expect(api.listarComprovantes).toHaveBeenCalledWith('despesa', 15);
    const links = montado.raiz.querySelectorAll('a.comprovantes-nome');
    expect(links).toHaveLength(2);
    expect((links[0] as HTMLAnchorElement).href).toContain('drive.google.com/file/d/d1');
    expect(montado.raiz.textContent).toContain('10/2026');
  });

  it('mostra o estado vazio', async () => {
    montado = montar();
    await espera();
    expect(montado.raiz.textContent).toContain('Nenhum comprovante anexado');
  });

  it('envia os arquivos escolhidos, recarrega a lista e avisa', async () => {
    api.enviarComprovantes.mockResolvedValue({ success: true, message: 'Comprovante enviado com sucesso.', data: [comprovante(1)] });
    montado = montar();
    await espera();
    api.listarComprovantes.mockResolvedValue([comprovante(1)]);

    const pdf = new File(['%PDF'], 'pix.pdf', { type: 'application/pdf' });
    escolher(montado.raiz, [pdf]);
    await espera();

    expect(api.enviarComprovantes).toHaveBeenCalledWith('despesa', 15, [pdf]);
    expect(montado.toasts.at(-1)).toEqual({ msg: 'Comprovante enviado com sucesso.', type: 'success' });
    expect(montado.mudancas).toEqual([1]);
    expect(montado.raiz.querySelectorAll('a.comprovantes-nome')).toHaveLength(1);
  });

  it('não chama a API com arquivo inválido e mostra o motivo', async () => {
    montado = montar();
    await espera();

    escolher(montado.raiz, [new File(['x'], 'virus.exe', { type: 'application/x-msdownload' })]);
    await espera();

    expect(api.enviarComprovantes).not.toHaveBeenCalled();
    expect(montado.raiz.querySelector('[role=alert]')?.textContent).toContain('virus.exe');
  });

  it('mostra o erro do servidor (ex.: mês fechado) e avisa com toast de erro', async () => {
    api.enviarComprovantes.mockRejectedValue(new Error('O período 10/2026 já está fechado financeiramente.'));
    montado = montar();
    await espera();

    escolher(montado.raiz, [new File(['%PDF'], 'pix.pdf', { type: 'application/pdf' })]);
    await espera();

    expect(montado.raiz.querySelector('[role=alert]')?.textContent).toContain('já está fechado');
    expect(montado.toasts.at(-1)?.type).toBe('danger');
  });

  it('envio parcial avisa com toast de aviso e lista as falhas', async () => {
    api.enviarComprovantes.mockResolvedValue({
      success: true, message: '1 de 2 comprovantes enviados.', data: [comprovante(1)],
      erros: [{ arquivo: 'b.pdf', mensagem: 'Quota exceeded' }],
    });
    montado = montar();
    await espera();

    escolher(montado.raiz, [
      new File(['%PDF'], 'a.pdf', { type: 'application/pdf' }),
      new File(['%PDF'], 'b.pdf', { type: 'application/pdf' }),
    ]);
    await espera();

    expect(montado.toasts.at(-1)?.type).toBe('warning');
    expect(montado.raiz.querySelector('[role=alert]')?.textContent).toContain('Quota exceeded');
  });

  it('remove o comprovante depois da confirmação', async () => {
    api.listarComprovantes.mockResolvedValue([comprovante(1), comprovante(2)]);
    api.removerComprovante.mockResolvedValue(undefined);
    vi.stubGlobal('confirm', vi.fn(() => true));
    montado = montar();
    await espera();

    (montado.raiz.querySelector('button[title="Remover comprovante"]') as HTMLButtonElement).click();
    await espera();

    expect(api.removerComprovante).toHaveBeenCalledWith(1);
    expect(montado.raiz.querySelectorAll('a.comprovantes-nome')).toHaveLength(1);
    expect(montado.mudancas).toEqual([1]);
    vi.unstubAllGlobals();
  });

  it('não remove se o usuário cancelar a confirmação', async () => {
    api.listarComprovantes.mockResolvedValue([comprovante(1)]);
    vi.stubGlobal('confirm', vi.fn(() => false));
    montado = montar();
    await espera();

    (montado.raiz.querySelector('button[title="Remover comprovante"]') as HTMLButtonElement).click();
    await espera();

    expect(api.removerComprovante).not.toHaveBeenCalled();
    vi.unstubAllGlobals();
  });

  it('sem permissão de edição e exclusão, só lista (sem envio nem remoção)', async () => {
    aplicarAcesso({ permissoes: ['financeiro.access'], modulos_ativos: ['financeiro'] } as any);
    api.listarComprovantes.mockResolvedValue([comprovante(1)]);
    montado = montar();
    await espera();

    expect(montado.raiz.querySelector('input[type=file]')).toBeNull();
    expect(montado.raiz.querySelector('button[title="Remover comprovante"]')).toBeNull();
    expect(montado.raiz.querySelectorAll('a.comprovantes-nome')).toHaveLength(1);
  });
});
