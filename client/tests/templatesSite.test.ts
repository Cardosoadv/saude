import { describe, it, expect } from 'vitest';
import { agruparPorPagina, compararVersoes, descreverAcao, linhasDoRelatorio, resumoDeInstalacao } from '../src/utils/templatesSite';

const INSTALADOS = [
  { slug: 'classico', nome: 'Clássico', versao: '1.2.0', origem: 'core' as const },
  { slug: 'moderno', nome: 'Moderno', versao: '1.0.0', origem: 'upload' as const },
  { slug: 'da-cli', nome: 'Da CLI', versao: '1.0.0', origem: 'cli' as const },
];
const pacote = (slug: string, versao: string) => ({ slug, nome: slug.toUpperCase(), versao });

describe('compararVersoes', () => {
  it('compara por partes numéricas, não por texto', () => {
    expect(compararVersoes('1.2.10', '1.2.9')).toBe(1);
    expect(compararVersoes('1.0.0', '1.0.0')).toBe(0);
    expect(compararVersoes('1.0', '1.0.0')).toBe(0);
    expect(compararVersoes('0.9.9', '1.0.0')).toBe(-1);
    expect(compararVersoes('2.0.0', '1.99.99')).toBe(1);
    expect(compararVersoes('1.2.0-beta', '1.2.0')).toBe(0);
  });
});

describe('resumoDeInstalacao', () => {
  it('template novo: instalar, sem ativar', () => {
    const r = resumoDeInstalacao(pacote('novo', '1.0.0'), INSTALADOS);
    expect(r.acao).toBe('instalar');
    expect(r.texto).toContain('não será ativado');
  });

  it('versão maior de um template enviado: "atualizar de X para Y"', () => {
    const r = resumoDeInstalacao(pacote('moderno', '1.1.0'), INSTALADOS);
    expect(r.acao).toBe('atualizar');
    expect(r.texto).toContain('de 1.0.0 para 1.1.0');
  });

  it('mesma versão e versão mais antiga avisam', () => {
    expect(resumoDeInstalacao(pacote('moderno', '1.0.0'), INSTALADOS).acao).toBe('reinstalar');
    const antiga = resumoDeInstalacao({ ...pacote('moderno', '0.9.0') }, INSTALADOS);
    expect(antiga.acao).toBe('versao-antiga');
    expect(antiga.texto).toContain('mais antigo');
  });

  it('slug de template do sistema ou da CLI é bloqueado', () => {
    for (const slug of ['classico', 'da-cli']) {
      const r = resumoDeInstalacao(pacote(slug, '9.9.9'), INSTALADOS);
      expect(r.acao, slug).toBe('bloqueado');
      expect(r.texto).toContain('não pode ser sobrescrito');
    }
  });
});

describe('relatórios', () => {
  it('agrupa as seções da ativação por página', () => {
    const grupos = agruparPorPagina([
      { id: 1, tipo: 'faq', pagina: 'Início', pagina_id: 10, acao: 'incompativel', erros: [] },
      { id: 2, tipo: 'hero', pagina: 'Sobre', pagina_id: 11, acao: 'revisar', erros: { titulo: 'x' } },
      { id: 3, tipo: 'depoimentos', pagina: 'Início', pagina_id: 10, acao: 'incompativel', erros: [] },
    ]);

    expect(grupos.map((g) => [g.pagina, g.paginaId, g.itens.map((i) => i.id)])).toEqual([['Início', 10, [1, 3]], ['Sobre', 11, [2]]]);
    expect(agruparPorPagina([])).toEqual([]);
  });

  it('descreve a ação e lista os erros do relatório', () => {
    expect(descreverAcao('incompativel')).toContain('fica oculta');
    expect(descreverAcao('revisar')).toContain('revisão');
    expect(linhasDoRelatorio({ 'layouts/x.php': 'Arquivo proibido.' })).toEqual([{ onde: 'layouts/x.php', mensagem: 'Arquivo proibido.' }]);
  });
});
