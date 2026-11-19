// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { effectScope, ref } from 'vue';

const apiFetch = vi.hoisted(() => vi.fn());
vi.mock('../src/services/api', () => ({ apiFetch }));

import { useClientAutocomplete } from '../src/composables/useClientAutocomplete';

function criar(...args: Parameters<typeof useClientAutocomplete>) {
  const escopo = effectScope();
  const ac = escopo.run(() => useClientAutocomplete(...args))!;
  return { ac, parar: () => escopo.stop() };
}

describe('useClientAutocomplete', () => {
  let parar: (() => void) | null = null;
  beforeEach(() => { vi.useFakeTimers(); apiFetch.mockReset(); });
  afterEach(() => { parar?.(); parar = null; vi.useRealTimers(); });

  it('padrão (clientes/buscar): mesmo formato de antes e preenche o formulário', async () => {
    apiFetch.mockResolvedValue({ success: true, data: [{ id_cliente: 7, nome: 'Maria da Silva', cpf_cnpj: '123.456.789-09' }] });
    const form = { id_cliente: null as number | string | null };
    const { ac, parar: p } = criar(form); parar = p;

    ac.clientSearch.value = 'Mar';
    ac.onClientInput();
    await vi.advanceTimersByTimeAsync(400);

    expect(apiFetch).toHaveBeenCalledWith('clientes/buscar?term=Mar');
    expect(ac.clientResults.value).toEqual([{ id: 7, text: 'Maria da Silva (123.456.789-09)', nome: 'Maria da Silva' }]);
    expect(ac.showClients.value).toBe(true);

    ac.selectClient(ac.clientResults.value[0]);
    expect(form.id_cliente).toBe(7);
    expect(ac.clientSearch.value).toBe('Maria da Silva (123.456.789-09)');
    expect(ac.showClients.value).toBe(false);

    ac.clearClient();
    expect(form.id_cliente).toBe('');
    expect(ac.clientSearch.value).toBe('');
  });

  it('não usa cache: um cliente novo aparece na busca seguinte pelo mesmo termo', async () => {
    apiFetch
      .mockResolvedValueOnce({ success: true, data: [] })
      .mockResolvedValueOnce({ success: true, data: [{ id_cliente: 9, nome: 'Novo' }] });
    const { ac, parar: p } = criar(); parar = p;

    ac.clientSearch.value = 'Novo';
    ac.onClientInput();
    await vi.advanceTimersByTimeAsync(400);
    ac.onClientInput();
    await vi.advanceTimersByTimeAsync(400);

    expect(apiFetch).toHaveBeenCalledTimes(2);
    expect(ac.clientResults.value[0]?.nome).toBe('Novo');
  });

  it('termo curto não busca', async () => {
    const { ac, parar: p } = criar(); parar = p;
    ac.clientSearch.value = 'M';
    ac.onClientInput();
    await vi.advanceTimersByTimeAsync(400);
    expect(apiFetch).not.toHaveBeenCalled();
    expect(ac.termoValido.value).toBe(false);
  });

  it('descarta resposta antiga que chega depois da nova', async () => {
    let resolverAntiga: (v: any) => void = () => {};
    apiFetch
      .mockImplementationOnce(() => new Promise((r) => { resolverAntiga = r; }))
      .mockResolvedValueOnce({ success: true, data: [{ id_cliente: 2, nome: 'Termo Novo' }] });
    const { ac, parar: p } = criar(); parar = p;

    ac.clientSearch.value = 'Antigo';
    ac.onClientInput();
    await vi.advanceTimersByTimeAsync(400);
    ac.clientSearch.value = 'Termo Novo';
    ac.onClientInput();
    await vi.advanceTimersByTimeAsync(400);
    resolverAntiga({ success: true, data: [{ id_cliente: 1, nome: 'Antigo' }] });
    await vi.advanceTimersByTimeAsync(0);

    expect(ac.clientResults.value.map((r) => r.nome)).toEqual(['Termo Novo']);
  });

  it('teclado: setas navegam e Enter devolve o cliente ativo', async () => {
    apiFetch.mockResolvedValue({ success: true, data: [{ id_cliente: 1, nome: 'A' }, { id_cliente: 2, nome: 'B' }] });
    const { ac, parar: p } = criar(); parar = p;
    ac.clientSearch.value = 'ab';
    ac.onClientInput();
    await vi.advanceTimersByTimeAsync(400);

    const tecla = (key: string) => ({ key, preventDefault: vi.fn() }) as unknown as KeyboardEvent;
    ac.onClientKeydown(tecla('ArrowDown'));
    ac.onClientKeydown(tecla('ArrowDown'));
    expect(ac.activeIndex.value).toBe(1);
    expect(ac.onClientKeydown(tecla('Enter'))?.id).toBe(2);
    ac.onClientKeydown(tecla('Escape'));
    expect(ac.showClients.value).toBe(false);
  });

  it('clique fora usa o contêiner da instância', () => {
    const dentro = document.createElement('div');
    const fora = document.createElement('div');
    const container = ref<HTMLElement | null>(dentro);
    const { ac, parar: p } = criar(undefined, { container }); parar = p;

    ac.showClients.value = true;
    ac.handleClickOutsideClient({ target: dentro } as unknown as MouseEvent);
    expect(ac.showClients.value).toBe(true);
    ac.handleClickOutsideClient({ target: fora } as unknown as MouseEvent);
    expect(ac.showClients.value).toBe(false);
  });
});
