---
titulo: Plano de contas
resumo: Estrutura hierárquica das contas contábeis (Ativo, Passivo, PL, Receitas e Despesas).
ordem: 30
telas: [contabil-plano-contas]
palavras_chave: [plano de contas, contas sintéticas, contas analíticas, ativo, passivo, patrimônio líquido, receitas, despesas]
---

## Estrutura das contas

A aba **Plano de Contas** (`#/contabil-plano-contas`) apresenta a estrutura padronizada de
classificação contábil do escritório. O plano padrão segue o Anexo 7 da **ITG 1000** (modelo
contábil para microempresas e empresas de pequeno porte), com as contas próprias de um escritório
de advocacia. As contas são organizadas em cinco grandes grupos:

| Grupo | Descrição | Natureza |
|---|---|---|
| **1 - Ativo** | Disponibilidades, honorários a receber, tributos a recuperar, imobilizado e intangível. Inclui `1.3 - Valores de Clientes (conta segregada)` | Devedora |
| **2 - Passivo** | Obrigações trabalhistas, fornecedores, tributos, empréstimos. Inclui `2.3 - Valores de Clientes (conta segregada)` | Credora |
| **3 - Patrimônio Líquido** | Capital social, reservas, resultados acumulados e distribuição de lucros por sócio | Credora |
| **4 - Receitas** | Honorários contratuais, de êxito e de sucumbência, deduções da receita e receitas financeiras | Credora |
| **5 - Custos e Despesas** | Custos dos serviços, despesas com pessoal, gerais, tributárias e financeiras | Devedora |

A ITG 1000 reúne o patrimônio líquido dentro do passivo (`2.3`) e as receitas e despesas em um só
grupo de resultado (`3`). O sistema exige um tipo único por grupo de primeiro nível; por isso o
patrimônio líquido, as receitas e as despesas ficam em grupos próprios (3, 4 e 5). O código que a
conta tem no Anexo 7 fica no campo **código de referência** (`reference_code`); contas sem código
de referência são específicas do escritório (por exemplo, honorários de êxito, custas a reembolsar e
valores de clientes).

## Contas sintéticas e analíticas

- **Contas Sintéticas**: contas de agrupamento e totalização (ex.: `1.1 - Ativo Circulante`,
  `1.1.1 - Disponibilidades`). Não recebem lançamentos diretos; seu saldo é a soma de suas
  contas filhas.
- **Contas Analíticas**: contas de último nível (folhas da árvore), que recebem diretamente
  os débitos e créditos dos [Lançamentos contábeis](lancamentos.md) e das
  [Importações financeiras](importacao.md) (ex.: `1.1.1.02.01 - Bancos Conta Movimento`).

## Navegação e visualização

- **Visualização em Árvore**: exibe a hierarquia completa com nós expansíveis e recolhíveis,
  facilitando a compreensão dos grupos e subgrupos;
- **Visualização em Tabela**: lista todas as contas com código, nome, grupo, natureza e saldo
  atual apurado;
- **Busca rápida**: pesquise pelo código estruturado (ex.: `1.1.1`) ou por termos do nome
  da conta (ex.: `Honorários`).

## Plano padrão para sociedades de advogados

O plano padrão (`itg-1000-advocacia`) acompanha o sistema e traz:

- Valores de clientes em grupos próprios do ativo (`1.3`) e do passivo (`2.3`), nunca no patrimônio líquido;
- Honorários contratuais, de êxito e de sucumbência, e custas adiantadas a reembolsar;
- Distribuição de lucros por sócio (`3.9`) e pró-labore;
- Tributos do Simples Nacional, com as contas do lucro presumido caso o regime mude.

Do Anexo 7 ficaram de fora os grupos que um escritório de serviços não usa (estoques, investimentos
societários, propriedades para investimento, IPI, ICMS, ajuste a valor recuperável e equivalência
patrimonial). Eles podem ser acrescentados importando um arquivo CSV ou JSON.

> [!NOTA]
> O seeder inicial `ContabilSeeder` carrega o plano padrão na implantação do sistema. Antes de
> adicionar novas contas analíticas, revise o plano com a assessoria contábil responsável pelo
> escritório.

## Convertendo o plano anterior

Quem já usa o plano anterior (códigos como `1.1.02` e `5.2.03`) converte para o padrão com o
comando abaixo, executado no servidor:

```
php spark contabil:adequar-itg1000 --simular
php spark contabil:adequar-itg1000
```

O primeiro comando faz a conversão inteira e a desfaz no fim, só para conferir. A conversão mantém
cada conta analítica no mesmo registro: lançamentos, saldos e a ligação das categorias financeiras
continuam nas mesmas contas, que só ganham o novo código e o novo grupo. Os nomes que identificam o
escritório (banco, sócios) são mantidos. Se o plano atual tiver alguma conta que não pertence ao
plano anterior, o comando não grava nada e lista as contas a tratar.
