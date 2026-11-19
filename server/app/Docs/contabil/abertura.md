---
titulo: Abertura contábil (saldos iniciais)
resumo: Como lançar o balancete de encerramento do ano anterior como saldo inicial no plano de contas, em um único lançamento.
ordem: 25
palavras_chave: [abertura, saldos iniciais, balancete, implantação, lucros acumulados, resultado do exercício, distribuição de lucros]
---

A **abertura contábil** leva para o sistema os saldos finais do balancete do ano anterior.
Ela é **um único lançamento** de partidas dobradas, com uma linha por conta, datado do primeiro
dia do novo exercício (por exemplo, `01/01/2026` para o balancete de `31/12/2025`).

## Regras

- Só contas **patrimoniais** recebem saldo: Ativo (1), Passivo (2) e Patrimônio Líquido (3).
  Receitas (4) e Despesas (5) começam o ano zeradas.
- O **resultado do exercício anterior** entra em **Lucros Acumulados** (3.2.01), somado ao saldo
  que já existia nessa conta. Se for prejuízo, vai a débito em 3.2.02.
- Cada conta usa o **lado da sua natureza**: ativo a débito, passivo e capital a crédito. Contas
  redutoras (Prejuízos Acumulados, Lucro Distribuído, Depreciação Acumulada) ficam no lado oposto
  ao do grupo.
- O lançamento só é aceito quando **débitos e créditos fecham ao centavo**, e cada conta aparece
  uma única vez.
- A abertura vai no **primeiro exercício** da entidade e é datada do **primeiro dia** dele. Os
  exercícios seguintes não precisam de abertura: os saldos passam de um ano para o outro pelo razão.
- Repetir a abertura com os mesmos saldos não duplica nada. Para mudar os valores de uma abertura
  já lançada, envie a nova com **substituir**: o sistema estorna a anterior e lança a nova de uma vez.

## Passo a passo

1. Confirme que o [plano de contas](plano-de-contas.md) oficial está importado e que o exercício
   fiscal do ano existe em [Exercícios e períodos](periodos.md).
2. Em **Lançamentos**, clique em **Abertura contábil**.
3. Informe a **data** (primeiro dia do exercício) e, se quiser, o histórico.
4. Digite os saldos nas colunas
   **Débito** e **Crédito** de cada conta.
5. Confira o indicador **Equilibrado** e clique em **Lançar abertura**.
6. Emita o **Balancete** em [Demonstrações e relatórios](relatorios.md) e compare com o balancete da
   contabilidade: cada saldo final do ano anterior deve ser o saldo inicial do novo exercício.

## Exemplo de saldos de abertura

Os códigos abaixo são os do [plano padrão ITG 1000](plano-de-contas.md). Os valores são apenas ilustrativos.

| Conta do balancete | Saldo | Conta no plano | Lado | Valor (R$) |
|---|---|---|---|---|
| Caixa Geral | 500,00 D | 1.1.1.01.01 Caixa Geral | D | 500,00 |
| Banco | 10.000,00 D | 1.1.1.02.01 Banco | D | 10.000,00 |
| Clientes | 5.000,00 D | 1.1.2.01.01 Atendimentos a Receber | D | 5.000,00 |
| Fornecedores | 2.500,00 C | conta de fornecedores do plano | C | 2.500,00 |
| Capital Social | 13.000,00 C | 3.1.01.01 Capital Social Subscrito | C | 13.000,00 |

**Conferência:** débitos = créditos (15.500,00). As contas de receita, dedução, despesa e resultado
financeiro do balancete **não são lançadas**: o resultado delas já está em lucros/prejuízos acumulados.
