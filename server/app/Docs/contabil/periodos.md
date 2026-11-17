---
titulo: Exercícios e períodos contábeis
resumo: Abertura, fechamento e reabertura de meses e exercícios fiscais.
ordem: 50
telas: [contabil-periodos]
palavras_chave: [períodos, exercícios fiscais, fechamento contábil, reabertura, ano fiscal]
---

## Ciclo fiscal

A aba **Períodos** (`#/contabil-periodos`) gerencia os exercícios anuais e períodos mensais
de escrituração contábil do escritório.

- **Exercício Fiscal**: representa o ano-calendário contábil (ex.: exercício de 2026);
- **Período Contábil**: divisão mensal do exercício (Janeiro a Dezembro), no qual as
  transações financeiras e lançamentos são consolidados.

## Fechamento de período

O fechamento contábil é o procedimento formal que congela a escrituração do mês:

1. Localize o mês desejado na lista de períodos do exercício atual;
2. Verifique se todas as conciliações em [Importação financeira](importacao.md) foram concluídas
   e se o Balancete está equilibrado em [Demonstrações](relatorios.md);
3. Clique em **Fechar Período**;
4. O status do período muda para **CLOSED** (Fechado).

### Efeitos do período fechado

- Fica terminantemente bloqueada qualquer nova escrituração com data pertencente ao mês fechado;
- Tentativas de importar receitas, despesas ou criar lançamentos avulsos na data fechada
  retornarão erro de validação do pacote contábil;
- O bloqueio previne distorções retroativas em demonstrações já emitidas para sócios, bancos
  ou órgãos fazendários.

## Reabertura de período

Caso uma inconsistência material seja identificada após o fechamento:

1. Usuários autorizados com permissão `contabil.delete` podem acionar o botão **Reabrir Período**;
2. É obrigatório registrar uma justificativa técnica fundamentada para a reabertura;
3. O sistema dispara evento de auditoria (`PeriodReopened`), registrando o usuário, data,
   hora e justificativa para preservação de rastreabilidade;
4. Após as devidas retificações no [Livro Diário](lancamentos.md), o período deve ser fechado
   novamente.

> [!ATENCAO]
> A reabertura de períodos contábeis retroativos altera os saldos das demonstrações financeiras
> do período e dos meses subsequentes. Utilize apenas quando estritamente necessário.
