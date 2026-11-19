/**
 * Service Worker — Saúde (PWA, fase 1)
 *
 * Guarda em cache só o "casco" do app (vue.html, config.js, manifest e ícones)
 * para abrir sem conexão e permitir a instalação. Requisições à API e a
 * qualquer outro endereço NÃO são interceptadas: dados processuais e
 * financeiros nunca vão para o cache (LGPD / computadores compartilhados).
 *
 * __BUILD_VERSION__ é substituído pelo scripts/postbuild.js a cada build; a
 * mudança do conteúdo deste arquivo é o que faz o navegador instalar a versão
 * nova e o app exibir o aviso "Nova versão disponível".
 */
const VERSAO = '__BUILD_VERSION__';
const PREFIXO = 'saude-shell-';
const CACHE = PREFIXO + VERSAO;

/** Base do escopo (ex.: https://exemplo.com.br/sistema/). */
const BASE = new URL('./', self.location.href);
const url = (caminho) => new URL(caminho, BASE).href;

const DOCUMENTO = url('vue.html');
const CONFIG = url('config.js');
const ESTATICOS = [
  url('manifest.webmanifest'),
  url('icons/icon-192.png'),
  url('icons/icon-512.png'),
  url('icons/icon-maskable-512.png'),
  url('icons/apple-touch-icon.png'),
  url('icons/badge-96.png'),
];

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE);
    // O documento é obrigatório; sem ele a instalação falha e a versão anterior segue ativa.
    await cache.add(new Request(DOCUMENTO, { cache: 'reload' }));
    // Os demais são opcionais (ex.: config.js pode não existir em algum ambiente).
    await Promise.all([CONFIG, ...ESTATICOS].map((u) =>
      cache.add(new Request(u, { cache: 'reload' })).catch(() => undefined)
    ));
  })());
  // Não chama skipWaiting(): a versão nova espera o usuário clicar em "Atualizar".
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const nomes = await caches.keys();
    await Promise.all(nomes
      .filter((n) => n.startsWith(PREFIXO) && n !== CACHE)
      .map((n) => caches.delete(n)));
    await self.clients.claim();
  })());
});

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') self.skipWaiting();
});

/** Tempo máximo esperando a rede antes de abrir com a cópia guardada (rede móvel ruim). */
const LIMITE_REDE_MS = 3000;

/**
 * Rede primeiro, com limite de tempo: se a rede falhar ou demorar mais que `LIMITE_REDE_MS`
 * e houver cópia em cache, responde com a cópia e deixa a rede terminar em segundo plano,
 * atualizando o cache para a próxima abertura. Sem cópia, espera a rede o tempo que for.
 * `tipo` restringe o que pode substituir a cópia (ex.: só HTML vira o documento do app).
 */
async function redePrimeiro(event, request, chave, tipo) {
  const cache = await caches.open(CACHE);

  const daRede = fetch(request).then(async (resposta) => {
    const tipoOk = !tipo || (resposta.headers.get('content-type') || '').includes(tipo);
    if (resposta.ok && resposta.type === 'basic' && !resposta.redirected && tipoOk) {
      await cache.put(chave, resposta.clone());
    }
    return resposta;
  });
  // Mantém o SW vivo até a rede terminar, mesmo que a página já tenha recebido a cópia.
  event.waitUntil(daRede.catch(() => undefined));

  const salva = await cache.match(chave);
  if (!salva) return daRede;

  let temporizador;
  const limite = new Promise((resolve) => { temporizador = setTimeout(() => resolve(salva), LIMITE_REDE_MS); });
  return Promise.race([daRede.catch(() => salva), limite]).finally(() => clearTimeout(temporizador));
}

/** Cache primeiro; busca na rede só o que não estiver guardado. */
async function cachePrimeiro(request) {
  const salva = await caches.match(request, { cacheName: CACHE });
  return salva || fetch(request);
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const destino = new URL(request.url);
  destino.hash = '';
  // Fora da origem ou fora do escopo (inclui a API em /api/...): não intercepta.
  if (destino.origin !== BASE.origin || !destino.pathname.startsWith(BASE.pathname)) return;

  // Abertura do app (/sistema/, /sistema/vue.html ou qualquer rota reescrita pelo .htaccess).
  if (request.mode === 'navigate') {
    event.respondWith(redePrimeiro(event, request, DOCUMENTO, 'text/html'));
    return;
  }

  destino.search = '';
  const limpo = destino.href;

  if (limpo === CONFIG) {
    event.respondWith(redePrimeiro(event, request, CONFIG, 'javascript'));
    return;
  }

  if (ESTATICOS.includes(limpo)) {
    event.respondWith(cachePrimeiro(request));
  }
  // Qualquer outra coisa segue direto para a rede, sem passar pelo cache.
});

// ─── Notificações push (fase 3) ───────────────────────────────────────────
// O servidor envia JSON cifrado: { title, body, url, tag, timestamp }. `url` é uma rota
// do painel (ex.: "#/tarefas"), relativa à pasta do app.

self.addEventListener('push', (event) => {
  let dados = {};
  try {
    dados = event.data ? event.data.json() : {};
  } catch (e) {
    dados = { body: event.data ? event.data.text() : '' };
  }

  const titulo = dados.title || 'Saúde';
  event.waitUntil(self.registration.showNotification(titulo, {
    body: dados.body || '',
    icon: url('icons/icon-192.png'),
    badge: url('icons/badge-96.png'),
    tag: dados.tag || undefined,
    // Mesmo tag (ex.: "prazos"): substitui o aviso anterior em vez de empilhar, mas avisa de novo.
    renotify: Boolean(dados.tag),
    timestamp: dados.timestamp || Date.now(),
    lang: 'pt-BR',
    data: { url: destinoSeguro(dados.url) },
  }));
});

/** Só aceita rotas do próprio painel (nada de endereços externos vindos da mensagem). */
function destinoSeguro(rota) {
  if (typeof rota !== 'string' || !rota.startsWith('#/')) return BASE.href;
  return BASE.href + rota;
}

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const destino = (event.notification.data && event.notification.data.url) || BASE.href;

  event.waitUntil((async () => {
    const janelas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    // Reaproveita uma janela do painel já aberta: muda a rota (hash) e traz para frente.
    const aberta = janelas.find((c) => c.url.startsWith(BASE.href));
    if (aberta) {
      // A página troca só a rota (hash), sem recarregar — ver usePwa.ts.
      aberta.postMessage({ type: 'NAVEGAR', url: destino });
      return aberta.focus();
    }
    return self.clients.openWindow(destino);
  })());
});
