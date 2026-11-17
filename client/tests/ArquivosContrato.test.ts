// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({
  listarArquivosContrato: vi.fn(), enviarArquivosContrato: vi.fn(), removerArquivoContrato: vi.fn(),
}));

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn() };
});
vi.mock('../src/services/contratosArquivosApi', () => api);

import ArquivosContrato from '../src/components/Contratos/ArquivosContrato.vue';
import { aplicarAcesso, limparAcesso } from '../src/composables/useAcesso';

const arquivo = (id: number, extra: Record<string, unknown> = {}) => ({
  id_contrato_arquivo: id, contrato_id: 5, categoria: 'Contrato original', nome: `Contrato-0005_${id}.pdf`,
  mime_type: 'application/pdf', tamanho: 2048, drive_id: `d${id}`, drive_link: `https://drive.google.com/file/d/d${id}/view`,
  ...extra,
});
const espera = async () => { for (let i = 0; i < 6; i++) { await nextTick(); await Promise.resolve(); } };

function montar(props: Record<string, any> = { contratoId: 5 }) {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const toasts: any[] = [];
  const mudancas: number[] = [];
  const app = createApp(ArquivosContrato, {
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

describe('ArquivosContrato', () => {
  let montado: ReturnType<typeof montar> | null = null;

  beforeEach(() => {
    Object.values(api).forEach((f) => f.mockReset());
    api.listarArquivosContrato.mockResolvedValue([]);
    limparAcesso();
  });
  afterEach(() => { montado?.desmontar(); montado = null; limparAcesso(); });

  it('lista os arquivos com link para o Drive e a categoria', async () => {
    api.listarArquivosContrato.mockResolvedValue([arquivo(1), arquivo(2, { categoria: 'Aditivo' })]);
    montado = montar();
    await espera();

    expect(api.listarArquivosContrato).toHaveBeenCalledWith(5);
    const links = montado.raiz.querySelectorAll('a.arquivos-nome');
    expect(links).toHaveLength(2);
    expect((links[0] as HTMLAnchorElement).href).toContain('drive.google.com/file/d/d1');
    expect(montado.raiz.textContent).toContain('Aditivo');
  });

  it('mostra o estado vazio', async () => {
    montado = montar();
    await espera();
    expect(montado.raiz.textContent).toContain('Nenhum arquivo anexado');
  });

  it('envia com a categoria escolhida, recarrega a lista e avisa', async () => {
    api.enviarArquivosContrato.mockResolvedValue({ success: true, message: 'Arquivo enviado com sucesso.', data: [arquivo(1)] });
    montado = montar();
    await espera();
    api.listarArquivosContrato.mockResolvedValue([arquivo(1)]);

    const select = montado.raiz.querySelector('select') as HTMLSelectElement;
    select.value = 'Aditivo';
    select.dispatchEvent(new Event('change'));
    const pdf = new File(['%PDF'], 'c.pdf', { type: 'application/pdf' });
    escolher(montado.raiz, [pdf]);
    await espera();

    expect(api.enviarArquivosContrato).toHaveBeenCalledWith(5, [pdf], 'Aditivo');
    expect(montado.toasts.at(-1)).toEqual({ msg: 'Arquivo enviado com sucesso.', type: 'success' });
    expect(montado.mudancas).toEqual([1]);
    expect(montado.raiz.querySelectorAll('a.arquivos-nome')).toHaveLength(1);
  });

  it('não chama a API com arquivo inválido e mostra o motivo', async () => {
    montado = montar();
    await espera();

    escolher(montado.raiz, [new File(['x'], 'virus.exe', { type: 'application/x-msdownload' })]);
    await espera();

    expect(api.enviarArquivosContrato).not.toHaveBeenCalled();
    expect(montado.raiz.querySelector('[role=alert]')?.textContent).toContain('virus.exe');
  });

  it('mostra o erro do servidor e avisa com toast de erro', async () => {
    api.enviarArquivosContrato.mockRejectedValue(new Error('A pasta raiz do Google Drive não está configurada.'));
    montado = montar();
    await espera();

    escolher(montado.raiz, [new File(['%PDF'], 'c.pdf', { type: 'application/pdf' })]);
    await espera();

    expect(montado.raiz.querySelector('[role=alert]')?.textContent).toContain('não está configurada');
    expect(montado.toasts.at(-1)?.type).toBe('danger');
  });

  it('remove o arquivo depois da confirmação', async () => {
    api.listarArquivosContrato.mockResolvedValue([arquivo(1), arquivo(2)]);
    api.removerArquivoContrato.mockResolvedValue(undefined);
    vi.stubGlobal('confirm', vi.fn(() => true));
    montado = montar();
    await espera();

    (montado.raiz.querySelector('button[title="Remover arquivo"]') as HTMLButtonElement).click();
    await espera();

    expect(api.removerArquivoContrato).toHaveBeenCalledWith(1);
    expect(montado.raiz.querySelectorAll('a.arquivos-nome')).toHaveLength(1);
    expect(montado.mudancas).toEqual([1]);
    vi.unstubAllGlobals();
  });

  it('não remove se o usuário cancelar a confirmação', async () => {
    api.listarArquivosContrato.mockResolvedValue([arquivo(1)]);
    vi.stubGlobal('confirm', vi.fn(() => false));
    montado = montar();
    await espera();

    (montado.raiz.querySelector('button[title="Remover arquivo"]') as HTMLButtonElement).click();
    await espera();

    expect(api.removerArquivoContrato).not.toHaveBeenCalled();
    vi.unstubAllGlobals();
  });

  it('sem permissão de edição e exclusão, só lista', async () => {
    aplicarAcesso({ permissoes: ['contratos.access'], modulos_ativos: ['contratos'] } as any);
    api.listarArquivosContrato.mockResolvedValue([arquivo(1)]);
    montado = montar();
    await espera();

    expect(montado.raiz.querySelector('input[type=file]')).toBeNull();
    expect(montado.raiz.querySelector('button[title="Remover arquivo"]')).toBeNull();
    expect(montado.raiz.querySelectorAll('a.arquivos-nome')).toHaveLength(1);
  });
});
