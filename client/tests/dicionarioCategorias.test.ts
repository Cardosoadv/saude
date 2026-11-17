import { describe, it, expect } from 'vitest';
import type { TransacaoIntegracao } from '../src/services/contabil';
import {
  alteracoesDicionario,
  aplicarDicionario,
  mapaDicionario,
  registrarSugestoes,
} from '../src/utils/dicionarioCategorias';

const BANCO = 1;
const HONORARIOS = 10;
const EXITO = 11;
const ALUGUEL = 20;
const OCUPACAO = 21;

function receitaUnica(uid: string, categoriaId: number, credito = HONORARIOS): TransacaoIntegracao {
  return {
    uid,
    tipo: 'receita',
    source_type: 'fin_pgto_receita',
    source_id: 1,
    data: '2026-07-01',
    descricao: 'Recebimento',
    valor: 100,
    conta_origem: 'Banco',
    status_contabil: 'pendente',
    categoria_id: categoriaId,
    categorias: [{ categoria_id: categoriaId, categoria: `Cat ${categoriaId}`, valor: 100 }],
    debito_id: BANCO,
    credito_id: credito,
  };
}

function despesaDividida(uid: string): TransacaoIntegracao {
  return {
    uid,
    tipo: 'despesa',
    source_type: 'fin_pgto_despesa',
    source_id: 2,
    data: '2026-07-01',
    descricao: 'Pagamento',
    valor: 100,
    conta_origem: 'Banco',
    status_contabil: 'pendente',
    divisao_sugerida: true,
    categoria_id: null,
    debito_id: ALUGUEL,
    credito_id: BANCO,
    divisoes: [
      { valor: 60, debito_id: ALUGUEL, credito_id: BANCO, categoria_id: 5, categoria: 'Aluguel' },
      { valor: 40, debito_id: ALUGUEL, credito_id: BANCO, categoria_id: 6, categoria: 'Condomínio' },
    ],
  };
}

describe('Dicionário de categorias', () => {
  it('não oferece atualização enquanto a sugestão não é alterada', () => {
    const t = receitaUnica('a', 3);
    registrarSugestoes([t]);
    expect(alteracoesDicionario(t, {})).toEqual([]);
  });

  it('oferece gravar a conta de crédito trocada numa receita de categoria única', () => {
    const t = receitaUnica('a', 3);
    registrarSugestoes([t]);
    t.credito_id = EXITO;

    expect(alteracoesDicionario(t, mapaDicionario([{ categoria_id: 3, tipo: 'receita', account_id: HONORARIOS }]))).toEqual([
      { categoria_id: 3, categoria: 'Cat 3', tipo: 'receita', account_id: EXITO, anterior_id: HONORARIOS },
    ]);
  });

  it('ignora a troca do lado que não é da categoria (débito bancário da receita)', () => {
    const t = receitaUnica('a', 3);
    registrarSugestoes([t]);
    t.debito_id = 99;
    expect(alteracoesDicionario(t, {})).toEqual([]);
  });

  it('na divisão, só lista as partes de categoria cuja conta de débito mudou', () => {
    const t = despesaDividida('d');
    registrarSugestoes([t]);
    t.divisoes![1].debito_id = OCUPACAO;

    expect(alteracoesDicionario(t, {})).toEqual([
      { categoria_id: 6, categoria: 'Condomínio', tipo: 'despesa', account_id: OCUPACAO, anterior_id: null },
    ]);
  });

  it('não oferece quando a conta escolhida já é a do dicionário', () => {
    const t = receitaUnica('a', 3);
    registrarSugestoes([t]);
    t.credito_id = EXITO;
    expect(alteracoesDicionario(t, { 'receita:3': EXITO })).toEqual([]);
  });

  it('aplica a nova associação às pendentes não mexidas e preserva escolhas manuais', () => {
    const alterada = receitaUnica('a', 3);
    const intocada = receitaUnica('b', 3);
    const manual = receitaUnica('c', 3);
    const outraCategoria = receitaUnica('d', 4);
    const importada = { ...receitaUnica('e', 3), status_contabil: 'importado' as const };
    const todas = [alterada, intocada, manual, outraCategoria, importada];
    registrarSugestoes(todas);

    alterada.credito_id = EXITO;
    manual.credito_id = 77;

    const ajustadas = aplicarDicionario(todas, [
      { categoria_id: 3, categoria: 'Cat 3', tipo: 'receita', account_id: EXITO, anterior_id: null },
    ]);

    expect(ajustadas).toBe(1);
    expect(intocada.credito_id).toBe(EXITO);
    expect(intocada.sugestao_origem).toBe('dicionario');
    expect(manual.credito_id).toBe(77);
    expect(outraCategoria.credito_id).toBe(HONORARIOS);
    expect(importada.credito_id).toBe(HONORARIOS);
    // A linha alterada passa a ter a nova conta como sugestão: não volta a oferecer
    expect(alteracoesDicionario(alterada, { 'receita:3': EXITO })).toEqual([]);
    expect(alterada.sugestao_conta_id).toBe(EXITO);
  });

  it('aplica a nova associação às partes das divisões sugeridas', () => {
    const t = despesaDividida('d');
    registrarSugestoes([t]);

    const ajustadas = aplicarDicionario([t], [
      { categoria_id: 6, categoria: 'Condomínio', tipo: 'despesa', account_id: OCUPACAO, anterior_id: null },
    ]);

    expect(ajustadas).toBe(1);
    expect(t.divisoes![0].debito_id).toBe(ALUGUEL);
    expect(t.divisoes![1].debito_id).toBe(OCUPACAO);
  });
});
