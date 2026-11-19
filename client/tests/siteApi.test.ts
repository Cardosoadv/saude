import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import './setup-globals';

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn(async () => ({ data: null })) };
});

import { ApiError, apiFetch } from '../src/services/api';
import * as site from '../src/services/siteApi';

const fetchMock = apiFetch as unknown as ReturnType<typeof vi.fn>;
const ultima = () => fetchMock.mock.calls.at(-1) as [string, any];

describe('siteApi: endpoints', () => {
  beforeEach(() => fetchMock.mockClear());

  it('configurações, schema e tema', async () => {
    await site.obterConfiguracoes();
    expect(ultima()).toEqual(['site/admin/configuracoes']);

    await site.salvarConfiguracoes({ 'contato.telefone': '1' });
    expect(ultima()).toEqual(['site/admin/configuracoes', { method: 'PUT', body: { 'contato.telefone': '1' } }]);

    await site.obterSchema();
    expect(ultima()[0]).toBe('site/admin/template/schema');

    await site.salvarTema({ cores: { gold: '#B08E35' } });
    expect(ultima()).toEqual(['site/admin/template/tema', { method: 'PUT', body: { cores: { gold: '#B08E35' } } }]);

    await site.restaurarTema();
    expect(ultima()[1].method).toBe('DELETE');
  });

  it('páginas, com filtros só quando preenchidos', async () => {
    await site.listarPaginas();
    expect(ultima()[0]).toBe('site/admin/paginas');

    await site.listarPaginas({ status: 'rascunho', busca: 'civil & família', tipo: '' });
    expect(ultima()[0]).toBe('site/admin/paginas?status=rascunho&busca=civil+%26+fam%C3%ADlia');

    await site.criarPagina({ slug: 'x', titulo: 'X', tipo: 'area' });
    expect(ultima()).toEqual(['site/admin/paginas', { method: 'POST', body: { slug: 'x', titulo: 'X', tipo: 'area' } }]);

    await site.atualizarPagina(4, { titulo: 'Y' });
    expect(ultima()).toEqual(['site/admin/paginas/4', { method: 'PUT', body: { titulo: 'Y' } }]);

    await site.publicarPagina(4);
    expect(ultima()).toEqual(['site/admin/paginas/4/publicar', { method: 'POST', body: undefined }]);
    await site.despublicarPagina(4);
    expect(ultima()[0]).toBe('site/admin/paginas/4/despublicar');
    await site.duplicarPagina(4);
    expect(ultima()[0]).toBe('site/admin/paginas/4/duplicar');
    await site.tokenPreview(4);
    expect(ultima()[0]).toBe('site/admin/paginas/4/preview-token');
    await site.excluirPagina(4);
    expect(ultima()).toEqual(['site/admin/paginas/4', { method: 'DELETE', body: undefined }]);
  });

  it('seções', async () => {
    await site.criarSecao(4, { tipo: 'hero', dados: { titulo: 'a' } });
    expect(ultima()).toEqual(['site/admin/paginas/4/secoes', { method: 'POST', body: { tipo: 'hero', dados: { titulo: 'a' } } }]);

    await site.atualizarSecao(9, { visivel: false });
    expect(ultima()).toEqual(['site/admin/secoes/9', { method: 'PUT', body: { visivel: false } }]);

    await site.reordenarSecoes(4, [3, 1, 2]);
    expect(ultima()).toEqual(['site/admin/paginas/4/secoes/ordem', { method: 'PUT', body: { ids: [3, 1, 2] } }]);

    await site.excluirSecao(9);
    expect(ultima()[0]).toBe('site/admin/secoes/9');
  });

  it('menus', async () => {
    await site.listarMenus();
    expect(ultima()[0]).toBe('site/admin/menus');

    await site.salvarMenu('cabecalho', [{ rotulo: 'Início', tipo: 'url', url: '/' }]);
    expect(ultima()).toEqual(['site/admin/menus/cabecalho', { method: 'PUT', body: { itens: [{ rotulo: 'Início', tipo: 'url', url: '/' }] } }]);
  });

  it('mídias: listagem paginada e envio multipart', async () => {
    await site.listarMidias({ page: 2, per_page: 12, busca: 'logo' });
    expect(ultima()[0]).toBe('site/admin/midias?page=2&per_page=12&busca=logo');

    await site.enviarMidia(new Blob(['x'], { type: 'image/png' }), 'Logo da clínica');
    const [url, opts] = ultima();
    expect(url).toBe('site/admin/midias');
    expect(opts.method).toBe('POST');
    expect(opts.body).toBeInstanceOf(FormData);
    expect(opts.body.get('alt')).toBe('Logo da clínica');
    expect(opts.body.get('arquivo')).toBeInstanceOf(Blob);

    await site.atualizarAltMidia(5, null);
    expect(ultima()).toEqual(['site/admin/midias/5', { method: 'PUT', body: { alt: null } }]);
    await site.usosMidia(5);
    expect(ultima()[0]).toBe('site/admin/midias/5/usos');
    await site.excluirMidia(5);
    expect(ultima()[1].method).toBe('DELETE');
  });

  it('templates: envio do .zip, ativação, exemplo, remoção e link de exportação', async () => {
    await site.listarTemplates();
    expect(ultima()[0]).toBe('site/admin/templates');

    await site.instalarTemplate(new Blob(['zip']));
    const [url, opts] = ultima();
    expect(url).toBe('site/admin/templates');
    expect(opts.body).toBeInstanceOf(FormData);
    expect(opts.body.get('arquivo')).toBeInstanceOf(Blob);

    await site.ativarTemplate('moderno');
    expect(ultima()).toEqual(['site/admin/templates/moderno/ativar', { method: 'POST', body: undefined }]);
    await site.paginasExemploTemplate('moderno');
    expect(ultima()[0]).toBe('site/admin/templates/moderno/paginas-exemplo');
    await site.removerTemplate('moderno');
    expect(ultima()[1].method).toBe('DELETE');

    expect(site.urlExportarTemplate('moderno')).toBe('http://localhost/processos/api/v2/site/admin/templates/moderno/exportar');
    expect(site.urlExportarTemplate('moderno', true)).toMatch(/exportar\?conteudo=1$/);
  });
});

describe('siteApi: erros', () => {
  const erro422 = (messages: unknown, avisos?: string[]) =>
    new ApiError('422', 422, { status: 422, error: 422, messages, ...(avisos ? { avisos } : {}) });

  it('errosDeCampo devolve os erros por caminho', () => {
    expect(site.errosDeCampo(erro422({ 'dados.botoes.1.rotulo': 'Campo obrigatório.', slug: 'Inválido.' }))).toEqual({
      'dados.botoes.1.rotulo': 'Campo obrigatório.',
      slug: 'Inválido.',
    });
  });

  it('outros erros não viram erros de campo', () => {
    expect(site.errosDeCampo(new ApiError('x', 500, { messages: { a: 'b' } }))).toEqual({});
    expect(site.errosDeCampo(new ApiError('x', 404, { messages: { error: 'Página não encontrada.' } }))).toEqual({});
    expect(site.errosDeCampo(new Error('rede'))).toEqual({});
    expect(site.errosDeCampo(erro422(['lista']))).toEqual({});
    expect(site.errosDeCampo(erro422(undefined))).toEqual({});
  });

  it('avisosDe lê avisos de respostas e de erros 422', () => {
    expect(site.avisosDe({ data: {}, avisos: ['a', 'b'] })).toEqual(['a', 'b']);
    expect(site.avisosDe(erro422({ x: 'y' }, ['pacote sem prévia']))).toEqual(['pacote sem prévia']);
    expect(site.avisosDe({ data: {} })).toEqual([]);
    expect(site.avisosDe(null)).toEqual([]);
  });
});

describe('instalarTemplateComProgresso (XMLHttpRequest)', () => {
  class XhrFalso {
    static ultimo: XhrFalso;
    method = ''; url = ''; headers: Record<string, string> = {}; body: any = null; withCredentials = false; timeout = 0;
    status = 0; responseText = '';
    upload: { onprogress?: (e: any) => void } = {};
    onload?: () => void; onerror?: () => void; ontimeout?: () => void;
    constructor() { XhrFalso.ultimo = this; }
    open(m: string, u: string) { this.method = m; this.url = u; }
    setRequestHeader(k: string, v: string) { this.headers[k] = v; }
    send(b: any) { this.body = b; }
    responder(status: number, corpo: unknown) { this.status = status; this.responseText = typeof corpo === 'string' ? corpo : JSON.stringify(corpo); this.onload?.(); }
  }
  const original = (globalThis as any).XMLHttpRequest;
  beforeEach(() => { (globalThis as any).XMLHttpRequest = XhrFalso; });
  afterEach(() => { (globalThis as any).XMLHttpRequest = original; });

  it('envia multipart com cookies, CSRF e cabeçalhos da API e informa o progresso', async () => {
    const progresso: number[] = [];
    const p = site.instalarTemplateComProgresso(new Blob(['zip']), (f) => progresso.push(f));
    const xhr = XhrFalso.ultimo;

    expect(xhr.method).toBe('POST');
    expect(xhr.url).toBe('http://localhost/processos/api/v2/site/admin/templates');
    expect(xhr.withCredentials).toBe(true);
    expect(xhr.headers).toMatchObject({ 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json', 'X-CSRF-TOKEN': 'csrf-token-mock-value' });
    expect(xhr.body).toBeInstanceOf(FormData);
    expect(xhr.body.get('arquivo')).toBeInstanceOf(Blob);

    xhr.upload.onprogress?.({ lengthComputable: true, loaded: 25, total: 100 });
    xhr.upload.onprogress?.({ lengthComputable: false, loaded: 0, total: 0 });
    xhr.responder(201, { data: { slug: 'moderno' }, atualizado: false, avisos: [] });

    await expect(p).resolves.toMatchObject({ atualizado: false, data: { slug: 'moderno' } });
    expect(progresso).toEqual([0.25, 1]);
  });

  it('422 vira ApiError com os erros por arquivo e os avisos', async () => {
    const p = site.instalarTemplateComProgresso(new Blob(['zip']), () => {});
    XhrFalso.ultimo.responder(422, { status: 422, messages: { 'layouts/x.php': 'Arquivo proibido.' }, avisos: ['sem prévia'] });

    const erro = await p.catch((e) => e);
    expect(erro).toBeInstanceOf(ApiError);
    expect(erro.status).toBe(422);
    expect(site.errosDeCampo(erro)).toEqual({ 'layouts/x.php': 'Arquivo proibido.' });
    expect(site.avisosDe(erro)).toEqual(['sem prévia']);
  });

  it('resposta que não é JSON, queda de rede e tempo esgotado viram ApiError', async () => {
    const a = site.instalarTemplateComProgresso(new Blob(['z']), () => {});
    XhrFalso.ultimo.responder(500, '<html>erro</html>');
    expect(await a.catch((e) => e)).toMatchObject({ status: 500, message: 'HTTP 500' });

    const b = site.instalarTemplateComProgresso(new Blob(['z']), () => {});
    XhrFalso.ultimo.onerror?.();
    expect(await b.catch((e) => e)).toMatchObject({ status: 0 });

    const c = site.instalarTemplateComProgresso(new Blob(['z']), () => {});
    XhrFalso.ultimo.ontimeout?.();
    expect((await c.catch((e) => e)).message).toMatch(/Tempo de conexão esgotado/);
  });

  it('prévia das páginas de exemplo é um GET', async () => {
    fetchMock.mockClear();
    await site.paginasExemploPrevia('moderno');
    expect(fetchMock.mock.calls.at(-1)).toEqual(['site/admin/templates/moderno/paginas-exemplo']);
  });
});
