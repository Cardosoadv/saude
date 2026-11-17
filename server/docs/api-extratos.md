# API de Extratos bancários (OFX)

Prefixo: `api/v2/financeiro/extratos`. Autenticação e módulo `financeiro` como nas demais rotas ApiV2.
Valores monetários são inteiros em **centavos** (negativo = saída); datas em `AAAA-MM-DD`.
Todos os exemplos abaixo usam dados **fictícios**.

## Permissões

| Ação | Permissão |
|---|---|
| Listar importações, movimentos, sugestões, regras, conferência | `financeiro.access` |
| Prévia e importar | `financeiro.extratos_importar` |
| Conciliar, desconciliar, ignorar (e lote) | `financeiro.extratos_conciliar` |
| Lançar (e lote), criar/alterar/excluir regras | `financeiro.extratos_lancar` |
| Anexar comprovante a um movimento | `financeiro.edit` |

Quem tem `financeiro.*` (administrador, profissional) já recebe as três. Perfis que listam permissões uma a uma
(por exemplo, recepção, que só tinha `financeiro.access/create/edit`) e usuários com permissões individuais
**não** ganham as novas automaticamente: conceda-as em Usuários → permissões. Sem elas, as rotas respondem `403`.

## Configurações

Editáveis em Configurações (aba Sistema). Valor ausente, não numérico ou fora da faixa volta ao padrão.

| Nome | Padrão | Faixa | O que controla |
|---|---|---|---|
| `EXTRATOS_MAX_MB` | 5 | 1 a 20 | Tamanho máximo do arquivo OFX. |
| `EXTRATOS_JANELA_DIAS` | 3 | 0 a 30 | Janela (dias antes e depois) das sugestões de conciliação, quando `janela` não é enviada. |
| `EXTRATOS_LIMITE_LOTE` | 50 | 1 a 200 | Itens por lote em `lancar-lote`. |
| `EXTRATOS_LIMITE_LOTE_CONCILIACAO` | 500 | 1 a 1000 | Itens por lote na conciliação em lote (prévia e confirmação). |

Auditoria: as importações e as ações de conciliar/lançar gravam quem fez e quando; memo e nome da contraparte
não vão para o log nem para a auditoria da importação.

## Regra de idempotência (a que importa)

Importar a mesma operação duas vezes **nunca** cria um segundo movimento, não importa o arquivo, a ordem ou o período.

- **Chave com FITID**: `sha256(banco | agência | conta | FITID)`.
- **Chave sem FITID** (ou FITID inválido): `sha256(banco | agência | conta | data | centavos | memo normalizado | n)`, onde `n` é a ordem de ocorrência do item idêntico no mesmo dia (dois cafés iguais no mesmo dia são dois movimentos).
- `CHECKNUM` e `REFNUM` **não** entram na chave (alguns bancos os repetem).
- A garantia final é o índice único `(conta_id, chave)` do banco. Linhas excluídas continuam ocupando a chave: um movimento apagado não volta numa nova importação.
- Mesma chave, **data ou valor diferentes** = *conflito*: nada é alterado, o item é contado em `conflitos`.
- O mesmo FITID em **contas diferentes** gera chaves diferentes; os dois são importados.
- Excluir o pagamento vinculado devolve o movimento para `pendente`; ele não é reimportado nem duplicado.
- Uma importação por conta de cada vez (lock por conta). Quem chega durante outra recebe `409`.
- O arquivo bruto **não** é guardado, só o hash do conteúdo. Logs trazem contagens e IDs, nunca memo nem nome da contraparte.

## Respostas e erros

Sucesso: `{ "success": true, "data": ..., "message": "..." }`.
Erro: `{ "success": false, "message": "..." }`.

| Código | Quando |
|---|---|
| `400` | Arquivo ausente, ilegível ou rejeitado pelo upload. |
| `403` | Sem permissão. |
| `404` | Conta, movimento ou importação inexistente. |
| `409` | Importação em andamento na mesma conta; alerta que exige confirmação (`requer_confirmacao: true` e `alertas`); conflito de conciliação. |
| `422` | Arquivo inválido ou de outra conta, parâmetros inválidos, mês fechado, regra de negócio violada. |
| `500` | Erro inesperado (detalhe só no log). |

## Importação

| Método e rota | Descrição |
|---|---|
| `POST previa` | Multipart: `conta_id` e `arquivo` (OFX). Mostra o que seria importado; **não grava nada**. |
| `POST importar` | Mesmo corpo, mais `confirmar=true` quando houver alerta de reemissão. Grava só os movimentos novos. |
| `GET importacoes?conta_id=&page=&per_page=` | Histórico de importações. |
| `GET importacoes/{id}` | Resumo e movimentos criados por aquela importação. |
| `GET movimentos?conta_id=&data_inicio=&data_fim=&status=&q=&page=&per_page=` | Movimentos. `status`: `pendente`, `conciliado`, `lancado`, `ignorado`. |

O arquivo precisa ser da conta escolhida (banco, agência e número conferem com o cadastro da conta); senão `422` e nada é gravado.

Exemplo de resposta de `importar`:

```json
{
  "success": true,
  "message": "Extrato importado.",
  "data": {
    "importacao_id": 12,
    "lidos": 30,
    "novos": 15,
    "duplicados": 15,
    "conflitos": 0,
    "periodo": ["2026-10-01", "2026-10-30"],
    "alertas": [
      { "codigo": "periodo_ja_coberto", "mensagem": "Todo o período deste arquivo já tinha sido importado antes." }
    ]
  }
}
```

### Alertas

| Código | Significado | Exige `confirmar`? |
|---|---|---|
| `arquivo_ja_enviado` | O mesmo arquivo (hash) já foi enviado; nenhum movimento se repete. | Não |
| `periodo_ja_coberto` | Todo o período já tinha sido importado. | Não |
| `conflitos` | Mesmo identificador com data ou valor diferentes; nada foi alterado. | Não |
| `mes_fechado` | Há movimentos novos em mês fechado: entram só como histórico (`pendente`) e não podem ser lançados. | Não |
| `possivel_reemissao_de_fitid` | Pelo menos 50% do arquivo tem FITID novo, mas data, valor e descrição de movimentos já importados. | **Sim** (`409` até confirmar) |

## Conciliação (com pagamentos já lançados)

| Método e rota | Descrição |
|---|---|
| `GET movimentos/{id}/sugestoes?janela=3` | Candidatos por valor, data (janela em dias, máx. 30) e nome. |
| `POST movimentos/{id}/conciliar` | Corpo: `pagamento_tipo` (`despesa`, `receita`, `transferencia`), `pagamento_id`; em transferência, opcional `movimento_par_id`. |
| `POST movimentos/{id}/desconciliar` | Volta para `pendente`. |
| `POST movimentos/{id}/ignorar` | Corpo: `motivo`. |
| `GET conciliacao/lote?conta_id=&janela=` | Prévia de sugestões de confiança alta (até 500). |
| `POST conciliacao/lote` | Corpo: `confirmar: true`, `itens: [{movimento_id, pagamento_tipo, pagamento_id}]`. |

Um pagamento só pode estar ligado a um movimento (índice único); tentar ligar de novo dá `409`.

## Lançamento a partir do extrato

| Método e rota | Descrição |
|---|---|
| `GET movimentos/{id}/sugestao-lancamento` | Tipo, regra que casou e contrapartes parecidas. |
| `POST movimentos/{id}/lancar` | Saída cria despesa + pagamento; entrada cria receita + recebimento. |
| `POST movimentos/lancar-lote` | Corpo: `itens: [{movimento_id, ...}]`, até 50. Itens inválidos voltam com o motivo, sem impedir os demais. |

Variações de `lancar`:

```json
{ "fornecedor_id": 8, "categoria_id": 21, "descricao": "Tarifa mensal" }
```

```json
{ "quitar": { "tipo": "despesa", "id": 501 } }
```

```json
{ "movimento_par_id": 9042, "descricao": "Transferência entre contas" }
```

- Sem `fornecedor_id`/`novo_fornecedor` e categoria, uma saída é recusada (`422`); uma entrada pode ficar sem cliente.
- `rateio`: `[{ "id": 21, "percentual": 60 }, { "id": 22, "percentual": 40 }]`; precisa fechar 100%.
- `criar_regra`: cria uma regra de classificação a partir do lançamento.
- Mês fechado bloqueia o lançamento, sem efeito colateral.

## Regras de classificação

`GET regras?conta_id=&ativas=1`, `GET regras/{id}`, `POST regras`, `PUT regras/{id}` (só os campos enviados mudam), `DELETE regras/{id}`.
Campos: `nome`, `padrao`, `campo`, `sinal`, `conta_id`, `fornecedor_id` ou `cliente_id`, `categoria_id`, `rateio`.

## Conferência de saldo e comprovantes

| Método e rota | Descrição |
|---|---|
| `GET conferencia?conta_id=&ano=&mes=` | Saldo do banco (LEDGERBAL) × saldo do sistema na mesma data, com os pendentes que explicam a diferença. Informativa. Importações anteriores à migration `saldo_final_data` ficam sem data do saldo. |
| `GET resumo-mes?ano=&mes=` | Resumo do mês para o painel do financeiro. |
| `POST movimentos/{id}/comprovantes` | Anexa comprovantes (`arquivos[]`, mesmos limites dos comprovantes de pagamento: 10 MB por arquivo, 5 por envio) ao pagamento que o movimento conciliou ou lançou. |

## Limitações conhecidas

- Duplicados e conflitos são contados, mas não guardados linha a linha.
- O limite de 50% do alerta de reemissão está em `ExtratoImportacaoService::LIMITE_REEMISSAO_FITID`.

## Testes

`.github/workflows/tests-extratos.yml` roda a suíte dos extratos. A regressão de idempotência de ponta a ponta (SQLite real) está em `tests/unit/Services/Financeiro/ExtratoIdempotenciaTest.php`.
