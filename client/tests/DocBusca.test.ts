// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({ obterIndice: vi.fn(), obterPagina: vi.fn(), buscar: vi.fn(), obterPaginaDaTela: vi.fn() }));

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn() };
});
vi.mock('../src/services/auth', () => ({ getCurrentUser: vi.fn() }));
vi.mock('../src/services/documentacaoApi', () => api);

import DocBusca from '../src/components/documentacao/DocBusca.vue';

const RESULTADOS = [
  { slug: 'processos/cadastro', titulo: 'Cadastro de processos', secao: 'Processos', trecho: 'Informe o número CNJ da ação.' },
  { slug: 'primeiros-passos', titulo: 'Primeiros passos', secao: 'Primeiros passos', trecho: '…o número do processo…' },
];

const espera = async () => { for (let i = 0; i < 6; i++) { await nextTick(); await Promise.resolve(); } };

function montar() {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const navegacoes: string[] = [];
  const app = createApp(DocBusca, { onNavegar: (s: string) => navegacoes.push(s) });
  app.mount(raiz);
  const campo = raiz.querySelector('[data-busca]') as HTMLInputElement;
  return { raiz, navegacoes, campo, app };
}

async function digitar(campo: HTMLInputElement, texto: string) {
  campo.value = texto;
  campo.dispatchEvent(new Event('input', { bubbles: true }));
  await vi.advanceTimersByTimeAsync(300);
  await espera();
}

const tecla = (el: Element, key: string) => el.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }));

describe('DocBusca', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    document.body.innerHTML = '';
    Object.values(api).forEach((f) => f.mockReset());
    api.buscar.mockResolvedValue({ success: true, data: structuredClone(RESULTADOS) });
  });
  afterEach(() => vi.useRealTimers());

  it('busca com debounce e destaca os termos', async () => {
    const { raiz, campo } = montar();

    campo.value = 'num';
    campo.dispatchEvent(new Event('input', { bubbles: true }));
    await digitar(campo, 'numero cnj');

    expect(api.buscar).toHaveBeenCalledOnce();
    expect(api.buscar.mock.calls[0][0]).toBe('numero cnj');
    expect(raiz.querySelectorAll('[data-resultado]').length).toBe(2);
    expect(raiz.querySelector('[data-resultado="processos/cadastro"] .doc-busca-trecho')!.innerHTML)
      .toBe('Informe o <mark>número</mark> <mark>CNJ</mark> da ação.');
    expect(raiz.querySelector('[data-resultado="processos/cadastro"]')!.getAttribute('aria-selected')).toBe('true');
  });

  it('termo curto não busca', async () => {
    const { raiz, campo } = montar();
    await digitar(campo, 'a');

    expect(api.buscar).not.toHaveBeenCalled();
    expect(raiz.querySelector('.doc-busca-painel')).toBeNull();
  });

  it('teclado: setas escolhem e Enter navega', async () => {
    const { raiz, campo, navegacoes } = montar();
    await digitar(campo, 'numero');

    tecla(campo, 'ArrowDown');
    await nextTick();
    expect(raiz.querySelector('[data-resultado="primeiros-passos"]')!.getAttribute('aria-selected')).toBe('true');
    tecla(campo, 'ArrowDown');
    tecla(campo, 'ArrowUp');
    await nextTick();
    expect(campo.getAttribute('aria-activedescendant')).toBe('doc-busca-item-1');

    tecla(campo, 'Enter');
    await nextTick();
    expect(navegacoes).toEqual(['primeiros-passos']);
    expect(campo.value).toBe('');
    expect(raiz.querySelector('.doc-busca-painel')).toBeNull();
  });

  it('clique no resultado navega; Escape fecha', async () => {
    const { raiz, campo, navegacoes } = montar();
    await digitar(campo, 'numero');

    tecla(campo, 'Escape');
    await nextTick();
    expect(raiz.querySelector('.doc-busca-painel')).toBeNull();

    tecla(campo, 'ArrowDown');
    await nextTick();
    raiz.querySelector('[data-resultado="processos/cadastro"]')!.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }));
    expect(navegacoes).toEqual(['processos/cadastro']);
  });

  it('sem resultados e falha', async () => {
    api.buscar.mockResolvedValueOnce({ success: true, data: [] });
    const { raiz, campo } = montar();
    await digitar(campo, 'inexistente');
    expect(raiz.querySelector('[data-sem-resultados]')!.textContent).toContain('inexistente');

    api.buscar.mockRejectedValueOnce(new Error('rede'));
    await digitar(campo, 'outro termo');
    expect(raiz.querySelector('[role="alert"]')!.textContent).toContain('Não foi possível buscar');
  });

  it('cancela a busca anterior quando uma nova começa', async () => {
    let primeira: AbortSignal | undefined;
    api.buscar.mockImplementationOnce((_q: string, signal: AbortSignal) => { primeira = signal; return new Promise(() => {}); });
    const { campo } = montar();

    await digitar(campo, 'primeira');
    await digitar(campo, 'segunda');

    expect(primeira!.aborted).toBe(true);
  });

  it('atalho "/" foca a busca, mas não quando já se digita em outro campo', async () => {
    const { campo, app } = montar();
    const outro = document.createElement('input');
    document.body.appendChild(outro);

    outro.focus();
    tecla(outro, '/');
    expect(document.activeElement).toBe(outro);

    tecla(document.body, '/');
    expect(document.activeElement).toBe(campo);

    app.unmount();
    outro.focus();
    tecla(document.body, '/');
    expect(document.activeElement).toBe(outro);
  });
});
