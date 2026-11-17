// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({
  listarPaginas: vi.fn(), obterSchema: vi.fn(), criarPagina: vi.fn(), atualizarPagina: vi.fn(),
  excluirPagina: vi.fn(), publicarPagina: vi.fn(), despublicarPagina: vi.fn(), duplicarPagina: vi.fn(),
  tokenPreview: vi.fn(), obterMidia: vi.fn(), listarMidias: vi.fn(),
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

import PaginasSiteView from '../src/views/Site/PaginasSiteView.vue';
import { aplicarAcesso, limparAcesso } from '../src/composables/useAcesso';

const p = (id: number, extra: Record<string, unknown> = {}) => ({
  id, slug: `pagina-${id}`, titulo: `Página ${id}`, tipo: 'pagina', layout: 'main', meta_title: null, meta_description: null,
  og_image_id: null, status: 'publicado', publicado_em: null, ordem: id, exibir_sitemap: 1, prioridade_sitemap: 0.8,
  changefreq: 'monthly', url: `http://localhost/processos/pagina/pagina-${id}`, updated_at: '2026-10-08 10:30:00', total_secoes: 3, ...extra,
});
const LISTA = [
  p(1, { tipo: 'home', slug: 'inicio', titulo: 'Início', url: 'http://localhost/processos/' }),
  p(2, { status: 'rascunho' }),
  p(3, { tipo: 'sistema', slug: 'noticias', titulo: 'Notícias', url: 'http://localhost/processos/noticias' }),
];
const espera = async () => { for (let i = 0; i < 6; i++) { await nextTick(); await Promise.resolve(); } };

function montar() {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const toasts: any[] = [];
  const abertas: number[] = [];
  createApp(PaginasSiteView, { onToast: (t: any) => toasts.push(t), onAbrirPagina: (id: number) => abertas.push(id) }).mount(raiz);
  return { raiz, toasts, abertas };
}
const linha = (raiz: HTMLElement, id: number) => raiz.querySelector(`[data-pagina="${id}"]`) as HTMLElement;
const clicar = (el: Element | null) => (el as HTMLElement).click();
function digitar(el: Element | null, texto: string) {
  (el as HTMLInputElement).value = texto;
  el!.dispatchEvent(new Event('input', { bubbles: true }));
}

describe('PaginasSiteView', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    limparAcesso();
    aplicarAcesso({ permissoes: ['site.conteudo', 'site.publicar'], modulos_ativos: ['site'] });
    Object.values(api).forEach((f) => f.mockReset());
    api.listarPaginas.mockResolvedValue({ data: LISTA });
    api.obterSchema.mockResolvedValue({ data: { layouts: ['main', 'area'] } });
    api.listarMidias.mockResolvedValue({ data: [], pager: { page: 1, per_page: 24, total: 0, last_page: 1 } });
    (window as any).confirm = vi.fn(() => true);
    (window as any).open = vi.fn();
  });
  afterEach(() => vi.useRealTimers());

  it('lista título, endereço, tipo, status e última alteração', async () => {
    const { raiz } = montar();
    await espera();

    expect(raiz.querySelectorAll('[data-pagina]')).toHaveLength(3);
    expect(linha(raiz, 1).querySelector('code')?.textContent).toBe('/');
    expect(linha(raiz, 2).querySelector('code')?.textContent).toBe('/pagina/pagina-2');
    expect(linha(raiz, 3).querySelector('code')?.textContent).toBe('/noticias');
    expect(linha(raiz, 2).querySelector('[data-status]')?.textContent).toBe('Rascunho');
    expect(linha(raiz, 1).querySelector('[data-status]')?.textContent).toBe('Publicado');
    expect(linha(raiz, 2).textContent).toContain('08/10/2026');
    expect(linha(raiz, 2).textContent).toContain('3 seções');
  });

  it('home não exclui nem publica; sistema só edita e abre', async () => {
    const { raiz } = montar();
    await espera();

    expect(linha(raiz, 1).querySelector('[data-excluir]')).toBeNull();
    expect(linha(raiz, 1).querySelector('[data-publicar],[data-despublicar]')).toBeNull();
    expect(linha(raiz, 1).querySelector('[data-duplicar]')).not.toBeNull();
    expect(linha(raiz, 3).querySelector('[data-excluir]')).toBeNull();
    expect(linha(raiz, 3).querySelector('[data-duplicar]')).toBeNull();
    expect(linha(raiz, 3).querySelector('[data-publicar]')).toBeNull();
    expect(linha(raiz, 3).querySelector('[data-dados]')).not.toBeNull();
    expect(linha(raiz, 2).querySelector('[data-excluir]')).not.toBeNull();
  });

  it('sem site.publicar não há botões de publicar nem despublicar', async () => {
    aplicarAcesso({ permissoes: ['site.conteudo'], modulos_ativos: ['site'] });
    const { raiz } = montar();
    await espera();

    expect(raiz.querySelector('[data-publicar],[data-despublicar]')).toBeNull();
    expect(linha(raiz, 2).querySelector('[data-editor]')).not.toBeNull();
  });

  it('filtra por status e busca no servidor', async () => {
    vi.useFakeTimers();
    const { raiz } = montar();
    await vi.advanceTimersByTimeAsync(0);
    api.listarPaginas.mockClear();

    const status = raiz.querySelector('select[aria-label="Filtrar por status"]') as HTMLSelectElement;
    status.value = 'rascunho';
    status.dispatchEvent(new Event('change', { bubbles: true }));
    await vi.advanceTimersByTimeAsync(0);
    expect(api.listarPaginas).toHaveBeenLastCalledWith({ status: 'rascunho', tipo: '', busca: '' });

    digitar(raiz.querySelector('input[type="search"]'), ' civil ');
    await vi.advanceTimersByTimeAsync(400);
    expect(api.listarPaginas).toHaveBeenLastCalledWith({ status: 'rascunho', tipo: '', busca: 'civil' });
  });

  it('criar: o endereço acompanha o título, mas para quando é editado à mão; leva ao editor', async () => {
    api.criarPagina.mockResolvedValue({ data: p(9) });
    const { raiz, abertas } = montar();
    await espera();

    clicar(raiz.querySelector('[data-nova]'));
    await espera();
    digitar(raiz.querySelector('#pg-titulo'), 'Direito Previdenciário');
    await espera();
    expect((raiz.querySelector('#pg-slug') as HTMLInputElement).value).toBe('direito-previdenciario');

    digitar(raiz.querySelector('#pg-slug'), 'previdenciario');
    digitar(raiz.querySelector('#pg-titulo'), 'Direito Previdenciário e Seguridade');
    await espera();
    expect((raiz.querySelector('#pg-slug') as HTMLInputElement).value).toBe('previdenciario');

    clicar(raiz.querySelector('[data-salvar]'));
    await espera();

    expect(api.criarPagina).toHaveBeenCalledWith({
      titulo: 'Direito Previdenciário e Seguridade', slug: 'previdenciario', tipo: 'pagina',
      exibir_sitemap: true, prioridade_sitemap: 0.8, changefreq: 'monthly',
    });
    expect(abertas).toEqual([9]);
    expect(raiz.querySelector('[role="dialog"]')).toBeNull();
  });

  it('criar com dados inválidos mostra os erros sem chamar a API', async () => {
    const { raiz } = montar();
    await espera();

    clicar(raiz.querySelector('[data-nova]'));
    await espera();
    clicar(raiz.querySelector('[data-salvar]'));
    await espera();

    expect(api.criarPagina).not.toHaveBeenCalled();
    expect(raiz.querySelector('[data-campo="titulo"] .erro')?.textContent).toMatch(/Informe o título/);
    expect(raiz.querySelector('[data-campo="slug"] .erro')?.textContent).toMatch(/Informe o endereço/);
  });

  it('erro 422 da API (slug repetido) aparece no campo', async () => {
    const { ApiError } = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
    api.criarPagina.mockRejectedValue(new ApiError('422', 422, { messages: { slug: 'O campo slug deve conter um valor único.' } }));
    const { raiz } = montar();
    await espera();

    clicar(raiz.querySelector('[data-nova]'));
    await espera();
    digitar(raiz.querySelector('#pg-titulo'), 'Sobre');
    await espera();
    clicar(raiz.querySelector('[data-salvar]'));
    await espera();

    expect(raiz.querySelector('[data-campo="slug"] .erro')?.textContent).toContain('valor único');
    expect(raiz.querySelector('[role="dialog"]')).not.toBeNull();
  });

  it('editar página em rascunho: mudar o endereço não avisa', async () => {
    const { raiz } = montar();
    await espera();

    clicar(linha(raiz, 2).querySelector('[data-dados]'));
    await espera();
    digitar(raiz.querySelector('#pg-slug'), 'novo-endereco');
    await espera();

    expect(raiz.querySelector('[data-aviso-slug]')).toBeNull();
  });

  it('editar página publicada: mudar o endereço avisa antes de salvar, pede confirmação e manda só o que mudou', async () => {
    api.listarPaginas.mockResolvedValue({ data: [p(5, { slug: 'antigo' })] });
    api.atualizarPagina.mockResolvedValue({ data: p(5), avisos: ['O endereço antigo deixará de funcionar.'] });
    const { raiz, toasts } = montar();
    await espera();

    clicar(raiz.querySelector('[data-pagina="5"] [data-dados]'));
    await espera();
    digitar(raiz.querySelector('#pg-slug'), 'novo');
    await espera();
    expect(raiz.querySelector('[data-aviso-slug]')?.textContent).toContain('/pagina/antigo');

    clicar(raiz.querySelector('[data-salvar]'));
    await espera();

    expect((window.confirm as any).mock.calls[0][0]).toContain('deixará de funcionar');
    expect(api.atualizarPagina).toHaveBeenCalledWith(5, { slug: 'novo' });
    expect(toasts.map((t) => t.type)).toEqual(['success', 'warning']);
  });

  it('recusar a confirmação do endereço não salva', async () => {
    (window.confirm as any).mockReturnValue(false);
    api.listarPaginas.mockResolvedValue({ data: [p(5, { slug: 'antigo' })] });
    const { raiz } = montar();
    await espera();

    clicar(raiz.querySelector('[data-pagina="5"] [data-dados]'));
    await espera();
    digitar(raiz.querySelector('#pg-slug'), 'novo');
    await espera();
    clicar(raiz.querySelector('[data-salvar]'));
    await espera();

    expect(api.atualizarPagina).not.toHaveBeenCalled();
  });

  it('publicar com erro nas seções lista o que corrigir', async () => {
    const { ApiError } = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
    api.publicarPagina.mockRejectedValue(new ApiError('422', 422, { messages: { 'secoes.34.titulo': 'Campo obrigatório.', 'secoes.34.botoes.1.rotulo': 'Campo obrigatório.' } }));
    const { raiz, abertas } = montar();
    await espera();

    clicar(linha(raiz, 2).querySelector('[data-publicar]'));
    await espera();

    const painel = raiz.querySelector('[data-falhas]')!;
    expect(painel.textContent).toContain('"Página 2" não foi publicada');
    expect(painel.textContent).toContain('Seção #34: titulo: Campo obrigatório.; botoes.1.rotulo: Campo obrigatório.');

    clicar(painel.querySelector('[data-corrigir]'));
    expect(abertas).toEqual([2]);
  });

  it('publicar com sucesso recarrega a lista', async () => {
    api.publicarPagina.mockResolvedValue({ data: p(2), avisos: [] });
    const { raiz, toasts } = montar();
    await espera();
    api.listarPaginas.mockClear();

    clicar(linha(raiz, 2).querySelector('[data-publicar]'));
    await espera();

    expect(api.publicarPagina).toHaveBeenCalledWith(2);
    expect(api.listarPaginas).toHaveBeenCalled();
    expect(toasts.at(-1)?.type).toBe('success');
  });

  it('despublicar e excluir pedem confirmação', async () => {
    api.excluirPagina.mockResolvedValue({ id: 2 });
    api.despublicarPagina.mockResolvedValue({ data: p(1) });
    api.listarPaginas.mockResolvedValue({ data: [p(7), p(2, { status: 'rascunho' })] });
    const { raiz } = montar();
    await espera();

    (window.confirm as any).mockReturnValueOnce(false);
    clicar(linha(raiz, 7).querySelector('[data-despublicar]'));
    await espera();
    expect(api.despublicarPagina).not.toHaveBeenCalled();

    clicar(linha(raiz, 7).querySelector('[data-despublicar]'));
    await espera();
    expect(api.despublicarPagina).toHaveBeenCalledWith(7);

    clicar(linha(raiz, 2).querySelector('[data-excluir]'));
    await espera();
    expect(api.excluirPagina).toHaveBeenCalledWith(2);
  });

  it('duplicar avisa que a cópia é rascunho', async () => {
    api.duplicarPagina.mockResolvedValue({ data: p(10, { titulo: 'Página 2 (cópia)', status: 'rascunho' }) });
    const { raiz, toasts } = montar();
    await espera();

    clicar(linha(raiz, 2).querySelector('[data-duplicar]'));
    await espera();

    expect(toasts.at(-1)?.msg).toContain('Página 2 (cópia)');
  });

  it('abrir no site: publicada abre a URL; rascunho abre a prévia com token', async () => {
    api.tokenPreview.mockResolvedValue({ data: { token: 't', url: 'http://localhost/processos/pagina/pagina-2?preview=t', expira_em: 'x' } });
    const { raiz } = montar();
    await espera();

    clicar(linha(raiz, 1).querySelector('[data-abrir-site]'));
    await espera();
    expect(window.open).toHaveBeenLastCalledWith('http://localhost/processos/', '_blank', 'noopener');

    clicar(linha(raiz, 2).querySelector('[data-abrir-site]'));
    await espera();
    expect(api.tokenPreview).toHaveBeenCalledWith(2);
    expect(window.open).toHaveBeenLastCalledWith('http://localhost/processos/pagina/pagina-2?preview=t', '_blank', 'noopener');
  });

  it('clicar no título abre o editor de seções', async () => {
    const { raiz, abertas } = montar();
    await espera();

    clicar(linha(raiz, 2).querySelector('.titulo'));

    expect(abertas).toEqual([2]);
  });

  it('falha ao listar mostra o erro com nova tentativa', async () => {
    api.listarPaginas.mockRejectedValueOnce(new Error('rede caiu')).mockResolvedValue({ data: LISTA });
    const { raiz } = montar();
    await espera();

    expect(raiz.querySelector('.erro')?.textContent).toContain('rede caiu');
    clicar(raiz.querySelector('.erro button'));
    await espera();
    expect(raiz.querySelectorAll('[data-pagina]')).toHaveLength(3);
  });
});
