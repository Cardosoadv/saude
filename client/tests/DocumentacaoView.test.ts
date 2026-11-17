// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createApp, nextTick, reactive } from 'vue';

const api = vi.hoisted(() => ({ obterIndice: vi.fn(), obterPagina: vi.fn(), buscar: vi.fn(), obterPaginaDaTela: vi.fn() }));

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn() };
});
vi.mock('../src/services/auth', () => ({ getCurrentUser: vi.fn() }));
vi.mock('../src/services/documentacaoApi', () => api);

import { h } from 'vue';
import { ApiError } from '../src/services/api';
import DocumentacaoView from '../src/views/Documentacao/DocumentacaoView.vue';

const INDICE = [
  { slug: 'primeiros-passos', titulo: 'Primeiros passos', resumo: 'Comece aqui.', capa: true, paginas: [
    { slug: 'primeiros-passos/navegacao', titulo: 'Navegação', resumo: 'Menu e barra.' },
  ] },
  { slug: 'sem-capa', titulo: 'Sem Capa', resumo: '', capa: false, paginas: [
    { slug: 'sem-capa/avulsa', titulo: 'Avulsa', resumo: '' },
  ] },
];
const PAGINA = {
  slug: 'primeiros-passos/navegacao', secao: 'primeiros-passos', capa: false, titulo: 'Navegação', resumo: 'Menu e barra.',
  html: '<h2 id="menu">Menu</h2><p>Texto do <a href="#/documentacao/primeiros-passos">início</a>.</p>',
  sumario: [{ nivel: 2, texto: 'Menu', ancora: 'menu' }],
  anterior: { slug: 'primeiros-passos', titulo: 'Primeiros passos' }, proxima: null, atualizado_em: '2026-10-13T10:00:00-03:00',
};

const espera = async () => { for (let i = 0; i < 8; i++) { await nextTick(); await Promise.resolve(); } };

function montar(inicial: { slug?: string | null; ancora?: string | null } = {}) {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const props = reactive({ slug: inicial.slug ?? null, ancora: inicial.ancora ?? null });
  const navegacoes: any[] = [];
  createApp({ render: () => h(DocumentacaoView, { ...props, onNavegar: (d: any) => navegacoes.push(d) }) }).mount(raiz);
  return { raiz, props, navegacoes };
}

describe('DocumentacaoView: leitor', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    Object.values(api).forEach((f) => f.mockReset());
    api.obterIndice.mockResolvedValue({ success: true, data: structuredClone(INDICE) });
    api.obterPagina.mockResolvedValue({ success: true, data: structuredClone(PAGINA) });
  });

  it('sem slug mostra o início com as seções e o índice lateral', async () => {
    const { raiz, navegacoes } = montar();
    await espera();

    expect(api.obterIndice).toHaveBeenCalledOnce();
    expect(api.obterPagina).not.toHaveBeenCalled();
    expect(raiz.querySelectorAll('[data-inicio] [data-secao]').length).toBe(2);
    expect(raiz.querySelector('[data-secao="sem-capa"] h2 a')).toBeNull();
    expect(raiz.querySelector('.doc-arvore-inicio')!.getAttribute('aria-current')).toBe('page');

    const link = raiz.querySelector('[data-link="primeiros-passos/navegacao"]') as HTMLAnchorElement;
    expect(link.getAttribute('href')).toBe('#/documentacao/primeiros-passos/navegacao');
    link.click();
    expect(navegacoes).toEqual([{ slug: 'primeiros-passos/navegacao', ancora: null }]);
  });

  it('mostra a página, o sumário e as vizinhas, e marca a página no índice', async () => {
    const { raiz, navegacoes } = montar({ slug: 'primeiros-passos/navegacao' });
    await espera();

    expect(api.obterPagina).toHaveBeenCalledWith('primeiros-passos/navegacao');
    expect(raiz.querySelector('[data-pagina] h1')!.textContent).toBe('Navegação');
    expect(raiz.querySelector('[data-pagina] .doc-resumo')!.textContent).toBe('Menu e barra.');
    expect(raiz.querySelector('.doc-corpo h2#menu')).not.toBeNull();
    expect(raiz.querySelector('.doc-atualizado')!.textContent).toContain('13/10/2026');
    expect(raiz.querySelector('[data-arvore-link="primeiros-passos/navegacao"]')!.getAttribute('aria-current')).toBe('page');

    const item = raiz.querySelector('[data-sumario="menu"]') as HTMLAnchorElement;
    expect(item.getAttribute('href')).toBe('#/documentacao/primeiros-passos/navegacao#menu');
    item.click();
    expect(navegacoes.at(-1)).toEqual({ slug: 'primeiros-passos/navegacao', ancora: 'menu' });

    expect(raiz.querySelector('[data-proxima]')).toBeNull();
    (raiz.querySelector('[data-anterior]') as HTMLElement).click();
    expect(navegacoes.at(-1)).toEqual({ slug: 'primeiros-passos', ancora: null });
  });

  it('links da documentação dentro do conteúdo navegam sem recarregar; os outros seguem normais', async () => {
    api.obterPagina.mockResolvedValue({ success: true, data: { ...structuredClone(PAGINA),
      html: '<p><a id="l1" href="#/documentacao/processos/cadastro#partes">a</a> <a id="l2" href="#/processos">b</a> <a id="l3" href="https://x.test" target="_blank">c</a></p>' } });
    const { raiz, navegacoes } = montar({ slug: 'primeiros-passos/navegacao' });
    await espera();

    // Registra se a tela cancelou o clique e, depois dela, impede a navegação real do happy-dom.
    const clicar = (id: string) => {
      let cancelado = false;
      const depois = (ev: Event) => { cancelado = ev.defaultPrevented; ev.preventDefault(); };
      document.addEventListener('click', depois, { once: true });
      raiz.querySelector(`#${id}`)!.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 }));
      return cancelado;
    };

    expect(clicar('l1')).toBe(true);
    expect(navegacoes).toEqual([{ slug: 'processos/cadastro', ancora: 'partes' }]);
    expect(clicar('l2')).toBe(false);
    expect(clicar('l3')).toBe(false);
    expect(navegacoes).toHaveLength(1);
  });

  it('recarrega quando o slug muda e não recarrega o índice', async () => {
    const { props } = montar({ slug: 'primeiros-passos/navegacao' });
    await espera();

    props.slug = 'primeiros-passos';
    await espera();
    expect(api.obterPagina).toHaveBeenLastCalledWith('primeiros-passos');
    expect(api.obterIndice).toHaveBeenCalledOnce();

    props.slug = null;
    await espera();
    expect(api.obterPagina).toHaveBeenCalledTimes(2);
  });

  it('404 mostra "Página não encontrada" e volta ao início', async () => {
    api.obterPagina.mockRejectedValue(new ApiError('Não encontrada', 404));
    const { raiz, navegacoes } = montar({ slug: 'nao/existe' });
    await espera();

    expect(raiz.querySelector('[data-falha] h1')!.textContent).toBe('Página não encontrada');
    (raiz.querySelector('[data-voltar-inicio]') as HTMLElement).click();
    expect(navegacoes).toEqual([{ slug: null, ancora: null }]);
  });

  it('erro de rede permite tentar de novo', async () => {
    api.obterPagina.mockRejectedValueOnce(new ApiError('Falhou', 500));
    const { raiz } = montar({ slug: 'primeiros-passos/navegacao' });
    await espera();

    expect(raiz.querySelector('[data-falha] h1')!.textContent).toBe('Algo deu errado');
    (raiz.querySelector('[data-falha] .btn') as HTMLElement).click();
    await espera();
    expect(raiz.querySelector('[data-pagina] h1')!.textContent).toBe('Navegação');
  });

  it('no celular o índice abre como gaveta e fecha pelo fundo ou ao navegar', async () => {
    const original = window.matchMedia;
    (window as any).matchMedia = (q: string) => ({ matches: q.includes('max-width: 768px'), media: q, addEventListener() {}, removeEventListener() {} });
    try {
      const { raiz, navegacoes } = montar({ slug: 'primeiros-passos/navegacao' });
      await espera();
      const layout = raiz.querySelector('.doc-layout')!;

      (raiz.querySelector('[data-alternar-arvore]') as HTMLElement).click();
      await nextTick();
      expect(layout.classList.contains('gaveta-aberta')).toBe(true);
      expect(layout.classList.contains('arvore-recolhida')).toBe(false);

      (raiz.querySelector('[data-fechar-gaveta]') as HTMLElement).click();
      await nextTick();
      expect(layout.classList.contains('gaveta-aberta')).toBe(false);

      (raiz.querySelector('[data-alternar-arvore]') as HTMLElement).click();
      await nextTick();
      (raiz.querySelector('[data-arvore-link="primeiros-passos/navegacao"]') as HTMLElement).click();
      await nextTick();
      expect(layout.classList.contains('gaveta-aberta')).toBe(false);
      expect(navegacoes.at(-1)).toEqual({ slug: 'primeiros-passos/navegacao', ancora: null });
    } finally {
      (window as any).matchMedia = original;
    }
  });

  it('recolhe o índice lateral e imprime', async () => {
    const print = vi.fn();
    (window as any).print = print;
    const { raiz } = montar({ slug: 'primeiros-passos/navegacao' });
    await espera();

    const layout = raiz.querySelector('.doc-layout')!;
    (raiz.querySelector('[data-alternar-arvore]') as HTMLElement).click();
    await nextTick();
    expect(layout.classList.contains('arvore-recolhida')).toBe(true);

    (raiz.querySelector('[data-imprimir]') as HTMLElement).click();
    expect(print).toHaveBeenCalledOnce();
  });
});
