// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({
  obterPagina: vi.fn(), obterSchema: vi.fn(), tokenPreview: vi.fn(), atualizarSecao: vi.fn(), criarSecao: vi.fn(),
  excluirSecao: vi.fn(), reordenarSecoes: vi.fn(), publicarPagina: vi.fn(), despublicarPagina: vi.fn(),
  atualizarPagina: vi.fn(), obterMidia: vi.fn(), listarMidias: vi.fn(), listarPaginas: vi.fn(),
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

import PaginaEditorView from '../src/views/Site/PaginaEditorView.vue';
import { aplicarAcesso, limparAcesso } from '../src/composables/useAcesso';
import { temAlteracoesNaoSalvas, marcarSujo } from '../src/composables/useSaidaProtegida';

const SCHEMA = {
  template: { slug: 'classico', nome: 'Clássico', versao: '1' },
  layouts: ['main', 'area'], menus: ['cabecalho'], textos: {}, tema: { cores: {}, fontes: {} },
  secoes: {
    hero: { rotulo: 'Destaque principal', campos: { titulo: { tipo: 'texto_inline', obrigatorio: true }, subtitulo: { tipo: 'textarea' } } },
    pilares: { rotulo: 'Pilares', campos: { itens: { tipo: 'lista', itens: { titulo: { tipo: 'texto', obrigatorio: true } } } } },
  },
};
const sec = (id: number, tipo: string, dados: Record<string, any>, extra: Record<string, unknown> = {}) => ({
  id, pagina_id: 1, tipo, ancora: null, ordem: id, visivel: 1, dados, compativel: true, ...extra,
});
const PAGINA = (extra: Record<string, unknown> = {}) => ({
  id: 1, slug: 'inicio', titulo: 'Início', tipo: 'pagina', layout: 'main', meta_title: null, meta_description: null, og_image_id: null,
  status: 'rascunho', ordem: 1, exibir_sitemap: 1, prioridade_sitemap: 0.8, changefreq: 'monthly', url: 'http://localhost/processos/pagina/inicio',
  secoes: [sec(10, 'hero', { titulo: 'Bem-vindo' }), sec(11, 'pilares', { itens: [{ titulo: 'Ética' }] }), sec(12, 'faq', { x: 1 }, { compativel: false, visivel: 0 })],
  ...extra,
});
const espera = async () => { for (let i = 0; i < 8; i++) { await nextTick(); await Promise.resolve(); } };

function montar(props: Record<string, any> = { paginaId: 1 }) {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const toasts: any[] = [];
  const views: string[] = [];
  createApp(PaginaEditorView, { ...props, onToast: (t: any) => toasts.push(t), onSetView: (v: string) => views.push(v) }).mount(raiz);
  return { raiz, toasts, views };
}
const q = (raiz: HTMLElement, sel: string) => raiz.querySelector(sel) as HTMLElement;
const clicar = (el: Element | null) => (el as HTMLElement).click();
function digitar(el: Element | null, texto: string) {
  (el as HTMLInputElement).value = texto;
  el!.dispatchEvent(new Event('input', { bubbles: true }));
}

describe('PaginaEditorView', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    limparAcesso();
    marcarSujo(false);
    aplicarAcesso({ permissoes: ['site.conteudo', 'site.publicar'], modulos_ativos: ['site'] });
    Object.values(api).forEach((f) => f.mockReset());
    api.obterPagina.mockResolvedValue({ data: PAGINA() });
    api.obterSchema.mockResolvedValue({ data: SCHEMA });
    api.tokenPreview.mockResolvedValue({ data: { token: 't', url: 'about:blank#preview=t', expira_em: 'x' } });
    api.listarMidias.mockResolvedValue({ data: [], pager: { page: 1, per_page: 24, total: 0, last_page: 1 } });
    (window as any).confirm = vi.fn(() => true);
    (window as any).happyDOM.settings.disableIframePageLoading = true;
    (window as any).happyDOM.settings.handleDisabledFileLoadingAsSuccess = true;
  });

  it('lista as seções com tipo e resumo, abre a primeira e carrega a prévia', async () => {
    const { raiz } = montar();
    await espera();

    const lista = [...raiz.querySelectorAll('[data-secao]')].map((s) => s.textContent);
    expect(lista).toHaveLength(3);
    expect(lista[0]).toContain('Destaque principal');
    expect(lista[0]).toContain('Bem-vindo');
    expect(lista[1]).toContain('Ética');
    expect((q(raiz, '#campo-titulo') as HTMLInputElement).value).toBe('Bem-vindo');
    expect(q(raiz, '[data-iframe]').getAttribute('src')).toBe('about:blank#preview=t');
    expect(q(raiz, '[data-status]').textContent).toBe('Rascunho');
  });

  it('seção incompatível: aviso, sem formulário, preservada', async () => {
    const { raiz } = montar();
    await espera();

    expect(q(raiz, '[data-secao="12"] [data-incompativel]')).not.toBeNull();
    clicar(q(raiz, '[data-secao="12"] [data-selecionar]'));
    await espera();

    expect(q(raiz, '[data-aviso-incompativel]').textContent).toContain('o conteúdo foi preservado');
    expect(raiz.querySelector('.schema-form')).toBeNull();
    expect(raiz.querySelector('[data-salvar-secao]')).toBeNull();
  });

  it('editar marca como não salvo; trocar de seção não perde o que foi digitado', async () => {
    const { raiz } = montar();
    await espera();

    digitar(q(raiz, '#campo-titulo'), 'Novo título');
    await espera();
    expect(raiz.textContent).toContain('Alterações não salvas');
    expect(temAlteracoesNaoSalvas()).toBe(true);

    clicar(q(raiz, '[data-secao="11"] [data-selecionar]'));
    await espera();
    clicar(q(raiz, '[data-secao="10"] [data-selecionar]'));
    await espera();

    expect((q(raiz, '#campo-titulo') as HTMLInputElement).value).toBe('Novo título');
  });

  it('salvar seção manda dados e âncora, e zera o estado', async () => {
    api.atualizarSecao.mockResolvedValue({ data: sec(10, 'hero', { titulo: 'Novo título', subtitulo: '' }, { ancora: 'topo' }), avisos: [] });
    const { raiz, toasts } = montar();
    await espera();

    digitar(q(raiz, '#campo-titulo'), 'Novo título');
    digitar(q(raiz, '#ed-ancora'), 'topo');
    await espera();
    clicar(q(raiz, '[data-salvar-secao]'));
    await espera();

    expect(api.atualizarSecao).toHaveBeenCalledWith(10, { dados: { titulo: 'Novo título', subtitulo: '' }, ancora: 'topo' });
    expect(temAlteracoesNaoSalvas()).toBe(false);
    expect(toasts.at(-1)?.type).toBe('success');
    expect(api.tokenPreview).toHaveBeenCalledTimes(1 + 0 || 1);
  });

  it('erro 422 aparece no campo (inclusive em listas) e mantém o digitado', async () => {
    const { ApiError } = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
    api.atualizarSecao.mockRejectedValue(new ApiError('422', 422, { messages: { 'dados.itens.0.titulo': 'Campo obrigatório.' } }));
    const { raiz, toasts } = montar();
    await espera();

    clicar(q(raiz, '[data-secao="11"] [data-selecionar]'));
    await espera();
    digitar(q(raiz, '[data-campo="itens.0.titulo"] input'), '');
    await espera();
    clicar(q(raiz, '[data-salvar-secao]'));
    await espera();

    expect(q(raiz, '[data-campo="itens.0.titulo"] > .schema-erro').textContent).toBe('Campo obrigatório.');
    expect(toasts.at(-1)?.type).toBe('danger');
    expect(temAlteracoesNaoSalvas()).toBe(true);
  });

  it('âncora inválida bloqueia o salvamento', async () => {
    const { raiz } = montar();
    await espera();

    digitar(q(raiz, '#ed-ancora'), 'Contato Aqui');
    await espera();

    expect(q(raiz, '[data-campo-ancora] .erro').textContent).toMatch(/minúsculas/);
    clicar(q(raiz, '[data-salvar-secao]'));
    await espera();
    expect(api.atualizarSecao).not.toHaveBeenCalled();
  });

  it('ocultar/mostrar salva na hora e desfaz se a API falhar', async () => {
    api.atualizarSecao.mockResolvedValueOnce({ data: sec(10, 'hero', {}) }).mockRejectedValueOnce(new Error('rede'));
    const { raiz, toasts } = montar();
    await espera();

    clicar(q(raiz, '[data-secao="10"] [data-visivel]'));
    await espera();
    expect(api.atualizarSecao).toHaveBeenCalledWith(10, { visivel: false });
    expect(q(raiz, '[data-secao="10"]').classList.contains('secao--oculta')).toBe(true);

    clicar(q(raiz, '[data-secao="10"] [data-visivel]'));
    await espera();
    expect(q(raiz, '[data-secao="10"]').classList.contains('secao--oculta')).toBe(true);
    expect(toasts.at(-1)?.type).toBe('danger');
  });

  it('reordena com os botões e com arrastar e soltar (PUT ordem)', async () => {
    api.reordenarSecoes.mockResolvedValue({ data: [] });
    const { raiz } = montar();
    await espera();

    clicar(q(raiz, '[data-secao="10"] [data-descer]'));
    await espera();
    expect(api.reordenarSecoes).toHaveBeenLastCalledWith(1, [11, 10, 12]);
    expect([...raiz.querySelectorAll('[data-secao]')].map((s) => s.getAttribute('data-secao'))).toEqual(['11', '10', '12']);

    q(raiz, '[data-secao="12"] .alca').dispatchEvent(new Event('dragstart', { bubbles: true }));
    q(raiz, '[data-secao="11"]').dispatchEvent(new Event('drop', { bubbles: true, cancelable: true }));
    await espera();
    expect(api.reordenarSecoes).toHaveBeenLastCalledWith(1, [12, 11, 10]);
  });

  it('se a reordenação falhar, a ordem volta', async () => {
    api.reordenarSecoes.mockRejectedValue(new Error('rede'));
    const { raiz } = montar();
    await espera();

    clicar(q(raiz, '[data-secao="10"] [data-descer]'));
    await espera();

    expect([...raiz.querySelectorAll('[data-secao]')].map((s) => s.getAttribute('data-secao'))).toEqual(['10', '11', '12']);
  });

  it('adicionar: escolhe o tipo, preenche e cria (a seção só existe no servidor ao salvar)', async () => {
    api.criarSecao.mockResolvedValue({ data: sec(20, 'hero', { titulo: 'Nova', subtitulo: '' }), avisos: [] });
    const { raiz } = montar();
    await espera();

    clicar(q(raiz, '[data-adicionar]'));
    await espera();
    clicar(q(document.body, '[data-tipo="hero"]'));
    await espera();

    expect(raiz.querySelectorAll('[data-secao]')).toHaveLength(4);
    expect(api.criarSecao).not.toHaveBeenCalled();
    expect(q(raiz, '[data-secao="novo-1"]').textContent).toContain('Nova, ainda não salva');

    digitar(q(raiz, '#campo-titulo'), 'Nova');
    await espera();
    clicar(q(raiz, '[data-salvar-secao]'));
    await espera();

    expect(api.criarSecao).toHaveBeenCalledWith(1, { tipo: 'hero', dados: { titulo: 'Nova', subtitulo: '' }, ancora: null, visivel: true });
    expect(q(raiz, '[data-secao="20"]')).not.toBeNull();
    expect(raiz.querySelector('[data-secao="novo-1"]')).toBeNull();
  });

  it('cancelar uma seção nova a remove da lista', async () => {
    const { raiz } = montar();
    await espera();

    clicar(q(raiz, '[data-adicionar]'));
    await espera();
    clicar(q(document.body, '[data-tipo="pilares"]'));
    await espera();
    clicar(q(raiz, '[data-descartar]'));
    await espera();

    expect(raiz.querySelector('[data-secao="novo-1"]')).toBeNull();
  });

  it('excluir pede confirmação', async () => {
    api.excluirSecao.mockResolvedValue({ id: 10 });
    const { raiz } = montar();
    await espera();

    (window.confirm as any).mockReturnValueOnce(false);
    clicar(q(raiz, '[data-excluir]'));
    await espera();
    expect(api.excluirSecao).not.toHaveBeenCalled();

    clicar(q(raiz, '[data-excluir]'));
    await espera();
    expect(api.excluirSecao).toHaveBeenCalledWith(10);
    expect(raiz.querySelector('[data-secao="10"]')).toBeNull();
  });

  it('duplicar cria a cópia sem âncora, logo depois da original', async () => {
    api.criarSecao.mockResolvedValue({ data: sec(30, 'hero', { titulo: 'Bem-vindo', subtitulo: '' }) });
    api.reordenarSecoes.mockResolvedValue({ data: [] });
    const { raiz } = montar();
    await espera();

    clicar(q(raiz, '[data-duplicar]'));
    await espera();

    expect(api.criarSecao).toHaveBeenCalledWith(1, { tipo: 'hero', dados: { titulo: 'Bem-vindo', subtitulo: '' }, ancora: null, visivel: true });
    expect(api.reordenarSecoes).toHaveBeenCalledWith(1, [10, 30, 11, 12]);
    expect([...raiz.querySelectorAll('[data-secao]')].map((s) => s.getAttribute('data-secao'))).toEqual(['10', '30', '11', '12']);
  });

  it('duplicar fica desabilitado enquanto a seção tem alterações', async () => {
    const { raiz } = montar();
    await espera();

    digitar(q(raiz, '#campo-titulo'), 'Mudou');
    await espera();

    expect((q(raiz, '[data-duplicar]') as HTMLButtonElement).disabled).toBe(true);
  });

  it('Salvar rascunho grava todas as seções alteradas', async () => {
    api.atualizarSecao.mockImplementation(async (id: number) => ({ data: sec(id, id === 10 ? 'hero' : 'pilares', id === 10 ? { titulo: 'A', subtitulo: '' } : { itens: [{ titulo: 'B' }] }) }));
    const { raiz, toasts } = montar();
    await espera();

    digitar(q(raiz, '#campo-titulo'), 'A');
    await espera();
    clicar(q(raiz, '[data-secao="11"] [data-selecionar]'));
    await espera();
    digitar(q(raiz, '[data-campo="itens.0.titulo"] input'), 'B');
    await espera();
    clicar(q(raiz, '[data-salvar-tudo]'));
    await espera();

    expect(api.atualizarSecao).toHaveBeenCalledTimes(2);
    expect(temAlteracoesNaoSalvas()).toBe(false);
    expect(toasts.at(-1)?.msg).toBe('Rascunho salvo.');
  });

  it('publicar salva antes o que está pendente; erro de publicação lista as seções', async () => {
    const { ApiError } = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
    api.atualizarSecao.mockResolvedValue({ data: sec(10, 'hero', { titulo: 'A', subtitulo: '' }) });
    api.publicarPagina.mockRejectedValue(new ApiError('422', 422, { messages: { 'secoes.11.itens.0.titulo': 'Campo obrigatório.' } }));
    const { raiz } = montar();
    await espera();

    digitar(q(raiz, '#campo-titulo'), 'A');
    await espera();
    clicar(q(raiz, '[data-publicar]'));
    await espera();

    expect(api.atualizarSecao).toHaveBeenCalled();
    expect(api.publicarPagina).toHaveBeenCalledWith(1);
    expect(q(raiz, '[data-falhas]').textContent).toContain('Seção #11');
    expect(q(raiz, '[data-falhas]').textContent).toContain('itens.0.titulo: Campo obrigatório.');
  });

  it('publicar com sucesso muda o status; despublicar pede confirmação', async () => {
    api.publicarPagina.mockResolvedValue({ data: PAGINA({ status: 'publicado' }), avisos: [] });
    api.despublicarPagina.mockResolvedValue({ data: PAGINA({ status: 'rascunho' }) });
    const { raiz } = montar();
    await espera();

    clicar(q(raiz, '[data-publicar]'));
    await espera();
    expect(q(raiz, '[data-status]').textContent).toBe('Publicado');

    clicar(q(raiz, '[data-despublicar]'));
    await espera();
    expect(window.confirm).toHaveBeenCalled();
    expect(q(raiz, '[data-status]').textContent).toBe('Rascunho');
  });

  it('sem site.publicar não há botões de publicar; home e sistema também não', async () => {
    aplicarAcesso({ permissoes: ['site.conteudo'], modulos_ativos: ['site'] });
    const a = montar();
    await espera();
    expect(a.raiz.querySelector('[data-publicar],[data-despublicar]')).toBeNull();

    document.body.innerHTML = '';
    aplicarAcesso({ permissoes: ['site.conteudo', 'site.publicar'], modulos_ativos: ['site'] });
    api.obterPagina.mockResolvedValue({ data: PAGINA({ tipo: 'home' }) });
    const b = montar();
    await espera();
    expect(b.raiz.querySelector('[data-publicar],[data-despublicar]')).toBeNull();
  });

  it('prévia: alterna celular/desktop e recarrega com novo token', async () => {
    const { raiz } = montar();
    await espera();

    clicar(q(raiz, '[data-celular]'));
    await espera();
    expect(q(raiz, '[data-iframe]').getAttribute('style')).toContain('390px');

    api.tokenPreview.mockClear();
    clicar(q(raiz, '[data-recarregar-previa]'));
    await espera();
    expect(api.tokenPreview).toHaveBeenCalledWith(1);

    clicar(q(raiz, '[data-previa-toggle]'));
    await espera();
    expect(raiz.querySelector('[data-iframe]')).toBeNull();
  });

  it('página de sistema: só dados e SEO', async () => {
    api.obterPagina.mockResolvedValue({ data: PAGINA({ tipo: 'sistema', slug: 'noticias', secoes: [] }) });
    const { raiz } = montar();
    await espera();

    expect(raiz.textContent).toContain('Páginas de sistema');
    expect(raiz.querySelector('[data-adicionar]')).toBeNull();
    expect(api.tokenPreview).not.toHaveBeenCalled();
  });

  it('Dados e SEO: edita e manda só o que mudou', async () => {
    api.atualizarPagina.mockResolvedValue({ data: PAGINA({ titulo: 'Novo' }), avisos: [] });
    const { raiz } = montar();
    await espera();

    clicar(q(raiz, '[data-dados]'));
    await espera();
    digitar(q(document.body, '#pg-titulo'), 'Novo');
    await espera();
    clicar(q(document.body, '[data-salvar-dados]'));
    await espera();

    expect(api.atualizarPagina).toHaveBeenCalledWith(1, { titulo: 'Novo' });
    expect(q(raiz, 'h1').textContent).toBe('Novo');
  });

  it('voltar leva à lista; falha de carga mostra o erro', async () => {
    api.obterPagina.mockRejectedValueOnce(new Error('rede caiu')).mockResolvedValue({ data: PAGINA() });
    const { raiz, views } = montar();
    await espera();

    expect(q(raiz, '[role="alert"]').textContent).toContain('rede caiu');
    clicar(raiz.querySelector('[role="alert"] button'));
    await espera();
    clicar(q(raiz, '.breadcrumb a'));
    expect(views).toEqual(['site-paginas']);
  });
});
