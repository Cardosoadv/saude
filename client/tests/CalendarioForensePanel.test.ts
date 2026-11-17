// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({
  listarFeriados: vi.fn(), salvarFeriado: vi.fn(), excluirFeriado: vi.fn(),
  listarSuspensoes: vi.fn(), salvarSuspensao: vi.fn(), excluirSuspensao: vi.fn(),
}));
vi.mock('../src/services/calendarioForenseApi', () => api);

import CalendarioForensePanel from '../src/components/Configuracoes/CalendarioForensePanel.vue';

const espera = async () => { for (let i = 0; i < 6; i++) { await nextTick(); await Promise.resolve(); } };

function montar() {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const toasts: any[] = [];
  const app = createApp(CalendarioForensePanel, { onToast: (t: any) => toasts.push(t) });
  app.mount(raiz);
  return { raiz, toasts, desmontar: () => { app.unmount(); raiz.remove(); } };
}

function preencher(raiz: HTMLElement, rotulo: string, valor: string) {
  const campo = raiz.querySelector(`[aria-label="${rotulo}"]`) as HTMLInputElement;
  campo.value = valor;
  campo.dispatchEvent(new Event('input'));
  campo.dispatchEvent(new Event('change'));
}

describe('CalendarioForensePanel', () => {
  let montado: ReturnType<typeof montar> | null = null;

  beforeEach(() => {
    Object.values(api).forEach((f) => f.mockReset());
    api.listarFeriados.mockResolvedValue([
      { id_feriado: 1, data: '2026-09-07', nome: 'Independência do Brasil', abrangencia: 'nacional', recorrente: 0 },
    ]);
    api.listarSuspensoes.mockResolvedValue([
      { id_suspensao: 4, tribunal: 'TJSP', data_inicio: '2026-08-19', data_fim: '2026-08-20', motivo: 'Instabilidade' },
    ]);
    api.salvarFeriado.mockResolvedValue({ success: true });
    api.salvarSuspensao.mockResolvedValue({ success: true });
  });
  afterEach(() => { montado?.desmontar(); montado = null; });

  it('lista feriados e suspensões cadastrados', async () => {
    montado = montar();
    await espera();
    const texto = montado.raiz.textContent ?? '';
    expect(texto).toContain('Independência do Brasil');
    expect(texto).toContain('Nacional');
    expect(texto).toContain('Instabilidade');
    expect(texto).toContain('19/08/2026 a 20/08/2026');
  });

  it('cadastra um feriado municipal pedindo UF e município', async () => {
    montado = montar();
    await espera();
    preencher(montado.raiz, 'Data do feriado', '2026-08-18');
    preencher(montado.raiz, 'Nome do feriado', 'Aniversário da cidade');
    preencher(montado.raiz, 'Abrangência', 'municipal');
    await espera();
    preencher(montado.raiz, 'UF', 'SP');
    preencher(montado.raiz, 'Município', 'Campinas');

    (montado.raiz.querySelector('form[aria-label="Cadastrar feriado"]') as HTMLFormElement)
      .dispatchEvent(new Event('submit', { cancelable: true }));
    await espera();

    expect(api.salvarFeriado).toHaveBeenCalledTimes(1);
    expect(api.salvarFeriado.mock.calls[0][0]).toMatchObject({
      data: '2026-08-18', nome: 'Aniversário da cidade', abrangencia: 'municipal', uf: 'SP', municipio: 'Campinas',
    });
    expect(montado.toasts.at(-1)).toMatchObject({ type: 'success' });
  });

  it('mostra o erro de validação devolvido pela API', async () => {
    api.salvarSuspensao.mockRejectedValue(Object.assign(new Error('x'), { data: { messages: { data_fim: 'A data final não pode ser anterior à inicial.' } } }));
    montado = montar();
    await espera();
    (montado.raiz.querySelector('form[aria-label="Cadastrar suspensão"]') as HTMLFormElement)
      .dispatchEvent(new Event('submit', { cancelable: true }));
    await espera();
    expect(montado.toasts.at(-1)).toMatchObject({ type: 'error' });
    expect(montado.toasts.at(-1).msg).toContain('anterior');
  });
});
