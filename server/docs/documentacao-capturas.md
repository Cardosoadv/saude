# Documentação interna: capturas de tela

Como produzir as imagens das páginas de `app/Docs` (issue #708). O formato das páginas está em
[`documentacao-formato.md`](documentacao-formato.md).

## Regras

- **Só dados fictícios.** Nunca capture processos, clientes, valores ou nomes reais, nem com borrão:
  a imagem fica no repositório e no histórico do Git para sempre.
- Arquivo em `app/Docs/<secao>/img/`, nome em minúsculas com hífen: `processos/img/lista-filtros.webp`.
- **WebP**, com largura máxima de **1600 px**. `docs:verificar` acusa imagem mais larga.
- Texto alternativo obrigatório, descrevendo o que a imagem mostra: `![Lista de processos com o filtro de status aberto](img/lista-filtros.webp)`.
- Capture só a área que interessa (um painel, um formulário), não a tela inteira com o menu.
- O backend lê largura e altura do arquivo e põe `width`/`height` no `<img>`; não é preciso informar.

## Roteiro com o Playwright

A SPA (`CognitioTechny/sistema`) roda com o Vite, e o Playwright responde às chamadas da API com JSON
fictício. Assim a captura não depende de banco nem de login real.

1. No repositório `sistema`: `npm ci` e `npx vite --port 5173`.
2. Para cada tela, monte um arquivo JSON com as respostas fictícias que ela consome (veja as chamadas na aba
   *Network* do navegador ou nos serviços em `src/services/`).
3. Rode o script abaixo, ajustando a lista `CAPTURAS`.
4. Converta para WebP, se preciso (`cwebp -q 82 entrada.png -o saida.webp`), e confira com
   `php spark docs:verificar`.

```js
// capturar.mjs: node capturar.mjs (com o Vite do sistema rodando na porta 5173)
import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const USUARIO = {
  id: 1, username: 'demo', email: 'demo@exemplo.test',
  // Libere só o necessário para a tela capturada.
  permissoes: ['processos.access', 'documentacao.access'],
  modulos_ativos: ['processos', 'documentacao'],
};

// [arquivo de saída, hash da tela, seletor a capturar, respostas fictícias { 'caminho/da/api': corpo }]
const CAPTURAS = [
  ['processos/img/lista.webp', '#/processos', '.page-content', JSON.parse(readFileSync('ficticio/processos.json', 'utf8'))],
];

const browser = await chromium.launch();
for (const [saida, hash, seletor, respostas] of CAPTURAS) {
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
  await page.route('**/config.js', (r) => r.fulfill({
    contentType: 'application/javascript',
    body: "window.__APP_CONFIG__={apiUrl:'http://localhost:5173/api/v2/',baseUrl:'http://localhost:5173/'}",
  }));
  await page.route('**/api/v2/**', (r) => {
    const caminho = new URL(r.request().url()).pathname.replace('/api/v2/', '');
    const corpo = caminho === 'user/info' ? USUARIO : (respostas[caminho] ?? { success: true, data: [] });
    return r.fulfill({ contentType: 'application/json', body: JSON.stringify(corpo) });
  });
  await page.goto(`http://localhost:5173/${hash}`);
  await page.waitForLoadState('networkidle');
  await page.locator(seletor).first().screenshot({ path: `../ordojuris/app/Docs/${saida.replace(/\.webp$/, '.png')}` });
  await page.close();
}
await browser.close();
```

O script grava PNG: converta para WebP antes do commit e apague o PNG.
