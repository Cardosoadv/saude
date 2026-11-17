import { apiFetch } from './api';
import { tenantSlugAtual } from './tenant';

/** Dados públicos do escritório da URL (`GET tenant/atual`). */
export interface TenantAtual {
  slug: string;
  nome: string | null;
}

/**
 * Escritório que a API resolveu para esta URL, para a tela de login mostrar o nome. Devolve `null` em qualquer
 * falha (backend antigo, rede): o login funciona sem esse enfeite. Erros `tenant_*` já disparam a tela dedicada
 * pelo `apiFetch`.
 */
export async function buscarTenantAtual(tenantSlug?: string | null): Promise<TenantAtual | null> {
  try {
    const slug = tenantSlug ?? tenantSlugAtual();
    const endpoint = slug ? `tenant/atual?tenant=${encodeURIComponent(slug)}` : 'tenant/atual';
    const resposta = await apiFetch<{ tenant?: { slug?: string; nome?: string | null } }>(endpoint);
    const tenant = resposta?.tenant;
    if (!tenant || typeof tenant.slug !== 'string') return null;

    return { slug: tenant.slug, nome: typeof tenant.nome === 'string' && tenant.nome !== '' ? tenant.nome : null };
  } catch {
    return null;
  }
}
