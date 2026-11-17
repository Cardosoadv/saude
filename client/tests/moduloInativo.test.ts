import './setup-globals';
import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest';
import { apiFetch, ApiError, EVENTO_MODULO_INATIVO, MENSAGEM_MODULO_INATIVO } from '../src/services/api';

function respostaJson(status: number, corpo: unknown): Response {
  return new Response(JSON.stringify(corpo), { status, headers: { 'Content-Type': 'application/json' } });
}

describe('apiFetch com módulo desativado', () => {
  const dispatchEvent = vi.fn();

  beforeEach(() => {
    (globalThis as any).window.dispatchEvent = dispatchEvent;
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    dispatchEvent.mockReset();
  });

  it('403 modulo_inativo vira ApiError com mensagem clara e avisa a SPA', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(respostaJson(403, {
      success: false, code: 'modulo_inativo', modulo: 'objetos', message: 'Este módulo está desativado.',
    })));

    const erro = await apiFetch('objetos').catch((e) => e);

    expect(erro).toBeInstanceOf(ApiError);
    expect(erro.status).toBe(403);
    expect(erro.moduloInativo).toBe('objetos');
    expect(erro.message).toBe(MENSAGEM_MODULO_INATIVO);
    expect(dispatchEvent).toHaveBeenCalledTimes(1);
    const evento = dispatchEvent.mock.calls[0][0] as CustomEvent;
    expect(evento.type).toBe(EVENTO_MODULO_INATIVO);
    expect(evento.detail).toEqual({ modulo: 'objetos' });
  });

  it('403 de permissão segue como antes', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(respostaJson(403, {
      success: false, message: 'Sem permissão para consultar este processo.',
    })));

    const erro = await apiFetch('processos/detalhes/1').catch((e) => e);

    expect(erro.moduloInativo).toBeUndefined();
    expect(erro.message).toBe('Sem permissão para consultar este processo.');
    expect(dispatchEvent).not.toHaveBeenCalled();
  });
});
