// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, h, nextTick, reactive } from 'vue';

const api = vi.hoisted(() => ({
  obterIndice: vi.fn(), obterPagina: vi.fn(), buscar: vi.fn(), obterPaginaDaTela: vi.fn(),
  listarParaEdicao: vi.fn(), obterParaEdicao: vi.fn(), criarPagina: vi.fn(), salvarPagina: vi.fn(),
  publicarPagina: vi.fn(), despublicarPagina: vi.fn(), excluirPagina: vi.fn(), copiarDoArquivo: vi.fn(),
  previsualizar: vi.fn(), enviarImagem: vi.fn(),
}));

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn(), debounce: (fn: any) => fn };
});
vi.mock('../src/services/auth', () => ({ getCurrentUser: vi.fn() }));
vi.mock('../src/services/documentacaoApi', () => api);

import { ApiError } from '../src/services/api';
import { aplicarAcesso, limparAcesso } from '../src/composables/useAcesso';
import { temAlteracoesNaoSalvas, marcarSujo } from '../src/composables/useSaidaProtegida';
import DocumentacaoAdminView from '../src/views/Documentacao/DocumentacaoAdminView.vue';
import DocumentacaoEditorView from '../src/views/Documentacao/DocumentacaoEditorView.vue';
import DocumentacaoView from '../src/views/Documentacao/DocumentacaoView.vue';

const PAGINA = {
  id: 7, slug: 'processos/prazos', titulo: 'Prazos', resumo: 'Como contar', ordem: 20, modulo: null, permissao: null,
  telas: ['tarefas'], palavras_chave: ['prazo'], markdown: '## Contagem\n', status: 'rascunho', tem_arquivo: false,
  publicado_em: null, criado_em: '2026-10-14 10:00:00', atualizado_em: '2026-10-14 10:00:00',
};
const LISTA = [
  { id: null, slug: 'processos', titulo: 'Processos', secao: 'processos', origem: 'arquivo', status: 'publicado', tem_arquivo: true, atualizado_em: '2026-10-13T10:00:00-03:00' },
  { id: 7, slug: 'processos/prazos', titulo: 'Prazos', secao: 'processos', origem: 'banco', status: 'rascunho', tem_arquivo: false, atualizado_em: '2026-10-14T10:00:00-03:00' },
];

const espera = async () => { for (let i = 0; i < 10; i++) { await nextTick(); await Promise.resolve(); } };
const montados: { unmount(): void }[] = [];

function montar(componente: any, props: Record<string, any> = {}) {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const estado = reactive({ ...props });
  const eventos: Record<string, any[]> = {};
  const ouvintes = new Proxy({}, {
    get: (_t, nome: string) => (...args: any[]) => { (eventos[nome] ??= []).push(args.length > 1 ? args : args[0]); },
  });
  const app = createApp({
    render: () => h(componente, {
      ...estado,
      onAbrirEditor: (ouvintes as any).abrirEditor, onNova: (ouvintes as any).nova, onAbrirDocumentacao: (ouvintes as any).abrirDocumentacao,
      onToast: (ouvintes as any).toast, onCriada: (ouvintes as any).criada, onExcluida: (ouvintes as any).excluida,
      onVoltar: (ouvintes as any).voltar, onAbrirEdicao: (ouvintes as any).abrirEdicao, onNavegar: (ouvintes as any).navegar,
    }),
  });
  app.mount(raiz);
  montados.push(app);
  return { raiz, estado, eventos };
}
const q = (raiz: Element, sel: string) => raiz.querySelector(sel) as HTMLElement;
function digitar(el: Element, texto: string) {
  (el as HTMLInputElement).value = texto;
  el.dispatchEvent(new Event('input', { bubbles: true }));
}

beforeEach(() => {
  document.body.innerHTML = '';
  marcarSujo(false);
  Object.values(api).forEach((f) => f.mockReset());
  api.listarParaEdicao.mockResolvedValue({ data: structuredClone(LISTA) });
  api.obterParaEdicao.mockResolvedValue({ data: structuredClone(PAGINA) });
  api.previsualizar.mockResolvedValue({ data: { html: '<h2 id="contagem">Contagem</h2>', sumario: [], avisos: [] } });
  (window as any).confirm = vi.fn(() => true);
});
afterEach(() => {
  montados.splice(0).forEach((a) => { try { a.unmount(); } catch { /* */ } });
  vi.restoreAllMocks();
});

describe('DocumentacaoAdminView', () => {
  it('lista as duas fontes, filtra e abre', async () => {
    const { raiz, eventos } = montar(DocumentacaoAdminView);
    await espera();

    expect(raiz.querySelectorAll('[data-pagina-edicao]').length).toBe(2);
    expect(q(raiz, '[data-pagina-edicao="processos"]').textContent).toContain('Manual oficial');
    expect(q(raiz, '[data-pagina-edicao="processos/prazos"]').textContent).toContain('Rascunho');

    const filtro = q(raiz, '[data-filtro]') as HTMLSelectElement;
    filtro.value = 'rascunho';
    filtro.dispatchEvent(new Event('change'));
    await espera();
    expect(raiz.querySelectorAll('[data-pagina-edicao]').length).toBe(1);

    q(raiz, '[data-pagina-edicao="processos/prazos"] [data-editar]').click();
    expect(eventos.abrirEditor).toEqual([7]);
    q(raiz, '[data-nova]').click();
    expect(eventos.nova).toHaveLength(1);
  });

  it('"Editar cópia" cria o rascunho a partir do arquivo e abre o editor', async () => {
    api.copiarDoArquivo.mockResolvedValue({ data: { ...PAGINA, id: 9, slug: 'processos' }, avisos: [] });
    const { raiz, eventos } = montar(DocumentacaoAdminView);
    await espera();

    q(raiz, '[data-pagina-edicao="processos"] [data-copiar]').click();
    await espera();
    expect(api.copiarDoArquivo).toHaveBeenCalledWith('processos');
    expect(eventos.abrirEditor).toEqual([9]);
  });

  it('excluir pede confirmação e recarrega', async () => {
    api.excluirPagina.mockResolvedValue({});
    const { raiz } = montar(DocumentacaoAdminView);
    await espera();

    q(raiz, '[data-pagina-edicao="processos/prazos"] [data-excluir]').click();
    await espera();
    expect(window.confirm).toHaveBeenCalled();
    expect(api.excluirPagina).toHaveBeenCalledWith(7);
    expect(api.listarParaEdicao).toHaveBeenCalledTimes(2);
  });
});

describe('DocumentacaoEditorView', () => {
  it('nova página: cria como rascunho e avisa o id', async () => {
    api.criarPagina.mockResolvedValue({ data: { ...PAGINA, id: 11 }, avisos: ['Link recusado: x.xlsx'] });
    const { raiz, eventos } = montar(DocumentacaoEditorView, { id: null });
    await espera();

    expect(q(raiz, '[data-publicar]').hasAttribute('disabled')).toBe(true);
    digitar(q(raiz, '[data-campo="titulo"]'), 'Prazos');
    digitar(q(raiz, '[data-campo="slug"]'), 'Processos/Prazos');
    digitar(q(raiz, '[data-campo="telas"]'), 'tarefas, list');
    digitar(q(raiz, '[data-campo="markdown"]'), '## Contagem');
    await espera();
    expect(temAlteracoesNaoSalvas()).toBe(true);
    expect(api.previsualizar).toHaveBeenLastCalledWith('## Contagem', 'processos/prazos', expect.anything());
    expect(q(raiz, '[data-previa]').innerHTML).toContain('Contagem');

    q(raiz, '[data-salvar]').click();
    await espera();
    expect(api.criarPagina).toHaveBeenCalledWith(expect.objectContaining({ slug: 'processos/prazos', titulo: 'Prazos', telas: ['tarefas', 'list'] }));
    expect(eventos.criada).toEqual([11]);
    expect(q(raiz, '[data-avisos]').textContent).toContain('Link recusado');
    expect(temAlteracoesNaoSalvas()).toBe(false);
  });

  it('mostra os erros por campo do 422', async () => {
    api.criarPagina.mockRejectedValue(new ApiError('Dados inválidos', 422, { messages: { slug: 'Já existe uma página com este endereço.', 'telas.0': 'Tela inválida.' } }));
    const { raiz } = montar(DocumentacaoEditorView, { id: null });
    await espera();
    digitar(q(raiz, '[data-campo="titulo"]'), 'X');

    q(raiz, '[data-salvar]').click();
    await espera();
    expect(raiz.textContent).toContain('Já existe uma página com este endereço.');
    expect(raiz.textContent).toContain('Tela inválida.');
  });

  it('editar: carrega, salva e publica (salvando antes se houver alteração)', async () => {
    api.salvarPagina.mockImplementation(async (_id: number, e: any) => ({ data: { ...PAGINA, ...e, ordem: 20 }, avisos: [] }));
    api.publicarPagina.mockResolvedValue({ data: { ...PAGINA, titulo: 'Prazos processuais', status: 'publicado' } });
    const { raiz } = montar(DocumentacaoEditorView, { id: 7 });
    await espera();

    expect(api.obterParaEdicao).toHaveBeenCalledWith(7);
    expect((q(raiz, '[data-campo="titulo"]') as HTMLInputElement).value).toBe('Prazos');
    expect(q(raiz, '[data-status]').textContent).toContain('Rascunho');
    expect(q(raiz, '[data-salvar]').hasAttribute('disabled')).toBe(true);

    digitar(q(raiz, '[data-campo="titulo"]'), 'Prazos processuais');
    await espera();
    expect(q(raiz, '[data-publicar]').textContent).toContain('Salvar e publicar');

    q(raiz, '[data-publicar]').click();
    await espera();
    expect(api.salvarPagina).toHaveBeenCalledWith(7, expect.objectContaining({ titulo: 'Prazos processuais' }));
    expect(api.publicarPagina).toHaveBeenCalledWith(7);
    expect(q(raiz, '[data-status]').textContent).toContain('Publicado');
    expect(q(raiz, '[data-despublicar]')).not.toBeNull();
  });

  it('Ctrl+S salva e o envio de imagem insere o Markdown', async () => {
    api.salvarPagina.mockResolvedValue({ data: structuredClone(PAGINA), avisos: [] });
    api.enviarImagem.mockResolvedValue({ data: { caminho: 'processos/img/tela-abc123.png', markdown: '![Descreva a imagem](img/tela-abc123.png)', largura: 800, altura: 400 } });
    const { raiz } = montar(DocumentacaoEditorView, { id: 7 });
    await espera();

    const area = q(raiz, '[data-campo="markdown"]') as HTMLTextAreaElement;
    digitar(area, 'Texto');
    area.dispatchEvent(new KeyboardEvent('keydown', { key: 's', ctrlKey: true, bubbles: true, cancelable: true }));
    await espera();
    expect(api.salvarPagina).toHaveBeenCalledOnce();

    const campo = q(raiz, '[data-imagem]') as HTMLInputElement;
    Object.defineProperty(campo, 'files', { value: [new File(['x'], 'tela.png', { type: 'image/png' })], configurable: true });
    campo.dispatchEvent(new Event('change'));
    await espera();
    expect(api.enviarImagem).toHaveBeenCalledWith(expect.any(File), 'processos');
    expect(area.value).toContain('![Descreva a imagem](img/tela-abc123.png)');
  });

  it('excluir confirma e avisa', async () => {
    api.excluirPagina.mockResolvedValue({});
    const { raiz, eventos } = montar(DocumentacaoEditorView, { id: 7 });
    await espera();

    q(raiz, '[data-excluir]').click();
    await espera();
    expect(api.excluirPagina).toHaveBeenCalledWith(7);
    expect(eventos.excluida).toHaveLength(1);
  });
});

describe('DocumentacaoView: atalhos de edição', () => {
  const PAGINA_LIDA = {
    slug: 'processos', secao: 'processos', capa: true, origem: 'arquivo', status: 'publicado', id: null, titulo: 'Processos', resumo: '',
    html: '<p>x</p>', sumario: [], anterior: null, proxima: null, atualizado_em: '2026-10-13T10:00:00-03:00',
  };

  beforeEach(() => {
    limparAcesso();
    api.obterIndice.mockResolvedValue({ success: true, data: [] });
    api.obterPagina.mockResolvedValue({ success: true, data: structuredClone(PAGINA_LIDA) });
  });

  it('só quem edita vê os botões; página de arquivo vira cópia editável', async () => {
    aplicarAcesso({ permissoes: ['documentacao.access'], modulos_ativos: ['documentacao'] });
    const leitor = montar(DocumentacaoView, { slug: 'processos' });
    await espera();
    expect(q(leitor.raiz, '[data-editar-pagina]')).toBeNull();
    expect(q(leitor.raiz, '[data-edicao]')).toBeNull();

    aplicarAcesso({ permissoes: ['documentacao.access', 'documentacao.editar'], modulos_ativos: ['documentacao'] });
    api.copiarDoArquivo.mockResolvedValue({ data: { ...PAGINA, id: 21, slug: 'processos' }, avisos: [] });
    await espera();
    q(leitor.raiz, '[data-editar-pagina]').click();
    await espera();
    expect(api.copiarDoArquivo).toHaveBeenCalledWith('processos');
    expect(leitor.eventos.abrirEdicao).toEqual([21]);

    q(leitor.raiz, '[data-edicao]').click();
    expect(leitor.eventos.abrirEdicao.at(-1)).toBeNull();
  });

  it('página do banco abre direto o editor e mostra o selo de rascunho', async () => {
    aplicarAcesso({ permissoes: ['documentacao.access', 'documentacao.editar'], modulos_ativos: ['documentacao'] });
    api.obterPagina.mockResolvedValue({ success: true, data: { ...PAGINA_LIDA, origem: 'banco', status: 'rascunho', id: 7 } });
    const { raiz, eventos } = montar(DocumentacaoView, { slug: 'processos' });
    await espera();

    expect(q(raiz, '[data-rascunho]')).not.toBeNull();
    q(raiz, '[data-editar-pagina]').click();
    expect(eventos.abrirEdicao).toEqual([7]);
    expect(api.copiarDoArquivo).not.toHaveBeenCalled();
  });
});
