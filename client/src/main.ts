import { createApp } from 'vue'
// Fontes e ícones empacotados (antes vinham de CDN e bloqueavam a abertura com rede ruim).
// Só o subconjunto latino, nos pesos usados pelo design system.
import '@fontsource/dm-sans/latin-300.css'
import '@fontsource/dm-sans/latin-400.css'
import '@fontsource/dm-sans/latin-500.css'
import '@fontsource/dm-sans/latin-600.css'
import '@fontsource/playfair-display/latin-600.css'
import '@fontsource/playfair-display/latin-700.css'
import 'bootstrap-icons/font/bootstrap-icons.min.css'
import './style.css'
import App from './App.vue'
import '@fortawesome/fontawesome-free/css/all.css'
import { iniciarPwa } from './composables/usePwa'

createApp(App).mount('#app')

// PWA: o service worker só é registrado no build de produção (vue.html servido via https).
iniciarPwa({ registrar: import.meta.env.PROD })
