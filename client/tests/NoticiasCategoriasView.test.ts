// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({
  listarCategoriasNoticias: vi.fn(), criarCategoriaNoticia: vi.fn(), atualizarCategoriaNoticia: vi.fn(), excluirCategoriaNoticia: vi.fn(),
}));

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn() };
});
vi.mock('../src/services/auth', () => ({ getCurrentUser: vi.fn() }));
vi.mock('../src/services/siteApi', async () => {
  const real = await vi.importActual<typeof import('../src/services/siteApi')>('../src/services/siteApi');
  return { ...real, ...api };
});

import NoticiasCategoriasView from '../src/views/Noticias/NoticiasCategoriasView.vue';
import { ApiError } from '../src/services/api';

const c = (id: number, name: string, total = 0, extra: Record<string, unknown> = {}) => ({
  id, name, slug: name.toLowerCase(), meta_description: null, total_noticias: total, created_at: null, updated_at: null, ...extra,
});
const LISTA = [c(1, 'Civil', 3, { meta_description: 'Direito civil' }), c(2, 'Tributario')];
const espera = async () => { for (let i = 0; i < 6; i++) { await nextTick(); await Promise.resolve(); } };

function montar() {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const toasts: any[] = [];
  const views: string[] = [];
  createApp(NoticiasCategoriasView, { onToast: (t: any) => toasts.push(t), onSetView: (v: string) => views.push(v) }).mount(raiz);
  return { raiz, toasts, views };
}
const linha = (raiz: HTMLElement, id: number) => raiz.querySelector(`[data-categoria="${id}"]`) as HTMLElement;
const clicar = (el: Element | null) => (el as HTMLElement).click();
function digitar(el: Element | null, texto: string) {
  (el as HTMLInputElement).value = texto;
  el!.dispatchEvent(new Event('input', { bubbles: true }));
}

describe('NoticiasCategoriasView', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    Object.values(api).forEach((f) => f.mockReset());
    api.listarCategoriasNoticias.mockResolvedValue({ data: LISTA });
    (window as any).confirm = vi.fn(() => true);
  });

  it('lista as categorias com total de notícias; a que tem notícias não pode ser excluída', async () => {
    const { raiz } = montar();
    await espera();

    expect(linha(raiz, 1).textContent).toContain('Direito civil');
    expect(linha(raiz, 1).querySelector('[data-total]')!.textContent).toBe('3');
    expect((linha(raiz, 1).querySelector('[data-excluir]') as HTMLButtonElement).disabled).toBe(true);
    expect((linha(raiz, 2).querySelector('[data-excluir]') as HTMLButtonElement).disabled).toBe(false);
  });

  it('cria categoria validando o nome antes de chamar a API', async () => {
    api.criarCategoriaNoticia.mockResolvedValue({ data: c(3, 'Família') });
    const { raiz, toasts } = montar();
    await espera();

    clicar(raiz.querySelector('[data-nova]'));
    await espera();
    clicar(document.querySelector('[data-salvar]'));
    await espera();
    expect(document.querySelector('[data-campo="name"]')!.textContent).toContain('Informe o nome');
    expect(api.criarCategoriaNoticia).not.toHaveBeenCalled();

    digitar(document.querySelector('#cat-nome'), ' Família ');
    await espera();
    expect((document.querySelector('#cat-slug') as HTMLInputElement).placeholder).toBe('familia');
    clicar(document.querySelector('[data-salvar]'));
    await espera();

    expect(api.criarCategoriaNoticia).toHaveBeenCalledWith({ name: 'Família', slug: '', meta_description: '' });
    expect(toasts.at(-1)).toEqual({ msg: 'Categoria criada.', type: 'success' });
    expect(document.querySelector('[data-salvar]')).toBeNull();
    expect(api.listarCategoriasNoticias).toHaveBeenCalledTimes(2);
  });

  it('edita e mostra os erros por campo devolvidos pela API', async () => {
    api.atualizarCategoriaNoticia.mockRejectedValueOnce(
      new ApiError('Erro', 422, { messages: { slug: 'Este endereço já é usado por outra categoria.' } }),
    );
    const { raiz } = montar();
    await espera();

    clicar(linha(raiz, 2).querySelector('[data-editar]'));
    await espera();
    expect((document.querySelector('#cat-nome') as HTMLInputElement).value).toBe('Tributario');
    digitar(document.querySelector('#cat-slug'), 'civil');
    clicar(document.querySelector('[data-salvar]'));
    await espera();

    expect(api.atualizarCategoriaNoticia).toHaveBeenCalledWith(2, { name: 'Tributario', slug: 'civil', meta_description: '' });
    expect(document.querySelector('[data-campo="slug"]')!.textContent).toContain('já é usado');
  });

  it('exclui após confirmar e avisa quando a API recusa', async () => {
    api.excluirCategoriaNoticia.mockResolvedValueOnce({ id: 2, message: 'ok' })
      .mockRejectedValueOnce(new ApiError('Erro', 422, { messages: { categoria: 'A categoria tem 1 notícia.' } }));
    const { raiz, toasts } = montar();
    await espera();

    clicar(linha(raiz, 2).querySelector('[data-excluir]'));
    await espera();
    expect(api.excluirCategoriaNoticia).toHaveBeenCalledWith(2);
    expect(toasts.at(-1).type).toBe('success');

    clicar(linha(raiz, 2).querySelector('[data-excluir]'));
    await espera();
    expect(toasts.at(-1)).toEqual({ msg: 'A categoria tem 1 notícia.', type: 'danger' });
  });

  it('o breadcrumb volta para a lista de notícias', async () => {
    const { raiz, views } = montar();
    await espera();
    clicar(raiz.querySelector('.breadcrumb a'));
    expect(views).toEqual(['noticias']);
  });
});
