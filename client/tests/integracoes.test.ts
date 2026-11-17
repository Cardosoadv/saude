import './setup-globals';
import { vi, describe, it, expect, afterEach } from 'vitest';
import {
  ErroValidacaoIntegracao,
  listarIntegracoes,
  salvarIntegracoes,
  type CampoIntegracao,
  type GrupoIntegracao,
} from '../src/services/integracoesApi';
import { camposDoGrupo, haAlteracoes, montarEnvio, situacaoDoCampo, valoresIniciais } from '../src/utils/integracoes';

const campo = (extra: Partial<CampoIntegracao>): CampoIntegracao => ({
  chave: 'X', rotulo: 'X', tipo: 'texto', descricao: '', valor: '', tem_valor: false, origem: 'vazio', ...extra,
});

const grupo: GrupoIntegracao = {
  slug: 'ia', titulo: 'Inteligência artificial', descricao: '', icone: 'bi-stars',
  secoes: [
    { titulo: 'Gemini', campos: [
      campo({ chave: 'API_KEY_GEMINI', tipo: 'segredo', valor: null, tem_valor: true, origem: 'banco' }),
      campo({ chave: 'API_MODEL_GEMINI', tipo: 'texto', valor: 'gemini-pro', tem_valor: true, origem: 'banco' }),
    ] },
    { titulo: 'Provedor', campos: [
      campo({ chave: 'AI_PROVIDER_DEFAULT', tipo: 'opcao', valor: '', opcoes: { '': 'Usar o padrão', gemini: 'Gemini' } }),
    ] },
  ],
};

describe('valoresIniciais e camposDoGrupo', () => {
  it('lista todos os campos na ordem da tela', () => {
    expect(camposDoGrupo(grupo).map((c) => c.chave)).toEqual(['API_KEY_GEMINI', 'API_MODEL_GEMINI', 'AI_PROVIDER_DEFAULT']);
  });

  it('segredo começa vazio (o servidor nunca o devolve) e os demais com o valor atual', () => {
    expect(valoresIniciais(grupo)).toEqual({ API_KEY_GEMINI: '', API_MODEL_GEMINI: 'gemini-pro', AI_PROVIDER_DEFAULT: '' });
  });
});

describe('montarEnvio', () => {
  const inicial = () => valoresIniciais(grupo);

  it('sem edição não envia nada', () => {
    const envio = montarEnvio(grupo, inicial(), new Set());
    expect(envio).toEqual({ valores: {}, limpar: [] });
    expect(haAlteracoes(envio)).toBe(false);
  });

  it('envia só o que mudou, inclusive vazio para limpar um campo comum', () => {
    const editados = { ...inicial(), API_MODEL_GEMINI: '', AI_PROVIDER_DEFAULT: 'gemini' };
    expect(montarEnvio(grupo, editados, new Set()).valores).toEqual({ API_MODEL_GEMINI: '', AI_PROVIDER_DEFAULT: 'gemini' });
  });

  it('segredo só vai se algo foi digitado; em branco mantém o atual', () => {
    expect(montarEnvio(grupo, { ...inicial(), API_KEY_GEMINI: '   ' }, new Set()).valores).toEqual({});
    expect(montarEnvio(grupo, { ...inicial(), API_KEY_GEMINI: '  nova-chave  ' }, new Set()).valores).toEqual({ API_KEY_GEMINI: 'nova-chave' });
  });

  it('apagar segredo é um pedido à parte e vence o que foi digitado', () => {
    const envio = montarEnvio(grupo, { ...inicial(), API_KEY_GEMINI: 'qualquer' }, new Set(['API_KEY_GEMINI']));
    expect(envio).toEqual({ valores: {}, limpar: ['API_KEY_GEMINI'] });
    expect(haAlteracoes(envio)).toBe(true);
  });
});

describe('situacaoDoCampo', () => {
  it('distingue configurado, valor do servidor e não configurado', () => {
    expect(situacaoDoCampo(campo({ tem_valor: true, origem: 'banco' })).tom).toBe('ok');
    expect(situacaoDoCampo(campo({ tem_valor: true, origem: 'env' })).texto).toContain('.env');
    expect(situacaoDoCampo(campo({ tem_valor: false })).tom).toBe('neutro');
  });
});

describe('integracoesApi', () => {
  const resposta = (corpo: object, status = 200) =>
    new Response(JSON.stringify(corpo), { status, headers: { 'content-type': 'application/json' } });

  afterEach(() => vi.restoreAllMocks());

  it('lista o catálogo', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(resposta({ success: true, data: { grupos: [grupo], criptografia: true } }));
    const catalogo = await listarIntegracoes();
    expect(catalogo.grupos[0].slug).toBe('ia');
    expect(catalogo.criptografia).toBe(true);
  });

  it('recusa resposta sem sucesso', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(resposta({ success: false, message: 'Sem permissão' }));
    await expect(listarIntegracoes()).rejects.toThrow('Sem permissão');
  });

  it('salva enviando valores e limpar em JSON', async () => {
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(resposta({ success: true, alteradas: ['API_KEY_GEMINI'] }));
    const r = await salvarIntegracoes({ API_KEY_GEMINI: 'nova' }, ['GOOGLE_REFRESH_TOKEN']);

    expect(r.alteradas).toEqual(['API_KEY_GEMINI']);
    const [url, opts] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toContain('configuracoes/integracoes');
    expect((opts.method ?? '').toUpperCase()).toBe('POST');
    expect(JSON.parse(opts.body as string)).toEqual({ valores: { API_KEY_GEMINI: 'nova' }, limpar: ['GOOGLE_REFRESH_TOKEN'] });
  });

  it('422 vira ErroValidacaoIntegracao com a mensagem de cada chave', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(resposta({ success: false, message: 'Corrija os campos indicados.', errors: { API_PJE: 'Informe um endereço completo.' } }, 422));

    const erro = await salvarIntegracoes({ API_PJE: 'x' }).catch((e) => e);

    expect(erro).toBeInstanceOf(ErroValidacaoIntegracao);
    expect(erro.erros).toEqual({ API_PJE: 'Informe um endereço completo.' });
  });

  it('outros erros seguem como estão', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(resposta({ success: false, message: 'Erro interno' }, 500));
    const erro = await salvarIntegracoes({ API_PJE: 'https://x.test/' }).catch((e) => e);
    expect(erro).not.toBeInstanceOf(ErroValidacaoIntegracao);
    expect(erro.status).toBe(500);
  });
});
