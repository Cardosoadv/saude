/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_URL?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}

/** Configuração de runtime carregada por config.js antes do bundle. */
interface AppConfig {
  /** URL base da API (com barra final). */
  apiUrl?: string
  /** URL base do site (sem /api/v2/). */
  baseUrl?: string
  /**
   * Slug do tenant, para quando a URL não segue `/<tenant>/sistema/` (ex.: desenvolvimento).
   * Tem prioridade sobre o slug lido da URL.
   */
  tenantSlug?: string
}

interface Window {
  __APP_CONFIG__?: AppConfig
  /** Definido pelo PHP quando servido via vue_index.php. */
  BASE_URL?: string
  /** Cache legado (script externo), presente apenas em alguns ambientes. */
  CacheManager?: {
    get(key: string): any
    set(key: string, value: any, minutes?: number): void
  }
}
