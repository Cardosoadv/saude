# Saúde — Painel (front-end)

SPA em **Vue 3**, **TypeScript** e **Vite**. O build gera um único arquivo estático (`vue.html`) que fala
com a API do diretório [`../server`](../server/README.md).

## Telas

Clientes · CRM (pipeline e leads) · Tarefas · Contratos · Fornecedores · Financeiro (receitas, despesas,
transferências, extrato/OFX, balanço, DRE, fluxo de caixa, fechamento do mês) · Contabilidade (plano de contas,
lançamentos, livros e demonstrações) · Business Canvas · Site (páginas, notícias, menus, mídias, aparência,
templates) · Documentação · Configurações (usuários, módulos, MCP/IA, Google Drive, banco de dados) · Perfil.

O menu e as telas seguem os **módulos ativos** e as **permissões** do usuário (`useAcesso`, `utils/acessoTelas.ts`).

## Desenvolvimento

```bash
npm ci
cp config_exemplo.js public/config.js   # apiUrl e baseUrl do servidor
npm run dev        # servidor de desenvolvimento
npm run typecheck  # vue-tsc
npm test           # vitest
npm run build      # gera dist/ e copia o resultado para vue.html (+ PWA)
```

Em produção publique `vue.html`, `config.js`, `sw.js`, `manifest.webmanifest` e `icons/` na mesma pasta
(o service worker só controla a pasta onde está). Edite `config.js` no servidor para trocar a URL da API sem recompilar.

## PWA

O painel é instalável (menu do usuário › *Instalar app*), abre sem conexão e avisa quando há versão nova.
O `scripts/postbuild.js` grava a versão no `sw.js` a cada build com conteúdo diferente.
