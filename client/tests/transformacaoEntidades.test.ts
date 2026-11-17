import './setup-globals';
import { vi, describe, it, expect, afterEach } from 'vitest';
import { apiFetch } from '../src/services/api';

function respostaJson(status: number, corpo: unknown): Response {
  return new Response(JSON.stringify(corpo), {
    status,
    headers: { 'Content-Type': 'application/json' }
  });
}

/**
 * Cadastro único: "marcar como cliente/fornecedor" liga o outro papel no mesmo
 * registro. Nada é copiado e o ID devolvido é o do próprio cadastro.
 */
describe('Papéis cliente/fornecedor no cadastro único', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('marca fornecedor como cliente e devolve o mesmo ID', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      respostaJson(200, {
        success: true,
        cliente_id: 45,
        message: 'Fornecedor marcado como cliente com sucesso!'
      })
    );
    vi.stubGlobal('fetch', fetchMock);

    const res = await apiFetch('fornecedores/transformar-em-cliente/45', {
      method: 'POST',
      body: {}
    });

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const callArgs = fetchMock.mock.calls[0];
    expect(callArgs[0]).toContain('fornecedores/transformar-em-cliente/45');
    expect(JSON.parse(callArgs[1].body)).toEqual({});
    expect(res.success).toBe(true);
    expect(res.cliente_id).toBe(45);
  });

  it('marca cliente como fornecedor passando dados bancários', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      respostaJson(200, {
        success: true,
        fornecedor_id: 12,
        message: 'Cliente marcado como fornecedor com sucesso!'
      })
    );
    vi.stubGlobal('fetch', fetchMock);

    const payload = {
      banco: 'Banco Inter',
      agencia: '0001',
      conta: '98765-4',
      tipo_conta: 'corrente',
      pix: 'contato@cliente.com'
    };

    const res = await apiFetch('clientes/transformar-em-fornecedor/12', {
      method: 'POST',
      body: payload
    });

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const callArgs = fetchMock.mock.calls[0];
    expect(callArgs[0]).toContain('clientes/transformar-em-fornecedor/12');
    expect(JSON.parse(callArgs[1].body)).toEqual(payload);
    expect(res.success).toBe(true);
    expect(res.fornecedor_id).toBe(12);
  });

  it('propaga a mensagem de erro da API', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        respostaJson(400, {
          success: false,
          message: 'Cliente não encontrado.'
        })
      )
    );

    await expect(
      apiFetch('clientes/transformar-em-fornecedor/999', {
        method: 'POST',
        body: {}
      })
    ).rejects.toThrow('Cliente não encontrado.');
  });
});
