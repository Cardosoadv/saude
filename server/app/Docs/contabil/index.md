---
titulo: Contábil
resumo: Escrituração contábil em partidas dobradas, conciliação e integração financeira direta.
ordem: 110
modulo: contabil
permissao: contabil.access
telas: [contabil]
palavras_chave: [contábil, contabilidade, livro diário, razão, balancete, dre, balanço patrimonial, plano de contas, partidas dobradas, exercícios fiscais]
---

A seção **Contábil** do menu reúne o controle contábil oficial do escritório, integrando
as operações financeiras diárias às normas de contabilidade em partidas dobradas:

| Tela | Para que serve |
|---|---|
| **Importar do Financeiro** | concilia receitas recebidas, despesas pagas e transferências com as contas contábeis. Veja [Importação e integração financeira](importacao.md) |
| **Lançamentos Contábeis** | escrituração em partidas dobradas, consulta ao Livro Diário e estornos. Veja [Lançamentos contábeis](lancamentos.md) |
| **Abertura contábil** | lança os saldos iniciais do balancete do ano anterior. Veja [Abertura contábil](abertura.md) |
| **Plano de Contas** | árvore hierárquica e contas analíticas (Ativo, Passivo, PL, Receitas e Despesas). Veja [Plano de contas](plano-de-contas.md) |
| **Demonstrações e Relatórios** | Balancete de Verificação, DRE, Balanço Patrimonial e Livro Razão. Veja [Demonstrações e relatórios](relatorios.md) |
| **Períodos e Exercícios** | controle de abertura, fechamento e reabertura de meses e anos fiscais. Veja [Exercícios e períodos](periodos.md) |

## Conceitos fundamentais

- **Partidas Dobradas**: todo fato contábil é registrado com pelo menos um débito e um crédito,
  mantendo a equação fundamental `Ativo = Passivo + Patrimônio Líquido`. A soma dos débitos é
  sempre idêntica à soma dos créditos.
- **Entidade Contábil**: a contabilidade é estruturada por entidade jurídica (no padrão da banca,
  Cardoso & Bruno Advogados).
- **Imutabilidade e Rastreabilidade**: lançamentos contábeis já escriturados nunca são apagados
  ou editados diretamente. Retificações são realizadas exclusivamente por lançamentos de estorno
  com registro de justificativa, data e usuário responsável.
- **Idempotência**: cada movimentação financeira possui chave única de importação, impedindo
  duplicidades mesmo em caso de múltiplos envios ou reprocessamentos.
- **Competência e Caixa**: as receitas e despesas são reconhecidas pelo regime de competência
  (fato gerador) para fins de apuração societária, permitindo também visões sob regime de caixa
  para conciliação com os recebimentos e pagamentos efetivos.

> [!DICA]
> Se o módulo contábil foi ativado recentemente, comece conferindo a árvore no [Plano de contas](plano-de-contas.md)
> e realize a importação das transações pendentes em [Importação financeira](importacao.md).
