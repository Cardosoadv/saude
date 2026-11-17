// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({
  listarMenus: vi.fn(), obterSchema: vi.fn(), listarPaginas: vi.fn(), obterPagina: vi.fn(), salvarMenu: vi.fn(),
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

import MenusSiteView from '../src/views/Site/MenusSiteView.vue';
import { temAlteracoesNaoSalvas, marcarSujo } from '../src/composables/useSaidaProtegida';

const MENUS = {
  cabecalho: [
    { id: 1, rotulo: 'Áreas', tipo: 'ancora', ancora: 'expertise', pagina_id: null, url: null, destaque: false, filhos: [] },
    { id: 2, rotulo: 'Civil', tipo: 'pagina', pagina_id: 2, url: null, ancora: null, destaque: false, filhos: [] },
    { id: 3, rotulo: 'Rascunho', tipo: 'pagina', pagina_id: 3, url: null, ancora: null, destaque: false, filhos: [] },
    { id: 4, rotulo: 'Consulta Inicial', tipo: 'url', url: '/contato', pagina_id: null, ancora: null, destaque: true, filhos: [] },
  ],
  rodape: [{ id: 9, rotulo: 'Notícias', tipo: 'url', url: '/noticias', pagina_id: null, ancora: null, destaque: false, filhos: [] }],
  lateral_areas: [],
};
const PAGINAS = [
  { id: 1, titulo: 'Início', status: 'publicado', tipo: 'home' },
  { id: 2, titulo: 'Direito Civil', status: 'publicado', tipo: 'area' },
  { id: 3, titulo: 'Previdenciário', status: 'rascunho', tipo: 'area' },
];
const espera = async () => { for (let i = 0; i < 8; i++) { await nextTick(); await Promise.resolve(); } };

function montar() {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const toasts: any[] = [];
  createApp(MenusSiteView, { onToast: (t: any) => toasts.push(t) }).mount(raiz);
  return { raiz, toasts };
}
const q = (raiz: HTMLElement, sel: string) => raiz.querySelector(sel) as HTMLElement;
const clicar = (el: Element | null) => (el as HTMLElement).click();
function digitar(el: Element | null, texto: string) {
  (el as HTMLInputElement).value = texto;
  el!.dispatchEvent(new Event('input', { bubbles: true }));
}
const rotulos = (raiz: HTMLElement) => [...raiz.querySelectorAll('[data-item] [data-campo="rotulo"] input')].map((i) => (i as HTMLInputElement).value);

describe('MenusSiteView', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    marcarSujo(false);
    Object.values(api).forEach((f) => f.mockReset());
    api.listarMenus.mockResolvedValue({ data: structuredClone(MENUS) });
    api.obterSchema.mockResolvedValue({ data: { menus: ['cabecalho', 'rodape', 'lateral_areas'] } });
    api.listarPaginas.mockResolvedValue({ data: PAGINAS });
    api.obterPagina.mockResolvedValue({ data: { secoes: [{ ancora: 'expertise' }, { ancora: null }, { ancora: 'contato' }] } });
  });

  it('abas por local do template, com a árvore do primeiro', async () => {
    const { raiz } = montar();
    await espera();

    expect([...raiz.querySelectorAll('[role="tab"]')].map((t) => t.textContent?.trim())).toEqual(['Cabeçalho', 'Rodapé', 'Lateral (outras áreas)']);
    expect(rotulos(raiz)).toEqual(['Áreas', 'Civil', 'Rascunho', 'Consulta Inicial']);
    expect((q(raiz, '[data-item="3"] input[type="checkbox"]') as HTMLInputElement).checked).toBe(true);
  });

  it('trocar de aba mostra o outro menu; menu vazio avisa', async () => {
    const { raiz } = montar();
    await espera();

    clicar(q(raiz, '[data-aba="rodape"]'));
    await espera();
    expect(rotulos(raiz)).toEqual(['Notícias']);

    clicar(q(raiz, '[data-aba="lateral_areas"]'));
    await espera();
    expect(raiz.textContent).toContain('Este menu está vazio.');
  });

  it('cada item mostra o destino do seu tipo, com sugestões de âncora da home', async () => {
    const { raiz } = montar();
    await espera();

    expect((q(raiz, '[data-item="0"] [data-campo="ancora"] input') as HTMLInputElement).value).toBe('expertise');
    expect([...raiz.querySelectorAll('[data-item="0"] datalist option')].map((o) => o.getAttribute('value'))).toEqual(['expertise', 'contato']);
    expect((q(raiz, '[data-item="1"] [data-campo="pagina_id"] select') as HTMLSelectElement).value).toBe('2');
    expect((q(raiz, '[data-item="3"] [data-campo="url"] input') as HTMLInputElement).value).toBe('/contato');
  });

  it('avisa quando o item aponta para página em rascunho', async () => {
    const { raiz } = montar();
    await espera();

    expect(q(raiz, '[data-item="2"] [data-aviso-destino]').textContent).toContain('está em rascunho');
    expect(raiz.querySelector('[data-item="1"] [data-aviso-destino]')).toBeNull();
    expect(q(raiz, '[data-avisos]').textContent).toContain('"Rascunho"');
  });

  it('avisa quando a página foi excluída', async () => {
    api.listarPaginas.mockResolvedValue({ data: PAGINAS.filter((p) => p.id !== 2) });
    const { raiz } = montar();
    await espera();

    expect(q(raiz, '[data-item="1"] [data-aviso-destino]').textContent).toContain('foi excluída');
  });

  it('editar marca como não salvo; salvar manda a árvore inteira só do menu aberto', async () => {
    api.salvarMenu.mockImplementation(async (_local: string, itens: any[]) => ({ data: itens.map((i, n) => ({ ...i, id: n + 1, filhos: i.filhos ?? [] })), avisos: [] }));
    const { raiz, toasts } = montar();
    await espera();

    digitar(q(raiz, '[data-item="0"] [data-campo="rotulo"] input'), 'Áreas de Atuação');
    await espera();
    expect(temAlteracoesNaoSalvas()).toBe(true);
    expect(q(raiz, '[data-aba="cabecalho"]').textContent).toContain('•');
    expect(q(raiz, '[data-aba="rodape"]').textContent).not.toContain('•');

    clicar(q(raiz, '[data-salvar]'));
    await espera();

    expect(api.salvarMenu).toHaveBeenCalledTimes(1);
    const [local, itens] = api.salvarMenu.mock.calls[0];
    expect(local).toBe('cabecalho');
    expect(itens).toHaveLength(4);
    expect(itens[0]).toEqual({ rotulo: 'Áreas de Atuação', tipo: 'ancora', destaque: false, ancora: 'expertise' });
    expect(itens[3]).toEqual({ rotulo: 'Consulta Inicial', tipo: 'url', destaque: true, url: '/contato' });
    expect(temAlteracoesNaoSalvas()).toBe(false);
    expect(toasts.at(-1)?.type).toBe('success');
  });

  it('reordena com botões e com arrastar e soltar', async () => {
    const { raiz } = montar();
    await espera();

    clicar(q(raiz, '[data-item="0"] [data-descer]'));
    await espera();
    expect(rotulos(raiz).slice(0, 2)).toEqual(['Civil', 'Áreas']);

    q(raiz, '[data-item="3"] .alca').dispatchEvent(new Event('dragstart', { bubbles: true }));
    q(raiz, '[data-item="0"]').dispatchEvent(new Event('drop', { bubbles: true, cancelable: true }));
    await espera();
    expect(rotulos(raiz)[0]).toBe('Consulta Inicial');
  });

  it('submenu de um nível: tornar subitem, mover e tirar do submenu', async () => {
    const { raiz } = montar();
    await espera();

    clicar(q(raiz, '[data-item="1"] [data-subitem]'));
    await espera();
    expect(raiz.querySelector('[data-item="0.filhos.0"]')).not.toBeNull();
    expect(rotulos(raiz)).toEqual(['Áreas', 'Civil', 'Rascunho', 'Consulta Inicial']);
    expect(raiz.querySelector('[data-item="0.filhos.0"] [data-subitem]')).toBeNull();
    expect(q(raiz, '[data-item="0.filhos.0"]').classList.contains('menu-item--sub')).toBe(true);
    // quem já tem filhos não vira subitem
    expect((q(raiz, '[data-item="0"] [data-subitem]') as HTMLButtonElement).disabled).toBe(true);

    clicar(q(raiz, '[data-item="0.filhos.0"] [data-sair-submenu]'));
    await espera();
    expect(raiz.querySelector('[data-item="0.filhos.0"]')).toBeNull();
    expect(q(raiz, '[data-item="1"]').classList.contains('menu-item--sub')).toBe(false);
    expect(rotulos(raiz)).toEqual(['Áreas', 'Civil', 'Rascunho', 'Consulta Inicial']);
  });

  it('subitens vão aninhados no envio', async () => {
    api.salvarMenu.mockResolvedValue({ data: [], avisos: [] });
    const { raiz } = montar();
    await espera();

    clicar(q(raiz, '[data-item="1"] [data-subitem]'));
    await espera();
    clicar(q(raiz, '[data-salvar]'));
    await espera();

    const itens = api.salvarMenu.mock.calls[0][1];
    expect(itens).toHaveLength(3);
    expect(itens[0].filhos).toEqual([{ rotulo: 'Civil', tipo: 'pagina', destaque: false, pagina_id: 2 }]);
  });

  it('adicionar, trocar o tipo e remover item', async () => {
    const { raiz } = montar();
    await espera();

    clicar(q(raiz, '[data-adicionar]'));
    await espera();
    expect(rotulos(raiz)).toHaveLength(5);

    const tipo = q(raiz, '[data-item="4"] select.tipo') as HTMLSelectElement;
    tipo.value = 'url';
    tipo.dispatchEvent(new Event('change', { bubbles: true }));
    await espera();
    expect(raiz.querySelector('[data-item="4"] [data-campo="url"]')).not.toBeNull();
    expect(raiz.querySelector('[data-item="4"] [data-campo="pagina_id"]')).toBeNull();

    clicar(q(raiz, '[data-item="4"] [data-remover]'));
    await espera();
    expect(rotulos(raiz)).toHaveLength(4);
    expect(temAlteracoesNaoSalvas()).toBe(false);
  });

  it('item incompleto não vai para a API e mostra os erros no campo', async () => {
    const { raiz, toasts } = montar();
    await espera();

    clicar(q(raiz, '[data-adicionar]'));
    await espera();
    clicar(q(raiz, '[data-salvar]'));
    await espera();

    expect(api.salvarMenu).not.toHaveBeenCalled();
    expect(q(raiz, '[data-item="4"] [data-campo="rotulo"] .erro').textContent).toMatch(/Informe o rótulo/);
    expect(q(raiz, '[data-item="4"] [data-campo="pagina_id"] .erro').textContent).toMatch(/Escolha a página/);
    expect(toasts.at(-1)?.type).toBe('warning');
  });

  it('erro 422 da API aparece no item certo e nada é perdido', async () => {
    const { ApiError } = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
    api.salvarMenu.mockRejectedValue(new ApiError('422', 422, { messages: { 'itens.3.url': 'URL inválida (use http, https, mailto, tel ou um caminho iniciado por /).' } }));
    const { raiz, toasts } = montar();
    await espera();

    digitar(q(raiz, '[data-item="0"] [data-campo="rotulo"] input'), 'Outro');
    await espera();
    clicar(q(raiz, '[data-salvar]'));
    await espera();

    expect(q(raiz, '[data-item="3"] [data-campo="url"] .erro').textContent).toContain('URL inválida');
    expect(rotulos(raiz)[0]).toBe('Outro');
    expect(toasts.at(-1)?.type).toBe('danger');
    expect(temAlteracoesNaoSalvas()).toBe(true);
  });

  it('descartar volta ao salvo', async () => {
    const { raiz } = montar();
    await espera();

    digitar(q(raiz, '[data-item="0"] [data-campo="rotulo"] input'), 'Mudou');
    await espera();
    clicar(q(raiz, '[data-descartar]'));
    await espera();

    expect(rotulos(raiz)[0]).toBe('Áreas');
    expect(temAlteracoesNaoSalvas()).toBe(false);
  });

  it('o limite de 50 itens desabilita o botão de adicionar', async () => {
    api.listarMenus.mockResolvedValue({ data: { cabecalho: Array.from({ length: 50 }, (_, i) => ({ id: i + 1, rotulo: `I${i}`, tipo: 'url', url: '/x', pagina_id: null, ancora: null, destaque: false, filhos: [] })), rodape: [], lateral_areas: [] } });
    const { raiz } = montar();
    await espera();

    expect((q(raiz, '[data-adicionar]') as HTMLButtonElement).disabled).toBe(true);
  });

  it('falha ao carregar mostra o erro com nova tentativa', async () => {
    api.listarMenus.mockRejectedValueOnce(new Error('rede caiu')).mockResolvedValue({ data: structuredClone(MENUS) });
    const { raiz } = montar();
    await espera();

    expect(raiz.textContent).toContain('rede caiu');
    clicar(raiz.querySelector('[role="alert"] button'));
    await espera();
    expect(rotulos(raiz)).toHaveLength(4);
  });
});
