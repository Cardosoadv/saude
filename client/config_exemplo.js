/**
 * Configuração de runtime — Saúde
 *
 * Este arquivo é carregado ANTES do bundle compilado do Vue e permite configurar a aplicação em
 * produção SEM recompilar. Copie para `config.js` (ao lado do vue.html) e ajuste.
 */
window.__APP_CONFIG__ = {

  /**
   * URL base da API (com barra final).
   * Exemplos:
   *   - Local:     'http://localhost:8080/api/v2/'
   *   - Produção:  'https://exemplo.com.br/api/v2/'
   */
  apiUrl: 'http://localhost:8080/api/v2/',

  /**
   * URL base do site (sem /api/v2/), usada para assets do backend (logos, avatares, etc.).
   * Exemplos:
   *   - Local:     'http://localhost:8080/'
   *   - Produção:  'https://exemplo.com.br/'
   */
  baseUrl: 'http://localhost:8080/',

};
