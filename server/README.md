# OrdoJuris - API de Gestão Jurídica v7.1.0

API RESTful para gestão de escritórios de advocacia e departamentos jurídicos. Fornece endpoints para controle completo de processos, clientes, tarefas, finanças, contabilidade com partidas dobradas e conciliação financeira, contratos, intimações, CRM e site institucional gerenciável, servindo dados estruturados em JSON para consumo por aplicações frontend independentes (SPA Vue.js, portais de clientes, etc) e renderizando o portal público diretamente no backend.

Construída com **PHP 8.3** e **CodeIgniter 4.7**, a API segue uma arquitetura **MVC+S+R** (Model, View, Controller, Service, Repository) orientada a serviços e APIs RESTful, com autenticação via **CodeIgniter Shield** e motor de templates **Twig**.

> **Nota:** As Views mantidas neste repositório são para **geração de relatórios** (PDFs, DRE, Procurações, Substabelecimentos, Papel Timbrado, Sitemap XML), para o **Site Institucional OrdoJuris** e para o painel do cliente. Toda a interface de usuário do painel administrativo é responsabilidade do frontend desacoplado (Vue.js SPA).

---

## Principais Recursos da API

### Módulos Core & Governança

- **Módulo Contábil & Importação Financeira** — Integração com o componente agnóstico `cognitiotechny/contabil` (DDD/Arquitetura Limpa), com partidas dobradas, conciliação e importação direta a partir de receitas recebidas, despesas pagas e transferências bancárias, chave de idempotência para garantia de integridade, controle de exercícios/períodos fiscais, relatórios contábeis (Balancete de Verificação, Livro Razão, Livro Diário, DRE Contábil, Balanço Patrimonial) e exportação PDF.


- **Gestão Dinâmica de Módulos** — Ativação e desativação granular de módulos funcionais do sistema via banco de dados e API administrativa (`module.access`, `module.edit`). Filtro global HTTP `modulo:<slug>` que bloqueia requisições a módulos inativos com HTTP 403 Forbidden, payload de autenticação (`user/info`) enriquecido com permissões efetivas e módulos ativos, supressão automática de blocos embutidos nos detalhes de processos/clientes/casos e condicionamento dinâmico das ferramentas MCP.

- **Processos** — CRUD completo, filtros avançados, etiquetas, movimentos, partes, objetos e anotações.

- **Clientes** — Cadastro de PF/PJ, busca otimizada e associação com processos.

- **Tarefas** — Criação, atribuição, integração com Google Calendar.

- **Contratos** — Gestão de prazos, renovações, honorários de êxito e exportação CSV/PDF.

- **Intimações** — Registro, vínculo automático e sincronização com DJe.
- **Notificações push (PWA)** — Avisos de intimações novas e prazos de tarefas no celular/computador, com Web Push implementado sem dependências externas. Veja [docs/notificacoes-push.md](docs/notificacoes-push.md).

- **Privacidade Granular** — Controle de acesso por registro em Processos, Clientes e Contratos: cada um pode ser marcado como `público` (visível a todo o escritório) ou `restrito` (visível apenas ao responsável, aos advogados com acesso concedido e a quem tem a permissão `privacidade.bypass`). O responsável pode conceder/revogar acesso a outros advogados e propagar a lista de acessos do cliente para os processos e contratos vinculados a ele. Aplicado de forma transversal — inclusive a listas derivadas, como intimações, tarefas e indicadores do dashboard — e registrado na auditoria.

### Site Institucional Gerenciável (CMS & Templates)

- **Motor de Renderização Dinâmica (Twig)** — Motor de renderização desacoplado em sandbox seguro Twig servindo páginas, seções e temas diretamente do banco de dados (`site_configuracoes`, `site_paginas`, `site_secoes`, `site_menus`, `site_midias`, `site_templates`), sem acoplamento a código estático.

- **Templates Instaláveis (.zip)** — Ciclo de vida completo de templates instaláveis via upload de arquivo `.zip`, com validação rigorosa de integridade e manifesto (`template.json`), ativação, exportação e pré-visualização de páginas modelo (`paginas-exemplo`). Acompanha o template oficial "Clássico" com conversão de layout sem regressão visual.

- **API Administrativa RESTful (V2)** — Endpoints completos sob `/api/v2/site` para configurações institucionais, gestão de páginas públicas (status rascunho/publicado, slug, SEO), seções dinâmicas estruturadas com schemas declarativos, menus de navegação hierárquicos e temas.

- **Biblioteca de Mídias** — Upload seguro de imagens com validação MIME, sanitização, metadados (texto alternativo), integridade referencial antes de exclusão e endpoint para preview e seletor modal.

- **Personalização de Aparência** — Customização em tempo real de paletas de cores da marca (primária, secundária, neutras) e tipografia (fontes para títulos e corpo) via painel administrativo.

- **SEO Avançado & Sitemap** — Injeção dinâmica de metadados Open Graph, tags canônicas, dados estruturados JSON-LD Schema.org (`LegalService`) e geração automatizada de `sitemap.xml` a partir do banco de dados.

- **Cache & Performance** — Cache de páginas HTML renderizadas com invalidação reativa em cascata por eventos de alteração de conteúdo e comando de limpeza manual.

- **Comandos CLI Spark** — Utilitários de linha de comando para gestão de templates (`site:template:instalar`, `site:template:ativar`, `site:template:listar`) e cache (`site:cache:clear`).

- **Sincronização de Identidade** — Reutilização automática das configurações institucionais nos documentos gerados pelo sistema (Papel Timbrado, Procurações e Substabelecimentos).

### Módulos Financeiros

- **Receitas e Despesas** — Controle de lançamentos com categorias e contas.

- **DRE** — Demonstrativo de Resultados do Exercício (relatório PDF gerado server-side).

- **Fechamento de Mês** — Consolidação de saldos e bloqueio de lançamentos retroativos.

- **Transferências** — Movimentações entre contas com rastreabilidade completa.

### Módulos Complementares

- **CRM** — Funil de vendas (Kanban), oportunidades e conversão de leads do site.

- **Checklist** — Modelos de procedimentos com sugestão automática via IA (Gemini).

- **Business Canvas** — Planejamento estratégico com sticky notes interativos.

- **Legal Ops (BI)** — Dashboard de inteligência jurídica com métricas de risco e provisionamento.

- **Jurisprudência** — Repositório indexado com integração DJe e importação de acórdãos do STJ (legislação citada, jurisprudência citada e acórdãos similares), com busca full-text e resumo por IA.

- **Base de Conhecimento** — Artigos e tutoriais centralizados.

- **Mensagens** — Sistema de comunicação interna com notificações por e-mail.

- **Auditoria** — Rastreamento completo de alterações em todos os módulos.

### Geração de Documentos (Views de Relatório)

- **Procurações e Substabelecimentos** — Layout A4 otimizado com impressão em lote e dados dinâmicos da banca.

- **Papel Timbrado** — Layout institucional com visualização prévia em tela (`@media screen`) e renderização PDF via Dompdf.

- **DRE (PDF)** — Relatório financeiro com identidade visual completa.

- **Sitemap XML** — Geração dinâmica para indexação e SEO do portal público.

---

## Arquitetura

```text
┌──────────────────────────────────────────────────────────────┐
│                      Frontend (Vue.js SPA)                   │
│                 Portal do Cliente · Admin Panel              │
└──────────────────────────────────────────────────────────────┘
                       │  HTTP/JSON (REST)
                       ▼
┌──────────────────────────────────────────────────────────────┐
│                        API Gateway                           │
│              /api/v2/*  ·  CodeIgniter Shield                │
├──────────────────────────────────────────────────────────────┤
│  Controllers (ApiV2/)     Recebem requisição, verificam      │
│                           permissões, retornam JSON          │
├──────────────────────────────────────────────────────────────┤
│  Services                 Lógica de negócio, validações,     │
│                           formatação de dados                │
├──────────────────────────────────────────────────────────────┤
│  Repositories             Acesso a dados, queries complexas, │
│                           gerenciamento de cache versionado  │
├──────────────────────────────────────────────────────────────┤
│  Models                   Configuração de tabela, validação, │
│                           callbacks de auditoria (Trait)     │
├──────────────────────────────────────────────────────────────┤
│  Views (apenas relatórios)  PDFs, DRE, Procurações, XML      │
└──────────────────────────────────────────────────────────────┘
                       │
                       ▼
              Banco de Dados MySQL
```

### Camadas

| Camada         | Responsabilidade |

|----------------|-----------------|

| **Controllers** | Recebem requisições, verificam permissões via Shield e delegam ao Service. Retornam JSON ou, em casos de relatório, renderizam a View correspondente. **Não contêm lógica de negócio.** |

| **Services**    | Regras de negócio, validações complexas e orquestração. Comunicam-se prioritariamente com Repositories. |

| **Repositories** | Abstraem acesso a dados, centralizam queries complexas e gerenciam cache versionado. Estendem `BaseRepository`. |

| **Models**      | Configuração de tabela, regras de validação e callbacks de auditoria via `AuditoriaTrait`. |

| **Views**       | Geração de PDFs (DRE, Procurações), XML (Sitemap) e layouts de impressão, Site, Area do Cliente. |

| **Traits**      | Componentes reutilizáveis como `AuditoriaTrait` para log automático de operações. |

> Para detalhes da estrutura de diretórios, consulte `.agents/estrutura_diretorios.md`.

---

## Integração com IA externa (MCP)

Além da API REST, o OrdoJuris expõe um endpoint **MCP (Model Context Protocol)** em `/mcp` — o padrão aberto usado por Claude Desktop, Claude.ai, ChatGPT (Connectors) e outros clientes de chat para dar a um modelo acesso a ferramentas e dados de um sistema externo. Isso permite perguntar a uma IA, diretamente do chat, coisas como "quais tarefas venceram esta semana?" ou "me dê um resumo do processo X", sem sair do chat.

O mesmo endpoint atende dois tipos de chamador, diferenciados pelo header `Authorization: Bearer <token>`:

- **IA interna do sistema** (Davinci/GeminiService) — usa o token de serviço fixo definido em `mcp.internalToken` (.env). Não é pensado para uso externo.

- **Chats de IA externos** — cada usuário gera seu próprio token pessoal (com escopo restrito a `mcp`) e o usa para autenticar. A IA passa a agir estritamente como aquele usuário: toda leitura e escrita respeita as permissões dele, e nenhuma ação pode ser atribuída a outro usuário do escritório.

### Gerando um token pessoal

Autenticado na API (`/api/v2/login`), chame:

```bash

curl -X POST https://SEU_DOMINIO/api/v2/integracao-ia/tokens \

  -H "Authorization: Bearer <seu_token_de_sessão_ou_token_existente>" \

  -H "Content-Type: application/json" \

  -d '{"nome": "Claude Desktop"}'

```

A resposta traz o valor do token **uma única vez** — ele não pode ser recuperado depois, apenas revogado (`DELETE /api/v2/integracao-ia/tokens/{id}`) e substituído por um novo. Use `GET /api/v2/integracao-ia/tokens` para listar os tokens ativos (sem expor o valor).

### Conectando um chat de IA

Configuração de exemplo para um cliente MCP via HTTP (ex.: Claude Desktop, `claude_desktop_config.json`, ou qualquer cliente que suporte MCP remoto por Streamable HTTP):

```json

{

  "mcpServers": {

    "ordojuris": {

      "url": "https://SEU_DOMINIO/mcp",

      "headers": {

        "Authorization": "Bearer SEU_TOKEN_GERADO_ACIMA"

      }

    }

  }

}

```

As ferramentas disponíveis (`buscar_processos`, `obter_processo`, `buscar_clientes`, `obter_cliente`, `listar_tarefas`, `criar_tarefa`, `criar_anotacao_processo`, `listar_intimacoes`, `obter_intimacao`, `obter_indicadores_dashboard`) estão definidas em `app/Mcp/Tools/` e são descobertas automaticamente pelo servidor MCP (`app/Controllers/Mcp/McpController.php`) via atributos `#[McpTool]`.

---

## Stack Tecnológica

| Componente       | Tecnologia                    |

|------------------|-------------------------------|

| **Linguagem**    | PHP 8.3                       |

| **Framework**    | CodeIgniter 4.7               |

| **Autenticação** | CodeIgniter Shield             |

| **Banco de Dados** | MySQL                       |

| **PDF**          | Dompdf 3.1                    |

| **Motor de Templates** | Twig 3.14                     |

| **IA**           | Google Gemini API              |

| **Cache**        | Cache versionado (BaseRepository) |

| **Testes**       | PHPUnit 10/11 (SQLite em memória) |

---

## Instalação

```bash

# Clone o repositório

git clone <repo-url> processos

cd processos

# Instale as dependências

composer install

# Configure o ambiente

cp env .env

# Edite .env com suas credenciais de banco, Shield, etc.

# Execute as migrations

php spark migrate --all

# Inicie o servidor de desenvolvimento

php spark serve

```

### Extensões PHP Requeridas

`dom`, `mbstring`, `intl`, `sqlite3`, `mysql`, `curl`, `gd`, `fileinfo`

---

## Testes

```bash

# Executar testes unitários

vendor/bin/phpunit tests/unit

# Ambiente: SQLite em memória (:memory:)

```

---

## Convenções do Projeto

- **Idioma:** Todo código, PHPDoc e comunicação em **Inglês (en)** [Traduzir o que já existe apenas se o custo for muito baixo!].

- **Codificação:** Todos os arquivos em **UTF-8 (sem BOM)**.

- **Novos Controllers:** Obrigatoriamente sob `App\Controllers\ApiV2`, respondendo a `/api/v2/`.

- **Soft Deletes:** Coluna `deleted_at` para exclusão lógica.

- **Auditoria:** `AuditoriaTrait` em todos os Models principais.

- **Versionamento:** `vendor/`, `build/` e `.env` são ignorados.

---

## Licença

Proprietário — © OrdoJuris
