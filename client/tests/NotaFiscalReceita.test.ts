// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({
  listarNotas: vi.fn(), obterConfiguracao: vi.fn(), atualizarNota: vi.fn(), cancelarNota: vi.fn(),
  descartarNota: vi.fn(), abrirDocumento: vi.fn(), previaNota: vi.fn(), emitirNota: vi.fn(),
  STATUS_CLASSE: { autorizado: 'badge-success', em_processamento: 'badge-info', indeterminado: 'badge-warning' },
}));

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn() };
});
vi.mock('../src/services/notasFiscaisApi', () => api);

import NotaFiscalReceita from '../src/components/Financeiro/NotaFiscalReceita.vue';
import { aplicarAcesso, limparAcesso } from '../src/composables/useAcesso';

const configPronta = {
  emitente: { id: 'e1', cnpj: '11222333000181', razao: 'ESCRITÓRIO LTDA', crt: '1', certificado_validade: '2027-10-08' },
  padroes: {}, pronto: true, pendencias: [], codigos_cancelamento: { 1: 'Erro na emissão' },
};
const nota = (extra: Record<string, unknown> = {}) => ({
  id_nota_fiscal: 1, receita_id: 3, status: 'autorizado', status_rotulo: 'autorizada', chave_nfe: 'CH', numero: '15',
  serie: '1', numero_nfse: '12', codigo_verificacao: 'A1B2', valor_servicos: 1500, valor_liquido: 1500,
  discriminacao: 'Honorários', tomador_documento: '00000000191', tomador_nome: 'Maria', pdf_url: 'https://www.nfse.gov.br/x',
  ultimo_erro: null, emitida_em: '2026-11-08 10:00:00', cancelamento_motivo: null, created_at: '2026-11-08 10:00:00',
  updated_at: '2026-11-08 10:00:00', pode_atualizar: true, pode_cancelar: true, pode_descartar: false, tem_documento: true,
  ...extra,
});
const espera = async () => { for (let i = 0; i < 6; i++) { await nextTick(); await Promise.resolve(); } };

function montar() {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const app = createApp(NotaFiscalReceita, { receitaId: 3, onToast: () => {} });
  app.mount(raiz);
  return { raiz, desmontar: () => { app.unmount(); raiz.remove(); } };
}

const botaoEmitir = (raiz: HTMLElement) =>
  Array.from(raiz.querySelectorAll('button')).find((b) => b.textContent?.includes('Emitir NFS-e')) as HTMLButtonElement | undefined;

describe('NotaFiscalReceita', () => {
  let montado: ReturnType<typeof montar> | null = null;

  beforeEach(() => {
    Object.values(api).forEach((f) => typeof f === 'function' && 'mockReset' in f && f.mockReset());
    api.listarNotas.mockResolvedValue([]);
    api.obterConfiguracao.mockResolvedValue(configPronta);
    limparAcesso();
  });
  afterEach(() => { montado?.desmontar(); montado = null; limparAcesso(); });

  it('oferece a emissão quando a receita não tem nota e a configuração está pronta', async () => {
    montado = montar();
    await espera();

    expect(api.listarNotas).toHaveBeenCalledWith(3);
    expect(montado.raiz.textContent).toContain('Nenhuma nota emitida');
    expect(botaoEmitir(montado.raiz)?.disabled).toBe(false);
  });

  it('mostra a nota autorizada e não oferece segunda emissão', async () => {
    api.listarNotas.mockResolvedValue([nota()]);
    montado = montar();
    await espera();

    expect(montado.raiz.textContent).toContain('NFS-e nº 12');
    expect(montado.raiz.textContent).toContain('Autorizada');
    expect(botaoEmitir(montado.raiz)).toBeUndefined();
  });

  it('lista as pendências e bloqueia a emissão sem configuração', async () => {
    api.obterConfiguracao.mockResolvedValue({ ...configPronta, pronto: false, pendencias: ['Enviar o certificado digital A1 do emitente.'] });
    montado = montar();
    await espera();

    expect(montado.raiz.textContent).toContain('Enviar o certificado digital A1');
    expect(botaoEmitir(montado.raiz)?.disabled).toBe(true);
  });

  it('sem permissão de emitir, só exibe as notas', async () => {
    aplicarAcesso({ permissoes: ['financeiro.access'], modulos_ativos: ['financeiro'] } as any);
    api.listarNotas.mockResolvedValue([nota()]);
    montado = montar();
    await espera();

    expect(montado.raiz.textContent).toContain('NFS-e nº 12');
    expect(montado.raiz.querySelector('button[title="Cancelar a nota"]')).toBeNull();
    expect(botaoEmitir(montado.raiz)).toBeUndefined();
  });
});
