// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const apiFetch = vi.hoisted(() => vi.fn());
const baixarArquivo = vi.hoisted(() => vi.fn());
vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch, baixarArquivo };
});

import ReceitasList from '../src/views/Receitas/ReceitasList.vue';
import DespesasList from '../src/views/Despesas/DespesasList.vue';
import ReceitasRecebidasList from '../src/views/Receitas/ReceitasRecebidasList.vue';
import DespesasPagasList from '../src/views/Despesas/DespesasPagasList.vue';

const espera = async () => { for (let i = 0; i < 8; i++) { await nextTick(); await Promise.resolve(); } };

const linhas = [
  { id_receita: 11, id_despesa: 11, id_pgto_receita: 11, id_pgto_despesa: 11, receita: 'R', despesa: 'D', valor: 10, vencimento_dt: '2026-11-10' },
  { id_receita: 12, id_despesa: 12, id_pgto_receita: 12, id_pgto_despesa: 12, receita: 'R2', despesa: 'D2', valor: 20, vencimento_dt: '2026-11-11' },
];

function montar(componente: any) {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const toasts: any[] = [];
  const app = createApp(componente, { searchQuery: '', onToast: (t: any) => toasts.push(t) });
  app.mount(raiz);
  return { raiz, toasts, desmontar: () => { app.unmount(); raiz.remove(); } };
}

async function aplicarLote(raiz: HTMLElement, acao: string) {
  raiz.querySelectorAll<HTMLInputElement>('input[type=checkbox][value]').forEach((c) => {
    if (c.value) { c.checked = true; c.dispatchEvent(new Event('change')); }
  });
  const select = Array.from(raiz.querySelectorAll('select')).find((s) => s.querySelector('option[value="exportar"]')) as HTMLSelectElement;
  select.value = acao;
  select.dispatchEvent(new Event('change'));
  await espera();
  (Array.from(raiz.querySelectorAll('button')).find((b) => b.textContent?.trim() === 'Aplicar') as HTMLButtonElement).click();
  await espera();
}

const corpoDoDownload = () => {
  const [endpoint, opts] = baixarArquivo.mock.calls[0];
  const fd = opts.body as FormData;
  return { endpoint, metodo: opts.method, acao: fd.get('acao'), chaves: [...new Set([...fd.keys()])] };
};

describe.each([
  ['Receitas a Receber', ReceitasList, 'financeiro/receitas', 'receitas[]', 'receitas/excluirEmLote'],
  ['Despesas a Pagar', DespesasList, 'financeiro/despesas', 'despesas[]', 'despesas/excluirEmLote'],
  ['Receitas Recebidas', ReceitasRecebidasList, 'financeiro/pagamentoReceitas', 'recebimentos[]', 'pagamentoReceitas/excluirEmLote'],
  ['Despesas Pagas', DespesasPagasList, 'financeiro/pagamentoDespesas', 'pagamentos[]', 'pagamentoDespesas/excluirEmLote'],
])('ações da lista %s', (_nome, componente, raizApi, campoIds, rotaExcluirLote) => {
  beforeEach(() => {
    window.scrollTo = vi.fn() as any;
    window.confirm = vi.fn(() => true);
    apiFetch.mockReset();
    baixarArquivo.mockReset().mockResolvedValue(undefined);
    apiFetch.mockImplementation(async (url: string) =>
      String(url).includes('/listar')
        ? { success: true, data: linhas, pager: { current_page: 1, per_page: 10, total: 2, last_page: 1 } }
        : { success: true, data: [], message: 'ok' });
  });

  it('exporta as selecionadas por POST, na rota e com o campo que o backend lê', async () => {
    const m = montar(componente);
    await espera();
    await aplicarLote(m.raiz, 'exportar');

    expect(baixarArquivo).toHaveBeenCalledTimes(1);
    const d = corpoDoDownload();
    expect(d.endpoint).toBe(`${raizApi}/acaoEmLote`);
    expect(d.metodo).toBe('POST');
    expect(d.acao).toBe('exportar');
    expect(d.chaves).toContain(campoIds);
    m.desmontar();
  });

  it('exclui em lote na rota existente', async () => {
    const m = montar(componente);
    await espera();
    await aplicarLote(m.raiz, 'excluir');

    const chamada = apiFetch.mock.calls.find(([url]) => String(url).includes('excluir'));
    expect(chamada?.[0]).toBe(`financeiro/${rotaExcluirLote}`);
    expect(chamada?.[1]).toMatchObject({ method: 'POST', body: { ids: expect.any(Array) } });
    m.desmontar();
  });

  it('o "Exportar Tudo" baixa pelo cliente da API', async () => {
    const m = montar(componente);
    await espera();
    (Array.from(m.raiz.querySelectorAll('button')).find((b) => b.textContent?.includes('Exportar Tudo')) as HTMLButtonElement).click();
    await espera();

    expect(baixarArquivo).toHaveBeenCalledWith(`${raizApi}/exportar`, expect.objectContaining({ method: 'GET' }));
    m.desmontar();
  });
});
