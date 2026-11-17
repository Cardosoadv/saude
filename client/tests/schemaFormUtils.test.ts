import { describe, it, expect } from 'vitest';
import {
  buscarIcones, caminhoDe, completarDados, dadosParaEnvio, errosOrfaos, errosSemPrefixo, iconeValido,
  linkComTipo, moverItem, novoItem, removerItem, rotuloDoCampo, temErroEm, valorPadrao,
} from '../src/utils/schemaForm';
import type { CamposSchema } from '../src/types/site';

const HERO: CamposSchema = {
  sobretitulo: { tipo: 'texto', max: 120 },
  titulo: { tipo: 'texto_inline', obrigatorio: true },
  subtitulo: { tipo: 'textarea' },
  imagem: { tipo: 'imagem' },
  ativo: { tipo: 'bool' },
  botoes: {
    tipo: 'lista',
    itens: {
      rotulo: { tipo: 'texto', obrigatorio: true, max: 40 },
      link: { tipo: 'link', obrigatorio: true },
      estilo: { tipo: 'opcao', opcoes: ['primario', 'contorno'], padrao: 'primario' },
    },
  },
  ultimas: { tipo: 'dinamico', fonte: 'ultimas_noticias' },
};

describe('valorPadrao / completarDados', () => {
  it('cada tipo tem um valor inicial coerente', () => {
    expect(valorPadrao({ tipo: 'texto' })).toBe('');
    expect(valorPadrao({ tipo: 'bool' })).toBe(false);
    expect(valorPadrao({ tipo: 'imagem' })).toBeNull();
    expect(valorPadrao({ tipo: 'lista' })).toEqual([]);
    expect(valorPadrao({ tipo: 'link' })).toEqual({ tipo: 'pagina', pagina_id: null });
    expect(valorPadrao({ tipo: 'opcao', opcoes: ['a', 'b'] })).toBe('a');
    expect(valorPadrao({ tipo: 'dinamico' })).toBeUndefined();
  });

  it('`padrao` do schema vence e é copiado', () => {
    const padrao = { tipo: 'url', url: '/x' };
    const v = valorPadrao({ tipo: 'link', padrao }) as any;
    expect(v).toEqual(padrao);
    v.url = 'mudou';
    expect(padrao.url).toBe('/x');
  });

  it('completa só o que falta, inclusive dentro de listas, sem mexer na entrada', () => {
    const entrada = { titulo: 'Olá', botoes: [{ rotulo: 'Ver' }] };
    const dados = completarDados(HERO, entrada);

    expect(dados.titulo).toBe('Olá');
    expect(dados.sobretitulo).toBe('');
    expect(dados.ativo).toBe(false);
    expect(dados.botoes[0]).toEqual({ rotulo: 'Ver', link: { tipo: 'pagina', pagina_id: null }, estilo: 'primario' });
    expect('ultimas' in dados).toBe(false);
    expect(entrada).toEqual({ titulo: 'Olá', botoes: [{ rotulo: 'Ver' }] });
  });

  it('item novo de lista já nasce completo', () => {
    expect(novoItem(HERO.botoes)).toEqual({ rotulo: '', link: { tipo: 'pagina', pagina_id: null }, estilo: 'primario' });
  });
});

describe('dadosParaEnvio (round-trip)', () => {
  it('mantém o valor e tira os campos dinâmicos', () => {
    const dados = completarDados(HERO, {
      titulo: 'Direito <em>Civil</em>',
      imagem: 7,
      botoes: [{ rotulo: 'Fale', link: { tipo: 'ancora', ancora: 'contato' }, estilo: 'contorno' }],
    });
    dados.ultimas = [{ id: 1 }];

    const envio = dadosParaEnvio(HERO, dados);

    expect('ultimas' in envio).toBe(false);
    expect(envio.titulo).toBe('Direito <em>Civil</em>');
    expect(envio.imagem).toBe(7);
    expect(envio.botoes).toEqual([{ rotulo: 'Fale', link: { tipo: 'ancora', ancora: 'contato' }, estilo: 'contorno' }]);
    // o que a API devolve, completado de novo, é idêntico
    expect(completarDados(HERO, envio)).toEqual({ ...dados, ultimas: undefined, sobretitulo: '', subtitulo: '', ativo: false });
  });

  it('tira dinâmicos também dentro dos itens de uma lista', () => {
    const campos: CamposSchema = { itens: { tipo: 'lista', itens: { nome: { tipo: 'texto' }, auto: { tipo: 'dinamico', fonte: 'areas' } } } };
    expect(dadosParaEnvio(campos, { itens: [{ nome: 'a', auto: [1] }] })).toEqual({ itens: [{ nome: 'a' }] });
  });
});

describe('listas', () => {
  it('moverItem devolve uma cópia reordenada', () => {
    const origem = ['a', 'b', 'c'];
    expect(moverItem(origem, 0, 2)).toEqual(['b', 'c', 'a']);
    expect(moverItem(origem, 2, 0)).toEqual(['c', 'a', 'b']);
    expect(origem).toEqual(['a', 'b', 'c']);
  });

  it('índices inválidos não mudam a ordem', () => {
    expect(moverItem(['a', 'b'], 0, 5)).toEqual(['a', 'b']);
    expect(moverItem(['a', 'b'], -1, 0)).toEqual(['a', 'b']);
    expect(moverItem(['a', 'b'], 1, 1)).toEqual(['a', 'b']);
  });

  it('removerItem tira só o índice pedido', () => {
    expect(removerItem(['a', 'b', 'c'], 1)).toEqual(['a', 'c']);
  });
});

describe('erros por campo', () => {
  const erros = {
    'dados.titulo': 'Campo obrigatório.',
    'dados.botoes.1.rotulo': 'Campo obrigatório.',
    'dados.botoes.1.estilo': 'Valor não está entre as opções.',
    ancora: 'Âncora inválida.',
  };

  it('tira o prefixo e descarta o que não o tem', () => {
    expect(errosSemPrefixo(erros, 'dados.')).toEqual({
      titulo: 'Campo obrigatório.',
      'botoes.1.rotulo': 'Campo obrigatório.',
      'botoes.1.estilo': 'Valor não está entre as opções.',
    });
  });

  it('caminhoDe monta o caminho como a API', () => {
    expect(caminhoDe('', 'titulo')).toBe('titulo');
    expect(caminhoDe('botoes.1', 'rotulo')).toBe('botoes.1.rotulo');
    expect(caminhoDe('', 'botoes', 1, 'rotulo')).toBe('botoes.1.rotulo');
  });

  it('temErroEm vê erros do próprio campo e de dentro dele', () => {
    const e = errosSemPrefixo(erros, 'dados.');
    expect(temErroEm(e, 'botoes')).toBe(true);
    expect(temErroEm(e, 'botoes.1')).toBe(true);
    expect(temErroEm(e, 'botoes.0')).toBe(false);
    expect(temErroEm(e, 'bot')).toBe(false);
  });

  it('errosOrfaos lista o que não casa com nenhum campo', () => {
    expect(errosOrfaos(HERO, { titulo: 'x', 'botoes.1.rotulo': 'y', 'botoes.x.rotulo': 'z', inexistente: 'w', 'botoes.0.nada': 'v' }))
      .toEqual({ 'botoes.x.rotulo': 'z', inexistente: 'w', 'botoes.0.nada': 'v' });
  });
});

describe('link, ícone e rótulo', () => {
  it('trocar o tipo do link descarta o resto', () => {
    expect(linkComTipo('url')).toEqual({ tipo: 'url', url: '' });
    expect(linkComTipo('ancora')).toEqual({ tipo: 'ancora', ancora: '' });
    expect(linkComTipo('pagina')).toEqual({ tipo: 'pagina', pagina_id: null });
  });

  it('só aceita nomes bi-…', () => {
    expect(iconeValido('bi-award')).toBe(true);
    expect(iconeValido('bi-person-badge')).toBe(true);
    expect(iconeValido('award')).toBe(false);
    expect(iconeValido('bi-Award')).toBe(false);
    expect(iconeValido('bi-x"><script>')).toBe(false);
  });

  it('a busca de ícones ignora o prefixo e as maiúsculas', () => {
    const achados = buscarIcones('PERSON');
    expect(achados.length).toBeGreaterThan(0);
    expect(achados.every((i) => i.includes('person'))).toBe(true);
    expect(buscarIcones('bi-person')).toContain('bi-person');
    expect(buscarIcones('')).toHaveLength(24);
    expect(buscarIcones('zzzz')).toEqual([]);
  });

  it('rótulo legível', () => {
    expect(rotuloDoCampo('sobretitulo')).toBe('Sobretitulo');
    expect(rotuloDoCampo('link_texto')).toBe('Link texto');
  });
});
