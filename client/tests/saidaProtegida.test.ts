import { describe, it, expect, beforeEach, vi } from 'vitest';
import { confirmarSaida, marcarSujo, MENSAGEM_SAIDA, temAlteracoesNaoSalvas } from '../src/composables/useSaidaProtegida';

describe('useSaidaProtegida', () => {
  beforeEach(() => marcarSujo(false));

  it('sem alterações sai sem perguntar', () => {
    const perguntar = vi.fn();
    expect(confirmarSaida(perguntar)).toBe(true);
    expect(perguntar).not.toHaveBeenCalled();
  });

  it('com alterações pergunta; recusar mantém na tela e o estado sujo', () => {
    marcarSujo(true);
    const perguntar = vi.fn(() => false);

    expect(confirmarSaida(perguntar)).toBe(false);
    expect(perguntar).toHaveBeenCalledWith(MENSAGEM_SAIDA);
    expect(temAlteracoesNaoSalvas()).toBe(true);
  });

  it('confirmar descarta e limpa o estado', () => {
    marcarSujo(true);

    expect(confirmarSaida(() => true)).toBe(true);
    expect(temAlteracoesNaoSalvas()).toBe(false);
  });
});
