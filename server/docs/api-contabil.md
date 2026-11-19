# API do Módulo Contábil & Integração Financeira

Contrato dos endpoints do módulo contábil consumidos pela SPA (`CognitioTechny/sistema`) e integrados
ao pacote agnóstico `cognitiotechny/contabil` via adaptadores de arquitetura hexagonal.

- [Convenções](#convenções) · [Permissões](#permissões) · [Arquitetura de Ports](#arquitetura-de-ports)
- [Endpoints de Integração](#endpoints-de-integração-financeira)
- [Endpoints do Pacote Contábil](#endpoints-do-pacote-contábil)
- [Regras de Integridade e Idempotência](#regras-de-integridade-e-idempotência)
- [Mapa de Telas e Ajuda Contextual](#mapa-de-telas-e-ajuda-contextual)

---

## Convenções

Base: `/api/v2/contabil`. Autenticação via sessão Shield (`credentials: 'include'`).
Todas as rotas utilizam o filtro de módulo `modulo:contabil` e filtros de permissão `permission:contabil.*`.

| Código HTTP | Significado | Formato |
|---|---|---|
| 200 / 201 | Sucesso | `{"success": true, "data": …}` ou `{"data": …}` |
| 401 | Não autenticado | Resposta padrão do filtro Shield |
| 403 | Permissão insuficiente ou módulo contábil inativo | `{"success": false, "code": "modulo_inativo", "modulo": "contabil", …}` |
| 404 | Recurso inexistente (conta, lançamento, período) | `{"success": false, "message": "…"}` |
| 422 | Erro de validação ou regra contábil violada (ex.: período fechado, desbalanceamento) | `{"success": false, "error": "…", "messages": {…}}` |

---

## Permissões

Matriz de permissões gerenciada pelo Shield (`app/Config/AuthGroups.php`):

| Permissão | Finalidade |
|---|---|
| `contabil.access` | Acesso básico de consulta ao módulo contábil, listagem de contas e transações. |
| `contabil.create` | Criação de lançamentos manuais, contas analíticas e importação do financeiro. |
| `contabil.edit` | Alteração cadastral de contas, classificação e estorno de lançamentos contábeis. |
| `contabil.delete` | Exclusão de contas inativas e reabertura excepcional de períodos contábeis fechados. |
| `contabil.relatorios` | Emissão e visualização de Balancete, DRE, Balanço Patrimonial e Livro Razão. |

---

## Arquitetura de Ports

O pacote `cognitiotechny/contabil` é agnóstico e opera por injeção de dependências via interfaces (Ports)
implementadas em `app/Libraries/Contabil/`:

1. **`SaudeContabilAuthorizer`** (`AuthorizerInterface`):
   - Mapeia as ações do pacote (`contabil.*`) na biblioteca Shield do usuário corrente.
2. **`SaudeContabilCurrentUser`** (`CurrentUserInterface`):
   - Extrai o identificador do usuário autenticado no sistema para carimbo de auditoria.
3. **`SaudeContabilCurrentEntity`** (`CurrentEntityInterface`):
   - Fornece o ID e metadados da entidade contábil ativa (padrão: "Clínica Saúde").
4. **`SaudeContabilPdfRenderer`** (`PdfRendererInterface`):
   - Renderiza relatórios e demonstrações em formato PDF utilizando Dompdf.

Os serviços são configurados em `app/Config/Services.php` e carregados automaticamente pelo CodeIgniter.

---

## Endpoints de Integração Financeira

Controlados por `App\Controllers\ApiV2\Contabil\IntegracaoController`:

### 1. Transações para Conciliação
`GET /api/v2/contabil/integracao/transacoes`
- **Query Params**:
  - `data_inicio`: data inicial (`YYYY-MM-DD`, opcional)
  - `data_fim`: data final (`YYYY-MM-DD`, opcional)
  - `tipo`: filtro por tipo (`receita`, `despesa`, `transferencia` ou vazio para todos)
  - `status`: filtro por status (`pendente`, `importado` ou vazio para todos)
- **Resposta**:
  ```json
  {
    "success": true,
    "data": [
      {
        "uid": "rec_45",
        "tipo": "receita",
        "id_origem": 45,
        "data": "2026-10-15",
        "descricao": "Atendimentos - Consulta de 10/11/2026",
        "valor": 5000.0,
        "conta_financeira": "Banco do Brasil",
        "categoria_financeira": "Atendimentos",
        "status_contabil": "pendente",
        "id_lancamento": null,
        "sugestao_debito": 14,
        "sugestao_credito": 82
      }
    ],
    "kpis": {
      "total_geral": 120,
      "total_pendentes": 15,
      "total_importadas": 105,
      "valor_pendente": 18450.0,
      "valor_importado": 342900.0
    }
  }
  ```

### 2. Contas Analíticas para Seleção
`GET /api/v2/contabil/integracao/contas`
- **Query Params**: `entity_id` (opcional, padrão entidade ativa)
- **Resposta**: lista plana de contas analíticas ativas com `id`, `code`, `name`, `type`, `nature`.

### 3. Importação em Lote
`POST /api/v2/contabil/integracao/importar`
- **Corpo**:
  ```json
  {
    "transacoes": [
      {
        "uid": "rec_45",
        "tipo": "receita",
        "id_origem": 45,
        "conta_debito_id": 14,
        "conta_credito_id": 82
      }
    ]
  }
  ```
- **Resposta**:
  ```json
  {
    "success": true,
    "importadas": 1,
    "falhas": [],
    "total": 1
  }
  ```

### 4. Modelos e Inicialização de Plano de Contas
- `GET /api/v2/contabil/integracao/planos-modelo`: retorna os modelos disponíveis. O primeiro, `itg-1000-clinica`, é o plano padrão do sistema; os demais (`professional-services`, `generic`) vêm do pacote contábil.
- `POST /api/v2/contabil/integracao/plano-padrao`: importa e inicializa o plano modelo no banco (corpo `{ "model": "itg-1000-clinica", "entity_id": 1 }`).
- Instalações com o plano anterior convertem o plano pelo comando `php spark contabil:adequar-itg1000` (veja a página de ajuda *Plano de contas*).

### 5. Abertura Contábil
- `POST /api/v2/contabil/integracao/abertura`: lança os saldos iniciais em um único lançamento de várias linhas.
  Corpo: `{ "data": "2026-01-01", "descricao": "", "substituir": false, "entity_id": 1, "saldos": [{ "code": "1.1.1.02.01", "side": "D", "amount": 3426.65 }] }`
  (`amount` em reais). Usa o caso de uso `OpeningBalances` do pacote contábil (v1.1.0), com `source_type` `contabil.opening-balances`.
  Só aceita contas patrimoniais folha ativas, uma vez cada; recusa (400) saldos que não fecham ao centavo e datas que não sejam
  o primeiro dia do exercício. Cria o exercício fiscal do ano civil se faltar. A abertura vai só no primeiro exercício da entidade
  (409 nos seguintes ou em exercício fechado). Repetir com os mesmos saldos devolve 200 com `created: false`; com outros saldos
  devolve 409, a não ser que `substituir` seja `true`, que estorna a abertura anterior e lança a nova na mesma transação.
  `postar: false` é recusado (400): a abertura não tem rascunho.

### 6. Dicionário Categoria → Conta
Guarda, por entidade, qual conta contábil recebe a perna "da categoria" financeira: o **crédito** nas receitas
e o **débito** nas despesas (tabela `contabil_categorias_contas`, chave `entity_id` + `categoria_id` + `tipo`).

- `GET /api/v2/contabil/integracao/categorias-contas?entity_id=1`: lista as associações
  (`categoria_id`, `categoria`, `tipo`, `account_id`, `rotulo`).
- `POST /api/v2/contabil/integracao/categorias-contas`: cria ou substitui associações e devolve o dicionário atualizado.
  Corpo: `{ "entity_id": 1, "itens": [{ "categoria_id": 3, "tipo": "receita", "account_id": 24 }] }`.
  `account_id` nulo remove a associação. Recusa (400) `tipo` diferente de `receita`/`despesa` e contas que não
  aceitam lançamentos.

Em `GET integracao/transacoes`, receitas e despesas passam a trazer as categorias do rateio do financeiro:

- `categorias`: `[{ "categoria_id", "categoria", "valor" }]`, com o valor pago repartido na proporção do rateio
  (pagamentos parciais seguem a mesma proporção; a sobra de centavos vai para a última categoria).
- Com **uma** categoria: `categoria_id` preenchido e a conta da categoria vem do dicionário (`sugestao_origem: "dicionario"`)
  ou da conta padrão do plano (`"padrao"`).
- Com **duas ou mais**: `divisao_sugerida: true` e `divisoes` já montadas (uma parte por categoria, com `categoria_id`,
  `categoria` e `sugestao_origem`). Enviadas de volta em `integracao/importar`, geram um lançamento por parte.

---

## Endpoints do Pacote Contábil

Montados sob `/api/v2/contabil/` a partir dos controllers do pacote:

### Contas Contábeis (`AccountsController`)
- `GET /accounts`: lista todas as contas
- `GET /accounts/tree`: árvore hierárquica das contas
- `POST /accounts`: cadastra nova conta analítica
- `GET /accounts/{id}`: detalhe da conta
- `PUT /accounts/{id}`: atualização de nome/código
- `DELETE /accounts/{id}`: exclusão de conta sem movimentação
- `POST /accounts/{id}/deactivate`: inativação
- `POST /accounts/{id}/reactivate`: reativação
- `GET /accounts/export`: exportação estruturada

### Exercícios e Períodos (`FiscalYearsController`, `PeriodsController`)
- `GET /fiscal-years`: exercícios fiscais cadastrados
- `POST /fiscal-years`: criação de novo exercício anual
- `GET /fiscal-years/{id}/periods`: períodos (meses) do exercício
- `POST /periods/{id}/close`: fecha o período contábil (bloqueia novas escriturações)
- `POST /periods/{id}/reopen`: reabre o período (requer `contabil.delete` e justificativa)

### Lançamentos (`EntriesController`)
- `GET /entries`: listagem com filtros por data, conta e tipo
- `POST /entries`: criação de lançamento avulso em partidas dobradas
- `POST /entries/batch`: criação atômica em lote (até 500 lançamentos)
- `GET /entries/{id}`: detalhes do lançamento e suas pernas
- `POST /entries/{id}/reverse`: estorno do lançamento com justificativa

### Demonstrações e Relatórios (`ReportsController`)
- `GET /reports/trial-balance?start_date=…&end_date=…`: Balancete de Verificação
- `GET /reports/income-statement?start_date=…&end_date=…&basis=accrual|cash`: DRE
- `GET /reports/balance-sheet?date=…`: Balanço Patrimonial
- `GET /reports/ledger?account_id=…&start_date=…&end_date=…`: Livro Razão
- `GET /reports/journal?start_date=…&end_date=…`: Livro Diário
- `GET /reports/by-source?source_type=…&source_id=…`: rastreio por documento de origem

---

## Regras de Integridade e Idempotência

1. **Valores Monetários**: o pacote trabalha internamente com valores inteiros em centavos (*minor units*). Na API de integração, a conversão é realizada por `Money::fromDecimal()` de forma exata, prevenindo erros de arredondamento de ponto flutuante.
2. **Idempotência de Importação**: cada transação importada recebe uma `idempotency_key` única:
   - Receita: `saude:fin_receita:{id}:1`
   - Despesa: `saude:fin_despesa:{id}:1`
   - Transferência: `saude:fin_transferencia:{id}:1`
3. **Imutabilidade**: lançamentos confirmados são indeléveis. Qualquer retificação gera novo lançamento com tipo `REVERSAL` referenciando o ID original.
4. **Fechamento de Período**: lançamentos e estornos com datas em períodos fechados são rejeitados com erro 422.

---

## Mapa de Telas e Ajuda Contextual

A documentação interna do sistema (`app/Docs/contabil/`) alimenta dinamicamente a ajuda contextual da topbar (`AjudaContextual.vue`):

| `currentView` da SPA | Página de Ajuda (`app/Docs/`) | Conteúdo |
|---|---|---|
| `contabil` | `contabil/index.md` | Visão geral do módulo contábil e conceitos fundamentais |
| `contabil-importar` | `contabil/importacao.md` | Conciliação e importação financeira em lote |
| `contabil-lancamentos` | `contabil/lancamentos.md` | Consulta ao Livro Diário, partidas dobradas e estornos |
| `contabil-plano-contas` | `contabil/plano-de-contas.md` | Árvore e grupos de contas (Ativo, Passivo, PL, etc.) |
| `contabil-relatorios` / `contabil-livros` | `contabil/relatorios.md` | Balancete, DRE, Balanço Patrimonial e Livro Razão |
| `contabil-periodos` | `contabil/periodos.md` | Abertura, fechamento e reabertura de meses fiscais |
