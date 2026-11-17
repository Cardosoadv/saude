import { describe, it, expect } from 'vitest';
import { destacarTermos, termosDaBusca } from '../src/utils/buscaDocumentacao';

describe('buscaDocumentacao', () => {
  it('termos: minúsculos, sem acento, sem curtos e sem repetidos', () => {
    expect(termosDaBusca('  Número  a CNJ número ')).toEqual(['numero', 'cnj']);
    expect(termosDaBusca('a b')).toEqual([]);
  });

  it('destaca sem diferenciar acentos e caixa, mantendo o texto original', () => {
    expect(destacarTermos('Informe o Número CNJ.', 'numero cnj')).toBe('Informe o <mark>Número</mark> <mark>CNJ</mark>.');
    expect(destacarTermos('ação e acao', 'AÇÃO')).toBe('<mark>ação</mark> e <mark>acao</mark>');
  });

  it('junta destaques vizinhos e sobrepostos', () => {
    expect(destacarTermos('menulateral', 'menu lateral')).toBe('<mark>menulateral</mark>');
    expect(destacarTermos('processos', 'proc cesso')).toBe('<mark>processo</mark>s');
  });

  it('escapa HTML do texto (o trecho da API é texto puro)', () => {
    expect(destacarTermos('<b>menu</b> & "x"', 'menu')).toBe('&lt;b&gt;<mark>menu</mark>&lt;/b&gt; &amp; &quot;x&quot;');
    expect(destacarTermos('<img src=x onerror=alert(1)>', '')).toBe('&lt;img src=x onerror=alert(1)&gt;');
  });
});
