import { describe, it, expect, beforeEach, vi } from 'vitest';

vi.mock('../src/services/auth', () => ({ getCurrentUser: vi.fn() }));

const mockApiFetch = vi.fn();
vi.mock('../src/services/api', () => ({
  apiFetch: (...args: unknown[]) => mockApiFetch(...args),
}));

import { aplicarAcesso, limparAcesso } from '../src/composables/useAcesso';
import { telaLiberada } from '../src/utils/acessoTelas';
import {
  listarTransacoesIntegracao,
  listarContasIntegracao,
  importarTransacoesFinanceiras,
  listarDicionarioCategorias,
  salvarDicionarioCategorias,
  listarContas,
  listarModelosPlano,
  importarPlanoPadrao,
  lancarAbertura,
  importarPlanoArquivo,
  mensagemErroImportacao,
  listarArvoreContas,
  criarConta,
  listarLancamentos,
  criarLancamento,
  estornarLancamento,
  obterBalancete,
  obterLivroRazao,
  obterLivroDiario,
  obterDreContabil,
  obterBalancoPatrimonial,
  listarExerciciosFiscais,
  fecharPeriodo,
  reabrirPeriodo,
} from '../src/services/contabil';

describe('Módulo Contábil - Acesso a Telas', () => {
  beforeEach(() => {
    limparAcesso();
  });

  const telasContabeis = [
    'contabil',
    'contabil-importar',
    'contabil-lancamentos',
    'contabil-plano-contas',
    'contabil-relatorios',
    'contabil-livros',
    'contabil-periodos',
  ];

  it('bloqueia todas as telas contábeis quando o módulo não está ativo', () => {
    aplicarAcesso({ permissoes: ['contabil.access'], modulos_ativos: ['financeiro'] });

    for (const tela of telasContabeis) {
      expect(telaLiberada(tela)).toBe(false);
    }
  });

  it('bloqueia todas as telas contábeis quando o usuário não possui permissão contabil.access', () => {
    aplicarAcesso({ permissoes: ['financeiro.access'], modulos_ativos: ['contabil'] });

    for (const tela of telasContabeis) {
      expect(telaLiberada(tela)).toBe(false);
    }
  });

  it('libera todas as telas contábeis quando o módulo está ativo e o usuário tem a permissão', () => {
    aplicarAcesso({ permissoes: ['contabil.access'], modulos_ativos: ['contabil'] });

    for (const tela of telasContabeis) {
      expect(telaLiberada(tela)).toBe(true);
    }
  });
});

describe('Módulo Contábil - Serviços de API', () => {
  beforeEach(() => {
    mockApiFetch.mockReset();
  });

  it('listarContas consulta contabil/accounts com entity_id', async () => {
    mockApiFetch.mockResolvedValueOnce([
      { id: 1, code: '1.1.1.01', name: 'Caixa Geral', type: 'ASSET' },
    ]);

    const contas = await listarContas(1);

    expect(mockApiFetch).toHaveBeenCalledWith('contabil/accounts?entity_id=1');
    expect(contas).toHaveLength(1);
    expect(contas[0].name).toBe('Caixa Geral');
  });

  it('listarArvoreContas consulta a árvore hierárquica', async () => {
    mockApiFetch.mockResolvedValueOnce({
      data: [{ id: 1, code: '1', name: 'Ativo', children: [] }],
    });

    const arvore = await listarArvoreContas(1);
    expect(mockApiFetch).toHaveBeenCalledWith('contabil/accounts/tree?entity_id=1');
    expect(arvore).toHaveLength(1);
    expect(arvore[0].code).toBe('1');
  });

  it('criarConta envia payload com entity_id via POST', async () => {
    mockApiFetch.mockResolvedValueOnce({
      data: { id: 42, code: '1.1.1.02', name: 'Banco Inter', type: 'ASSET' },
    });

    const res = await criarConta(
      { code: '1.1.1.02', name: 'Banco Inter', type: 'ASSET' },
      1
    );

    expect(mockApiFetch).toHaveBeenCalledWith('contabil/accounts', {
      method: 'POST',
      body: {
        code: '1.1.1.02',
        name: 'Banco Inter',
        type: 'ASSET',
        entity_id: 1,
      },
    });
    expect(res.id).toBe(42);
  });

  it('listarTransacoesIntegracao envia query params e formata resposta com kpis', async () => {
    mockApiFetch.mockResolvedValueOnce({
      success: true,
      data: [
        {
          uid: 'rec-1',
          tipo: 'receita',
          source_type: 'fin_pgto_receitas',
          source_id: 1,
          data: '2026-01-10',
          descricao: 'Honorários advocatícios',
          valor: 5000,
          status_contabil: 'pendente',
        },
      ],
      kpis: {
        total_geral: 1,
        total_pendentes: 1,
        total_importados: 0,
        valor_pendente: 5000,
        valor_importado: 0,
      },
    });

    const resultado = await listarTransacoesIntegracao({
      data_inicio: '2026-01-01',
      data_fim: '2026-01-31',
      tipo: 'receita',
      status: 'pendente',
    });

    expect(mockApiFetch).toHaveBeenCalledWith(
      'contabil/integracao/transacoes?data_inicio=2026-01-01&data_fim=2026-01-31&tipo=receita&status=pendente'
    );
    expect(resultado.transacoes).toHaveLength(1);
    expect(resultado.kpis.total_pendentes).toBe(1);
  });

  it('listarContasIntegracao consulta rota de mapeamento contábil', async () => {
    mockApiFetch.mockResolvedValueOnce({
      success: true,
      data: [{ id: 10, code: '1.1.1.01', name: 'Banco Caixa' }],
    });

    const contas = await listarContasIntegracao(1);
    expect(mockApiFetch).toHaveBeenCalledWith('contabil/integracao/contas?entity_id=1');
    expect(contas).toHaveLength(1);
  });

  it('importarTransacoesFinanceiras envia lote para conciliação', async () => {
    mockApiFetch.mockResolvedValueOnce({
      success: true,
      resultado: { sucesso: 2, ignorados: 0, erros: [] },
      message: '2 lançamentos importados com sucesso.',
    });

    const res = await importarTransacoesFinanceiras(
      [
        { uid: 'rec-1', source_type: 'fin_pgto_receitas', source_id: 1, debito_id: 10, credito_id: 20 },
      ],
      true,
      1
    );

    expect(mockApiFetch).toHaveBeenCalledWith('contabil/integracao/importar', {
      method: 'POST',
      body: {
        itens: [
          { uid: 'rec-1', source_type: 'fin_pgto_receitas', source_id: 1, debito_id: 10, credito_id: 20 },
        ],
        postar: true,
        entity_id: 1,
      },
    });
    expect(res.sucesso).toBe(2);
  });

  it('listarDicionarioCategorias e salvarDicionarioCategorias usam a rota do dicionário', async () => {
    mockApiFetch.mockResolvedValueOnce({ success: true, data: [{ categoria_id: 3, tipo: 'receita', account_id: 24 }] });
    expect(await listarDicionarioCategorias(1)).toHaveLength(1);
    expect(mockApiFetch).toHaveBeenCalledWith('contabil/integracao/categorias-contas?entity_id=1');

    mockApiFetch.mockResolvedValueOnce({ success: true, data: [] });
    await salvarDicionarioCategorias([{ categoria_id: 3, categoria: 'Êxito', tipo: 'receita', account_id: null }], 1);
    expect(mockApiFetch).toHaveBeenCalledWith('contabil/integracao/categorias-contas', {
      method: 'POST',
      body: { itens: [{ categoria_id: 3, tipo: 'receita', account_id: null }], entity_id: 1 },
    });
  });

  it('listarLancamentos e criarLancamento gerenciam partidas dobradas', async () => {
    mockApiFetch.mockResolvedValueOnce({
      data: [{ id: 1, number: 101, accrual_date: '2026-01-10', status: 'POSTED' }],
    });

    const lista = await listarLancamentos({ date_from: '2026-01-01', date_to: '2026-01-31' });
    expect(mockApiFetch).toHaveBeenCalledWith(
      'contabil/entries?from=2026-01-01&to=2026-01-31'
    );
    expect(lista).toHaveLength(1);

    mockApiFetch.mockResolvedValueOnce({
      data: { id: 2, number: 102, description: 'Serviços prestados' },
    });

    const novo = await criarLancamento(
      {
        accrual_date: '2026-01-15',
        description: 'Serviços prestados',
        lines: [
          { account_id: 1, side: 'D', amount: 1500 },
          { account_id: 2, side: 'C', amount: 1500 },
        ],
      },
      1
    );

    expect(mockApiFetch).toHaveBeenCalledWith('contabil/entries', {
      method: 'POST',
      body: {
        accrual_date: '2026-01-15',
        description: 'Serviços prestados',
        lines: [
          { account_id: 1, side: 'D', amount: 1500 },
          { account_id: 2, side: 'C', amount: 1500 },
        ],
        entity_id: 1,
      },
    });
    expect(novo.id).toBe(2);
  });

  it('estornarLancamento envia motivo do estorno', async () => {
    mockApiFetch.mockResolvedValueOnce({});

    await estornarLancamento(42, 'Lançamento duplicado no financeiro', '2026-01-20');

    expect(mockApiFetch).toHaveBeenCalledWith('contabil/entries/42/reverse', {
      method: 'POST',
      body: {
        reason: 'Lançamento duplicado no financeiro',
        date: '2026-01-20',
      },
    });
  });

  it('relatórios e demonstrações consultam as rotas adequadas', async () => {
    mockApiFetch.mockResolvedValue({ data: [] });

    await obterBalancete('2026-01-01', '2026-12-31', 1);
    expect(mockApiFetch).toHaveBeenCalledWith(
      'contabil/reports/trial-balance?from=2026-01-01&to=2026-12-31&entity_id=1'
    );

    await obterLivroRazao(5, '2026-01-01', '2026-12-31', 1);
    expect(mockApiFetch).toHaveBeenCalledWith(
      'contabil/reports/ledger?account_id=5&from=2026-01-01&to=2026-12-31&entity_id=1'
    );

    await obterLivroDiario('2026-01-01', '2026-12-31', 1);
    expect(mockApiFetch).toHaveBeenCalledWith(
      'contabil/reports/journal?from=2026-01-01&to=2026-12-31&entity_id=1'
    );

    await obterDreContabil('2026-01-01', '2026-12-31', 1);
    expect(mockApiFetch).toHaveBeenCalledWith(
      'contabil/reports/income-statement?from=2026-01-01&to=2026-12-31&entity_id=1'
    );

    await obterBalancoPatrimonial('2026-12-31', 1);
    expect(mockApiFetch).toHaveBeenCalledWith(
      'contabil/reports/balance-sheet?as_of=2026-12-31&entity_id=1'
    );
  });

  it('listarExerciciosFiscais, fecharPeriodo e reabrirPeriodo gerenciam o fechamento', async () => {
    mockApiFetch.mockResolvedValueOnce([
      { id: 1, label: 'Exercício 2026', status: 'OPEN', periods: [] },
    ]);

    const anos = await listarExerciciosFiscais(1);
    expect(mockApiFetch).toHaveBeenCalledWith('contabil/fiscal-years?entity_id=1');
    expect(anos).toHaveLength(1);

    mockApiFetch.mockResolvedValueOnce({});
    await fecharPeriodo(10);
    expect(mockApiFetch).toHaveBeenCalledWith('contabil/periods/10/close', { method: 'POST' });

    mockApiFetch.mockResolvedValueOnce({});
    await reabrirPeriodo(10, 'Ajuste de auditoria contábil');
    expect(mockApiFetch).toHaveBeenCalledWith('contabil/periods/10/reopen', {
      method: 'POST',
      body: { reason: 'Ajuste de auditoria contábil' },
    });
  });
});

describe('Módulo Contábil - Importação do plano de contas', () => {
  beforeEach(() => {
    mockApiFetch.mockReset();
  });

  it('listarModelosPlano consulta os modelos do pacote', async () => {
    mockApiFetch.mockResolvedValueOnce({ data: [{ id: 'generic', rotulo: 'Genérico' }] });

    const modelos = await listarModelosPlano();

    expect(mockApiFetch).toHaveBeenCalledWith('contabil/integracao/planos-modelo');
    expect(modelos).toEqual([{ id: 'generic', rotulo: 'Genérico' }]);
  });

  it('lancarAbertura envia os saldos e informa se gravou agora', async () => {
    mockApiFetch.mockResolvedValueOnce({ resultado: { created: true, entry: { id: 7 } }, message: 'ok' });

    const saldos = [
      { code: '1.1.02', side: 'D' as const, amount: 10 },
      { code: '3.1.01', side: 'C' as const, amount: 10 },
    ];
    const r = await lancarAbertura({ data: '2026-01-01', saldos }, 1);

    expect(mockApiFetch).toHaveBeenCalledWith('contabil/integracao/abertura', {
      method: 'POST',
      body: { data: '2026-01-01', saldos, postar: true, entity_id: 1 },
    });
    expect(r.created).toBe(true);
    expect(r.message).toBe('ok');
  });

  it('importarPlanoPadrao envia o modelo e devolve o resultado', async () => {
    mockApiFetch.mockResolvedValueOnce({ resultado: { created: 40, skipped: 2 } });

    const r = await importarPlanoPadrao('professional-services', 1);

    expect(mockApiFetch).toHaveBeenCalledWith('contabil/integracao/plano-padrao', {
      method: 'POST',
      body: { model: 'professional-services', entity_id: 1 },
    });
    expect(r).toEqual({ created: 40, skipped: 2 });
  });

  it('importarPlanoArquivo envia o CSV cru com o content-type correto', async () => {
    mockApiFetch.mockResolvedValueOnce({ data: { created: 2, skipped: 0 } });

    const r = await importarPlanoArquivo('code,name,type\n1,Ativo,asset\n', 'csv', 1);

    expect(mockApiFetch).toHaveBeenCalledWith('contabil/accounts/import?entity_id=1&format=csv&skip_existing=1', {
      method: 'POST',
      body: 'code,name,type\n1,Ativo,asset\n',
      headers: { 'Content-Type': 'text/csv' },
    });
    expect(r.created).toBe(2);
  });

  it('importarPlanoArquivo envia JSON como application/json', async () => {
    mockApiFetch.mockResolvedValueOnce({ data: { created: 1, skipped: 0 } });

    await importarPlanoArquivo('{"accounts":[]}', 'json', 3);

    expect(mockApiFetch).toHaveBeenCalledWith('contabil/accounts/import?entity_id=3&format=json&skip_existing=1', {
      method: 'POST',
      body: '{"accounts":[]}',
      headers: { 'Content-Type': 'application/json' },
    });
  });

  it('mensagemErroImportacao lista as linhas rejeitadas pela API', () => {
    const erro = {
      message: '[object Object]',
      data: { error: { message: 'Importação recusada.', details: [{ row: 2, message: 'tipo inválido' }, { row: 5, message: 'código repetido' }] } },
    };

    expect(mensagemErroImportacao(erro)).toBe('Importação recusada. linha 2: tipo inválido; linha 5: código repetido');
  });

  it('mensagemErroImportacao cai na mensagem do erro quando não há detalhes', () => {
    expect(mensagemErroImportacao(new Error('Sem rede'))).toBe('Sem rede');
    expect(mensagemErroImportacao({})).toBe('Falha ao importar o plano de contas.');
  });

  it('Livro Diário junta todas as páginas de data.entries seguindo next_cursor', async () => {
    mockApiFetch.mockReset();
    mockApiFetch
      .mockResolvedValueOnce({ data: { entries: [{ id: 1 }, { id: 2 }], next_cursor: 'abc', total: 300 } })
      .mockResolvedValueOnce({ data: { entries: [{ id: 3 }], next_cursor: null, total: 300 } });

    const diario = await obterLivroDiario('2026-01-01', '2026-12-31', 1);

    expect(diario.entries.map((e) => e.id)).toEqual([1, 2, 3]);
    expect(diario.total).toBe(300);
    expect(mockApiFetch).toHaveBeenCalledTimes(2);
    expect(mockApiFetch).toHaveBeenLastCalledWith(
      'contabil/reports/journal?from=2026-01-01&to=2026-12-31&entity_id=1&cursor=abc'
    );
  });

  it('Livro Diário não entra em laço quando a API repete o cursor', async () => {
    mockApiFetch.mockReset();
    mockApiFetch.mockResolvedValue({ data: { entries: [{ id: 1 }], next_cursor: 'igual' } });

    const diario = await obterLivroDiario();

    expect(diario.entries).toHaveLength(2);
    expect(mockApiFetch).toHaveBeenCalledTimes(2);
  });

  it('Livro Razão por conta junta todas as páginas de rows seguindo next_cursor', async () => {
    mockApiFetch.mockReset();
    mockApiFetch
      .mockResolvedValueOnce({
        data: {
          sections: [
            {
              account: { id: 5, code: '1.1.1.01', name: 'Caixa Geral', nature: 'DEBIT' },
              opening: 10000,
              rows: [{ entry_id: 1, entry_number: 1, date: '2026-01-05', description: 'Recebimento', debit: 5000, credit: 0, balance: 15000, account_id: 5, account_code: '1.1.1.01' }],
              debits: 5000,
              credits: 2000,
              closing: 13000,
            },
          ],
          next_cursor: 'cur-2',
        },
      })
      .mockResolvedValueOnce({
        data: {
          sections: [
            {
              account: { id: 5, code: '1.1.1.01', name: 'Caixa Geral', nature: 'DEBIT' },
              opening: 10000,
              rows: [{ entry_id: 2, entry_number: 2, date: '2026-01-10', description: 'Pagamento', debit: 0, credit: 2000, balance: 13000, account_id: 5, account_code: '1.1.1.01' }],
              debits: 5000,
              credits: 2000,
              closing: 13000,
            },
          ],
          next_cursor: null,
        },
      });

    const razao = await obterLivroRazao(5, '2026-01-01', '2026-12-31', 1);

    expect(razao.sections).toHaveLength(1);
    expect(razao.sections[0].rows.map((r) => r.entry_id)).toEqual([1, 2]);
    expect(razao.sections[0].closing).toBe(13000);
    expect(mockApiFetch).toHaveBeenCalledTimes(2);
    expect(mockApiFetch).toHaveBeenLastCalledWith(
      'contabil/reports/ledger?account_id=5&from=2026-01-01&to=2026-12-31&entity_id=1&cursor=cur-2'
    );
  });

  it('Livro Razão de todas as contas consulta o intervalo completo de códigos quando contaId não é informado', async () => {
    mockApiFetch.mockReset();
    mockApiFetch.mockResolvedValueOnce({
      data: {
        sections: [
          {
            account: { id: 5, code: '1.1.1.01', name: 'Caixa Geral', nature: 'DEBIT' },
            opening: 0,
            rows: [],
            debits: 0,
            credits: 0,
            closing: 0,
          },
        ],
        next_cursor: null,
      },
    });

    const razao = await obterLivroRazao(null, '2026-01-01', '2026-12-31', 1);

    expect(razao.sections).toHaveLength(1);
    expect(mockApiFetch).toHaveBeenCalledWith(
      'contabil/reports/ledger?from_code=0&to_code=999999999&from=2026-01-01&to=2026-12-31&entity_id=1'
    );
  });
});
