/**
 * Configuração de Runtime — Ordo Juris
 * 
 * Este arquivo é carregado ANTES do bundle compilado do Vue e permite
 * configurar a aplicação em produção SEM necessidade de recompilar.
 *
 * Em produção, edite as propriedades abaixo conforme o ambiente.
 * Quando servido via vue_index.php, estas variáveis são sobrescritas
 * pelo PHP automaticamente.
 *
 * @version 1.5.60
 */
window.__APP_CONFIG__ = {

  /**
   * URL base da API (com barra final).
   * Exemplos:
   *   - Local:     'http://localhost/processos/api/v2/'
   *   - Produção:  'https://cardosoebruno.adv.br/ordo/cb/api/v2/'
   */
  apiUrl: 'http://localhost/processos/api/v2/',

  /**
   * URL base do site (sem /api/v2/).
   * Usado para referenciar assets do backend (logos, avatares, etc.)
   * Exemplos:
   *   - Local:     'http://localhost/processos/'
   *   - Produção:  'https://cardosoebruno.adv.br/ordo/cb/'
   */
  baseUrl: 'http://localhost/processos/',

  /**
   * (Opcional) Slug do tenant (escritório).
   * Normalmente NÃO é necessário: o sistema é servido em `https://<host>/<tenant>/sistema/` e o slug
   * é lido dessa URL (segmento antes de `sistema`), sendo enviado à API no cabeçalho `X-Tenant-Slug`.
   * Use esta opção apenas quando a URL não seguir esse padrão (ex.: desenvolvimento local).
   * Tem prioridade sobre o slug da URL. Exemplo: 'cb'
   */
  // tenantSlug: 'cb',

};
