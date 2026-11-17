import { describe, it, expect, beforeEach, vi } from 'vitest';
import './setup-globals';

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn(async () => ({})) };
});

import { apiFetch } from '../src/services/api';
import {
  ConsultaErro, consultarProcessos, consultarVeiculo, formatarCnpj, formatarCpf,
  formatarNumeroCnj, homologAtivo, normalizarPlaca, placaValida,
  dataIso, poloDaParte, processoParaImportar, enviarParaImportacao, retirarImportacaoPendente,
} from '../src/services/consultasApi';

describe('consultasApi', () => {
  beforeEach(() => { vi.mocked(apiFetch).mockReset(); });

  it('normaliza e valida placas antiga e Mercosul', () => {
    expect(normalizarPlaca('abc-1d23')).toBe('ABC1D23');
    expect(placaValida('ABC-1234')).toBe(true);
    expect(placaValida('ABC1D23')).toBe(true);
    expect(placaValida('12345')).toBe(false);
  });

  it('formata documentos', () => {
    expect(formatarCpf('00000000191')).toBe('000.000.001-91');
    expect(formatarCnpj('47368913000129')).toBe('47.368.913/0001-29');
    expect(formatarNumeroCnj('10000000020234000000')).toBe('1000000-00.2023.4.00.0000');
    expect(formatarNumeroCnj('123')).toBe('123');
  });

  it('processos envia só dígitos e extrai a lista', async () => {
    vi.mocked(apiFetch).mockResolvedValueOnce({
      success: true,
      data: { valor_consulta: 0, homolog: true, data: { acoesProcessos: { acoes: { processos: [{ numeroProcessoUnico: '1' }] } }, cadastralCompleto: { dadosCadastrais: { nome: 'FULANO' } } } },
    });
    const r = await consultarProcessos('000.000.001-91', '');
    const [rota, opts] = vi.mocked(apiFetch).mock.calls[0] as [string, any];
    expect(rota).toBe('consultas/processos');
    expect(opts.body).toEqual({ cpf: '00000000191' });
    expect(r.processos).toHaveLength(1);
    expect(r.cadastral.dadosCadastrais.nome).toBe('FULANO');
    expect(r.cobranca.homolog).toBe(true);
  });

  it('processos sem resultado devolve lista vazia', async () => {
    vi.mocked(apiFetch).mockResolvedValueOnce({ success: true, data: { data: {} } });
    expect((await consultarProcessos('00000000191', '47.368.913/0001-29')).processos).toEqual([]);
    expect((vi.mocked(apiFetch).mock.calls[0][1] as any).body.cnpj).toBe('47368913000129');
  });

  it('veículo envia a placa normalizada', async () => {
    vi.mocked(apiFetch).mockResolvedValueOnce({ success: true, data: { data: { placa: 'ABC1234', marca: 'VW' } } });
    const r = await consultarVeiculo('abc-1234');
    expect((vi.mocked(apiFetch).mock.calls[0][1] as any).body).toEqual({ placa: 'ABC1234' });
    expect(r.veiculo.marca).toBe('VW');
  });

  it('propaga a mensagem de erro do backend', async () => {
    vi.mocked(apiFetch).mockResolvedValueOnce({ success: false, error: true, message: 'Saldo insuficiente' });
    await expect(consultarVeiculo('ABC1234')).rejects.toThrow(new ConsultaErro('Saldo insuficiente'));
  });

  it('status de homologação tolera falha', async () => {
    vi.mocked(apiFetch).mockRejectedValueOnce(new Error('x'));
    expect(await homologAtivo()).toBe(false);
    vi.mocked(apiFetch).mockResolvedValueOnce({ homolog: true });
    expect(await homologAtivo()).toBe(true);
  });

  it('converte processo da API Brasil para a importação em lote', () => {
    const item = processoParaImportar({
      numeroProcessoUnico: '1000000-00.2023.4.00.0000',
      tribunal: 'tjsp',
      orgaoJulgador: ' 1ª Vara Cível ',
      classeProcessual: { nome: 'Procedimento Comum Cível' },
      dataDistribuicao: '2023-01-10T00:00:00',
      valorCausa: { valor: 1500.5 },
      urlProcesso: 'javascript:alert(1)',
      partes: [
        { nome: 'Maria da Silva', polo: 'ATIVO', tipo: 'AUTOR' },
        { nome: 'Banco Exemplo S.A.', polo: 'PASSIVO', tipo: 'RÉU' },
        { nome: 'Perito', polo: 'NEUTRO', tipo: 'PERITO' },
        { nome: 'Maria da Silva', polo: 'ATIVO' },
      ],
    }, '000.000.001-91');

    expect(item).not.toBeNull();
    expect(item!.numero_processo).toBe('10000000020234000000');
    expect(item!.numeroprocessocommascara).toBe('1000000-00.2023.4.00.0000');
    expect(item!.arquivo).toMatchObject({
      cliente_documento: '00000000191',
      siglaTribunal: 'TJSP',
      nomeOrgao: '1ª Vara Cível',
      tipoDocumento: 'Procedimento Comum Cível',
      dataDistribuicao: '2023-01-10',
      valorCausa: '1500.50',
      link: null,
      poloAtivo: ['Maria da Silva'],
      poloPassivo: ['Banco Exemplo S.A.'],
    });
  });

  it('ignora processo sem número CNJ completo', () => {
    expect(processoParaImportar({ numeroProcessoUnico: '123' })).toBeNull();
  });

  it('reconhece polos e datas', () => {
    expect(poloDaParte({ tipo: 'Requerido' })).toBe('P');
    expect(poloDaParte({ polo: 'Polo Ativo' })).toBe('A');
    expect(poloDaParte({ polo: 'Terceiro' })).toBeNull();
    expect(dataIso('5/3/2024')).toBe('2024-03-05');
    expect(dataIso('ontem')).toBeNull();
  });

  it('entrega a importação pendente uma única vez', () => {
    const item = processoParaImportar({ numeroProcessoUnico: '10000000020234000000' })!;
    enviarParaImportacao([item]);
    expect(retirarImportacaoPendente()).toEqual([item]);
    expect(retirarImportacaoPendente()).toBeNull();
  });
});
