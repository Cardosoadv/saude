# Saúde — API e site público

API REST em **PHP 8.3** e **CodeIgniter 4.7**, autenticação com **CodeIgniter Shield**, arquitetura
Model/Repository/Service/Controller. O painel (Vue) fica em [`../client`](../client/README.md); o site público
da clínica é renderizado aqui, com templates Twig/PHP instaláveis.

## Módulos

| Módulo | O que faz |
|---|---|
| **Clientes** | cadastro (PF/PJ), responsável e visibilidade por registro, pasta no Google Drive |
| **Usuários e permissões** | grupos (`superadmin`, `admin`, `profissional`, `recepcao`, `user`), permissões granulares, perfil, “esqueci minha senha” |
| **CRM** | funil de oportunidades, leads vindos do site, interações |
| **Tarefas** | kanban/lista, responsável, prazo, notificação por e-mail e push (PWA), Google Calendar |
| **Contratos** | planos, convênios e prestação de serviços, aditivos, arquivos no Drive |
| **Financeiro** | receitas, despesas, transferências, rateio, extrato OFX com conciliação, DRE, balanço, fluxo de caixa, fechamento do mês |
| **Contábil** | partidas dobradas (pacote `cognitiotechny/contabil`), plano de contas ITG 1000, livros e demonstrações |
| **Fornecedores** | cadastro e conversão cliente ⇄ fornecedor |
| **Site** | CMS: páginas e seções, notícias, menus, mídias, tema, templates `.zip`, SEO (`sitemap.xml`, `robots.txt`, `llms.txt`) |
| **MCP / IA** | endpoint `/mcp` para assistentes de IA agirem com as permissões do usuário |
| **Documentação** | manual do sistema em Markdown (`app/Docs`), com ajuda contextual |
| **Módulos** | ativação/desativação de módulos em tempo de execução |

## Instalação

```bash
composer install
cp env .env                                  # banco, app.baseURL, e-mail...
php spark app:instalar --email voce@clinica.com --usuario admin --senha 'SenhaForte#123'
php spark serve
```

`app:instalar` roda as migrations, carrega os cadastros-base (módulos, categorias financeiras, funil do CRM,
plano de contas) e cria o superadmin. Pode ser repetido sem duplicar dados.

Sem acesso ao terminal (hospedagem compartilhada), as migrations também rodam em **Configurações › Banco de dados**.

## Comandos úteis

| Comando | Para que serve |
|---|---|
| `php spark migrate` | aplica migrations pendentes |
| `php spark contabil:migrate` | aplica as migrations do pacote contábil |
| `php spark docs:verificar` | valida a documentação em `app/Docs` |
| `php spark push:vapid` / `push:notificar` | chaves e envio das notificações push |
| `php spark drive:sync` | processa a fila de pastas do Google Drive |
| `php spark financeiro:fechar-mes` | fecha o mês financeiro |

## Testes

```bash
vendor/bin/phpunit
```

Os testes usam SQLite em memória.

## Integração com IA (MCP)

O endpoint `/mcp` expõe ferramentas (`app/Mcp/Tools`) a clientes MCP (Claude, ChatGPT...). Gere um token pessoal em
**Configurações › Integração IA (MCP)** ou conecte o conector nativo do Claude.ai. Toda ação respeita as
permissões do usuário que autorizou. Veja `app/Docs/administracao/integracao-ia.md`.

## Convenções

- Controllers novos em `App\Controllers\ApiV2`, rotas em `app/Routes/apiV2.php`.
- Exclusão lógica (`deleted_at`) e auditoria (`AuditoriaTrait`) nos models principais.
- `vendor/`, `.env` e `writable/` não são versionados.
