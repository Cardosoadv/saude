# Ordo Juris — Painel Processual (Front-End)

Este é o projeto front-end para a carteira de processos judiciais da **Cardoso & Bruno Sociedade de Advogados**, desenvolvido em **Vue 3**, **TypeScript** e **Vite** com um Design System premium sob medida em **Vanilla CSS**.

**Versão Atual:** `7.4.1`

---

## ✨ Recursos Principais
- **Módulo Contábil Integrado**: Escrituração contábil em partidas dobradas, conciliação e importação direta do financeiro (receitas recebidas, despesas pagas e transferências) com o componente `cognitiotechny/contabil`, plano de contas oficial, livros contábeis (Diário, Razão) e demonstrações financeiras (Balancete de Verificação, DRE e Balanço Patrimonial).
- **Controle Dinâmico de Módulos & Permissões**: Interceptação reativa de rotas, menus e visibilidade de widgets conforme os módulos ativos e permissões do usuário via composable `useAcesso`. Inclui painel administrativo de ativação/desativação de módulos (`module.edit`) e tratamento automático de bloqueios 403 da API com modal contextual e recarga de sessão.
- **Painel Administrativo do Site Institucional (CMS)**: Suíte completa de gestão de conteúdo para o portal público sob `/site`, desacoplada da renderização do site:
  - **Configurações**: Dados da banca, contatos, redes sociais e scripts de rastreamento.
  - **Biblioteca de Mídias**: Upload de imagens, edição de texto alternativo e modal reutilizável para seleção de imagens em qualquer seção.
  - **Páginas & SEO**: Criação e publicação de páginas públicas com suporte a metadados SEO.
  - **Editor de Seções & SchemaForm**: Adição, reordenação (drag-and-drop) e parametrização dinâmica de seções baseadas no schema declarativo do template ativo.
  - **Menus**: Estruturação de menus de cabeçalho, rodapé e links externos/internos.
  - **Aparência**: Personalização em tempo real das cores da marca e tipografia do template.
  - **Templates**: Galeria de templates instalados, upload de pacotes `.zip`, ativação e exportação.
- **Comprovantes de Pagamento (Google Drive)**: anexo de PDF, JPG, PNG e WEBP (até 10 MB, 5 por vez) nas telas de edição de pagamentos de despesas e de recebimentos de receitas, com lista, link para o Drive e remoção; as listagens mostram o clipe com a contagem de anexos. Componente [ComprovantesPagamento.vue](src/components/Financeiro/ComprovantesPagamento.vue) e cliente [comprovantesApi.ts](src/services/comprovantesApi.ts). Requer a API do ordojuris com os endpoints de comprovantes.
- **PWA (app instalável)**: o painel pode ser instalado no celular e no computador (menu do usuário → *Instalar app*; no iPhone/iPad, *Compartilhar → Adicionar à Tela de Início*), abre sem conexão e avisa quando há versão nova. Veja a seção [PWA](#-pwa-app-instalável).
- **TypeScript**: Todo o código (`.vue` com `<script setup lang="ts">`, serviços, composables, utils e testes) foi migrado para TypeScript. Veja a seção [Migração para TypeScript](#-migração-para-typescript).
- **Arquitetura Modular (SFC)**: Divisão de um código monolítico antigo em componentes altamente focados e reutilizáveis (layouts, widgets, modais, views).
- **Single-File Bundling**: Configurado com o plugin `vite-plugin-singlefile` para compilar e embutir todo o HTML, CSS e JavaScript reativo em um único arquivo HTML estático.
- **Pipeline de Build Cross-Platform**: O comando de build gera o arquivo empacotado e executa um script pós-build em Node que copia o resultado diretamente para [vue.html](vue.html) na raiz do projeto, tornando-o pronto para ser aberto estaticamente em qualquer navegador ou servidor local (XAMPP).
- **Mocks de Alta Fidelidade**: O módulo [api.ts](src/services/api.ts) simula com exatidão a resposta de requisições de rede, permitindo filtros em tempo real, adição/remoção de movimentações, tarefas, anotações e troca de estados offline.
- **Design System Premium**: Cores exclusivas (Navy Blue, Accent Warm Gold), fontes elegantes Playfair Display/DM Sans, e acabamento em glassmorphism nas tabelas e cartões.

---

## 📁 Estrutura do Projeto

```
front/
├── .agents/
│   └── relatorio_evolucao.md  # Relatório de controle evolucionário (1.3.3)
├── public/                    # Assets estáticos globais
├── scripts/
│   └── postbuild.js           # Utilitário de pós-processamento de build (Node)
├── src/
│   ├── assets/                # Imagens e arquivos visuais
│   ├── components/            # layout, modals, widgets, reports, editor, documentos, Financeiro
│   ├── composables/           # useClientAutocomplete, useSearchAutocomplete, useDocumentoEditor (.ts)
│   ├── services/
│   │   ├── api.ts             # Chamadas de rede, debounce e mocks de fidelidade
│   │   └── auth.ts            # Autenticação
│   ├── types/
│   │   └── domain.ts          # Tipos de domínio compartilhados (Id, Etiqueta, Anotacao, ...)
│   ├── utils/
│   │   └── formatters.ts      # Formatadores
│   ├── views/                 # Uma pasta por módulo (Processos, Clientes, Financeiro, ...)
│   ├── App.vue                # Orquestrador central e controle global de toasts
│   ├── main.ts                # Inicialização e montagem do Vue 3
│   └── style.css              # Design System e classes CSS centralizadas
├── tests/                     # Testes Vitest (.ts)
├── env.d.ts                   # Tipos globais (import.meta.env, window.__APP_CONFIG__, ...)
├── tsconfig.json              # Configuração do TypeScript
├── index.html                 # Template estático raiz do Vite
├── package.json               # Dependências e scripts do Node
├── vite.config.js             # Configuração do compilador Vite + SingleFile
├── sw.js, manifest.webmanifest, icons/  # PWA (gerados pelo postbuild a partir de public/)
└── vue.html                   # Arquivo autônomo compilado final (Build Output)
```

---

## 🛠️ Como Executar e Construir

### Pré-requisitos
Certifique-se de ter o [Node.js](https://nodejs.org/) instalado em seu sistema (versão recomendada >= 18).

### 1. Instalar as Dependências
Abra o terminal na pasta do projeto e execute:
```bash
npm install
```

### 2. Iniciar o Servidor de Desenvolvimento
Inicie o servidor local do Vite com HMR (Hot Module Replacement):
```bash
npm run dev
```
O terminal exibirá o endereço IP local (geralmente `http://localhost:5173`) para acessar o painel em tempo real.

### 3. Compilar para Produção (Arquivo Único `vue.html`)
Para compilar o código em um único arquivo HTML autônomo otimizado e atualizado:
```bash
npm run build
```
O build executa antes a checagem de tipos (`vue-tsc --noEmit`); se houver erro de tipo, o build é interrompido. O script compilará a aplicação e colocará o arquivo otimizado diretamente em `./vue.html`. Agora você pode abrir este arquivo clicando duas vezes sobre ele no Windows Explorer ou distribuí-lo de forma autônoma!

### 4. Testes e Checagem de Tipos
```bash
npm test          # Executa os testes (Vitest)
npm run typecheck # Checagem de tipos com vue-tsc, sem gerar arquivos
```

---

## 📱 PWA (app instalável)

O painel é um Progressive Web App. O que faz parte:

| Arquivo | Papel |
|---|---|
| [public/manifest.webmanifest](public/manifest.webmanifest) | Nome, cores, ícones e atalhos (Processos, Tarefas, Intimações) do app instalado |
| [public/icons/](public/icons) | Ícones 192/512, *maskable* 512, `apple-touch-icon` 180 e `badge-96` (monocromático, para a barra do Android), gerados do emblema do `logo.png` |
| [public/sw.js](public/sw.js) | Service worker: guarda só o casco do app (`vue.html`, `config.js`, manifest e ícones) |
| [src/composables/usePwa.ts](src/composables/usePwa.ts) | Registro do SW, aviso de versão nova, instalação e estado da conexão |
| [src/components/layout/PwaAvisos.vue](src/components/layout/PwaAvisos.vue) | Faixa "Sem conexão" e cartão "Nova versão disponível — Atualizar" |

**Regras de cache do service worker**
- Abertura do app (`/sistema/`, `vue.html`) e `config.js`: rede primeiro, mas com limite de 3 s. Se a rede falhar ou demorar e houver cópia em cache, abre com a cópia e atualiza em segundo plano (rede móvel ruim não deixa o app preso na tela de abertura).
- Manifest e ícones: cache primeiro.
- **API e qualquer outro endereço: nunca interceptados nem guardados** (dados sigilosos / LGPD).

**Fontes e ícones empacotados**: DM Sans, Playfair Display (`@fontsource`, só o subconjunto latino) e Bootstrap Icons são importados em `src/main.ts` e embutidos no `vue.html`, só em woff2 (plugin `somenteWoff2` no `vite.config.js`). Antes vinham de CDN e bloqueavam a primeira pintura: com rede ruim, o app instalado no Android ficava parado na tela de abertura.

**Build e deploy**
- `vite.config.js` usa `base: './'` e o `index.html` referencia manifest, ícones e favicon como arquivos públicos (`/manifest.webmanifest`), para o build não renomeá-los com hash (os renomeados não vão no deploy).
- O SW só é registrado no build de produção, em `https` ou `localhost` (nunca no `npm run dev` nem em `file://`).
- O `postbuild.js` grava em `sw.js` a versão `<versão do package.json>-<hash do build>` e copia `sw.js`, `manifest.webmanifest` e `icons/` para a raiz, ao lado do `vue.html`. Esses arquivos estão no `.gitignore`.
- O deploy (`.github/workflows/deploy.yml`) envia esses arquivos junto com o `vue.html`. Se faltarem no servidor, o `.htaccess` devolve o `vue.html` no lugar e o registro do SW falha.
- O [.htaccess](.htaccess) define `Cache-Control: no-cache` para `sw.js`, `vue.html`, `config.js` e o manifest, e o tipo `application/manifest+json`. **O `.htaccess` não é enviado pelo deploy**: atualize-o manualmente no servidor.

**Notificações push (fase 3)**: no Perfil, o cartão *Notificações neste aparelho* ([NotificacoesPushCard.vue](src/components/Perfil/NotificacoesPushCard.vue) + [usePush.ts](src/composables/usePush.ts)) pede a permissão, inscreve o aparelho e guarda as preferências (intimações novas / prazos das minhas tarefas). O `sw.js` exibe a notificação e, no clique, abre ou foca o painel na rota indicada (só rotas `#/...` do próprio painel). Ao entrar, uma inscrição deixada por outro usuário no mesmo navegador é cancelada; ao clicar em *Sair*, o aparelho é desvinculado. Requer o backend com `api/v2/push` e as chaves VAPID configuradas (veja `docs/notificacoes-push.md` no ordojuris). No iPhone/iPad, só com o app instalado (iOS 16.4+).

**Atualizações**: a cada deploy o navegador encontra o `sw.js` novo (ao abrir o app, ao voltar para a aba ou a cada 1 h) e o app mostra "Nova versão disponível". Ao clicar em *Atualizar*, a versão nova assume e a página recarrega.

---

## 🟦 Migração para TypeScript

O front foi migrado de JavaScript para TypeScript (PR #147, `refactor/typescript-migration`), em três etapas:

1. **Infraestrutura, serviços, composables e testes** — adição do TypeScript, `vue-tsc`, `tsconfig.json` e `env.d.ts`; conversão de `services/`, `composables/`, `utils/` e `tests/`.
2. **Componentes** — todos os componentes em `<script setup lang="ts">`, com os tipos de domínio compartilhados em [src/types/domain.ts](src/types/domain.ts).
3. **Views e `App.vue`** — conversão das views e do `App.vue` para `lang="ts"`.

**Estado atual**
- Todos os arquivos `.js` e `.vue` de `src/` e `tests/` já estão em TypeScript (`main.js` → `main.ts`, `api.js` → `api.ts`). Restam em JS apenas arquivos de configuração/build: `vite.config.js`, `scripts/postbuild.js`, `config.js` e `config_exemplo.js`.
- O modo `strict` está **desligado** em [tsconfig.json](tsconfig.json) (`strict: false`, `noImplicitAny: false`); apertar os tipos é a próxima etapa.
- O `npm run build` roda `vue-tsc --noEmit` antes do Vite.
- A versão do TypeScript está fixada em `~5.9` (compatibilidade com o `vue-tsc`).

**Convenções**
- Novos arquivos devem ser criados em TypeScript (`.ts` ou `.vue` com `lang="ts"`).
- Tipos de entidades do backend ficam em `src/types/domain.ts`. O PHP pode devolver `id` ou `id_<entidade>`, e IDs/flags como número ou string — por isso o tipo `Id` e vários campos opcionais.
- Configuração de runtime injetada por `config.js` (`window.__APP_CONFIG__`) é tipada em `env.d.ts`.
