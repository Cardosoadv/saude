---
titulo: Despesas
resumo: Lançar contas a pagar, classificar, ratear entre advogados e registrar o pagamento.
ordem: 20
telas: [despesas, despesa-detail, despesas-pagas, despesa-paga-detail]
palavras_chave: [despesa, conta a pagar, pagamento, paga, fornecedor, boleto]
---

As despesas funcionam como as [receitas](receitas.md), do lado da saída.

## Despesas a Pagar

Contadores **Vencidas**, **Vence Hoje** e **Vence na Semana**; abas **Todas Pendentes**, **Vencidas**, **Hoje** e
**Semana**. **Ações em lote**: **Registrar Pagamento**, **Exportar CSV** e **Excluir Permanente**.

## Lançar uma despesa

Clique em **Nova Despesa** e preencha **Dados da Despesa**:

- **Descrição da Despesa**, **Valor**, **Vencimento** e **Fornecedor** (veja [Fornecedores](../fornecedores/index.md)).
- **Contrato Vinculado** (opcional).
- **Rateio de Categorias**: pelo menos uma categoria; a soma precisa ser igual ao valor total.
- **Rateio entre Advogados**: divide o custo entre os sócios. Sem rateio, a despesa é do escritório.

Para uma despesa **já paga**, ative **Pagar Imediatamente** e informe a **Conta Bancária** e a
**Data do Pagamento**. Clique em **Salvar Despesa**.

## Registrar o pagamento

Use **Registrar Pagamento** na lista. A despesa vai para **Despesas Pagas**, o histórico de pagamentos.

> [!ATENCAO]
> Lançamentos de um mês já fechado não podem ser alterados. Veja [Fechamento do mês](fechamento.md).
