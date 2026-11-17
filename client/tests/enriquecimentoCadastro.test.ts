import { describe, it, expect, beforeEach, vi } from 'vitest';
import './setup-globals';

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn(async () => ({})) };
});

import { apiFetch, ApiError } from '../src/services/api';
import {
  ConsultaErro, cadastrosPorDocumento, sugerirEnriquecimento, aplicarEnriquecimento, documentoEnriquecivel,
} from '../src/services/consultasApi';

describe('enriquecimento de cadastros', () => {
  beforeEach(() => { vi.mocked(apiFetch).mockReset(); });

  it('só aceita CPF ou CNPJ completos', () => {
    expect(documentoEnriquecivel('000.000.001-91')).toBe(true);
    expect(documentoEnriquecivel('47.368.913/0001-29')).toBe(true);
    expect(documentoEnriquecivel('123')).toBe(false);
    expect(documentoEnriquecivel(null)).toBe(false);
  });

  it('busca cadastros pelo documento sem máscara e ignora documento incompleto', async () => {
    vi.mocked(apiFetch).mockResolvedValueOnce({ success: true, data: [{ tipo: 'cliente', id: 3, nome: 'Fulano', pode_editar: true }] });
    const r = await cadastrosPorDocumento('000.000.001-91');
    expect(vi.mocked(apiFetch).mock.calls[0][0]).toBe('consultas/cadastros/00000000191');
    expect(r).toHaveLength(1);

    expect(await cadastrosPorDocumento('123')).toEqual([]);
    expect(apiFetch).toHaveBeenCalledTimes(1);
  });

  it('envia o resultado da consulta de CPF para não cobrar de novo', async () => {
    vi.mocked(apiFetch).mockResolvedValueOnce({
      success: true, message: 'ok',
      data: { fonte: 'cpf', sugestoes: [{ campo: 'cidade', rotulo: 'Cidade', atual: null, sugerido: 'BH' }], consulta: null },
    });
    const r = await sugerirEnriquecimento('cliente', 3, { nome: 'FULANO' });
    const [rota, opts] = vi.mocked(apiFetch).mock.calls[0] as [string, any];
    expect(rota).toBe('consultas/enriquecimento/cliente/3');
    expect(opts.body).toEqual({ resultado: { nome: 'FULANO' } });
    expect(r.sugestoes[0].campo).toBe('cidade');
    expect(r.consulta).toBeNull();
  });

  it('aplica só os campos escolhidos', async () => {
    vi.mocked(apiFetch).mockResolvedValueOnce({ success: true, message: '1 campo atualizado.', data: { uf: 'MG' } });
    const r = await aplicarEnriquecimento('fornecedor', 9, { uf: 'MG' });
    const [rota, opts] = vi.mocked(apiFetch).mock.calls[0] as [string, any];
    expect(rota).toBe('consultas/enriquecimento/fornecedor/9/aplicar');
    expect(opts.body).toEqual({ campos: { uf: 'MG' } });
    expect(r.campos).toEqual({ uf: 'MG' });
  });

  it('usa a mensagem do backend quando a API responde com erro HTTP', async () => {
    vi.mocked(apiFetch).mockRejectedValueOnce(new ApiError('true', 422, { error: true, message: 'O resultado da consulta é de outro CPF.' }));
    await expect(sugerirEnriquecimento('cliente', 3)).rejects.toEqual(new ConsultaErro('O resultado da consulta é de outro CPF.'));
  });
});
