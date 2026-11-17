import { describe, it, expect, beforeEach, vi } from 'vitest';
import './setup-globals';

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn(async () => ({ data: {} })) };
});

import { apiFetch } from '../src/services/api';
import {
  anexarComprovantesAoMovimento, conciliarLote, conciliarMovimento, conferirSaldo, desconciliarMovimento,
  ignorarMovimento, lancarLote, lancarMovimento, listarMovimentos, listarRegras, previaConciliacaoLote, resumoMesExtrato,
} from '../src/services/extratosApi';
import {
  dadosPorRegra, formatarCentavos, limitarSelecao, reaisParaCentavos, textoPendenciasExtrato, textoSituacaoSaldo, validarRateio,
} from '../src/utils/extratos';

const chamada = () => vi.mocked(apiFetch).mock.calls[0] as [string, any];

describe('extratosApi: movimentos e conciliação', () => {
  beforeEach(() => { vi.mocked(apiFetch).mockReset(); vi.mocked(apiFetch).mockResolvedValue({ data: {} }); });

  it('lista movimentos ignorando filtros vazios', async () => {
    vi.mocked(apiFetch).mockResolvedValueOnce({ data: [{ id_movimento: 1 }], total: 1 });
    const p = await listarMovimentos({ conta_id: 3, status: 'pendente', q: '', data_inicio: '2026-10-01' }, 2, 50);
    expect(chamada()[0]).toBe('financeiro/extratos/movimentos?conta_id=3&status=pendente&data_inicio=2026-10-01&page=2&per_page=50');
    expect(p.total).toBe(1);
  });

  it('concilia, desconcilia e ignora com os corpos da API', async () => {
    await conciliarMovimento(5, 'despesa', 9);
    expect(chamada()).toEqual(['financeiro/extratos/movimentos/5/conciliar', { method: 'POST', body: { pagamento_tipo: 'despesa', pagamento_id: 9 } }]);
    vi.mocked(apiFetch).mockClear();
    await desconciliarMovimento(5);
    expect(chamada()[0]).toBe('financeiro/extratos/movimentos/5/desconciliar');
    vi.mocked(apiFetch).mockClear();
    await ignorarMovimento(5, 'Tarifa já lançada');
    expect(chamada()[1].body).toEqual({ motivo: 'Tarifa já lançada' });
  });

  it('lote de conciliação sempre envia confirmar=true', async () => {
    await conciliarLote([{ movimento_id: 1, pagamento_tipo: 'receita', pagamento_id: 2 }]);
    expect(chamada()[1].body).toEqual({ confirmar: true, itens: [{ movimento_id: 1, pagamento_tipo: 'receita', pagamento_id: 2 }] });
    vi.mocked(apiFetch).mockClear();
    await previaConciliacaoLote(3);
    expect(chamada()[0]).toBe('financeiro/extratos/conciliacao/lote?conta_id=3');
  });

  it('lança, quita e lança em lote', async () => {
    await lancarMovimento(7, { fornecedor_id: 1, categoria_id: 2 });
    expect(chamada()).toEqual(['financeiro/extratos/movimentos/7/lancar', { method: 'POST', body: { fornecedor_id: 1, categoria_id: 2 } }]);
    vi.mocked(apiFetch).mockClear();
    await lancarMovimento(7, { quitar: { tipo: 'despesa', id: 4 } });
    expect(chamada()[1].body.quitar).toEqual({ tipo: 'despesa', id: 4 });
    vi.mocked(apiFetch).mockClear();
    await lancarLote([{ movimento_id: 1, fornecedor_id: 1, categoria_id: 2 }]);
    expect(chamada()[0]).toBe('financeiro/extratos/movimentos/lancar-lote');
  });

  it('regras, conferência, resumo e comprovante', async () => {
    await listarRegras(3);
    expect(chamada()[0]).toBe('financeiro/extratos/regras?conta_id=3');
    vi.mocked(apiFetch).mockClear();
    await conferirSaldo(3, 2026, 10);
    expect(chamada()[0]).toBe('financeiro/extratos/conferencia?conta_id=3&ano=2026&mes=10');
    vi.mocked(apiFetch).mockClear();
    await resumoMesExtrato(2026, 10);
    expect(chamada()[0]).toBe('financeiro/extratos/resumo-mes?ano=2026&mes=10');
    vi.mocked(apiFetch).mockClear();
    await anexarComprovantesAoMovimento(7, [new File(['x'], 'c.pdf')]);
    expect(chamada()[0]).toBe('financeiro/extratos/movimentos/7/comprovantes');
    expect(chamada()[1].body.getAll('arquivos[]')).toHaveLength(1);
  });
});

describe('utils/extratos: movimentos', () => {
  it('formata e converte valores em centavos', () => {
    expect(formatarCentavos(-1550).replace(/\s/g, ' ')).toBe('-R$ 15,50');
    expect(formatarCentavos(null)).toBe('—');
    expect(reaisParaCentavos('1.234,56')).toBe(123456);
    expect(reaisParaCentavos('abc')).toBeNull();
  });

  it('rateio precisa fechar 100% (ou ficar vazio)', () => {
    expect(validarRateio([])).toBeNull();
    expect(validarRateio([{ id: 1, percentual: 60 }, { id: 2, percentual: 40 }])).toBeNull();
    expect(validarRateio([{ id: 1, percentual: 60 }])).toMatch(/100%/);
  });

  it('limita a seleção a 50', () => {
    const r = limitarSelecao(Array.from({ length: 60 }, (_, i) => i));
    expect(r.ids).toHaveLength(50);
    expect(r.cortou).toBe(true);
  });

  it('lote pela regra: exige regra completa para despesa', () => {
    const base = { tipo: 'despesa' as const, pode_lancar: true, descricao_sugerida: 'Tarifa fictícia' };
    expect(dadosPorRegra({ ...base, regra: null })).toHaveProperty('motivo');
    expect(dadosPorRegra({ ...base, regra: { fornecedor_id: 1, cliente_id: null, categoria_id: null, rateio: [] } })).toHaveProperty('motivo');
    expect(dadosPorRegra({ ...base, pode_lancar: false, regra: null })).toEqual({ motivo: 'O movimento não está mais pendente.' });
    expect(dadosPorRegra({ ...base, regra: { fornecedor_id: 1, cliente_id: null, categoria_id: 2, rateio: [{ id: 1, percentual: 100 }] } }))
      .toEqual({ dados: { descricao: 'Tarifa fictícia', fornecedor_id: 1, categoria_id: 2, rateio: [{ id: 1, percentual: 100 }] } });
    expect(dadosPorRegra({ tipo: 'receita', pode_lancar: true, descricao_sugerida: 'x', regra: { fornecedor_id: null, cliente_id: null, categoria_id: null, rateio: [] } }))
      .toEqual({ dados: { descricao: 'x' } });
  });

  it('textos de conferência e fechamento', () => {
    expect(textoSituacaoSaldo('explicada_por_pendentes')).toMatch(/pendentes/);
    expect(textoSituacaoSaldo('sem_saldo_banco')).toMatch(/Sem saldo/);
    expect(textoPendenciasExtrato(0)).toMatch(/Nenhum/);
    expect(textoPendenciasExtrato(1)).toMatch(/^1 movimento/);
    expect(textoPendenciasExtrato(4)).toMatch(/^4 movimentos/);
  });
});
