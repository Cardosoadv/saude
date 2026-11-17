// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({ apiFetch: vi.fn() }));
vi.mock('../src/services/api', async (original) => ({
  ...(await original<typeof import('../src/services/api')>()),
  apiFetch: api.apiFetch,
}));

const integ = vi.hoisted(() => ({ listarIntegracoes: vi.fn(), salvarIntegracoes: vi.fn() }));
vi.mock('../src/services/integracoesApi', async (original) => ({
  ...(await original<typeof import('../src/services/integracoesApi')>()),
  listarIntegracoes: integ.listarIntegracoes,
  salvarIntegracoes: integ.salvarIntegracoes,
}));

const acesso = vi.hoisted(() => ({ liberado: vi.fn() }));
vi.mock('../src/composables/useAcesso', () => ({ pode: () => true, liberado: acesso.liberado, recarregarAcesso: vi.fn() }));

const nfse = vi.hoisted(() => ({ obterConfiguracao: vi.fn(), obterEmitente: vi.fn() }));
vi.mock('../src/services/notasFiscaisApi', async (original) => ({
  ...(await original<typeof import('../src/services/notasFiscaisApi')>()),
  obterConfiguracao: nfse.obterConfiguracao,
  obterEmitente: nfse.obterEmitente,
}));

import ConfiguracoesView from '../src/views/Configuracoes/ConfiguracoesView.vue';

const espera = async () => { for (let i = 0; i < 8; i++) { await nextTick(); await Promise.resolve(); } };

const campo = (chave: string, extra: object = {}) => ({
  chave, rotulo: chave, tipo: 'texto', descricao: '', valor: '', tem_valor: false, origem: 'vazio', ...extra,
});

const catalogo = () => ({
  criptografia: true,
  grupos: [
    { slug: 'cnj', titulo: 'API CNJ', descricao: 'Consulta no CNJ.', icone: 'bi-bank', secoes: [{ titulo: 'Acesso', campos: [campo('API_PJE', { rotulo: 'Endereço da API', tipo: 'url' })] }] },
    { slug: 'ia', titulo: 'Inteligência artificial', descricao: 'IA.', icone: 'bi-stars', secoes: [{ titulo: 'Gemini', campos: [campo('API_MODEL_GEMINI', { rotulo: 'Modelo' })] }] },
    { slug: 'apibrasil', titulo: 'APIBrasil', descricao: 'CPF.', icone: 'bi-person-vcard', secoes: [{ titulo: 'Acesso', campos: [campo('APIBRASIL_CPF_TIPO', { rotulo: 'Tipo' })] }] },
    { slug: 'google', titulo: 'Google', descricao: 'Agenda e Drive.', icone: 'bi-google', secoes: [{ titulo: 'Credenciais', campos: [campo('GOOGLE_CLIENT_ID', { rotulo: 'Client ID' })] }] },
  ],
});

function montar() {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const app = createApp(ConfiguracoesView, { onToast: () => {}, 'onSet-view': () => {} });
  app.mount(raiz);
  return { raiz, desmontar: () => { app.unmount(); raiz.remove(); } };
}

const subguias = (raiz: HTMLElement) =>
  Array.from(raiz.querySelectorAll('[data-testid="subguias-sistema"] button')).map((b) => (b.textContent ?? '').trim());

const clicar = (raiz: HTMLElement, id: string) => (raiz.querySelector('#' + id) as HTMLButtonElement).click();

describe('Configurações > Sistema: sub-guias', () => {
  let montado: ReturnType<typeof montar> | null = null;

  beforeEach(() => {
    api.apiFetch.mockReset();
    integ.listarIntegracoes.mockReset();
    integ.salvarIntegracoes.mockReset();
    acesso.liberado.mockReset();
    acesso.liberado.mockReturnValue(true);
    nfse.obterConfiguracao.mockReset();
    nfse.obterEmitente.mockReset();
    nfse.obterConfiguracao.mockResolvedValue({
      emitente: { id: 'em-1', cnpj: '12345678000190', razao: 'Escritório Exemplo Ltda', crt: '1', certificado_validade: '2027-05-01' },
      padroes: { servico_codigo: '17.14', codigo_nbs: '123456789', codigo_municipio: '3106200' },
      pronto: true, pendencias: [], codigos_cancelamento: {}, webhook_url: 'https://exemplo.test/api/v2/webhooks/nfse/token',
    });
    nfse.obterEmitente.mockResolvedValue(null);
    api.apiFetch.mockImplementation(async (endpoint: string) =>
      endpoint === 'configuracoes'
        ? { success: true, data: [{ id: 1, nome: 'privacidade_granular_ativa', valor: '1', descricao: 'Privacidade' }] }
        : { success: true, data: {} },
    );
    integ.listarIntegracoes.mockResolvedValue(catalogo());
    integ.salvarIntegracoes.mockResolvedValue({ alteradas: ['API_MODEL_GEMINI'] });
  });
  afterEach(() => { montado?.desmontar(); montado = null; });

  it('mostra Geral e uma sub-guia para cada título (API CNJ, IA, APIBrasil, Google)', async () => {
    montado = montar();
    await espera();

    expect(subguias(montado.raiz)).toEqual(['Geral', 'API CNJ', 'Inteligência artificial', 'APIBrasil', 'Google', 'NFS-e']);
  });

  it('Geral é a inicial e mostra a tabela de parâmetros avulsos', async () => {
    montado = montar();
    await espera();

    expect(montado.raiz.querySelector('#subtab-geral')?.getAttribute('aria-selected')).toBe('true');
    expect(montado.raiz.textContent).toContain('privacidade_granular_ativa');
    expect(montado.raiz.textContent).toContain('Nova Configuração');
    expect(montado.raiz.textContent).not.toContain('Estas configurações valem só para este escritório');
  });

  it('cada sub-guia troca o conteúdo e esconde a tabela e o botão "Nova Configuração"', async () => {
    montado = montar();
    await espera();

    for (const [id, titulo, rotulo] of [
      ['subtab-cnj', 'API CNJ', 'Endereço da API'],
      ['subtab-ia', 'Inteligência artificial', 'Modelo'],
      ['subtab-apibrasil', 'APIBrasil', 'Tipo'],
      ['subtab-google', 'Google', 'Client ID'],
    ]) {
      clicar(montado.raiz, id);
      await espera();

      expect(montado.raiz.querySelector('#' + id)?.getAttribute('aria-selected')).toBe('true');
      expect(montado.raiz.querySelector('#subtab-geral')?.getAttribute('aria-selected')).toBe('false');
      expect(montado.raiz.querySelector(`[aria-label="${rotulo}"]`), `${titulo}: campo ${rotulo}`).not.toBeNull();
      expect(montado.raiz.textContent).toContain('Estas configurações valem só para este escritório');
      expect(montado.raiz.textContent).not.toContain('privacidade_granular_ativa');
      expect(montado.raiz.textContent).not.toContain('Nova Configuração');
    }

    clicar(montado.raiz, 'subtab-geral');
    await espera();
    expect(montado.raiz.textContent).toContain('privacidade_granular_ativa');
    expect(montado.raiz.textContent).toContain('Nova Configuração');
  });

  it('depois de salvar numa sub-guia, o catálogo é recarregado (selos e segredos atualizam)', async () => {
    montado = montar();
    await espera();
    clicar(montado.raiz, 'subtab-ia');
    await espera();
    const chamadasAntes = integ.listarIntegracoes.mock.calls.length;

    const modelo = montado.raiz.querySelector('[aria-label="Modelo"]') as HTMLInputElement;
    modelo.value = 'gemini-flash';
    modelo.dispatchEvent(new Event('input'));
    await espera();
    (montado.raiz.querySelector('[data-testid="salvar"]') as HTMLButtonElement).click();
    await espera();

    expect(integ.salvarIntegracoes).toHaveBeenCalledWith({ API_MODEL_GEMINI: 'gemini-flash' }, []);
    expect(integ.listarIntegracoes.mock.calls.length).toBe(chamadasAntes + 1);
  });

  it('sem permissão para as integrações, a aba continua só com a sub-guia Geral', async () => {
    integ.listarIntegracoes.mockRejectedValue(new Error('Você não tem permissão para ver as integrações.'));
    montado = montar();
    await espera();

    expect(subguias(montado.raiz)).toEqual(['Geral', 'NFS-e']);
    expect(montado.raiz.textContent).toContain('privacidade_granular_ativa');
  });

  it('NFS-e: abre o formulário de emitente, certificado e padrões fiscais com os dados do escritório', async () => {
    montado = montar();
    await espera();

    clicar(montado.raiz, 'subtab-nfse');
    await espera();

    expect(montado.raiz.querySelector('#subtab-nfse')?.getAttribute('aria-selected')).toBe('true');
    const texto = montado.raiz.textContent ?? '';
    expect(texto).toContain('Estas configurações valem só para este escritório');
    expect(texto).toContain('Escritório Exemplo Ltda');
    expect(texto).toContain('2. Certificado A1');
    expect(texto).toContain('3. Padrões fiscais');
    expect((montado.raiz.querySelector('#pd-codigo') as HTMLInputElement).value).toBe('17.14');
    expect(texto).toContain('/api/v2/webhooks/nfse/');
    expect(texto).not.toContain('privacidade_granular_ativa');
    expect(texto).not.toContain('Nova Configuração');
  });

  it('NFS-e: só aparece para quem tem a permissão e o módulo Financeiro ativo', async () => {
    acesso.liberado.mockReturnValue(false);
    montado = montar();
    await espera();

    expect(subguias(montado.raiz)).toEqual(['Geral', 'API CNJ', 'Inteligência artificial', 'APIBrasil', 'Google']);
    expect(acesso.liberado).toHaveBeenCalledWith('financeiro', 'financeiro.nfse_configurar');
    expect(nfse.obterConfiguracao).not.toHaveBeenCalled();
  });
});
