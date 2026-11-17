import { describe, it, expect, beforeEach, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

vi.mock('../src/services/auth', () => ({ getCurrentUser: vi.fn() }));

import { aplicarAcesso, limparAcesso } from '../src/composables/useAcesso';
import { REGRAS_TELAS, telaLiberada } from '../src/utils/acessoTelas';

/** Telas sem módulo próprio: as rotas da API delas não têm filtro de módulo. */
const TELAS_LIVRES = [
  'dashboard', 'configuracoes', 'perfil',
];

describe('acessoTelas', () => {
  beforeEach(() => {
    limparAcesso();
  });

  it('toda tela do App.vue tem regra ou é livre de propósito', () => {
    const app = readFileSync(resolve(__dirname, '../src/App.vue'), 'utf8');
    const telas = new Set([...app.matchAll(/currentView === '([a-z-]+)'/g)].map((m) => m[1]));

    const semDecisao = [...telas].filter((t) => !(t in REGRAS_TELAS) && !TELAS_LIVRES.includes(t));

    expect(semDecisao).toEqual([]);
  });

  it('clientes some com o módulo desativado, mesmo com permissão', () => {
    aplicarAcesso({ permissoes: ['clientes.access'], modulos_ativos: ['financeiro'] });

    expect(telaLiberada('clientes')).toBe(false);
    expect(telaLiberada('cliente-detail')).toBe(false);
  });

  it('clientes some sem permissão, mesmo com o módulo ativo', () => {
    aplicarAcesso({ permissoes: ['tarefas.access'], modulos_ativos: ['clientes', 'tarefas'] });

    expect(telaLiberada('clientes')).toBe(false);
    expect(telaLiberada('tarefas')).toBe(true);
  });

  it('telas do site seguem o módulo site e a permissão de cada grupo de rotas', () => {
    aplicarAcesso({ permissoes: ['site.conteudo'], modulos_ativos: ['site'] });

    for (const tela of ['noticias', 'noticias-form', 'site-paginas', 'site-pagina-editor', 'site-menus', 'site-midias']) {
      expect(telaLiberada(tela), tela).toBe(true);
    }
    for (const tela of ['site-configuracoes', 'site-aparencia', 'site-templates']) {
      expect(telaLiberada(tela), tela).toBe(false);
    }
  });

  it('configurações e aparência pedem site.configuracoes; templates pedem site.templates', () => {
    aplicarAcesso({ permissoes: ['site.configuracoes', 'site.templates'], modulos_ativos: ['site'] });

    expect(telaLiberada('site-configuracoes')).toBe(true);
    expect(telaLiberada('site-aparencia')).toBe(true);
    expect(telaLiberada('site-templates')).toBe(true);
    expect(telaLiberada('site-paginas')).toBe(false);
  });

  it('módulo site desativado esconde todas as telas do site, mesmo com permissão', () => {
    aplicarAcesso({ permissoes: ['site.conteudo', 'site.configuracoes', 'site.templates'], modulos_ativos: ['processos'] });

    for (const tela of Object.keys(REGRAS_TELAS).filter((t) => t.startsWith('site-') || t.startsWith('noticias'))) {
      expect(telaLiberada(tela), tela).toBe(false);
    }
  });

  it('telas livres abrem com tudo bloqueado', () => {
    aplicarAcesso({ permissoes: [], modulos_ativos: [] });

    for (const tela of TELAS_LIVRES) {
      expect(telaLiberada(tela)).toBe(true);
    }
    expect(telaLiberada('extrato')).toBe(false);
  });

  it('backend sem os campos libera todas as telas', () => {
    aplicarAcesso({ id: 1 });

    for (const tela of Object.keys(REGRAS_TELAS)) {
      expect(telaLiberada(tela)).toBe(true);
    }
  });
});
