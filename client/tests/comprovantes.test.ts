import { describe, it, expect, beforeEach, vi } from 'vitest';
import './setup-globals';

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn(async () => ({ data: [] })) };
});

import { apiFetch } from '../src/services/api';
import { enviarComprovantes, enviarComprovantesLote, listarComprovantes, pendenciasDoMes, removerComprovante } from '../src/services/comprovantesApi';
import { MAX_ARQUIVOS, TAMANHO_MAXIMO, mesLegivel, resumoLote, textoPendencias, validarArquivo, validarLote } from '../src/utils/comprovantes';

const arq = (name: string, type: string, size = 1000) => ({ name, type, size });

describe('validarArquivo', () => {
  it('aceita PDF, JPG, PNG e WEBP dentro do limite', () => {
    for (const tipo of ['application/pdf', 'image/jpeg', 'image/png', 'image/webp']) {
      expect(validarArquivo(arq('a', tipo))).toBeNull();
    }
    expect(validarArquivo(arq('a.pdf', 'application/pdf', TAMANHO_MAXIMO))).toBeNull();
  });

  it('recusa tipos fora da lista', () => {
    expect(validarArquivo(arq('x.exe', 'application/x-msdownload'))).toMatch(/PDF, JPG, PNG ou WEBP/);
    expect(validarArquivo(arq('x.gif', 'image/gif'))).toMatch(/"x.gif"/);
    expect(validarArquivo(arq('semtipo', ''))).not.toBeNull();
  });

  it('recusa acima de 10 MB e arquivo vazio', () => {
    expect(validarArquivo(arq('grande.pdf', 'application/pdf', TAMANHO_MAXIMO + 1))).toMatch(/máximo é 10,0 MB/);
    expect(validarArquivo(arq('vazio.pdf', 'application/pdf', 0))).toMatch(/vazio/);
  });
});

describe('validarLote', () => {
  it('exige ao menos um arquivo e no máximo 5', () => {
    expect(validarLote([])).toMatch(/ao menos um/);
    const seis = Array.from({ length: MAX_ARQUIVOS + 1 }, (_, i) => arq(`${i}.pdf`, 'application/pdf'));
    expect(validarLote(seis)).toMatch(/no máximo 5/);
    expect(validarLote(seis.slice(0, MAX_ARQUIVOS))).toBeNull();
  });

  it('aponta o primeiro arquivo inválido', () => {
    expect(validarLote([arq('ok.pdf', 'application/pdf'), arq('ruim.txt', 'text/plain')])).toMatch(/"ruim.txt"/);
  });
});

describe('mesLegivel', () => {
  it('converte AAAA-MM em MM/AAAA', () => {
    expect(mesLegivel('2026-10')).toBe('10/2026');
    expect(mesLegivel(null)).toBe('');
  });
});

describe('comprovantesApi', () => {
  const fetchMock = apiFetch as unknown as ReturnType<typeof vi.fn>;
  beforeEach(() => fetchMock.mockClear());

  it('lista pelo caminho do tipo de pagamento', async () => {
    fetchMock.mockResolvedValueOnce({ data: [{ id_comprovante: 1 }] });
    expect(await listarComprovantes('despesa', 15)).toEqual([{ id_comprovante: 1 }]);
    expect(fetchMock).toHaveBeenLastCalledWith('financeiro/pagamentoDespesas/15/comprovantes');

    await listarComprovantes('receita', 4);
    expect(fetchMock).toHaveBeenLastCalledWith('financeiro/pagamentoReceitas/4/comprovantes');
  });

  it('devolve lista vazia quando a resposta não traz dados', async () => {
    fetchMock.mockResolvedValueOnce({});
    expect(await listarComprovantes('despesa', 1)).toEqual([]);
  });

  it('envia os arquivos como multipart em arquivos[] com tempo maior', async () => {
    const a = new File(['a'], 'a.pdf', { type: 'application/pdf' });
    const b = new File(['b'], 'b.png', { type: 'image/png' });

    await enviarComprovantes('receita', 4, [a, b]);

    const [url, opts] = fetchMock.mock.calls.at(-1) as [string, any];
    expect(url).toBe('financeiro/pagamentoReceitas/4/comprovantes');
    expect(opts.method).toBe('POST');
    expect(opts.timeout).toBe(120000);
    expect(opts.body).toBeInstanceOf(FormData);
    expect((opts.body as FormData).getAll('arquivos[]')).toHaveLength(2);
  });

  it('remove pelo ID do comprovante', async () => {
    await removerComprovante(99);
    expect(fetchMock).toHaveBeenLastCalledWith('financeiro/comprovantes/99', { method: 'DELETE' });
  });

  it('anexa em lote: pagamentos[] como tipo:id e arquivos[]', async () => {
    const a = new File(['a'], 'a.pdf', { type: 'application/pdf' });

    await enviarComprovantesLote([{ tipo: 'despesa', id: 12 }, { tipo: 'receita', id: 7 }], [a]);

    const [url, opts] = fetchMock.mock.calls.at(-1) as [string, any];
    expect(url).toBe('financeiro/comprovantes/lote');
    expect(opts.method).toBe('POST');
    expect(opts.timeout).toBe(120000);
    expect((opts.body as FormData).getAll('pagamentos[]')).toEqual(['despesa:12', 'receita:7']);
    expect((opts.body as FormData).getAll('arquivos[]')).toHaveLength(1);
  });
});

describe('resumoLote', () => {
  it('concorda no singular e no plural', () => {
    expect(resumoLote(1)).toBe('1 pagamento selecionado');
    expect(resumoLote(3)).toBe('3 pagamentos selecionados');
  });
});

describe('pendências do mês', () => {
  const fetchMock = apiFetch as unknown as ReturnType<typeof vi.fn>;
  beforeEach(() => fetchMock.mockClear());

  it('consulta ano e mês e devolve o resumo', async () => {
    const resumo = { ano: 2026, mes: 10, total: 3, despesas: 2, receitas: 1, valor_despesas: 10, valor_receitas: 5, contas: [] };
    fetchMock.mockResolvedValueOnce({ success: true, data: resumo });

    await expect(pendenciasDoMes(2026, 10)).resolves.toEqual(resumo);
    expect(fetchMock).toHaveBeenLastCalledWith('financeiro/comprovantes/pendencias?ano=2026&mes=10');
  });

  it('textoPendencias concorda no singular e no plural', () => {
    expect(textoPendencias({ despesas: 3, receitas: 1 })).toBe('3 despesas e 1 receita sem comprovante neste mês.');
    expect(textoPendencias({ despesas: 1, receitas: 0 })).toBe('1 despesa sem comprovante neste mês.');
    expect(textoPendencias({ despesas: 0, receitas: 0 })).toBe('Nenhum pagamento sem comprovante.');
  });
});
