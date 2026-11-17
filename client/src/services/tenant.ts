/**
 * @version 1.2.0
 * Tenant (escritório) da sessão.
 *
 * O tenant slug pode ser enviado como parâmetro GET no login (`?tenant=<slug>` ou `?tenant_slug=<slug>`),
 * ou lido da URL/config. Uma vez autenticado, ele é mantido na sessão (backend e sessionStorage),
 * evitando a necessidade de múltiplos URLs para o painel (/sistema/ direto).
 */

/** Cabeçalho lido pelo `TenantFilter` do backend. */
export const CABECALHO_TENANT = 'X-Tenant-Slug';

/** Segmento fixo do caminho onde o painel é servido. */
export const SEGMENTO_SISTEMA = 'sistema';

/** Chave do sessionStorage para o tenant ativo na sessão atual. */
export const CHAVE_TENANT_SESSAO = 'ordojuris.tenant_slug';

const SLUG_VALIDO_RE = /^[a-z0-9](?:[a-z0-9-]{0,62})$/;

/** Normaliza e valida um slug; devolve `null` se não for um slug aceitável. */
export function normalizarSlug(valor: unknown): string | null {
  if (typeof valor !== 'string') return null;
  const slug = valor.trim().toLowerCase();
  return SLUG_VALIDO_RE.test(slug) ? slug : null;
}

/**
 * Extrai o slug do tenant dos parâmetros de consulta (GET da URL).
 * Procura por `tenant` ou `tenant_slug` (ex.: `?tenant=cb` ou `?tenant_slug=cb`).
 */
export function extrairTenantDaBusca(busca: string): string | null {
  if (!busca || typeof busca !== 'string') return null;
  const limpa = busca.startsWith('?') ? busca.slice(1) : busca;
  try {
    const params = new URLSearchParams(limpa);
    return normalizarSlug(params.get('tenant') ?? params.get('tenant_slug'));
  } catch {
    return null;
  }
}

/**
 * Extrai o tenant informado na URL atual (busca ou hash da página).
 */
export function extrairTenantDaUrl(): string | null {
  if (typeof window === 'undefined' || !window.location) return null;

  // 1. Parâmetro GET na busca da URL (?tenant=... ou ?tenant_slug=...)
  const busca = typeof window.location.search === 'string' ? window.location.search : '';
  const daBusca = extrairTenantDaBusca(busca);
  if (daBusca) return daBusca;

  // 2. Parâmetro GET dentro do hash (#/...?...tenant=...)
  const hash = typeof window.location.hash === 'string' ? window.location.hash : '';
  const idx = hash.indexOf('?');
  if (idx !== -1) {
    const doHash = extrairTenantDaBusca(hash.slice(idx));
    if (doHash) return doHash;
  }

  return null;
}

/**
 * Extrai o tenant de um caminho: `/cb/sistema/processos` → `cb`.
 * Devolve `null` quando não há segmento antes de `sistema` (ou ele não é um slug válido).
 */
export function extrairTenantDoCaminho(caminho: string): string | null {
  const segmentos = caminho.split('/').filter(Boolean);
  const posicao = segmentos.findIndex((s) => s.toLowerCase() === SEGMENTO_SISTEMA);
  if (posicao < 1) return null;
  return normalizarSlug(decodeURIComponentSeguro(segmentos[posicao - 1]));
}

function decodeURIComponentSeguro(valor: string): string {
  try {
    return decodeURIComponent(valor);
  } catch {
    return '';
  }
}

/**
 * Define o tenant ativo na sessão do navegador (`sessionStorage`).
 */
export function definirTenantSessao(slug: string): void {
  const normalizado = normalizarSlug(slug);
  if (!normalizado) return;
  try {
    window.sessionStorage?.setItem(CHAVE_TENANT_SESSAO, normalizado);
    window.localStorage?.setItem(CHAVE_TENANT_LOCAL, normalizado);
  } catch {
    // armazenamento bloqueado
  }
}

/**
 * Limpa o tenant gravado na sessão local.
 */
export function limparTenantSessao(): void {
  try {
    window.sessionStorage?.removeItem(CHAVE_TENANT_SESSAO);
  } catch {
    // armazenamento bloqueado
  }
}

/**
 * Slug do tenant atual:
 *  1. `tenantSlug` do `config.js` (prioridade configurada explicitamente)
 *  2. Parâmetro GET na URL (`?tenant=<slug>` ou `?tenant_slug=<slug>`)
 *  3. `sessionStorage` (gravado a partir do login na sessão atual)
 *  4. Caminho da URL (ex.: `/cb/sistema/` para compatibilidade retroativa)
 *  5. `localStorage` (último escritório lembrado neste navegador)
 */
export function tenantSlugAtual(): string | null {
  const configurado = normalizarSlug(window.__APP_CONFIG__?.tenantSlug);
  if (configurado) return configurado;

  const daUrl = extrairTenantDaUrl();
  if (daUrl) return daUrl;

  try {
    const daSessao = normalizarSlug(window.sessionStorage?.getItem(CHAVE_TENANT_SESSAO));
    if (daSessao) return daSessao;
  } catch {
    // sessionStorage inacessível
  }

  const caminho = typeof window.location?.pathname === 'string' ? window.location.pathname : '';
  const doCaminho = extrairTenantDoCaminho(caminho);
  if (doCaminho) return doCaminho;

  try {
    const doLocal = normalizarSlug(window.localStorage?.getItem(CHAVE_TENANT_LOCAL));
    if (doLocal) return doLocal;
  } catch {
    // localStorage inacessível
  }

  return null;
}

/** Cabeçalhos de tenant para uma requisição à API (vazio quando o tenant não foi identificado). */
export function cabecalhosDeTenant(): Record<string, string> {
  const slug = tenantSlugAtual();
  return slug ? { [CABECALHO_TENANT]: slug } : {};
}

/** Códigos de erro do backend (`TenantFilter`) que impedem o uso do sistema neste escritório/URL. */
export const CODIGOS_TENANT_BLOQUEIO = [
  'tenant_inativo',
  'tenant_nao_resolvido',
  'tenant_invalido',
  'tenant_divergente',
  'tenant_indisponivel',
] as const;

export type CodigoTenantBloqueio = (typeof CODIGOS_TENANT_BLOQUEIO)[number];

export function ehCodigoTenantBloqueio(codigo: unknown): codigo is CodigoTenantBloqueio {
  return typeof codigo === 'string' && (CODIGOS_TENANT_BLOQUEIO as readonly string[]).includes(codigo);
}

/** Texto da tela dedicada para cada código (o backend já manda uma mensagem, esta é a de reserva). */
export const MENSAGENS_TENANT: Record<CodigoTenantBloqueio, { titulo: string; texto: string }> = {
  tenant_inativo: {
    titulo: 'Escritório desativado',
    texto: 'O acesso deste escritório está desativado. Fale com o administrador da plataforma.',
  },
  tenant_nao_resolvido: {
    titulo: 'Escritório não encontrado',
    texto: 'Não encontramos o escritório deste endereço. Confira o link recebido do seu escritório.',
  },
  tenant_invalido: {
    titulo: 'Endereço inválido',
    texto: 'O identificador do escritório neste endereço é inválido. Confira o link recebido.',
  },
  tenant_divergente: {
    titulo: 'Sessão de outro escritório',
    texto: 'Você está conectado em outro escritório. Saia da conta e acesse novamente pelo link do seu escritório.',
  },
  tenant_indisponivel: {
    titulo: 'Serviço indisponível',
    texto: 'Não foi possível identificar o escritório agora. Tente novamente em instantes.',
  },
};

/** Evento disparado em `window` quando a API bloqueia o acesso por causa do escritório. */
export const EVENTO_TENANT_ERRO = 'tenant-erro';

/** Chave do localStorage com o escritório da última sessão neste navegador. */
export const CHAVE_TENANT_LOCAL = 'ordojuris.tenant';

/**
 * Ao trocar de escritório no mesmo navegador, descarta tudo o que ficou guardado localmente (caches, filtros,
 * rascunhos) para que dados de um escritório nunca apareçam em outro. Devolve `true` se limpou.
 */
export function sincronizarTenantLocal(slug: string | null = tenantSlugAtual()): boolean {
  try {
    const armazenamento = window.localStorage;
    if (!armazenamento) return false;

    const anterior = armazenamento.getItem(CHAVE_TENANT_LOCAL);
    const atual = slug ?? '';
    if (anterior === atual) return false;

    // Primeira visita (sem registro anterior): só registra, não há o que limpar de outro escritório.
    const trocou = anterior !== null;
    if (trocou) {
      armazenamento.clear();
      window.sessionStorage?.clear();
    }
    armazenamento.setItem(CHAVE_TENANT_LOCAL, atual);
    if (atual) {
      try {
        window.sessionStorage?.setItem(CHAVE_TENANT_SESSAO, atual);
      } catch {
        // sessionStorage inacessível
      }
    }

    return trocou;
  } catch {
    return false; // armazenamento bloqueado (modo privado): segue sem limpar
  }
}
