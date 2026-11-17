/**
 * @version 1.6.0
 * Auth Service – Integração com CodeIgniter Shield
 */
import { apiFetch, BASE_URL } from './api';
import { definirTenantSessao, limparTenantSessao, tenantSlugAtual } from './tenant';

export interface LoginCredentials {
  email?: string;
  username?: string;
  password: string;
  remember?: boolean;
}

/** Dados do usuário autenticado, como retornados pelo backend. */
export interface AuthUser {
  id?: number | string;
  user_id?: number | string;
  username?: string;
  email?: string;
  groups?: string[];
  /** Permissões efetivas (diretas + grupos, curingas expandidos). Ausente em backends antigos. */
  permissoes?: string[];
  /** Slugs dos módulos ativos (ex.: 'objetos'). Ausente em backends antigos. */
  modulos_ativos?: string[];
  [key: string]: any;
}

/**
 * Realiza login no backend Shield enviando o tenant slug como parâmetro GET no endpoint de login.
 * Valida a resposta para garantir que erros de credenciais não permitam entrada.
 * Após login bem-sucedido, registra o tenant na sessão e busca os dados do usuário automaticamente.
 */
export async function login(credentials: LoginCredentials, tenantSlug?: string | null) {
  const slug = tenantSlug ?? tenantSlugAtual();
  const endpoint = slug ? `login?tenant=${encodeURIComponent(slug)}` : 'login';
  const response = await apiFetch(endpoint, {
    method: 'POST',
    body: credentials
  });

  // Shield retorna JSON com mensagens de erro quando as credenciais são inválidas.
  // Exemplo: { "messages": { "error": "Invalid login credentials." } }
  // Ou pode retornar { "error": "..." } ou { "errors": [...] }
  if (response.messages?.error || response.error || response.errors) {
    const errorMsg = response.messages?.error
      || response.error
      || (Array.isArray(response.errors) ? response.errors.join(', ') : response.errors);
    throw new Error(errorMsg || 'Credenciais inválidas');
  }

  // Se a resposta foi um redirect para HTML (não-JSON), o apiFetch retorna
  // { success: true, redirected: true }. Isso pode significar tanto sucesso
  // (redirect ao dashboard) quanto falha (redirect à página de login).
  // Precisamos verificar buscando os dados do usuário.
  if (response.redirected || !response.user) {
    // Tenta buscar o usuário autenticado para confirmar que o login funcionou
    const user = await fetchCurrentUser();
    if (!user) {
      throw new Error('Credenciais inválidas. Por favor, tente novamente.');
    }
    if (user.tenant?.slug) {
      definirTenantSessao(user.tenant.slug);
    } else if (slug) {
      definirTenantSessao(slug);
    }
    return { success: true, user };
  }

  const userData = response.user || response;
  if (userData?.tenant?.slug) {
    definirTenantSessao(userData.tenant.slug);
  } else if (slug) {
    definirTenantSessao(slug);
  }

  return response;
}

/**
 * Realiza logout no backend Shield e limpa o estado e a sessão local de tenant.
 */
export async function logout() {
  try {
    await apiFetch('logout', {
      method: 'POST'
    });
  } catch (error) {
    // Mesmo que o servidor falhe, o frontend deve limpar o estado
    console.warn('[Auth] Logout server call failed:', error);
  } finally {
    limparTenantSessao();
  }
}

/**
 * Busca os dados do usuário autenticado.
 * Retorna null se não estiver autenticado.
 */
export async function getCurrentUser(): Promise<AuthUser | null> {
  return fetchCurrentUser();
}

/**
 * Fetch interno dos dados do usuário autenticado.
 * Tenta o endpoint 'user/info'. Se falhar, retorna null.
 */
async function fetchCurrentUser(): Promise<AuthUser | null> {
  try {
    const response = await apiFetch('user/info');

    // Se a resposta for um redirect para HTML (login page), o usuário não está autenticado
    if (response.redirected) {
      return null;
    }

    let userObj: AuthUser | null = null;
    if (response && (response.id || response.user_id || response.username || response.email)) {
      userObj = response;
    } else if (response?.user && (response.user.id || response.user.username)) {
      userObj = response.user;
    }

    if (userObj) {
      if (userObj.tenant?.slug) {
        definirTenantSessao(userObj.tenant.slug);
      }
      return userObj;
    }

    return null;
  } catch (error) {
    return null;
  }
}

/**
 * Retorna a URL da foto do usuário.
 */
export function getUserPhotoUrl(userId?: number | string | null): string | null {
  if (!userId) return null;
  return `${BASE_URL}usuarios/exibirFoto/${userId}`;
}
