import { describe, it, expect, beforeEach, vi } from 'vitest';

vi.mock('../src/services/auth', () => ({ getCurrentUser: vi.fn() }));

import { aplicarAcesso, limparAcesso } from '../src/composables/useAcesso';
import { telaLiberada } from '../src/utils/acessoTelas';

describe('acesso à documentação', () => {
  beforeEach(() => limparAcesso());

  it('exige o módulo documentacao e a permissão de leitura', () => {
    aplicarAcesso({ permissoes: ['documentacao.access'], modulos_ativos: ['documentacao'] });
    expect(telaLiberada('documentacao')).toBe(true);

    aplicarAcesso({ permissoes: ['documentacao.access'], modulos_ativos: ['processos'] });
    expect(telaLiberada('documentacao')).toBe(false);

    aplicarAcesso({ permissoes: [], modulos_ativos: ['documentacao'] });
    expect(telaLiberada('documentacao')).toBe(false);
  });

  it('edição exige documentacao.editar', () => {
    aplicarAcesso({ permissoes: ['documentacao.access'], modulos_ativos: ['documentacao'] });
    expect(telaLiberada('documentacao-admin')).toBe(false);
    expect(telaLiberada('documentacao-editor')).toBe(false);

    aplicarAcesso({ permissoes: ['documentacao.access', 'documentacao.editar'], modulos_ativos: ['documentacao'] });
    expect(telaLiberada('documentacao-admin')).toBe(true);
    expect(telaLiberada('documentacao-editor')).toBe(true);
  });
});
