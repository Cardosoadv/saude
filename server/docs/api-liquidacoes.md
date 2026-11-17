# API de Liquidação

Prefixo: `api/v2/liquidacoes`. Autenticação e módulo `processos` como nas demais rotas ApiV2.
Valores monetários são inteiros em **centavos**; datas em `AAAA-MM-DD`.

## Permissões

| Ação | Permissão |
|---|---|
| Listar / ver / histórico | `liquidacoes.access` ou `liquidacoes.exclusive` |
| Criar | `liquidacoes.create` |
| Editar (liquidação, itens, pagamentos, documentos) | `liquidacoes.edit` |
| Excluir | `liquidacoes.delete` |

Quem tem só `liquidacoes.exclusive` enxerga apenas as liquidações dos processos do próprio cliente.
A privacidade granular dos processos (visibilidade restrita) também vale. Liquidação ou processo sem acesso
respondem `404`, igual a registro inexistente.

## Domínio

- `status`: `rascunho`, `em_calculo`, `apresentada`, `impugnada`, `homologada`, `em_pagamento`, `quitada`.
- `tipo_liquidacao`: `por_calculo`, `por_arbitramento`, `procedimento_comum`.
- `tipo_item`: `principal`, `juros`, `multa`, `honorarios`, `custas`, `outros`.
- `status_pagamento` (parcela): `pendente`, `paga`, `vencida`, `cancelada`.
- `tipo_documento`: `sentenca`, `calculos`, `comprovante`, `outros`.

## Endpoints

| Método e rota | Descrição |
|---|---|
| `GET /` | Lista paginada. Filtros: `search`, `status`, `processo_id`, `cliente_id`, `tipo_liquidacao`, `page`. |
| `GET processo/{id}` | Liquidações de um processo. |
| `GET show/{id}` | Detalhe com `itens`, `pagamentos`, `documentos`, `historico` e `processo`. |
| `GET historico/{id}` | Histórico de recálculos de todos os itens, do mais recente ao mais antigo. |
| `POST store` | Cria. Corpo: `processo_id` (obrigatório), `tipo_liquidacao`, `status`, `data_liquidacao`, `observacoes` e, opcional, `itens[]`. |
| `PUT update/{id}` | Atualiza os dados gerais. `processo_id` e `valor_total` são ignorados (o total vem dos itens). |
| `DELETE delete/{id}` | Exclui (com itens, pagamentos e documentos). |
| `POST storeItem` | Adiciona item: `liquidacao_id`, `tipo_item`, `descricao`, `valor_base`, opcionais `valor_calculado`, `taxa`, `periodo_*`, `data_inicio`, `data_fim`, `base_calculo`, `fundamento_legal`. |
| `PUT updateItem/{id}` | Altera o item; recalcula o total e grava o histórico se o valor mudou. |
| `DELETE deleteItem/{id}` | Remove o item e recalcula o total. |
| `POST storePagamento` | Parcela: `liquidacao_id`, `numero_parcela`, `valor_parcela`, `data_vencimento`. |
| `PUT updatePagamento/{id}` | Altera a parcela (`data_pagamento`, `status_pagamento`, `forma_pagamento`, `comprovante_url`...). |
| `DELETE deletePagamento/{id}` | Remove a parcela. |
| `POST storeDocumento` | Registra documento: `liquidacao_id`, `tipo_documento`, `nome_arquivo`, `url_arquivo`. |
| `DELETE deleteDocumento/{id}` | Remove o documento. |

## Respostas

Sucesso: `{ "success": true, "data": ... }` (criações devolvem também `id`).
Erro: `{ "success": false, "message": "...", "errors": { campo: mensagem } }` com `404`, `422` ou `500`.

# Índices econômicos

Prefixo: `api/v2/indices`. Valores são **variações percentuais** (`0.83` = 0,83%). Consulta exige
`liquidacoes.access`/`liquidacoes.exclusive`; cadastro, lançamento, CSV e importação exigem também `admin.settings`.

| Método e rota | Descrição |
|---|---|
| `GET /` | Índices com primeira/última competência, total de valores, nº de lacunas, `atrasado` e resultado da última importação. |
| `GET {codigo}/valores?de=&ate=` | Série (`competencia => valor`). |
| `GET {codigo}/lacunas` | Meses sem valor entre o primeiro e o último da série. |
| `GET fator?indice=&inicio=&fim=&mes_inicial=&mes_final=&tolerar_lacuna_final=` | Fator acumulado com o passo a passo. |
| `POST /` · `PUT {id}` | Cadastra / altera índice (`codigo`, `nome`, `periodicidade` mensal\|diaria, `fonte` sgs\|manual\|csv, `codigo_serie`, `ativo`). |
| `POST {codigo}/valores` | Lança ou corrige um valor: `{ "competencia": "2025-03", "valor": "0,56" }`. |
| `POST {codigo}/csv` | Importa CSV (`arquivo` multipart ou `{ "csv": "..." }`). Tudo ou nada. |
| `POST atualizar` | Importa do SGS agora: `{ "codigo": "IPCA", "historico": true }` (ambos opcionais). |

## Regra do fator acumulado

- **Mensal:** multiplica `1 + valor/100` de cada mês entre a data inicial e a final. `mes_inicial` e `mes_final`
  aceitam `cheio` (padrão: variação inteira do mês), `pro_rata` (fração linear dos dias do mês dentro do período;
  início inclusive) ou `excluir`. A escolha depende do título judicial e da natureza do crédito, por isso é parâmetro.
- **Diário:** multiplica os dias de `inicio` a `fim`, ambos inclusive.
- Mês sem valor no meio do período **recusa o cálculo** (422, listando os meses). Com `tolerar_lacuna_final`, meses
  finais ainda não divulgados são ignorados e devolvidos em `ignoradas` (e `ate_competencia` indica até onde foi).
- O resultado fica em cache; qualquer carga com valores novos o invalida.

## CSV

`competencia;valor` com cabeçalho opcional. Competência: `AAAA-MM`, `AAAA-MM-DD`, `MM/AAAA` ou `DD/MM/AAAA`.
Vírgula decimal exige `;` como separador. Qualquer linha inválida cancela a importação inteira.

## Importação agendada (SGS)

```
php spark indices:atualizar            # todos os índices ativos com fonte sgs
php spark indices:atualizar IPCA       # um índice
php spark indices:atualizar --historico  # carga inicial completa (desde 07/1994)
```

Agende uma vez por dia, por exemplo no cron: `0 8 * * * cd /caminho/da/aplicacao && php spark indices:atualizar`.
Rodar todo dia é inofensivo: só entram valores que ainda não existem (o último mês gravado é rebuscado para
pegar revisões). Falhas ficam em `indices_importacoes` e aparecem em `GET /`. Cada carga com valores novos dispara
o evento `indices_atualizados` (lista de códigos), usado para recalcular liquidações (PR 3/6).

> Os códigos de série do SGS semeados pela migration (IPCA 433, INPC 188, IGP-M 189, Selic acumulada no mês 4390,
> IPCA-E 10764 inativo) **não puderam ser conferidos** na implementação, e a data de divulgação de cada índice
> também deve ser verificada no site do Banco Central. Corrija em `indices_economicos.codigo_serie` se necessário.

# Motor de cálculo

Cada item pode ter um **critério de cálculo** (`criterio`, JSON). Item **sem** critério é manual: vale o
`valor_calculado` informado (ou o `valor_base`). Item com critério é calculado a cada recálculo e guarda a
**memória de cálculo** (`GET memoria/{item_id}`). A liquidação tem `data_base` (padrão: hoje) e `criterio_padrao`,
usado pelos itens com `"usar_padrao": true` e sobreposto, chave a chave, pelo critério do item.

```json
{
  "correcao":  { "indice": "IPCA", "data_inicial": "2020-03-10", "mes_inicial": "cheio", "mes_final": "cheio" },
  "juros":     { "tipo": "simples", "taxa_mensal": "1", "data_inicial": "2020-04-01", "base": "corrigido" },
  "mudancas":  [ { "a_partir_de": "2021-12-01", "correcao": { "indice": "SELIC" }, "juros": { "tipo": "nenhum" } } ],
  "parcelas":  [ { "data": "2020-03-10", "valor": 100000 } ],
  "abatimentos": [ { "data": "2022-05-10", "valor": 50000 } ],
  "percentual": "10", "base_percentual": "principal"
}
```

- `juros.tipo`: `simples`, `compostos` ou `nenhum`; informe `taxa_mensal` **ou** `indice`. `data_inicial` pode ser
  uma data ou `"vencimento"` (a data da parcela, padrão). `base`: `corrigido` (padrão) ou `nominal`.
- `mudancas`: trocam a correção e/ou os juros **a partir do primeiro dia do mês** de `a_partir_de`.
  Cada troca é mês cheio de um único índice.
- `parcelas`: competências do item (verbas mensais); cada uma é corrigida desde a sua data. Sem `parcelas`, o item
  é uma parcela única de `valor_base` na data inicial da correção.
- `abatimentos`: pagamentos/depósitos parciais (valor positivo); entram como parcelas negativas, corrigidas desde a data.
- `percentual` (só itens `multa` e `honorarios`): percentual sobre `base_percentual` = `principal` (valor corrigido dos
  itens principal/custas/outros), `principal_juros` (idem com juros, mais itens de juros) ou `total` (todos os itens,
  e, nos honorários, também a multa). A multa é calculada antes dos honorários.

## Regras numéricas

Centavos inteiros; cálculos em bcmath com 12 casas; arredondamento **meio para cima, afastando de zero**, ao centavo,
aplicado ao valor corrigido de cada parcela, aos juros de cada parcela e a cada item de percentual. Período de juros em
meses = meses fechados + dias restantes/30, da data inicial até a data-base (a data-base não conta). Juros compostos com
taxa fixa: `(1+taxa)^meses_inteiros × (1+taxa × dias/30)`. Mês final de índice ainda não divulgado fica fora da correção
e é avisado na memória (`avisos`). Lacuna no meio do período recusa o cálculo (422).

> **Observação jurídica:** índice, taxa, termo inicial e base de cada item dependem do título judicial, da natureza do
> crédito e da lei vigente em cada período. Nada disso é fixo no código; os critérios que o escritório cadastrar como
> padrão devem ser conferidos por advogado.

## Endpoints

| Método e rota | Descrição |
|---|---|
| `POST recalcular/{id}` | Recalcula a liquidação. Corpo opcional: `data_base`, `motivo` ou `atualizar_ate_hoje: true`. Grava uma linha de histórico por item cujo valor mudou. Exige `liquidacoes.edit`. |
| `POST simular` | Calcula sem gravar. `item_id` ou `item` avulso (+ `criterio_padrao`), `data_base` e, para comparar, `cenarios: [{ nome, criterio }]` (cada cenário substitui o critério do item). Devolve valor, diferença para o primeiro cenário e memória. |
| `GET memoria/{item_id}` | Memória de cálculo gravada no item: por parcela, os segmentos de correção (índice, período, fator, mês a mês) e de juros. |

`GET show/{id}` devolve `criterio` decodificado em cada item, `tem_memoria` e `criterio_padrao`. Criar/alterar item ou
alterar `criterio_padrao`/`data_base` recalcula automaticamente. Valores novos de índice (evento `indices_atualizados`)
recalculam as liquidações em aberto que usam o índice; **homologada, em pagamento e quitada não são recalculadas**.

# Parcelas, pagamentos e documentos

## Parcelamento

`POST api/v2/liquidacoes/gerarParcelas/{id}` (permissão `liquidacoes.edit`) gera o plano a partir do `valor_total`
(ou de `valor_total` informado no corpo):

| Campo | Descrição |
|---|---|
| `numero_parcelas` | 1 a 360 |
| `primeiro_vencimento` | `AAAA-MM-DD` |
| `periodicidade` | `semanal`, `quinzenal`, `mensal` (padrão), `bimestral`, `trimestral`, `semestral`, `anual` |
| `entrada` | opcional, em centavos; vira a parcela `0` |
| `data_entrada` | opcional; padrão = `primeiro_vencimento` |
| `substituir` | `true` para refazer um plano com parcelas em aberto (parcelas pagas impedem refazer → 409) |

Os centavos que sobram da divisão ficam na última parcela. Meses curtos usam o último dia do mês.
Em liquidação `homologada`, gerar parcelas a passa para `em_pagamento`.

## Baixa e cancelamento

- `POST api/v2/liquidacoes/baixarPagamento/{pagamentoId}` — `data_pagamento` (não pode ser futura), `forma_pagamento`,
  `observacao`, `conta_id` (opcional) e `arquivos[]` (comprovantes, enviados ao Google Drive). Se o Drive falhar, a parcela
  não é baixada. Quando todas as parcelas ficam pagas, a liquidação passa a `quitada`.
- `POST api/v2/liquidacoes/cancelarPagamento/{pagamentoId}` — `motivo` opcional. Só para parcelas pendentes ou vencidas.

## Honorários no Financeiro

Se a liquidação tem `lancar_honorarios = 1`, a baixa cria receitas no Financeiro na mesma operação (se o Financeiro recusar,
por exemplo mês fechado, a baixa é desfeita):

- **sucumbência** = parcela × (soma dos itens `honorarios` ÷ valor total);
- **contratuais** = (parcela − sucumbência) × `honorarios_percentual` ÷ 100.

Sem `conta_id` a receita fica "a receber"; com `conta_id` já entra como recebida. Os ids ficam em
`lancamentos_financeiros` da parcela. Natureza, repasse ao cliente e tributação devem ser conferidos pelo advogado/contador.

## Documentos

`POST api/v2/liquidacoes/uploadDocumento/{id}` — `arquivos[]`, `tipo_documento` (`sentenca`, `calculos`, `comprovante`,
`outros`) e `pagamento_id` opcional. Os arquivos vão para a pasta do cliente no Drive, subpasta "Liquidações", com os mesmos
limites dos arquivos de contratos. Excluir um documento com `drive_id` envia o arquivo para a lixeira do Drive.

## Vencimentos

`php spark liquidacoes:vencimentos [dias_aviso]` (sugestão de cron: `0 7 * * *`) marca como `vencida` a parcela pendente com
vencimento passado, avisa por e-mail o responsável pelo processo e abre uma tarefa de cobrança; também avisa uma única vez das
parcelas que vencem nos próximos `dias_aviso` dias (padrão 3).

## Fluxo de caixa

`entradas_previstas_liquidacoes` aparece em cada mês da projeção do fluxo de caixa (visão consolidada, sem filtro de conta).
É informativo e não entra na regressão do saldo projetado. Parcelas já vencidas contam no primeiro mês projetado.

# Relatórios e integrações

## Memória de cálculo (PDF) e planilha

- `GET api/v2/liquidacoes/pdf/{id}` — PDF (A4 paisagem) com processo, partes, data-base, critérios usados, tabela por item e
  competência (valor original, fator, corrigido, juros, subtotal), abatimentos e total.
- `GET api/v2/liquidacoes/exportar/{id}?formato=csv|xlsx` — as mesmas linhas em planilha. O CSV é UTF-8 com BOM, separador `;`
  e vírgula decimal (abre direto no Excel pt-BR); o XLSX tem valores numéricos em reais (requer `ext-zip`).

Os relatórios só apresentam o que o motor gravou na memória de cálculo de cada item; itens manuais saem como "valor informado
manualmente". Respeitam o mesmo acesso da liquidação.

## Processo, BI e risco

- `GET api/v2/processos/.../detalhes` ganha o bloco `liquidacao` (liquidação mais recente: status, `valor_atualizado`,
  `total_pago`, `saldo_a_receber`, próxima parcela). Aparece só para quem tem `liquidacoes.access`/`liquidacoes.exclusive`.
- **Legal Ops:** em `valores_risco`/provisão/exposição, o valor da causa é trocado pelo valor atualizado da liquidação mais
  recente quando ela está `apresentada`, `impugnada`, `homologada` ou `em_pagamento`; `quitada` conta zero; rascunho e
  "em cálculo" não substituem o valor da causa. Qualquer alteração em liquidação invalida o cache do Legal Ops.

## Sugestão de critérios por IA

`POST api/v2/liquidacoes/sugerirCriterio/{id}` com `{"texto": "<trecho da sentença ou do acórdão>"}` (até 30.000 caracteres).
Devolve `criterio` (já validado), `sugestao` (inclui `multa_percentual` e `honorarios_percentual`), os `trechos` que
fundamentam cada critério, `observacoes` e `avisos` (índice não cadastrado, data inválida, juros sem taxa...).
**Nada é gravado**: o advogado confere e aplica pelo cadastro normal do critério. O provedor segue `AI_PROVIDER_STRATEGY`.
Atenção: o trecho enviado vai ao provedor de IA configurado.

## MCP

Ferramenta `obter_liquidacao(liquidacaoId | processoId)`: liquidação com itens, critérios, parcelas, resumo de pagamentos e
documentos (valores em centavos). Somente leitura.
