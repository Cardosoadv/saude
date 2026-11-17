// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const apiFetch = vi.hoisted(() => vi.fn());
vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch };
});

import ReceitasList from '../src/views/Receitas/ReceitasList.vue';
import DespesasList from '../src/views/Despesas/DespesasList.vue';

const espera = async () => { for (let i = 0; i < 6; i++) { await nextTick(); await Promise.resolve(); } };

/** Chamadas de listagem feitas até agora, com o número da página pedida. */
const paginasPedidas = (rota: string) =>
  apiFetch.mock.calls
    .map(([url]) => String(url))
    .filter((url) => url.includes(rota))
    .map((url) => new URLSearchParams(url.split('?')[1]).get('page'));

describe.each([
  ['Receitas a Receber', ReceitasList, 'receitas/listar'],
  ['Despesas a Pagar', DespesasList, 'despesas/listar'],
])('paginação de %s', (_nome, componente, rota) => {
  beforeEach(() => {
    window.scrollTo = vi.fn() as any;
    apiFetch.mockReset();
    apiFetch.mockImplementation(async (url: string) =>
      url.includes('/listar')
        ? { success: true, data: [], pager: { current_page: 1, per_page: 10, total: 30, last_page: 3 } }
        : { success: true, data: [] });
  });

  it('busca a página escolhida no servidor', async () => {
    const raiz = document.createElement('div');
    document.body.appendChild(raiz);
    const app = createApp(componente, { searchQuery: '' });
    app.mount(raiz);
    await espera();
    expect(paginasPedidas(rota)).toEqual(['1']);

    const botao = raiz.querySelector('button[aria-label="Ir para a página 2"]') as HTMLButtonElement;
    expect(botao).not.toBeNull();
    botao.click();
    await espera();
    expect(paginasPedidas(rota)).toEqual(['1', '2']);

    // A página atual não dispara nova busca.
    (raiz.querySelector('button[aria-label="Ir para a página 2"]') as HTMLButtonElement).click();
    await espera();
    expect(paginasPedidas(rota)).toEqual(['1', '2']);

    app.unmount();
    raiz.remove();
  });
});
