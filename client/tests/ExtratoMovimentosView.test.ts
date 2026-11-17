// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({
  listarMovimentos: vi.fn(), sugestoesConciliacao: vi.fn(), conciliarMovimento: vi.fn(), desconciliarMovimento: vi.fn(),
  ignorarMovimento: vi.fn(), previaConciliacaoLote: vi.fn(), conciliarLote: vi.fn(), sugestaoLancamento: vi.fn(),
  lancarMovimento: vi.fn(), lancarLote: vi.fn(), listarRegras: vi.fn(), excluirRegra: vi.fn(), conferirSaldo: vi.fn(),
  anexarComprovantesAoMovimento: vi.fn(),
}));
const acesso = vi.hoisted(() => ({ pode: vi.fn(() => true) }));

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return {
    ...real,
    apiFetch: vi.fn(async (rota: string) => {
      if (rota.startsWith('financeiro/contas')) return { success: true, data: [{ id_conta: 1, conta: 'Conta Fictícia' }] };
      if (rota.startsWith('financeiro/categorias')) return { success: true, data: [{ id_categoria: 21, categoria: 'Tarifas' }] };
      return { data: [] };
    }),
  };
});
vi.mock('../src/composables/useAcesso', () => acesso);
vi.mock('../src/services/extratosApi', async () => {
  const real = await vi.importActual<typeof import('../src/services/extratosApi')>('../src/services/extratosApi');
  return { ...real, ...api };
});

import ExtratoMovimentosView from '../src/views/Financeiro/ExtratoMovimentosView.vue';

const espera = async () => { for (let i = 0; i < 10; i++) { await nextTick(); await Promise.resolve(); } };
const mov = (id: number, extra: Record<string, any> = {}) => ({
  id_movimento: id, conta_id: 1, data: '2026-10-05', valor_centavos: -4500, memo: `TARIFA FICTICIA ${id}`,
  nome_contraparte: null, status: 'pendente', pagamento_tipo: null, pagamento_id: null, motivo_ignorado: null, ...extra,
});

function montar() {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const toasts: any[] = [];
  const app = createApp(ExtratoMovimentosView, { onToast: (t: any) => toasts.push(t) });
  app.mount(raiz);
  return { raiz, toasts, desmontar: () => { app.unmount(); raiz.remove(); } };
}

const botao = (raiz: HTMLElement, re: RegExp) =>
  Array.from(raiz.querySelectorAll('button')).find((b) => re.test(b.textContent?.trim() ?? '')) as HTMLButtonElement;

describe('ExtratoMovimentosView', () => {
  let m: ReturnType<typeof montar> | null = null;
  beforeEach(() => {
    Object.values(api).forEach((f) => f.mockReset());
    acesso.pode.mockReturnValue(true);
    api.listarMovimentos.mockResolvedValue({ itens: [mov(1), mov(2, { status: 'conciliado', pagamento_tipo: 'despesa', pagamento_id: 9 })], total: 2, page: 1 });
    api.sugestaoLancamento.mockResolvedValue({
      movimento: mov(1), tipo: 'despesa', pode_lancar: true, descricao_sugerida: 'Tarifa fictícia', regra: null, contrapartes: [{ id: 8, nome: 'Banco Fictício' }],
    });
  });
  afterEach(() => { m?.desmontar(); m = null; });

  it('lista movimentos com status e ações conforme o estado', async () => {
    m = montar();
    await espera();
    expect(api.listarMovimentos).toHaveBeenCalledWith(expect.objectContaining({ conta_id: 1, status: 'pendente' }), 1, 50);
    expect(m.raiz.textContent).toContain('TARIFA FICTICIA 1');
    expect(botao(m.raiz, /^Conciliar$/)).toBeDefined();
    expect(botao(m.raiz, /^Lançar$/)).toBeDefined();
    expect(botao(m.raiz, /Comprovante/)).toBeDefined();
    expect(botao(m.raiz, /^Desfazer$/)).toBeDefined();
  });

  it('sem permissões de conciliar e lançar, esconde as ações', async () => {
    acesso.pode.mockImplementation((...p: string[]) => p.includes('financeiro.access'));
    m = montar();
    await espera();
    expect(botao(m.raiz, /^Conciliar$/)).toBeUndefined();
    expect(botao(m.raiz, /^Lançar$/)).toBeUndefined();
    expect(botao(m.raiz, /^Ignorar$/)).toBeUndefined();
  });

  it('mostra estado vazio e erro da API', async () => {
    api.listarMovimentos.mockResolvedValueOnce({ itens: [], total: 0, page: 1 });
    m = montar();
    await espera();
    expect(m.raiz.textContent).toContain('Nenhum movimento encontrado');
    m.desmontar();
    api.listarMovimentos.mockRejectedValueOnce(new Error('Erro ao listar movimentos.'));
    m = montar();
    await espera();
    expect(m.raiz.textContent).toContain('Erro ao listar movimentos.');
  });

  it('lançar duas vezes (clique duplo) envia uma única requisição', async () => {
    let liberar!: (v: any) => void;
    api.lancarMovimento.mockReturnValue(new Promise((r) => { liberar = r; }));
    m = montar();
    await espera();
    botao(m.raiz, /^Lançar$/).click();
    await espera();

    // Fornecedor e categoria escolhidos no diálogo.
    const sel = (id: string) => m!.raiz.querySelector(id) as HTMLSelectElement;
    sel('#lanc-forn').value = '8'; sel('#lanc-forn').dispatchEvent(new Event('change'));
    sel('#lanc-cat').value = '21'; sel('#lanc-cat').dispatchEvent(new Event('change'));
    await espera();

    const lancar = botao(m.raiz.querySelector('[role=dialog]') as HTMLElement, /^Lançar$/);
    lancar.click();
    lancar.click();
    await espera();
    expect(api.lancarMovimento).toHaveBeenCalledTimes(1);
    expect(api.lancarMovimento).toHaveBeenCalledWith(1, expect.objectContaining({ fornecedor_id: 8, categoria_id: 21 }));

    liberar({ movimento: mov(1, { status: 'lancado' }), pagamento_tipo: 'despesa', pagamento_id: 3 });
    await espera();
    expect(m.toasts.at(-1)).toEqual({ msg: 'Movimento lançado.', type: 'success' });
    expect(m.raiz.querySelector('[role=dialog]')).toBeNull();
  });

  it('despesa sem fornecedor ou categoria não chega à API', async () => {
    m = montar();
    await espera();
    botao(m.raiz, /^Lançar$/).click();
    await espera();
    botao(m.raiz.querySelector('[role=dialog]') as HTMLElement, /^Lançar$/).click();
    await espera();
    expect(api.lancarMovimento).not.toHaveBeenCalled();
    expect(m.raiz.querySelector('[role=dialog]')?.textContent).toContain('Informe o fornecedor');
  });

  it('concilia com uma sugestão', async () => {
    api.sugestoesConciliacao.mockResolvedValue({
      movimento: mov(1), janela_dias: 3, em_aberto: [], pares_transferencia: [], confianca: 'media',
      pagamentos: [{ tipo: 'despesa', id: 9, data: '2026-10-05', valor_centavos: -4500, descricao: 'Tarifa mensal', contraparte: null, dias: 0, nome_confere: true, pontos: 90 }],
    });
    api.conciliarMovimento.mockResolvedValue(mov(1, { status: 'conciliado' }));
    m = montar();
    await espera();
    botao(m.raiz, /^Conciliar$/).click();
    await espera();
    expect(m.raiz.textContent).toContain('Tarifa mensal');
    botao(m.raiz.querySelector('[role=dialog]') as HTMLElement, /^Conciliar$/).click();
    await espera();
    expect(api.conciliarMovimento).toHaveBeenCalledWith(1, 'despesa', 9);
  });

  it('lote: lança só quem tem regra completa e mostra o resultado por item', async () => {
    api.listarMovimentos.mockResolvedValue({ itens: [mov(1), mov(2)], total: 2, page: 1 });
    api.sugestaoLancamento
      .mockResolvedValueOnce({ tipo: 'despesa', pode_lancar: true, descricao_sugerida: 'A', contrapartes: [], regra: { fornecedor_id: 8, cliente_id: null, categoria_id: 21, rateio: [] } })
      .mockResolvedValueOnce({ tipo: 'despesa', pode_lancar: true, descricao_sugerida: 'B', contrapartes: [], regra: null });
    api.lancarLote.mockResolvedValue({ total: 1, lancados: 1, rejeitados: 0, itens: [{ movimento_id: 1, ok: true, pagamento_tipo: 'despesa', pagamento_id: 5, motivo: null }] });
    m = montar();
    await espera();
    m.raiz.querySelectorAll<HTMLInputElement>('tbody input[type=checkbox]').forEach((c) => c.dispatchEvent(new Event('change')));
    await espera();
    botao(m.raiz, /Lançar selecionados/).click();
    await espera();
    expect(api.lancarLote).toHaveBeenCalledWith([{ movimento_id: 1, descricao: 'A', fornecedor_id: 8, categoria_id: 21 }]);
    const dialogo = m.raiz.querySelector('[role=dialog]')!.textContent!;
    expect(dialogo).toContain('1 lançados');
    expect(dialogo).toContain('Nenhuma regra casa');
  });

  it('conciliação em lote só vai à API depois da confirmação', async () => {
    api.previaConciliacaoLote.mockResolvedValue([{ movimento: mov(1), pagamento_tipo: 'despesa', pagamento_id: 9, pagamento: { descricao: 'Tarifa mensal' } }]);
    api.conciliarLote.mockResolvedValue({ conciliados: [1], rejeitados: [] });
    m = montar();
    await espera();
    botao(m.raiz, /Conciliar sugestões seguras/).click();
    await espera();
    expect(api.conciliarLote).not.toHaveBeenCalled();
    botao(m.raiz, /Confirmar conciliação/).click();
    await espera();
    expect(api.conciliarLote).toHaveBeenCalledWith([{ movimento_id: 1, pagamento_tipo: 'despesa', pagamento_id: 9 }]);
    expect(m.raiz.querySelector('[role=dialog]')?.textContent).toContain('1 conciliados');
  });
});
