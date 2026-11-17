// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, nextTick, h, ref } from 'vue';

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn(), debounce: (fn: (...a: any[]) => void) => fn };
});

import { apiFetch } from '../src/services/api';
import { aplicarAcesso, limparAcesso } from '../src/composables/useAcesso';
import VinculoJuridicoCampo from '../src/components/Financeiro/VinculoJuridicoCampo.vue';
import ModalCadastroRapidoJuridico from '../src/components/Financeiro/ModalCadastroRapidoJuridico.vue';
import RentabilidadeCard from '../src/components/widgets/RentabilidadeCard.vue';
import { obterBalancete, obterDreContabil } from '../src/services/contabil';

const fetchMock = apiFetch as unknown as ReturnType<typeof vi.fn>;
const espera = async () => { for (let i = 0; i < 8; i++) { await nextTick(); await Promise.resolve(); } };

function montar(componente: any, props: Record<string, any> = {}) {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const app = createApp(componente, props);
  app.mount(raiz);
  return { raiz, desmontar: () => { app.unmount(); raiz.remove(); } };
}

function digitar(input: HTMLInputElement, valor: string) {
  input.value = valor;
  input.dispatchEvent(new Event('input'));
}

const botao = (raiz: HTMLElement, texto: RegExp) =>
  Array.from(raiz.querySelectorAll('button')).find((b) => texto.test(b.textContent?.trim() ?? '')) as HTMLButtonElement | undefined;

describe('VinculoJuridicoCampo', () => {
  let m: ReturnType<typeof montar> | null = null;
  beforeEach(() => { fetchMock.mockReset(); limparAcesso(); });
  afterEach(() => { m?.desmontar(); m = null; });

  function montarCampo(inicial: Record<string, any> = {}) {
    const estado = { casoId: ref<number | null>(inicial.casoId ?? null), processoId: ref<number | null>(null), clientes: [] as any[] };
    const Pai = {
      setup() {
        return () => h(VinculoJuridicoCampo, {
          casoId: estado.casoId.value,
          processoId: estado.processoId.value,
          clienteId: inicial.clienteId ?? null,
          'onUpdate:casoId': (v: number | null) => { estado.casoId.value = v; },
          'onUpdate:processoId': (v: number | null) => { estado.processoId.value = v; },
          onCliente: (c: any) => estado.clientes.push(c),
        });
      },
    };
    m = montar(Pai);
    return estado;
  }

  it('busca casos e processos e vincula o escolhido, sugerindo o cliente', async () => {
    fetchMock.mockImplementation(async (url: string) => {
      if (url.startsWith('financeiro/vinculos/buscar')) {
        return { success: true, data: [
          { tipo: 'caso', id: 4, rotulo: 'Consultoria XPTO', cliente_id: 9, cliente_nome: 'XPTO Ltda' },
          { tipo: 'processo', id: 7, rotulo: '0000001-00.2026.8.13.0001 - Ação', cliente_id: 9, cliente_nome: 'XPTO Ltda' },
        ] };
      }
      return { success: true, data: { processo: null, caso: { id: 4, nome: 'Consultoria XPTO' }, cliente: { id: 9, nome: 'XPTO Ltda' }, ramo_direito: null } };
    });

    const estado = montarCampo();
    await espera();
    digitar(m!.raiz.querySelector('input') as HTMLInputElement, 'xp');
    await espera();

    expect(fetchMock).toHaveBeenCalledWith('financeiro/vinculos/buscar?q=xp&limite=10');
    const itens = m!.raiz.querySelectorAll('.autocomplete-list li');
    expect(itens.length).toBe(2);

    (itens[0] as HTMLElement).click();
    await espera();

    expect(estado.casoId.value).toBe(4);
    expect(estado.clientes).toEqual([{ id: 9, nome: 'XPTO Ltda' }]);
    expect(fetchMock).toHaveBeenCalledWith('financeiro/vinculos/resolver?caso_id=4');
    expect(m!.raiz.textContent).toContain('Centro de custo');
    expect(m!.raiz.textContent).toContain('XPTO Ltda');
  });

  it('não sugere cliente quando o lançamento já tem um', async () => {
    fetchMock.mockResolvedValue({ success: true, data: [{ tipo: 'processo', id: 7, rotulo: 'Proc', cliente_id: 9, cliente_nome: 'X' }] });
    const estado = montarCampo({ clienteId: 3 });
    digitar(m!.raiz.querySelector('input') as HTMLInputElement, 'pr');
    await espera();
    (m!.raiz.querySelector('.autocomplete-list li') as HTMLElement).click();
    await espera();

    expect(estado.processoId.value).toBe(7);
    expect(estado.clientes).toEqual([]);
  });

  it('remove o vínculo pelo chip', async () => {
    fetchMock.mockResolvedValue({ success: true, data: null });
    const estado = montarCampo({ casoId: 4 });
    await espera();
    (m!.raiz.querySelector('.vinculo-remover') as HTMLButtonElement).click();
    await espera();
    expect(estado.casoId.value).toBeNull();
  });

  it('esconde o cadastro rápido sem permissão de criar caso ou processo', async () => {
    fetchMock.mockResolvedValue({ success: true, data: [] });
    aplicarAcesso({ permissoes: ['financeiro.access'], modulos_ativos: null } as any);
    montarCampo();
    await espera();
    expect(botao(m!.raiz, /Cadastrar/)).toBeUndefined();

    m!.desmontar();
    aplicarAcesso({ permissoes: ['financeiro.access', 'casos.create'], modulos_ativos: null } as any);
    montarCampo();
    await espera();
    expect(botao(m!.raiz, /Cadastrar/)).toBeDefined();
  });
});

describe('ModalCadastroRapidoJuridico', () => {
  let m: ReturnType<typeof montar> | null = null;
  beforeEach(() => { fetchMock.mockReset(); limparAcesso(); });
  afterEach(() => { m?.desmontar(); m = null; });

  it('cadastra um caso com o cliente do lançamento e emite o item criado', async () => {
    const criados: any[] = [];
    fetchMock.mockImplementation(async (url: string) => {
      if (url === 'processos/ramos') return [{ id: 2, nome: 'Trabalhista', filhos: [] }];
      if (url === 'casos/salvar') return { success: true, data: { id: 31 } };
      throw new Error('inesperado ' + url);
    });
    aplicarAcesso({ permissoes: ['casos.create'], modulos_ativos: null } as any);
    m = montar(ModalCadastroRapidoJuridico, { clienteId: 9, clienteNome: 'XPTO Ltda', onCriado: (o: any) => criados.push(o) });
    await espera();

    expect(m.raiz.querySelector('#cr-cnj')).toBeNull();
    digitar(m.raiz.querySelector('#cr-titulo') as HTMLInputElement, 'Consultoria nova');
    botao(m.raiz, /Cadastrar caso/)!.click();
    await espera();

    const chamada = fetchMock.mock.calls.find(([u]) => u === 'casos/salvar')!;
    expect(chamada[1].body).toMatchObject({ titulo: 'Consultoria nova', cliente_id: 9, status: 'Ativo' });
    expect(criados).toEqual([expect.objectContaining({ tipo: 'caso', id: 31, rotulo: 'Consultoria nova', cliente_id: 9 })]);
  });

  it('exige o número CNJ completo para processo', async () => {
    fetchMock.mockResolvedValue([]);
    aplicarAcesso({ permissoes: ['processos.create'], modulos_ativos: null } as any);
    m = montar(ModalCadastroRapidoJuridico, {});
    await espera();

    digitar(m.raiz.querySelector('#cr-titulo') as HTMLInputElement, 'Ação de cobrança');
    digitar(m.raiz.querySelector('#cr-cnj') as HTMLInputElement, '123');
    botao(m.raiz, /Cadastrar processo/)!.click();
    await espera();

    expect(m.raiz.textContent).toContain('20 dígitos');
    expect(fetchMock.mock.calls.some(([u]) => u === 'processos/salvar')).toBe(false);
  });
});

describe('RentabilidadeCard', () => {
  let m: ReturnType<typeof montar> | null = null;
  beforeEach(() => { fetchMock.mockReset(); });
  afterEach(() => { m?.desmontar(); m = null; });

  it('mostra resultado e margem do processo no ano', async () => {
    fetchMock.mockResolvedValue({
      grupos: [{ id: 7, nome: 'Proc', receita: 1000, despesa: 250, resultado: 750, margem: 0.75, qtd_lancamentos: 2 }],
      lancamentos: [
        { tipo: 'receita', titulo_id: 1, data: '2026-03-01', descricao: 'Honorários', valor: 1000 },
        { tipo: 'despesa', titulo_id: 2, data: '2026-02-01', descricao: 'Custas', valor: 250 },
      ],
    });
    m = montar(RentabilidadeCard, { tipo: 'processo', id: 7 });
    await espera();

    const url = fetchMock.mock.calls[0][0] as string;
    expect(url).toContain('legal-ops/rentabilidade?agrupar=processo&id=7');
    expect(url).toContain(`inicio=${new Date().getFullYear()}-01-01`);
    expect(m.raiz.querySelector('select.form-control.form-select.rent-periodo')).not.toBeNull();
    expect(m.raiz.textContent).toContain('75%');
    expect(m.raiz.textContent).toContain('2 lançamentos');
  });

  it('explica quando não há lançamentos vinculados', async () => {
    fetchMock.mockResolvedValue({ grupos: [{ id: 4, nome: 'Caso', receita: 0, despesa: 0, resultado: 0, margem: null, qtd_lancamentos: 0 }], lancamentos: [] });
    m = montar(RentabilidadeCard, { tipo: 'caso', id: 4 });
    await espera();
    expect(m.raiz.textContent).toContain('Nenhum recebimento ou pagamento vinculado');
    expect(m.raiz.textContent).toContain('—');
  });
});

describe('relatórios contábeis por centro de custo', () => {
  beforeEach(() => { fetchMock.mockReset(); fetchMock.mockResolvedValue({ data: {} }); });

  it('envia dimension_type e dimension_id ao balancete e à DRE', async () => {
    await obterBalancete('2026-01-01', '2026-12-31', 1, { tipo: 'processo', id: 7 });
    await obterDreContabil('2026-01-01', '2026-12-31', 1, { tipo: 'ramo_direito' });
    await obterBalancete('2026-01-01', '2026-12-31');

    expect(fetchMock.mock.calls[0][0]).toContain('dimension_type=processo&dimension_id=7');
    expect(fetchMock.mock.calls[1][0]).toContain('dimension_type=ramo_direito');
    expect(fetchMock.mock.calls[1][0]).not.toContain('dimension_id');
    expect(fetchMock.mock.calls[2][0]).not.toContain('dimension_type');
  });
});
