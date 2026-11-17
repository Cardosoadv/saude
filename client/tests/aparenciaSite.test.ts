import { describe, it, expect } from 'vitest';
import {
  avisosDeContraste, calcularCorpo, contraste, errosLocais, estadoDoTema, familiasDoToken, hexCompleto, hexValido,
  luminancia, mesmaCor, temAlteracoes, urlFontes,
} from '../src/utils/aparenciaSite';
import type { SiteTema } from '../src/types/site';

const TEMA: SiteTema = {
  cores: {
    'navy-dark': { padrao: '#0D1B2A', atual: '#0D1B2A', personalizado: false },
    cream: { padrao: '#FAF9F6', atual: '#FAF9F6', personalizado: false },
    gold: { padrao: '#C9A84C', atual: '#B08E35', personalizado: true },
    'text-primary': { padrao: '#FAF9F6', atual: '#FAF9F6', personalizado: false },
    'text-dark': { padrao: '#1E293B', atual: '#1E293B', personalizado: false },
  },
  fontes: {
    serif: { padrao: 'Cormorant Garamond', atual: 'Lora', personalizado: true },
    sans: { padrao: 'Outfit', atual: 'Outfit', personalizado: false },
  },
  fontes_disponiveis: { 'Cormorant Garamond': 'serif', Lora: 'serif', 'Playfair Display': 'serif', Outfit: 'sans', Inter: 'sans' },
};

describe('cores', () => {
  it('valida e completa hexadecimais', () => {
    for (const ok of ['#fff', '#FFFFFF', '#1b4965']) expect(hexValido(ok), ok).toBe(true);
    for (const ruim of ['fff', '#ffff', '#12345g', 'red', '', '#1234567']) expect(hexValido(ruim), ruim).toBe(false);
    expect(hexCompleto('#ABC')).toBe('#aabbcc');
    expect(hexCompleto('#1B4965')).toBe('#1b4965');
    expect(mesmaCor('#FFF', '#ffffff')).toBe(true);
  });

  it('luminância e contraste seguem a fórmula do WCAG', () => {
    expect(luminancia('#000000')).toBe(0);
    expect(luminancia('#ffffff')).toBeCloseTo(1, 5);
    expect(contraste('#000000', '#ffffff')).toBeCloseTo(21, 1);
    expect(contraste('#ffffff', '#ffffff')).toBeCloseTo(1, 5);
    expect(contraste('#777777', '#ffffff')).toBeCloseTo(4.48, 1); // o cinza clássico que reprova no AA
    expect(contraste('#fff', '#000')).toBeCloseTo(contraste('#000', '#fff'), 5);
  });

  it('avisa só os pares abaixo do mínimo e ignora tokens ausentes ou inválidos', () => {
    expect(avisosDeContraste({ 'text-primary': '#FAF9F6', 'navy-dark': '#0D1B2A', 'text-dark': '#1E293B', cream: '#FAF9F6' })).toEqual([]);

    const ruins = avisosDeContraste({ 'text-primary': '#FAF9F6', 'navy-dark': '#E0E0E0', 'text-dark': 'oops', cream: '#FAF9F6' });
    expect(ruins).toHaveLength(1);
    expect(ruins[0]).toMatchObject({ texto: 'text-primary', fundo: 'navy-dark', minimo: 4.5 });
    expect(ruins[0].mensagem).toMatch(/contraste 1,\d:1.*WCAG AA.*4,5:1/);
    expect(avisosDeContraste({})).toEqual([]);
  });

  it('destaque usa o mínimo de 3:1', () => {
    expect(avisosDeContraste({ gold: '#C9A84C', 'navy-dark': '#0D1B2A' })).toEqual([]);
    expect(avisosDeContraste({ gold: '#202020', 'navy-dark': '#0D1B2A' })[0].minimo).toBe(3);
  });
});

describe('corpo do PUT', () => {
  it('estado inicial = valores atuais', () => {
    expect(estadoDoTema(TEMA)).toEqual({
      cores: { 'navy-dark': '#0D1B2A', cream: '#FAF9F6', gold: '#B08E35', 'text-primary': '#FAF9F6', 'text-dark': '#1E293B' },
      fontes: { serif: 'Lora', sans: 'Outfit' },
    });
    expect(estadoDoTema(null)).toEqual({ cores: {}, fontes: {} });
  });

  it('sem mudança, sem corpo', () => {
    expect(calcularCorpo(TEMA, estadoDoTema(TEMA))).toEqual({});
    expect(temAlteracoes(TEMA, estadoDoTema(TEMA))).toBe(false);
  });

  it('manda só o que mudou, com hexadecimal completo', () => {
    const atual = estadoDoTema(TEMA);
    atual.cores['navy-dark'] = '#123';
    atual.fontes.sans = 'Inter';

    expect(calcularCorpo(TEMA, atual)).toEqual({ cores: { 'navy-dark': '#112233' }, fontes: { sans: 'Inter' } });
    expect(temAlteracoes(TEMA, atual)).toBe(true);
  });

  it('voltar ao padrão do template manda vazio (remove a personalização)', () => {
    const atual = estadoDoTema(TEMA);
    atual.cores.gold = '#c9a84c'; // = padrão
    atual.fontes.serif = 'Cormorant Garamond'; // = padrão

    expect(calcularCorpo(TEMA, atual)).toEqual({ cores: { gold: '' }, fontes: { serif: '' } });
  });

  it('erros de cor digitada', () => {
    const atual = estadoDoTema(TEMA);
    atual.cores.cream = 'creme';
    expect(errosLocais(atual)).toEqual({ 'cores.cream': 'Cor deve ser hexadecimal (#RGB ou #RRGGBB).' });
  });
});

describe('fontes', () => {
  it('cada token mostra as famílias da sua categoria', () => {
    expect(familiasDoToken('serif', TEMA.fontes_disponiveis)).toEqual(['Cormorant Garamond', 'Lora', 'Playfair Display']);
    expect(familiasDoToken('sans', TEMA.fontes_disponiveis)).toEqual(['Outfit', 'Inter']);
    expect(familiasDoToken('mono', { A: 'serif' })).toEqual(['A']);
  });

  it('endereço do Google Fonts só com famílias da lista', () => {
    expect(urlFontes(['Lora', 'Playfair Display', 'Lora', 'Evil;font'], TEMA.fontes_disponiveis))
      .toBe('https://fonts.googleapis.com/css2?family=Lora:wght@400;700&family=Playfair+Display:wght@400;700&display=swap');
    expect(urlFontes(['Inexistente'], TEMA.fontes_disponiveis)).toBe('');
  });
});
