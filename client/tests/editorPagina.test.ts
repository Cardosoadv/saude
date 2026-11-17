import { describe, it, expect } from 'vitest';
import {
  alterada, corpoDaSecao, ehNova, erroDeAncora, iconeDoTipo, idsEmOrdem, itemDoServidor, itemNovo,
  ordemComCopiaApos, resumoDaSecao, separarErrosDaSecao,
} from '../src/utils/editorPagina';
import type { CamposSchema, SiteSecao } from '../src/types/site';

const HERO: CamposSchema = {
  titulo: { tipo: 'texto_inline', obrigatorio: true },
  subtitulo: { tipo: 'textarea' },
  imagem: { tipo: 'imagem' },
  botoes: { tipo: 'lista', itens: { rotulo: { tipo: 'texto' } } },
  noticias: { tipo: 'dinamico', fonte: 'ultimas_noticias' },
};
const secao = (extra: Partial<SiteSecao> = {}): SiteSecao => ({
  id: 7, pagina_id: 1, tipo: 'hero', ancora: null, ordem: 1, visivel: 1, dados: { titulo: 'Olá' }, ...extra,
});

describe('item de seção', () => {
  it('vem do servidor completo, visível e sem alterações', () => {
    const item = itemDoServidor(secao(), HERO);

    expect(item).toMatchObject({ chave: '7', id: 7, compativel: true, visivel: true, ancora: '' });
    expect(item.dados).toMatchObject({ titulo: 'Olá', subtitulo: '', imagem: null, botoes: [] });
    expect(alterada(item, HERO)).toBe(false);
  });

  it('seção de tipo fora do template fica incompatível e nunca "alterada"', () => {
    const item = itemDoServidor(secao({ tipo: 'faq', visivel: 0 }), null);

    expect(item.compativel).toBe(false);
    expect(item.visivel).toBe(false);
    expect(alterada(item, null)).toBe(false);
  });

  it('o backend pode marcar a seção como incompatível', () => {
    expect(itemDoServidor(secao({ compativel: false }), HERO).compativel).toBe(false);
  });

  it('detecta mudança nos dados e na âncora, mas não em campos dinâmicos', () => {
    const item = itemDoServidor(secao(), HERO);

    expect(alterada({ ...item, dados: { ...item.dados, titulo: 'Outro' } }, HERO)).toBe(true);
    expect(alterada({ ...item, ancora: 'contato' }, HERO)).toBe(true);
    expect(alterada({ ...item, ancora: '  ' }, HERO)).toBe(false);
    expect(alterada({ ...item, dados: { ...item.dados, noticias: [{ id: 1 }] } }, HERO)).toBe(false);
    expect(alterada({ ...item, dados: { ...item.dados, botoes: [{ rotulo: 'a' }] } }, HERO)).toBe(true);
  });

  it('seção nova conta como alteração pendente', () => {
    const nova = itemNovo(1, 'hero', HERO);

    expect(ehNova(nova)).toBe(true);
    expect(nova.chave).toBe('novo-1');
    expect(alterada(nova, HERO)).toBe(true);
    expect(nova.dados).toMatchObject({ titulo: '', botoes: [] });
  });

  it('o corpo não leva campos dinâmicos e a âncora vazia vira null', () => {
    const item = { ...itemDoServidor(secao(), HERO), ancora: '  ' };
    item.dados.noticias = [1];

    expect(corpoDaSecao(item, HERO)).toEqual({ dados: { titulo: 'Olá', subtitulo: '', imagem: null, botoes: [] }, ancora: null });
    expect(corpoDaSecao({ ...item, ancora: ' contato ' }, HERO).ancora).toBe('contato');
  });
});

describe('âncora e resumo', () => {
  it('âncora: minúsculas, números, hífen e sublinhado', () => {
    for (const ok of ['', 'contato', 'sobre-nos', 'a_1']) expect(erroDeAncora(ok), ok).toBeNull();
    for (const ruim of ['Contato', 'a b', 'ação', '#x']) expect(erroDeAncora(ruim), ruim).not.toBeNull();
  });

  it('resumo: primeiro texto preenchido, sem HTML, com limite', () => {
    expect(resumoDaSecao({ titulo: '', subtitulo: '<p>Texto <b>forte</b>&nbsp;aqui</p>' }, HERO)).toBe('Texto forte aqui');
    expect(resumoDaSecao({ titulo: 'Direito <em>Civil</em>' }, HERO)).toBe('Direito Civil');
    expect(resumoDaSecao({ titulo: 'x'.repeat(200) }, HERO, 20)).toHaveLength(20);
    expect(resumoDaSecao({ botoes: [{ rotulo: 'Fale conosco' }] }, HERO)).toBe('Fale conosco');
    expect(resumoDaSecao({ botoes: [] }, HERO)).toBe('Sem conteúdo');
    expect(resumoDaSecao({}, HERO)).toBe('Sem conteúdo');
    expect(resumoDaSecao({ qualquer: 'Valor' }, null)).toBe('Valor');
  });

  it('ícone padrão para tipo desconhecido', () => {
    expect(iconeDoTipo('hero')).toBe('bi-window');
    expect(iconeDoTipo('inventado')).toBe('bi-layout-text-window');
  });
});

describe('ordem e erros', () => {
  it('ids em ordem ignoram seções ainda não salvas', () => {
    const a = itemDoServidor(secao({ id: 1 }), HERO);
    const b = itemDoServidor(secao({ id: 2 }), HERO);
    expect(idsEmOrdem([b, itemNovo(1, 'hero', HERO), a])).toEqual([2, 1]);
  });

  it('a cópia entra logo depois da original', () => {
    expect(ordemComCopiaApos([1, 2, 3, 9], 2, 9)).toEqual([1, 2, 9, 3]);
    expect(ordemComCopiaApos([1, 2, 3], 3, 9)).toEqual([1, 2, 3, 9]);
    expect(ordemComCopiaApos([1, 2], 99, 9)).toEqual([1, 2, 9]);
  });

  it('separa erros de campo, de âncora e os demais', () => {
    expect(separarErrosDaSecao({
      'dados.titulo': 'Campo obrigatório.', 'dados.botoes.1.rotulo': 'Campo obrigatório.', ancora: 'Já usada.', tipo: 'O tipo não existe.',
    })).toEqual({
      campos: { titulo: 'Campo obrigatório.', 'botoes.1.rotulo': 'Campo obrigatório.' },
      ancora: 'Já usada.',
      outros: ['O tipo não existe.'],
    });
  });
});
