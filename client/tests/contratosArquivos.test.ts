import { describe, it, expect, vi } from 'vitest';
import './setup-globals';

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn(async () => ({ data: [] })) };
});

import { apiFetch } from '../src/services/api';
import { enviarArquivosContrato, listarArquivosContrato, removerArquivoContrato } from '../src/services/contratosArquivosApi';
import { MAX_ARQUIVOS, TAMANHO_MAXIMO, iconeDoArquivo, validarArquivo, validarLote } from '../src/utils/contratosArquivos';

const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
const arq = (name: string, type: string, size = 1000) => ({ name, type, size });

describe('validarArquivo (contratos)', () => {
  it('aceita PDF, Word e imagens dentro do limite de 20 MB', () => {
    for (const tipo of ['application/pdf', 'application/msword', DOCX, 'image/jpeg', 'image/png', 'image/webp']) {
      expect(validarArquivo(arq('a', tipo))).toBeNull();
    }
    expect(validarArquivo(arq('a.pdf', 'application/pdf', TAMANHO_MAXIMO))).toBeNull();
  });

  it('usa a extensão quando o navegador deixa o tipo vazio', () => {
    expect(validarArquivo(arq('minuta.docx', ''))).toBeNull();
    expect(validarArquivo(arq('semtipo', ''))).not.toBeNull();
  });

  it('recusa tipos fora da lista, vazio e acima de 20 MB', () => {
    expect(validarArquivo(arq('x.exe', 'application/x-msdownload'))).toMatch(/PDF, Word/);
    expect(validarArquivo(arq('v.pdf', 'application/pdf', 0))).toMatch(/vazio/);
    expect(validarArquivo(arq('g.pdf', 'application/pdf', TAMANHO_MAXIMO + 1))).toMatch(/máximo é 20,0 MB/);
  });
});

describe('validarLote (contratos)', () => {
  it('exige ao menos um arquivo e limita a quantidade', () => {
    expect(validarLote([])).toMatch(/ao menos um/);
    const muitos = Array.from({ length: MAX_ARQUIVOS + 1 }, () => arq('a.pdf', 'application/pdf'));
    expect(validarLote(muitos)).toMatch(/no máximo 5/);
    expect(validarLote([arq('a.pdf', 'application/pdf')])).toBeNull();
  });
});

describe('iconeDoArquivo', () => {
  it('escolhe o ícone pelo tipo', () => {
    expect(iconeDoArquivo('application/pdf')).toContain('pdf');
    expect(iconeDoArquivo('image/png')).toContain('image');
    expect(iconeDoArquivo(DOCX)).toContain('word');
    expect(iconeDoArquivo(null)).toContain('word');
  });
});

describe('contratosArquivosApi', () => {
  it('lista, envia com categoria e remove', async () => {
    const f = apiFetch as unknown as ReturnType<typeof vi.fn>;
    f.mockResolvedValueOnce({ data: [{ id_contrato_arquivo: 1 }] });
    expect(await listarArquivosContrato(5)).toEqual([{ id_contrato_arquivo: 1 }]);
    expect(f).toHaveBeenLastCalledWith('contratos/5/arquivos');

    f.mockResolvedValueOnce({ success: true, data: [] });
    const pdf = new File(['%PDF'], 'c.pdf', { type: 'application/pdf' });
    await enviarArquivosContrato(5, [pdf], 'Aditivo');
    const [url, opcoes] = f.mock.calls.at(-1)!;
    expect(url).toBe('contratos/5/arquivos');
    expect(opcoes.method).toBe('POST');
    expect(opcoes.body.get('categoria')).toBe('Aditivo');
    expect(opcoes.body.getAll('arquivos[]')).toHaveLength(1);

    await removerArquivoContrato(9);
    expect(f).toHaveBeenLastCalledWith('contratos/arquivos/9', { method: 'DELETE' });
  });
});
