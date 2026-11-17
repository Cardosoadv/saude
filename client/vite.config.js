import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { viteSingleFile } from 'vite-plugin-singlefile'

/**
 * As fontes empacotadas (@fontsource e bootstrap-icons) declaram woff2 e woff. Todo navegador
 * que roda o app lê woff2; tirar o woff evita embutir cada fonte duas vezes no vue.html.
 */
function somenteWoff2() {
  return {
    name: 'somente-woff2',
    enforce: 'pre',
    transform(codigo, id) {
      if (!/\.css(\?|$)/.test(id) || !/[\\/]node_modules[\\/](@fontsource|bootstrap-icons)[\\/]/.test(id)) return null
      return codigo.replace(/,\s*url\(\s*["']?[^)"']+\.woff(\?[^)"']*)?["']?\s*\)\s*format\(\s*["']woff["']\s*\)/g, '')
    },
  }
}

// https://vite.dev/config/
export default defineConfig({
  // Caminhos relativos: o vue.html é servido em /sistema/. Arquivos de public/ referenciados
  // como "/arquivo" no index.html viram "./arquivo" e não são renomeados com hash pelo build
  // (o postbuild copia manifest e ícones para a raiz, onde o Vite os trataria como assets).
  base: './',
  plugins: [
    somenteWoff2(),
    vue(),
    viteSingleFile({
      // Inline apenas os bundles gerados pelo Vite, excluindo config.js
      inlinePattern: ['**/*.css', '**/index-*.js'],
    }),
  ],
})
