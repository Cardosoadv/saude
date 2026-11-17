import { vi, describe, it, expect, beforeEach } from 'vitest';

vi.mock('../src/services/auth', () => ({
  getCurrentUser: vi.fn(),
}));

import { getCurrentUser } from '../src/services/auth';
import {
  aplicarAcesso,
  limparAcesso,
  liberado,
  moduloAtivo,
  pode,
  recarregarAcesso,
} from '../src/composables/useAcesso';

const usuario = {
  id: 1,
  permissoes: ['processos.access', 'objetos.access'],
  modulos_ativos: ['processos', 'objetos', 'checklist'],
};

describe('useAcesso', () => {
  beforeEach(() => {
    limparAcesso();
  });

  it('pode() exige ao menos uma das permissões, sem diferenciar maiúsculas', () => {
    aplicarAcesso(usuario);

    expect(pode('objetos.access')).toBe(true);
    expect(pode('OBJETOS.ACCESS')).toBe(true);
    expect(pode('objetos.edit', 'processos.access')).toBe(true);
    expect(pode('financeiro.access')).toBe(false);
  });

  it('moduloAtivo() consulta os slugs ativos', () => {
    aplicarAcesso(usuario);

    expect(moduloAtivo('objetos')).toBe(true);
    expect(moduloAtivo('financeiro')).toBe(false);
  });

  it('liberado() exige módulo ativo e permissão', () => {
    aplicarAcesso({ ...usuario, modulos_ativos: ['processos'] });

    expect(liberado('processos', 'processos.access')).toBe(true);
    // Permissão sem módulo ativo.
    expect(liberado('objetos', 'objetos.access')).toBe(false);
    // Módulo ativo sem permissão.
    expect(liberado('processos', 'processos.delete')).toBe(false);
  });

  it('liberado() sem permissões depende só do módulo', () => {
    aplicarAcesso(usuario);

    expect(liberado('checklist')).toBe(true);
    expect(liberado('financeiro')).toBe(false);
  });

  it('backend sem os campos libera tudo', () => {
    aplicarAcesso({ id: 1, username: 'antigo' });

    expect(pode('qualquer.coisa')).toBe(true);
    expect(moduloAtivo('qualquer')).toBe(true);
    expect(liberado('objetos', 'objetos.access')).toBe(true);
  });

  it('listas vazias bloqueiam tudo', () => {
    aplicarAcesso({ id: 1, permissoes: [], modulos_ativos: [] });

    expect(pode('processos.access')).toBe(false);
    expect(moduloAtivo('processos')).toBe(false);
  });

  it('recarregarAcesso() aplica o user/info atual', async () => {
    vi.mocked(getCurrentUser).mockResolvedValueOnce({ id: 1, permissoes: [], modulos_ativos: ['crm'] });

    await recarregarAcesso();

    expect(moduloAtivo('crm')).toBe(true);
    expect(moduloAtivo('objetos')).toBe(false);
  });
});
