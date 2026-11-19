import { describe, it, expect } from 'vitest';
import {
  ABAS, abaDoErro, achatar, agruparTextos, alterado, calcularAlteracoes, chavesExistentes, estadoInicial,
  normalizar, novaUnidade, previaGoogle, validarLocal, validarTodos,
} from '../src/utils/configuracoesSite';
import type { SiteConfiguracoes } from '../src/types/site';

const GRUPOS: SiteConfiguracoes = {
  identidade: [
    { chave: 'identidade.nome', tipo: 'string', grupo: 'identidade', valor: 'Silva & Souza Profissionais' },
    { chave: 'identidade.logo', tipo: 'imagem', grupo: 'identidade', valor: '3' },
  ],
  contato: [
    { chave: 'contato.telefone', tipo: 'string', grupo: 'contato', valor: '(11) 4000-1111' },
    { chave: 'contato.enderecos', tipo: 'json', grupo: 'contato', valor: [{ titulo: 'São Paulo / SP', cidade: 'São Paulo', uf: 'SP', extra: 'x' }] },
  ],
  seo: [{ chave: 'seo.descricao_padrao', tipo: 'text', grupo: 'seo', valor: 'Descrição' }],
  textos: [{ chave: 'textos.contato.nome', tipo: 'string', grupo: 'textos', valor: 'Seu nome completo' }],
};
const TEXTOS = { 'contato.nome': 'Nome completo *', 'contato.telefone': 'Telefone', 'rodape.copyright': 'Todos os direitos reservados.' };

describe('estado e normalização', () => {
  it('achata o GET e lista as chaves existentes', () => {
    expect(achatar(GRUPOS)['contato.telefone']).toBe('(11) 4000-1111');
    expect(chavesExistentes(GRUPOS).has('identidade.nome')).toBe(true);
    expect(chavesExistentes(GRUPOS).has('redes.instagram')).toBe(false);
    expect(achatar(null)).toEqual({});
  });

  it('monta o estado: padrões vazios, imagem numérica, unidades só com os campos conhecidos', () => {
    const e = estadoInicial(GRUPOS, TEXTOS);

    expect(e['identidade.nome']).toBe('Silva & Souza Profissionais');
    expect(e['identidade.nome_curto']).toBe('');
    expect(e['identidade.logo']).toBe(3);
    expect(e['identidade.favicon']).toBeNull();
    expect(e['contato.enderecos']).toEqual([{ ...novaUnidade(), titulo: 'São Paulo / SP', cidade: 'São Paulo', uf: 'SP' }]);
    expect(e['textos.contato.nome']).toBe('Seu nome completo');
    expect(e['textos.contato.telefone']).toBe('');
  });

  it('unidade totalmente vazia é descartada', () => {
    const campo = ABAS[1].campos.find((c) => c.chave === 'contato.enderecos')!;
    expect(normalizar(campo, [novaUnidade(), { ...novaUnidade(), cidade: ' BH ' }])).toEqual([{ ...novaUnidade(), cidade: 'BH' }]);
  });
});

describe('calcularAlteracoes', () => {
  const existentes = chavesExistentes(GRUPOS);
  const inicial = estadoInicial(GRUPOS, TEXTOS);

  it('sem mudança, corpo vazio', () => {
    expect(calcularAlteracoes(inicial, { ...inicial }, existentes, TEXTOS)).toEqual({});
    expect(alterado(inicial, { ...inicial })).toBe(false);
  });

  it('envia só o que mudou; chave existente vai pelo valor, nova leva o tipo', () => {
    const atual = { ...inicial, 'contato.telefone': '(11) 4000-2222', 'redes.instagram': 'https://instagram.com/x', 'identidade.favicon': 9 };

    expect(calcularAlteracoes(inicial, atual, existentes, TEXTOS)).toEqual({
      'contato.telefone': '(11) 4000-2222',
      'redes.instagram': { valor: 'https://instagram.com/x', tipo: 'string' },
      'identidade.favicon': { valor: 9, tipo: 'imagem' },
    });
  });

  it('limpar uma chave existente envia vazio; imagem limpa envia null', () => {
    const atual = { ...inicial, 'contato.telefone': '', 'identidade.logo': null };

    expect(calcularAlteracoes(inicial, atual, existentes, TEXTOS)).toEqual({ 'contato.telefone': '', 'identidade.logo': null });
  });

  it('restaurar o padrão de um texto manda vazio só se havia texto personalizado', () => {
    const restaurado = { ...inicial, 'textos.contato.nome': '' };
    expect(calcularAlteracoes(inicial, restaurado, existentes, TEXTOS)).toEqual({ 'textos.contato.nome': '' });

    const semLinha = { ...inicial, 'textos.contato.telefone': 'Fone' };
    const voltou = { ...semLinha, 'textos.contato.telefone': '' };
    expect(calcularAlteracoes(inicial, voltou, existentes, TEXTOS)).toEqual({});
  });

  it('texto novo de template: curto é string, longo é text', () => {
    const longo = 'x'.repeat(250);
    const textos = { ...TEXTOS, 'contato.aviso': longo };
    const ini = estadoInicial(GRUPOS, textos);
    const atual = { ...ini, 'textos.contato.telefone': 'Fone', 'textos.contato.aviso': 'Novo aviso' };

    expect(calcularAlteracoes(ini, atual, existentes, textos)).toEqual({
      'textos.contato.telefone': { valor: 'Fone', tipo: 'string' },
      'textos.contato.aviso': { valor: 'Novo aviso', tipo: 'text' },
    });
  });

  it('unidades alteradas vão como lista', () => {
    const atual = { ...inicial, 'contato.enderecos': [...inicial['contato.enderecos'], { ...novaUnidade(), cidade: 'Belo Horizonte' }] };
    const corpo = calcularAlteracoes(inicial, atual, existentes, TEXTOS) as any;

    expect(corpo['contato.enderecos']).toHaveLength(2);
  });
});

describe('validação local', () => {
  it('Google Ads: ID da conta e rótulos de conversão', () => {
    expect(validarLocal('integracoes.ads_id', 'AW-861297936')).toBeNull();
    expect(validarLocal('integracoes.ads_id', 'G-ABC123XYZ9')).toMatch(/inválido/);
    expect(validarLocal('integracoes.ads_id', '')).toBeNull();
    expect(validarLocal('integracoes.ads_conversao_formulario', '5OWvCJPanZ4dEJC62ZoD')).toBeNull();
    expect(validarLocal('integracoes.ads_conversao_whatsapp', 'AW-861297936/5OWvCJPanZ4dEJC62ZoD')).toMatch(/inválido/);
    expect(validarLocal('integracoes.ads_conversao_whatsapp', '')).toBeNull();
  });

  it('Google Analytics G-XXXXXXX', () => {
    expect(validarLocal('integracoes.ga_id', 'G-ABC123XYZ9')).toBeNull();
    expect(validarLocal('integracoes.ga_id', 'UA-1234')).toMatch(/inválido/);
    expect(validarLocal('integracoes.ga_id', 'g-abc123')).toMatch(/inválido/);
    expect(validarLocal('integracoes.ga_id', '')).toBeNull();
    expect(validarLocal('lgpd.encarregado_email', 'dpo@clinica.com.br')).toBeNull();
    expect(validarLocal('lgpd.encarregado_email', 'dpo@')).toMatch(/inválido/);
    expect(validarLocal('lgpd.politica_versao', 'x'.repeat(21))).toMatch(/20/);
  });

  it('e-mail, redes, SEO e cor', () => {
    expect(validarLocal('contato.email', 'a@b.com')).toBeNull();
    expect(validarLocal('contato.email', 'a@b')).toBe('E-mail inválido.');
    expect(validarLocal('redes.linkedin', 'https://linkedin.com/x')).toBeNull();
    expect(validarLocal('redes.linkedin', 'linkedin.com/x')).toMatch(/URL completa/);
    expect(validarLocal('seo.descricao_padrao', 'a'.repeat(160))).toBeNull();
    expect(validarLocal('seo.descricao_padrao', 'a'.repeat(161))).toBe('Máximo de 160 caracteres.');
    expect(validarLocal('seo.sufixo_titulo', 'a'.repeat(101))).toBe('Máximo de 100 caracteres.');
    expect(validarLocal('identidade.cor_documentos', '#abc')).toBeNull();
    expect(validarLocal('identidade.cor_documentos', 'azul')).toMatch(/hexadecimal/);
  });

  it('validarTodos e aba do primeiro erro', () => {
    const erros = validarTodos({ 'integracoes.ga_id': 'x', 'contato.email': 'ok@ok.com', 'seo.sufixo_titulo': 'a'.repeat(101) });
    expect(Object.keys(erros)).toEqual(['integracoes.ga_id', 'seo.sufixo_titulo']);
    expect(abaDoErro(erros)).toBe('integracoes');
    expect(abaDoErro({ 'textos.contato.nome': 'x' })).toBe('textos');
    expect(abaDoErro({})).toBeNull();
  });
});

describe('prévia no Google e textos', () => {
  it('título = título padrão + sufixo, cortado em 60; descrição em 160', () => {
    const p = previaGoogle({
      'seo.titulo_padrao': 'Clínica de saúde integrada e humanizada em São Paulo',
      'seo.sufixo_titulo': ' | Silva & Souza Profissionais',
      'seo.descricao_padrao': 'd'.repeat(200),
      'contato.site': 'https://www.silva.adv.br/',
    });

    expect(p.titulo.length).toBeLessThanOrEqual(60);
    expect(p.titulo.length).toBeGreaterThan(55);
    expect(p.titulo.endsWith('…')).toBe(true);
    expect(p.descricao).toHaveLength(160);
    expect(p.url).toBe('https://www.silva.adv.br');
  });

  it('sem título padrão usa o nome; sem nada mostra marcadores', () => {
    expect(previaGoogle({ 'identidade.nome': 'Silva' }).titulo).toBe('Silva');
    const vazio = previaGoogle({});
    expect(vazio.titulo).toBe('Título do site');
    expect(vazio.url).toBe('https://www.seusite.com.br');
    expect(vazio.descricao).toMatch(/Sem descrição/);
  });

  it('agrupa os textos do template pelo prefixo', () => {
    expect(agruparTextos({ 'contato.nome': 'a', 'contato.email': 'b', 'rodape.copyright': 'c', avulso: 'd' })).toEqual([
      { grupo: 'contato', itens: [{ chave: 'contato.nome', padrao: 'a' }, { chave: 'contato.email', padrao: 'b' }] },
      { grupo: 'rodape', itens: [{ chave: 'rodape.copyright', padrao: 'c' }] },
      { grupo: 'geral', itens: [{ chave: 'avulso', padrao: 'd' }] },
    ]);
  });
});

describe('checkbox do WhatsApp', () => {
  it('lê o bool do banco e envia o tipo ao criar a chave', () => {
    const inicial = estadoInicial(GRUPOS, TEXTOS);
    expect(inicial['contato.telefone_whatsapp']).toBe(false);

    const atual = { ...inicial, 'contato.telefone_whatsapp': true };
    expect(calcularAlteracoes(inicial, atual, chavesExistentes(GRUPOS), TEXTOS))
      .toEqual({ 'contato.telefone_whatsapp': { valor: true, tipo: 'bool' } });
    expect(normalizar(ABAS[1].campos.find((c) => c.ui === 'checkbox')!, '1')).toBe(true);
  });
});
