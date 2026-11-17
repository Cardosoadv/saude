---
titulo: Financeiro
resumo: Receitas, despesas, transferências, extrato, relatórios e fechamento do mês.
ordem: 100
modulo: financeiro
permissao: financeiro.access
palavras_chave: [financeiro, caixa, conta bancária, categoria, rateio, repasse]
---

A seção **Financeiro** do menu reúne o controle financeiro do escritório:

| Tela | Para que serve |
|---|---|
| **Receitas a Receber** e **Receitas Recebidas** | honorários e demais entradas. Veja [Receitas](receitas.md) |
| **Despesas a Pagar** e **Despesas Pagas** | contas e gastos. Veja [Despesas](despesas.md) |
| **Transferências** | movimentação entre contas do escritório. Veja [Transferências](transferencias.md) |
| **Extrato** | movimentos e saldo por conta e período. Veja [Extrato e categorias](extrato.md) |
| **Importar Extrato** e **Movimentos do Extrato** | traz o OFX do banco e concilia com os lançamentos. Veja [Importar extrato bancário](importacao-extrato.md) |
| **Balanço**, **DRE** e **Previsão de Fluxo de Caixa** | visão gerencial. Veja [Relatórios](relatorios.md) |
| **Fechamento do Mês** | consolida e trava o mês. Veja [Fechamento do mês](fechamento.md) |

## Conceitos

- **Conta bancária**: cada recebimento, pagamento e transferência sai de ou entra em uma conta.
- **Categoria**: classifica cada lançamento (ex.: honorários, aluguel). Um lançamento pode ser dividido em
  várias categorias, desde que a soma bata com o valor total. As categorias alimentam a DRE e o relatório por
  categorias.
- **Rateio / repasse entre advogados**: divide o valor entre os sócios que **participam do rateio** (marcação
  no cadastro do usuário). Sem rateio, o valor fica todo no caixa do escritório.
- **Favoritos**: lançamentos que se repetem (ex.: "Aluguel Mensal") podem ser salvos como favorito e
  carregados depois, já preenchidos.
