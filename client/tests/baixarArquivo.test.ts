// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { baixarArquivo, BASE_URL, ApiError } from '../src/services/api';

describe('baixarArquivo', () => {
  const fetchOriginal = globalThis.fetch;
  let cliques: string[] = [];

  beforeEach(() => {
    cliques = [];
    URL.createObjectURL = vi.fn(() => 'blob:teste');
    URL.revokeObjectURL = vi.fn();
    vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
      cliques.push(this.download);
    });
  });
  afterEach(() => { globalThis.fetch = fetchOriginal; vi.restoreAllMocks(); });

  it('usa o BASE_URL da API, os cookies e o nome do Content-Disposition', async () => {
    const fetchMock = vi.fn(async () => new Response('a;b\n', {
      headers: { 'content-type': 'text/csv', 'content-disposition': 'attachment; filename="receitas_2026-11-08.csv"' },
    }));
    globalThis.fetch = fetchMock as any;

    const corpo = new FormData();
    corpo.append('acao', 'exportar');
    await baixarArquivo('financeiro/receitas/acaoEmLote', { method: 'POST', body: corpo });

    const [url, opts] = fetchMock.mock.calls[0] as any;
    expect(url).toBe(BASE_URL + 'financeiro/receitas/acaoEmLote');
    expect(opts).toMatchObject({ method: 'POST', credentials: 'include', body: corpo });
    expect(cliques).toEqual(['receitas_2026-11-08.csv']);
  });

  it('erro da API vira ApiError com a mensagem dela, sem baixar nada', async () => {
    globalThis.fetch = vi.fn(async () => new Response(JSON.stringify({ message: 'Sem permissão' }), {
      status: 403, headers: { 'content-type': 'application/json' },
    })) as any;

    await expect(baixarArquivo('financeiro/receitas/exportar')).rejects.toMatchObject({ message: 'Sem permissão', status: 403 });
    await expect(baixarArquivo('financeiro/receitas/exportar')).rejects.toBeInstanceOf(ApiError);
    expect(cliques).toEqual([]);
  });

  it('resposta 200 em JSON (ex.: "nenhuma selecionada") também é erro', async () => {
    globalThis.fetch = vi.fn(async () => new Response(JSON.stringify({ success: false, message: 'Ação ou receitas não informadas' }), {
      headers: { 'content-type': 'application/json' },
    })) as any;

    await expect(baixarArquivo('x')).rejects.toMatchObject({ message: 'Ação ou receitas não informadas' });
  });
});
