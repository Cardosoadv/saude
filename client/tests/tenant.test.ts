import './setup-globals';
import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest';
import {
  CABECALHO_TENANT,
  CHAVE_TENANT_LOCAL,
  CHAVE_TENANT_SESSAO,
  CODIGOS_TENANT_BLOQUEIO,
  EVENTO_TENANT_ERRO,
  MENSAGENS_TENANT,
  ehCodigoTenantBloqueio,
  sincronizarTenantLocal,
  cabecalhosDeTenant,
  extrairTenantDoCaminho,
  extrairTenantDaBusca,
  extrairTenantDaUrl,
  definirTenantSessao,
  limparTenantSessao,
  normalizarSlug,
  tenantSlugAtual,
} from '../src/services/tenant';
import { ApiError, apiFetch, baixarArquivo } from '../src/services/api';
import { buscarTenantAtual } from '../src/services/tenantApi';
import { login } from '../src/services/auth';

const win = (globalThis as any).window;

function definirCaminho(pathname: string | undefined, search = '', hash = '') {
  win.location = pathname === undefined ? undefined : { pathname, search, hash };
}

describe('extrairTenantDoCaminho', () => {
  it('pega o segmento antes de /sistema/', () => {
    expect(extrairTenantDoCaminho('/cb/sistema/')).toBe('cb');
    expect(extrairTenantDoCaminho('/cb/sistema')).toBe('cb');
    expect(extrairTenantDoCaminho('/escritorio-1/sistema/processos/10')).toBe('escritorio-1');
  });

  it('funciona com prefixo na frente do tenant (ex.: /ordo/cb/sistema/)', () => {
    expect(extrairTenantDoCaminho('/ordo/cb/sistema/')).toBe('cb');
  });

  it('normaliza maiúsculas e ignora o "sistema" em qualquer caixa', () => {
    expect(extrairTenantDoCaminho('/CB/Sistema/')).toBe('cb');
  });

  it('devolve null sem segmento antes de sistema ou sem sistema no caminho', () => {
    expect(extrairTenantDoCaminho('/sistema/')).toBeNull();
    expect(extrairTenantDoCaminho('/')).toBeNull();
    expect(extrairTenantDoCaminho('')).toBeNull();
    expect(extrairTenantDoCaminho('/cb/outra-coisa/')).toBeNull();
  });

  it('rejeita slugs inválidos (injeção de cabeçalho, caracteres estranhos)', () => {
    expect(extrairTenantDoCaminho('/a%0d%0ab/sistema/')).toBeNull();
    expect(extrairTenantDoCaminho('/a b/sistema/')).toBeNull();
    expect(extrairTenantDoCaminho('/%E0%A4%A/sistema/')).toBeNull();
    expect(extrairTenantDoCaminho(`/${'a'.repeat(80)}/sistema/`)).toBeNull();
  });
});

describe('normalizarSlug', () => {
  it('aceita letras, números e hífen', () => {
    expect(normalizarSlug(' Cardoso-Bruno ')).toBe('cardoso-bruno');
  });
  it('rejeita vazio, hífen inicial e não-strings', () => {
    expect(normalizarSlug('')).toBeNull();
    expect(normalizarSlug('-x')).toBeNull();
    expect(normalizarSlug(undefined)).toBeNull();
    expect(normalizarSlug(42)).toBeNull();
  });
});

describe('extrairTenantDaBusca e extrairTenantDaUrl', () => {
  it('extrai parâmetro ?tenant= da query string', () => {
    expect(extrairTenantDaBusca('?tenant=cb')).toBe('cb');
    expect(extrairTenantDaBusca('tenant=banca-silva&outro=1')).toBe('banca-silva');
  });

  it('extrai parâmetro ?tenant_slug= da query string', () => {
    expect(extrairTenantDaBusca('?tenant_slug=norte')).toBe('norte');
  });

  it('retorna null se não houver parâmetro de tenant ou for inválido', () => {
    expect(extrairTenantDaBusca('?outro=1')).toBeNull();
    expect(extrairTenantDaBusca('')).toBeNull();
    expect(extrairTenantDaBusca('?tenant=invalido;drop')).toBeNull();
  });

  it('extrai tenant da URL via window.location.search', () => {
    definirCaminho('/sistema/', '?tenant=cb');
    expect(extrairTenantDaUrl()).toBe('cb');
  });

  it('extrai tenant da URL via window.location.hash com query', () => {
    definirCaminho('/sistema/', '', '#/login?tenant=meu-escritorio');
    expect(extrairTenantDaUrl()).toBe('meu-escritorio');
  });
});

describe('definirTenantSessao e limparTenantSessao', () => {
  beforeEach(() => {
    win.sessionStorage = {
      _dados: {} as Record<string, string>,
      getItem(k: string) { return this._dados[k] ?? null; },
      setItem(k: string, v: string) { this._dados[k] = String(v); },
      removeItem(k: string) { delete this._dados[k]; },
    };
    win.localStorage = {
      _dados: {} as Record<string, string>,
      getItem(k: string) { return this._dados[k] ?? null; },
      setItem(k: string, v: string) { this._dados[k] = String(v); },
      removeItem(k: string) { delete this._dados[k]; },
    };
  });
  afterEach(() => {
    delete win.sessionStorage;
    delete win.localStorage;
  });

  it('grava e limpa o tenant na sessão', () => {
    definirTenantSessao('cb');
    expect(win.sessionStorage.getItem(CHAVE_TENANT_SESSAO)).toBe('cb');
    limparTenantSessao();
    expect(win.sessionStorage.getItem(CHAVE_TENANT_SESSAO)).toBeNull();
  });
});

describe('tenantSlugAtual e cabecalhosDeTenant', () => {
  beforeEach(() => {
    delete win.__APP_CONFIG__.tenantSlug;
  });
  afterEach(() => {
    delete win.__APP_CONFIG__.tenantSlug;
    definirCaminho(undefined);
  });

  it('usa a URL da página', () => {
    definirCaminho('/cb/sistema/');
    expect(tenantSlugAtual()).toBe('cb');
    expect(cabecalhosDeTenant()).toEqual({ [CABECALHO_TENANT]: 'cb' });
  });

  it('tenantSlug do config.js tem prioridade sobre a URL', () => {
    definirCaminho('/cb/sistema/');
    win.__APP_CONFIG__.tenantSlug = 'dev';
    expect(tenantSlugAtual()).toBe('dev');
  });

  it('tenantSlug inválido no config cai para a URL', () => {
    definirCaminho('/cb/sistema/');
    win.__APP_CONFIG__.tenantSlug = 'inválido!';
    expect(tenantSlugAtual()).toBe('cb');
  });

  it('sem tenant na URL e sem config não envia cabeçalho', () => {
    definirCaminho('/sistema/');
    expect(cabecalhosDeTenant()).toEqual({});
  });

  it('usa parâmetro GET da URL (?tenant=cb) em /sistema/', () => {
    definirCaminho('/sistema/', '?tenant=cb');
    expect(tenantSlugAtual()).toBe('cb');
    expect(cabecalhosDeTenant()).toEqual({ [CABECALHO_TENANT]: 'cb' });
  });

  it('usa o tenant do sessionStorage quando a URL não traz parâmetro', () => {
    definirCaminho('/sistema/');
    win.sessionStorage = {
      getItem(k: string) { return k === CHAVE_TENANT_SESSAO ? 'sessao-escritorio' : null; }
    };
    expect(tenantSlugAtual()).toBe('sessao-escritorio');
    delete win.sessionStorage;
  });

  it('não quebra quando window.location não existe', () => {
    definirCaminho(undefined);
    expect(tenantSlugAtual()).toBeNull();
  });
});

describe('envio do tenant nas requisições', () => {
  const respostaJson = () =>
    new Response(JSON.stringify({ ok: true }), { status: 200, headers: { 'content-type': 'application/json' } });

  beforeEach(() => {
    definirCaminho('/cb/sistema/');
  });
  afterEach(() => {
    vi.restoreAllMocks();
    delete win.__APP_CONFIG__.tenantSlug;
    definirCaminho(undefined);
  });

  it('apiFetch envia X-Tenant-Slug', async () => {
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(respostaJson());
    await apiFetch('processos');
    const headers = (fetchMock.mock.calls[0][1] as RequestInit).headers as Record<string, string>;
    expect(headers['X-Tenant-Slug']).toBe('cb');
  });

  it('o chamador pode sobrescrever o cabeçalho', async () => {
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(respostaJson());
    await apiFetch('processos', { headers: { 'X-Tenant-Slug': 'outro' } });
    const headers = (fetchMock.mock.calls[0][1] as RequestInit).headers as Record<string, string>;
    expect(headers['X-Tenant-Slug']).toBe('outro');
  });

  it('apiFetch não envia o cabeçalho quando a URL não traz tenant', async () => {
    definirCaminho('/sistema/');
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(respostaJson());
    await apiFetch('processos');
    const headers = (fetchMock.mock.calls[0][1] as RequestInit).headers as Record<string, string>;
    expect('X-Tenant-Slug' in headers).toBe(false);
  });

  it('baixarArquivo envia X-Tenant-Slug', async () => {
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(respostaJson());
    await expect(baixarArquivo('relatorio')).rejects.toBeDefined(); // resposta JSON é tratada como erro
    const headers = (fetchMock.mock.calls[0][1] as RequestInit).headers as Record<string, string>;
    expect(headers['X-Tenant-Slug']).toBe('cb');
  });

  it('login envia tenant como parâmetro GET no endpoint de login', async () => {
    definirCaminho('/sistema/', '?tenant=cb');
    const respostaLogin = new Response(
      JSON.stringify({ status: 'success', user: { id: 1, email: 'a@b.com', tenant: { slug: 'cb' } } }),
      { status: 200, headers: { 'content-type': 'application/json' } }
    );
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(respostaLogin);
    await login({ email: 'a@b.com', password: '123' });
    const chamadaUrl = String(fetchMock.mock.calls[0][0]);
    expect(chamadaUrl).toContain('login?tenant=cb');
  });
});

describe('erros de escritório (tenant_*)', () => {
  const erroJson = (status: number, corpo: object) =>
    new Response(JSON.stringify(corpo), { status, headers: { 'content-type': 'application/json' } });

  beforeEach(() => {
    definirCaminho('/cb/sistema/');
    win.dispatchEvent = vi.fn();
  });
  afterEach(() => {
    vi.restoreAllMocks();
    delete win.dispatchEvent;
    definirCaminho(undefined);
  });

  it('apiFetch marca o ApiError e avisa a SPA quando o escritório está inativo', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      erroJson(403, { success: false, code: 'tenant_inativo', message: 'Este escritório está desativado.' }),
    );

    const erro = await apiFetch('processos').catch((e) => e);

    expect(erro).toBeInstanceOf(ApiError);
    expect(erro.status).toBe(403);
    expect(erro.tenantBloqueio).toBe('tenant_inativo');
    expect(erro.message).toBe('Este escritório está desativado.');
    expect(win.dispatchEvent).toHaveBeenCalledTimes(1);
    const evento = win.dispatchEvent.mock.calls[0][0] as CustomEvent;
    expect(evento.type).toBe(EVENTO_TENANT_ERRO);
    expect(evento.detail).toEqual({ codigo: 'tenant_inativo', mensagem: 'Este escritório está desativado.' });
  });

  it('usa a mensagem de reserva quando a API não manda texto', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(erroJson(404, { code: 'tenant_nao_resolvido' }));

    const erro = await apiFetch('tenant/atual').catch((e) => e);

    expect(erro.tenantBloqueio).toBe('tenant_nao_resolvido');
    expect(erro.message).toBe(MENSAGENS_TENANT.tenant_nao_resolvido.texto);
  });

  it('outros erros (módulo inativo, validação) não disparam a tela de escritório', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(erroJson(403, { code: 'modulo_inativo', modulo: 'objetos' }));

    const erro = await apiFetch('objetos').catch((e) => e);

    expect(erro.tenantBloqueio).toBeUndefined();
    const tipos = win.dispatchEvent.mock.calls.map((c: any[]) => c[0].type);
    expect(tipos).not.toContain(EVENTO_TENANT_ERRO);
  });

  it('ehCodigoTenantBloqueio só aceita os códigos conhecidos', () => {
    for (const codigo of CODIGOS_TENANT_BLOQUEIO) expect(ehCodigoTenantBloqueio(codigo)).toBe(true);
    expect(ehCodigoTenantBloqueio('modulo_inativo')).toBe(false);
    expect(ehCodigoTenantBloqueio(undefined)).toBe(false);
  });
});

describe('buscarTenantAtual', () => {
  const json = (corpo: object, status = 200) =>
    new Response(JSON.stringify(corpo), { status, headers: { 'content-type': 'application/json' } });

  afterEach(() => vi.restoreAllMocks());

  it('devolve slug e nome do escritório', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(json({ status: 'success', tenant: { slug: 'cb', nome: 'Cardoso & Bruno' } }));
    expect(await buscarTenantAtual()).toEqual({ slug: 'cb', nome: 'Cardoso & Bruno' });
  });

  it('nome vazio vira null e resposta sem tenant vira null', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ tenant: { slug: 'cb', nome: '' } }));
    expect(await buscarTenantAtual()).toEqual({ slug: 'cb', nome: null });
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({}));
    expect(await buscarTenantAtual()).toBeNull();
  });

  it('qualquer falha vira null (o login segue funcionando)', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(json({ code: 'tenant_indisponivel' }, 503));
    expect(await buscarTenantAtual()).toBeNull();
    vi.spyOn(globalThis, 'fetch').mockRejectedValue(new Error('rede'));
    expect(await buscarTenantAtual()).toBeNull();
  });
});

describe('sincronizarTenantLocal', () => {
  function armazenamentoFalso() {
    const dados = new Map<string, string>();
    return {
      dados,
      getItem: (k: string) => (dados.has(k) ? (dados.get(k) as string) : null),
      setItem: (k: string, v: string) => void dados.set(k, v),
      clear: () => dados.clear(),
    };
  }

  let local: ReturnType<typeof armazenamentoFalso>;
  let sessao: ReturnType<typeof armazenamentoFalso>;

  beforeEach(() => {
    local = armazenamentoFalso();
    sessao = armazenamentoFalso();
    win.localStorage = local;
    win.sessionStorage = sessao;
  });
  afterEach(() => {
    delete win.localStorage;
    delete win.sessionStorage;
  });

  it('primeira visita só registra o escritório', () => {
    local.setItem('filtro', 'x');
    expect(sincronizarTenantLocal('cb')).toBe(false);
    expect(local.getItem(CHAVE_TENANT_LOCAL)).toBe('cb');
    expect(local.getItem('filtro')).toBe('x');
  });

  it('mesmo escritório não limpa nada', () => {
    sincronizarTenantLocal('cb');
    local.setItem('filtro', 'x');
    expect(sincronizarTenantLocal('cb')).toBe(false);
    expect(local.getItem('filtro')).toBe('x');
  });

  it('trocar de escritório limpa local e sessão e registra o novo', () => {
    sincronizarTenantLocal('cb');
    local.setItem('filtro', 'x');
    sessao.setItem('ordo_system_stats', '1');

    expect(sincronizarTenantLocal('outro')).toBe(true);

    expect(local.getItem('filtro')).toBeNull();
    expect(sessao.getItem('ordo_system_stats')).toBeNull();
    expect(local.getItem(CHAVE_TENANT_LOCAL)).toBe('outro');
  });

  it('armazenamento indisponível não derruba o app', () => {
    win.localStorage = { getItem: () => { throw new Error('bloqueado'); } };
    expect(sincronizarTenantLocal('cb')).toBe(false);
  });
});
