import './setup-globals';
import { describe, it, expect, afterEach, vi } from 'vitest';
import { apiFetch, ApiError } from '../src/services/api';

describe('apiFetch sem conexão', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('troca o "Failed to fetch" por mensagem clara quando o aparelho está offline', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
    vi.stubGlobal('navigator', { onLine: false });
    const erro = await apiFetch('processos').catch((e) => e);
    expect(erro).toBeInstanceOf(ApiError);
    expect(erro.status).toBe(0);
    expect(erro.message).toMatch(/Sem conexão/);
  });

  it('mantém o erro original quando há rede (ex.: CORS ou servidor fora do ar)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
    vi.stubGlobal('navigator', { onLine: true });
    await expect(apiFetch('processos')).rejects.toThrow('Failed to fetch');
  });
});
