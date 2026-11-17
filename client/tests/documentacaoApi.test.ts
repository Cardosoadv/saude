import { describe, it, expect, beforeEach, vi } from 'vitest';
import './setup-globals';

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn(async () => ({ success: true, data: null })) };
});
vi.mock('../src/services/auth', () => ({ getCurrentUser: vi.fn() }));

import { apiFetch } from '../src/services/api';
import * as docs from '../src/services/documentacaoApi';
import { ehHashDaDocumentacao, hashDaDocumentacao, hashDaEdicao, lerHashDaDocumentacao, lerHashDaEdicao } from '../src/utils/rotasDocumentacao';

const fetchMock = apiFetch as unknown as ReturnType<typeof vi.fn>;
const ultima = () => fetchMock.mock.calls.at(-1) as [string, any];

describe('documentacaoApi: endpoints', () => {
  beforeEach(() => fetchMock.mockClear());

  it('índice, página, busca e tela', async () => {
    await docs.obterIndice();
    expect(ultima()).toEqual(['documentacao']);

    await docs.obterPagina('processos/cadastro');
    expect(ultima()[0]).toBe('documentacao/paginas/processos/cadastro');

    await docs.obterPagina('a b/ç');
    expect(ultima()[0]).toBe('documentacao/paginas/a%20b/%C3%A7');

    await docs.buscar('número & CNJ');
    expect(ultima()).toEqual(['documentacao/busca?q=n%C3%BAmero+%26+CNJ', {}]);

    const controle = new AbortController();
    await docs.buscar('x', controle.signal);
    expect(ultima()[1]).toEqual({ signal: controle.signal });

    await docs.obterPaginaDaTela('process-form');
    expect(ultima()[0]).toBe('documentacao/tela/process-form');
  });
});

describe('rotasDocumentacao', () => {
  it.each([
    ['#/documentacao', { slug: null, ancora: null }],
    ['#/documentacao/', { slug: null, ancora: null }],
    ['#/documentacao/primeiros-passos', { slug: 'primeiros-passos', ancora: null }],
    ['#/documentacao/processos/cadastro', { slug: 'processos/cadastro', ancora: null }],
    ['#/documentacao/processos/cadastro#partes', { slug: 'processos/cadastro', ancora: 'partes' }],
    ['#/documentacao/a%20b#acao-cnj', { slug: 'a b', ancora: 'acao-cnj' }],
    ['#/documentacao/x#', { slug: 'x', ancora: null }],
  ])('lê %s', (hash, esperado) => {
    expect(lerHashDaDocumentacao(hash)).toEqual(esperado);
  });

  it('ignora outros hashes', () => {
    expect(lerHashDaDocumentacao('#/documentos')).toBeNull();
    expect(lerHashDaDocumentacao('#/documentacaoX')).toBeNull();
    expect(lerHashDaDocumentacao('#/processos')).toBeNull();
    expect(ehHashDaDocumentacao('#/documentacao/x')).toBe(true);
  });

  it('monta o hash e volta ao mesmo lugar', () => {
    expect(hashDaDocumentacao()).toBe('#/documentacao');
    expect(hashDaDocumentacao(null, 'x')).toBe('#/documentacao');
    expect(hashDaDocumentacao('processos/cadastro')).toBe('#/documentacao/processos/cadastro');
    expect(hashDaDocumentacao('processos/cadastro', 'partes')).toBe('#/documentacao/processos/cadastro#partes');

    const ida = hashDaDocumentacao('a b/ç', 'ação');
    expect(lerHashDaDocumentacao(ida)).toEqual({ slug: 'a b/ç', ancora: 'ação' });
  });
});

describe('documentacaoApi: edição', () => {
  beforeEach(() => fetchMock.mockClear());

  it('endpoints de admin', async () => {
    const entrada = { slug: 'processos/prazos', titulo: 'Prazos', resumo: '', ordem: null, modulo: '', permissao: '', telas: [], palavras_chave: [], markdown: 'x' };

    await docs.listarParaEdicao();
    expect(ultima()).toEqual(['documentacao/admin/paginas']);
    await docs.obterParaEdicao(7);
    expect(ultima()).toEqual(['documentacao/admin/paginas/7']);
    await docs.criarPagina(entrada);
    expect(ultima()).toEqual(['documentacao/admin/paginas', { method: 'POST', body: entrada }]);
    await docs.salvarPagina(7, entrada);
    expect(ultima()).toEqual(['documentacao/admin/paginas/7', { method: 'PUT', body: entrada }]);
    await docs.publicarPagina(7);
    expect(ultima()).toEqual(['documentacao/admin/paginas/7/publicar', { method: 'POST', body: undefined }]);
    await docs.despublicarPagina(7);
    expect(ultima()[0]).toBe('documentacao/admin/paginas/7/despublicar');
    await docs.excluirPagina(7);
    expect(ultima()).toEqual(['documentacao/admin/paginas/7', { method: 'DELETE', body: undefined }]);
    await docs.copiarDoArquivo('processos/cadastro');
    expect(ultima()).toEqual(['documentacao/admin/paginas/de-arquivo/processos/cadastro', { method: 'POST', body: undefined }]);
    await docs.previsualizar('## a', 'processos/x');
    expect(ultima()).toEqual(['documentacao/admin/preview', { method: 'POST', body: { markdown: '## a', slug: 'processos/x' } }]);
  });

  it('imagem vai como multipart', async () => {
    const arquivo = new Blob(['x'], { type: 'image/png' }) as File;
    await docs.enviarImagem(arquivo, 'processos');
    const [rota, opts] = ultima();
    expect(rota).toBe('documentacao/admin/imagens');
    expect(opts.method).toBe('POST');
    expect(opts.body).toBeInstanceOf(FormData);
    expect((opts.body as FormData).get('secao')).toBe('processos');
  });
});

describe('rotas da edição', () => {
  it.each([
    ['#/documentacao-admin', { tela: 'lista' }],
    ['#/documentacao-admin/', { tela: 'lista' }],
    ['#/documentacao-admin/nova', { tela: 'editor', id: null }],
    ['#/documentacao-admin/12', { tela: 'editor', id: 12 }],
    ['#/documentacao-admin/xyz', { tela: 'lista' }],
  ])('lê %s', (hash, esperado) => {
    expect(lerHashDaEdicao(hash)).toEqual(esperado);
  });

  it('não confunde com a leitura', () => {
    expect(lerHashDaEdicao('#/documentacao')).toBeNull();
    expect(lerHashDaEdicao('#/documentacao/admin')).toBeNull();
    expect(ehHashDaDocumentacao('#/documentacao-admin')).toBe(false);
    expect(hashDaEdicao({ tela: 'lista' })).toBe('#/documentacao-admin');
    expect(hashDaEdicao({ tela: 'editor', id: null })).toBe('#/documentacao-admin/nova');
    expect(hashDaEdicao({ tela: 'editor', id: 3 })).toBe('#/documentacao-admin/3');
  });
});
