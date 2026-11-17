// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({ obterConfiguracao: vi.fn(), obterEmitente: vi.fn(), salvarPadroes: vi.fn() }));
vi.mock('../src/services/notasFiscaisApi', async (original) => ({
  ...(await original<typeof import('../src/services/notasFiscaisApi')>()),
  obterConfiguracao: api.obterConfiguracao,
  obterEmitente: api.obterEmitente,
  salvarPadroes: api.salvarPadroes,
}));

import NotaFiscalConfigModal from '../src/components/Financeiro/NotaFiscalConfigModal.vue';

const espera = async () => { for (let i = 0; i < 8; i++) { await nextTick(); await Promise.resolve(); } };

const configuracao = () => ({
  emitente: { id: 'em-1', cnpj: '12345678000190', razao: 'Escritório Exemplo Ltda', crt: '1', certificado_validade: '2027-05-01' },
  padroes: { servico_codigo: '17.14', codigo_nbs: '123456789', codigo_municipio: '3106200' },
  pronto: true, pendencias: [], codigos_cancelamento: {}, webhook_url: null,
});

function montar() {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const eventos = { fechar: 0, salvo: 0, toasts: [] as any[] };
  const app = createApp(NotaFiscalConfigModal, {
    onFechar: () => { eventos.fechar++; },
    onSalvo: () => { eventos.salvo++; },
    onToast: (t: any) => eventos.toasts.push(t),
  });
  app.mount(raiz);
  return { raiz, eventos, desmontar: () => { app.unmount(); raiz.remove(); } };
}

const fechar = (raiz: HTMLElement) => (raiz.querySelector('.modal-close') as HTMLButtonElement);
const clicarAba = (raiz: HTMLElement, texto: string) =>
  (Array.from(raiz.querySelectorAll('.filter-tab')).find((b) => (b.textContent ?? '').includes(texto)) as HTMLButtonElement).click();

describe('NotaFiscalConfigModal (moldura do NotaFiscalConfigForm)', () => {
  let montado: ReturnType<typeof montar> | null = null;

  beforeEach(() => {
    Object.values(api).forEach((f) => f.mockReset());
    api.obterConfiguracao.mockResolvedValue(configuracao());
    api.obterEmitente.mockResolvedValue(null);
    api.salvarPadroes.mockResolvedValue({ success: true, message: 'Padrões salvos.', data: {} });
  });
  afterEach(() => { montado?.desmontar(); montado = null; });

  it('mostra o título do modal e o formulário completo', async () => {
    montado = montar();
    await espera();

    expect(montado.raiz.querySelector('#nfse-config-titulo')?.textContent).toContain('Configurar NFS-e');
    expect(montado.raiz.textContent).toContain('Escritório Exemplo Ltda');
    expect(montado.raiz.querySelectorAll('.filter-tab').length).toBe(3);
  });

  it('fechar sem ter gravado nada não avisa "salvo"', async () => {
    montado = montar();
    await espera();

    fechar(montado.raiz).click();
    await espera();

    expect(montado.eventos.fechar).toBe(1);
    expect(montado.eventos.salvo).toBe(0);
  });

  it('depois de gravar os padrões, fechar avisa "salvo" e repassa o aviso de sucesso', async () => {
    montado = montar();
    await espera();

    clicarAba(montado.raiz, 'Padrões fiscais');
    await espera();
    const codigo = montado.raiz.querySelector('#pd-codigo') as HTMLInputElement;
    codigo.value = '17.15';
    codigo.dispatchEvent(new Event('input'));
    await espera();
    (Array.from(montado.raiz.querySelectorAll('button')).find((b) => (b.textContent ?? '').includes('Salvar padrões')) as HTMLButtonElement).click();
    await espera();

    expect(api.salvarPadroes).toHaveBeenCalledWith(expect.objectContaining({ servico_codigo: '17.15' }));
    expect(montado.eventos.toasts.at(-1)).toMatchObject({ msg: 'Padrões salvos.', type: 'success' });
    expect(montado.eventos.salvo).toBe(0);

    fechar(montado.raiz).click();
    await espera();

    expect(montado.eventos.salvo).toBe(1);
    expect(montado.eventos.fechar).toBe(1);
  });

  it('não fecha enquanto há uma gravação em andamento', async () => {
    let concluir: (v: unknown) => void = () => {};
    api.salvarPadroes.mockReturnValue(new Promise((resolve) => { concluir = resolve; }));
    montado = montar();
    await espera();

    clicarAba(montado.raiz, 'Padrões fiscais');
    await espera();
    (Array.from(montado.raiz.querySelectorAll('button')).find((b) => (b.textContent ?? '').includes('Salvar padrões')) as HTMLButtonElement).click();
    await espera();

    expect(fechar(montado.raiz).disabled).toBe(true);
    fechar(montado.raiz).click();
    await espera();
    expect(montado.eventos.fechar).toBe(0);

    concluir({ success: true, message: 'Padrões salvos.', data: {} });
    await espera();
    expect(fechar(montado.raiz).disabled).toBe(false);
  });
});
