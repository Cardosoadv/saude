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
4. Clique em **Preencher com o balancete de 31/12/2025** ou digite os saldos nas colunas
   **Débito** e **Crédito** de cada conta.
5. Confira o indicador **Equilibrado** e clique em **Lançar abertura**.
6. Emita o **Balancete** em [Demonstrações e relatórios](relatorios.md) e compare com o PDF da
   contabilidade: cada saldo final de 2025 deve ser o saldo inicial de 2026.

## Balancete de 31/12/2025 no plano Cardoso & Bruno

Os códigos abaixo são os do [plano padrão ITG 1000](plano-de-contas.md). Quem ainda usa o plano anterior converte antes de lançar a abertura (veja *Convertendo o plano anterior*).

Balancete analítico da Pontual Gestão Empresarial e Contabilidade, emitido em 05/03/2026.

| Conta do balancete | Saldo em 31/12/2025 | Conta no plano | Lado | Valor (R$) |
|---|---|---|---|---|
| Caixa Geral | 468,21 D | 1.1.1.01.01 Caixa Geral | D | 468,21 |
| Banco Inter | 3.426,65 D | 1.1.1.02.01 Banco Inter | D | 3.426,65 |
| Aplicação - CDB | 4.862,57 D | 1.1.1.03.01 Aplicação - CDB | D | 4.862,57 |
| Clientes Diversos | 12.833,74 D | 1.1.2.01.01 Honorários a Receber | D | 12.833,74 |
| Direitos Creditórios | 20.000,00 D | 1.2.1.01.06 Direitos Creditórios | D | 20.000,00 |
| INSS a Recolher | 462,83 C | 2.1.1.01.03 INSS a Recolher | C | 462,83 |
| Pró-Labore a Pagar | 0,00 | 2.1.1.01.12 | - | não lançar |
| Simples Nacional (passivo) | 0,00 | 2.1.4.02.10 | - | não lançar |
| Empréstimos de Sócios | 403.208,80 C | 2.1.6.01.03 Empréstimos de Sócios (Curto Prazo) | C | 403.208,80 |
| Capital Social | 100.000,00 C | 3.1.01.01 Capital Social Subscrito | C | 100.000,00 |
| Lucros Acumulados | 517.705,44 C | 3.8.01.01 Lucros Acumulados | C | 517.705,44 |
| **Resultado do exercício 2025** | 149.305,25 C | 3.8.01.01 Lucros Acumulados | C | 149.305,25 |
| Prejuízos Acumulados | 10.000,00 D | 3.8.02.01 (-) Prejuízos Acumulados | D | 10.000,00 |
| Lucro Distribuído - Fabiano Dias | 127.368,17 D | 3.9.01.01 | D | 127.368,17 |
| Lucro Distribuído - Rodrigo Dias | 991.722,98 D | 3.9.01.02 | D | 991.722,98 |

Na tela, as duas linhas de Lucros Acumulados aparecem somadas: **667.010,69 a crédito** em 3.8.01.01.

**Conferência:** débitos 1.170.682,32 = créditos 1.170.682,32. O ativo lançado (41.591,17) é o ativo
do balancete. As contas de receita, dedução, despesa e resultado financeiro do balancete (Honorários,
Simples Nacional, Pró-Labore, INSS, etc.) **não são lançadas**: o resultado delas já está em 3.2.01.

> [!ATENCAO]
> O Passivo do balancete aparece como 107.714,08 D porque o relatório soma Passivo e Patrimônio
> Líquido. O passivo propriamente dito é 403.671,63 a crédito (INSS 462,83 + Empréstimos de Sócios
> 403.208,80).

> [!ATENCAO]
> O lucro distribuído acumulado (1.119.091,15) supera os lucros acumulados somados ao resultado de
> 2025 (667.010,69 menos 10.000,00 de prejuízos), e o saldo de empréstimos de sócios é de 403 mil.
> Isso é o que consta no balancete e foi lançado como está; confirme com a contabilidade a
> natureza dessas distribuições antes de fechar o primeiro exercício no sistema.
