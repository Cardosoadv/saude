// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({ previaExtrato: vi.fn(), importarExtrato: vi.fn(), listarImportacoes: vi.fn() }));
const acesso = vi.hoisted(() => ({ pode: vi.fn(() => true) }));

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn(async () => ({ success: true, data: [{ id_conta: 1, conta: 'Conta Fictícia' }] })) };
});
vi.mock('../src/composables/useAcesso', () => acesso);
vi.mock('../src/services/extratosApi', async () => {
  const real = await vi.importActual<typeof import('../src/services/extratosApi')>('../src/services/extratosApi');
  return { ...real, ...api };
});

import ExtratoImportacaoView from '../src/views/Financeiro/ExtratoImportacaoView.vue';
import { ConfirmacaoNecessaria } from '../src/services/extratosApi';

const espera = async () => { for (let i = 0; i < 8; i++) { await nextTick(); await Promise.resolve(); } };
const resultado = (extra: Record<string, any> = {}) => ({
  importacao_id: null, lidos: 4, novos: 4, duplicados: 0, conflitos: 0,
  periodo: ['2026-10-01', '2026-10-30'], alertas: [], conflitos_detalhe: [], ...extra,
});

function montar() {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const toasts: any[] = [];
  const app = createApp(ExtratoImportacaoView, { onToast: (t: any) => toasts.push(t) });
  app.mount(raiz);
  return { raiz, toasts, desmontar: () => { app.unmount(); raiz.remove(); } };
}

function escolher(raiz: HTMLElement, f: File) {
  const campo = raiz.querySelector('input[type=file]') as HTMLInputElement;
  Object.defineProperty(campo, 'files', { value: [f], configurable: true });
  campo.dispatchEvent(new Event('change'));
}

const botao = (raiz: HTMLElement, re: RegExp) =>
  Array.from(raiz.querySelectorAll('button')).find((b) => re.test(b.textContent?.trim() ?? '')) as HTMLButtonElement;

describe('ExtratoImportacaoView', () => {
  let m: ReturnType<typeof montar> | null = null;
  beforeEach(() => {
    Object.values(api).forEach((f) => f.mockReset());
    acesso.pode.mockReturnValue(true);
    api.listarImportacoes.mockResolvedValue({ itens: [], total: 0, page: 1 });
  });
  afterEach(() => { m?.desmontar(); m = null; });

  it('sem permissão de importar, esconde o envio e mantém o histórico', async () => {
    acesso.pode.mockReturnValue(false);
    m = montar();
    await espera();
    expect(m.raiz.querySelector('input[type=file]')).toBeNull();
    expect(m.raiz.textContent).toContain('não tem permissão');
    expect(m.raiz.textContent).toContain('Histórico de importações');
  });

  it('recusa arquivo que não é OFX sem chamar a API', async () => {
    m = montar();
    await espera();
    escolher(m.raiz, new File(['x'], 'extrato.pdf'));
    await espera();
    expect(m.raiz.textContent).toContain('formato OFX');
    expect(botao(m.raiz, /Ver prévia/).disabled).toBe(true);
    expect(api.previaExtrato).not.toHaveBeenCalled();
  });

  it('prévia de arquivo repetido mostra "0 novos, N já importados"', async () => {
    api.previaExtrato.mockResolvedValue(resultado({ novos: 0, duplicados: 4 }));
    m = montar();
    await espera();
    escolher(m.raiz, new File(['OFX'], 'extrato.ofx'));
    await espera();
    botao(m.raiz, /Ver prévia/).click();
    await espera();
    expect(m.raiz.textContent).toContain('0 novos, 4 já importados');
    expect(api.previaExtrato).toHaveBeenCalledWith(1, expect.any(File));
  });

  it('erro de conta errada aparece e não deixa importar', async () => {
    api.previaExtrato.mockRejectedValue(new Error('O arquivo é de outra conta.'));
    m = montar();
    await espera();
    escolher(m.raiz, new File(['OFX'], 'extrato.ofx'));
    await espera();
    botao(m.raiz, /Ver prévia/).click();
    await espera();
    expect(m.raiz.textContent).toContain('outra conta');
    expect(botao(m.raiz, /Importar$/)).toBeUndefined();
  });

  it('importa e mostra o resultado; clique duplo envia uma vez só', async () => {
    api.previaExtrato.mockResolvedValue(resultado());
    let liberar!: (v: any) => void;
    api.importarExtrato.mockReturnValue(new Promise((r) => { liberar = r; }));
    m = montar();
    await espera();
    escolher(m.raiz, new File(['OFX'], 'extrato.ofx'));
    await espera();
    botao(m.raiz, /Ver prévia/).click();
    await espera();

    const importar = botao(m.raiz, /Importar$/);
    importar.click();
    importar.click();
    await espera();
    expect(api.importarExtrato).toHaveBeenCalledTimes(1);
    expect(botao(m.raiz, /Importando/).disabled).toBe(true);

    liberar(resultado({ importacao_id: 12 }));
    await espera();
    expect(m.raiz.textContent).toContain('Extrato importado');
    expect(m.toasts[0].type).toBe('success');
  });

  it('alerta de reemissão pede confirmação em diálogo antes de importar', async () => {
    const alerta = { codigo: 'possivel_reemissao_de_fitid', mensagem: 'O banco pode ter reemitido o extrato.' };
    api.previaExtrato.mockResolvedValue(resultado({ alertas: [alerta] }));
    api.importarExtrato.mockResolvedValue(resultado({ importacao_id: 3 }));
    m = montar();
    await espera();
    escolher(m.raiz, new File(['OFX'], 'extrato.ofx'));
    await espera();
    botao(m.raiz, /Ver prévia/).click();
    await espera();

    botao(m.raiz, /^Importar$/).click();
    await espera();
    expect(api.importarExtrato).not.toHaveBeenCalled();
    expect(m.raiz.querySelector('[role=dialog]')?.textContent).toContain('reemitido');

    botao(m.raiz, /Importar mesmo assim/).click();
    await espera();
    expect(api.importarExtrato).toHaveBeenCalledWith(1, expect.any(File), true);
  });

  it('409 da API durante a importação também abre o diálogo', async () => {
    api.previaExtrato.mockResolvedValue(resultado());
    api.importarExtrato.mockRejectedValue(new ConfirmacaoNecessaria('Confirme.', [{ codigo: 'possivel_reemissao_de_fitid', mensagem: 'Pode duplicar.' }]));
    m = montar();
    await espera();
    escolher(m.raiz, new File(['OFX'], 'extrato.ofx'));
    await espera();
    botao(m.raiz, /Ver prévia/).click();
    await espera();
    botao(m.raiz, /^Importar$/).click();
    await espera();
    expect(m.raiz.querySelector('[role=dialog]')?.textContent).toContain('Pode duplicar');
  });
});
