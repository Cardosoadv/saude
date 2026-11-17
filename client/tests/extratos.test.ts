import { describe, it, expect, beforeEach, vi } from 'vitest';
import './setup-globals';

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn(async () => ({ data: {} })) };
});

import { ApiError, apiFetch } from '../src/services/api';
import { ConfirmacaoNecessaria, importarExtrato, listarImportacoes, previaExtrato } from '../src/services/extratosApi';
import { alertasQueExigemConfirmacao, nadaNovo, periodoLegivel, validarArquivoOfx, TAMANHO_MAXIMO_OFX } from '../src/utils/extratos';

const arquivo = () => new File(['OFXHEADER:100'], 'extrato-ficticio.ofx');

describe('extratosApi', () => {
  beforeEach(() => { vi.mocked(apiFetch).mockReset(); });

  it('prévia envia conta_id e arquivo, sem confirmar', async () => {
    vi.mocked(apiFetch).mockResolvedValueOnce({ data: { lidos: 3, novos: 3 } });
    const r = await previaExtrato(7, arquivo());

    const [rota, opts] = vi.mocked(apiFetch).mock.calls[0] as [string, any];
    expect(rota).toBe('financeiro/extratos/previa');
    expect(opts.method).toBe('POST');
    expect(opts.body.get('conta_id')).toBe('7');
    expect(opts.body.get('arquivo')).toBeInstanceOf(File);
    expect(opts.body.has('confirmar')).toBe(false);
    expect(r.lidos).toBe(3);
  });

  it('importar com confirmação envia confirmar=true', async () => {
    vi.mocked(apiFetch).mockResolvedValueOnce({ data: { novos: 1 } });
    await importarExtrato(7, arquivo(), true);
    const [rota, opts] = vi.mocked(apiFetch).mock.calls[0] as [string, any];
    expect(rota).toBe('financeiro/extratos/importar');
    expect(opts.body.get('confirmar')).toBe('true');
  });

  it('409 com requer_confirmacao vira ConfirmacaoNecessaria com os alertas', async () => {
    const alertas = [{ codigo: 'possivel_reemissao_de_fitid', mensagem: 'Confirme.' }];
    vi.mocked(apiFetch).mockRejectedValueOnce(new ApiError('Confirme.', 409, { requer_confirmacao: true, alertas }));

    const erro = await importarExtrato(7, arquivo()).catch((e) => e);
    expect(erro).toBeInstanceOf(ConfirmacaoNecessaria);
    expect(erro.alertas).toEqual(alertas);
  });

  it('outros erros passam adiante (ex.: conta errada, 422)', async () => {
    vi.mocked(apiFetch).mockRejectedValueOnce(new ApiError('O arquivo é de outra conta.', 422));
    await expect(importarExtrato(7, arquivo())).rejects.toThrow('outra conta');
  });

  it('409 sem requer_confirmacao (importação em andamento) não vira confirmação', async () => {
    vi.mocked(apiFetch).mockRejectedValueOnce(new ApiError('Importação em andamento.', 409, {}));
    await expect(importarExtrato(7, arquivo())).rejects.not.toBeInstanceOf(ConfirmacaoNecessaria);
  });

  it('listarImportacoes monta a query e normaliza a resposta', async () => {
    vi.mocked(apiFetch).mockResolvedValueOnce({ data: [{ id_importacao: 1 }], total: 11, page: 2 });
    const p = await listarImportacoes(7, 2, 10);
    expect(vi.mocked(apiFetch).mock.calls[0][0]).toBe('financeiro/extratos/importacoes?page=2&per_page=10&conta_id=7');
    expect(p).toEqual({ itens: [{ id_importacao: 1 }], total: 11, page: 2 });
  });
});

describe('utils/extratos', () => {
  it('valida extensão, vazio e tamanho', () => {
    expect(validarArquivoOfx({ name: 'a.OFX', size: 10 })).toBeNull();
    expect(validarArquivoOfx({ name: 'a.pdf', size: 10 })).toMatch(/OFX/);
    expect(validarArquivoOfx({ name: 'a.ofx', size: 0 })).toMatch(/vazio/);
    expect(validarArquivoOfx({ name: 'a.ofx', size: TAMANHO_MAXIMO_OFX + 1 })).toMatch(/máximo/);
  });

  it('só a reemissão de FITID exige confirmação', () => {
    const lista = [
      { codigo: 'arquivo_ja_enviado', mensagem: 'a' },
      { codigo: 'possivel_reemissao_de_fitid', mensagem: 'b' },
      { codigo: 'conflitos', mensagem: 'c' },
    ];
    expect(alertasQueExigemConfirmacao(lista).map((a) => a.codigo)).toEqual(['possivel_reemissao_de_fitid']);
  });

  it('formata período e detecta "0 novos, N já importados"', () => {
    expect(periodoLegivel(['2026-10-01', '2026-10-30'])).toBe('01/10/2026 a 30/10/2026');
    expect(periodoLegivel(['2026-10-01', '2026-10-01'])).toBe('01/10/2026');
    expect(periodoLegivel([null, null])).toBe('—');
    expect(nadaNovo({ novos: 0, duplicados: 5 })).toBe(true);
    expect(nadaNovo({ novos: 1, duplicados: 5 })).toBe(false);
  });
});
