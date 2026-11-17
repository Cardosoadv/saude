---
titulo: Importação e integração financeira
resumo: Conciliação e importação automática de receitas recebidas, despesas pagas e transferências para a contabilidade.
ordem: 10
telas: [contabil-importar]
palavras_chave: [importação, integração, receitas recebidas, despesas pagas, transferências, conciliação, partidas dobradas, categorias, dicionário, rateio, divisão]
---

## Visão geral

A aba **Importar do Financeiro** (`#/contabil` ou `#/contabil-importar`) é a ponte entre
o módulo financeiro do sistema e a escrituração contábil em partidas dobradas.

As movimentações financeiras já liquidadas (recebidas ou pagas) são listadas com sugestão
automática das contas contábeis de débito e crédito, prontas para conciliação em lote.

## Como funciona a conciliação automática

O sistema classifica cada transação conforme sua origem:

- **Receitas Recebidas**: geram débito na conta bancária/caixa do Ativo e crédito na conta
  de Receita de Atendimentos (ou outra categoria cadastrada).
- **Despesas Pagas**: geram débito na conta de Despesa/Custo correspondente e crédito na
  conta bancária/caixa de onde o valor saiu.
- **Transferências**: geram débito na conta bancária de destino e crédito na conta bancária
  de origem.

## Sugestão de contas pelas categorias

As categorias usadas no financeiro aparecem abaixo da descrição de cada transação e definem a
conta sugerida para a perna "da categoria": o **crédito** nas receitas e o **débito** nas despesas.
A conta vem do **dicionário de categorias**; enquanto a categoria não estiver no dicionário, é
sugerida a conta padrão do plano. O selo **dicionário** indica que a sugestão veio dele.

Quando a receita ou despesa foi rateada entre **duas ou mais categorias**, a transação já chega
dividida (selo **por categoria**): uma parte por categoria, com o valor pago repartido na mesma
proporção do rateio — inclusive em pagamentos parciais. Cada parte vira um lançamento próprio no
diário. Abra **Dividido em N** para revisar valores e contas, ou **Desfazer divisão** para importar
em um único lançamento.

### Atualizar o dicionário

Sempre que você troca a conta sugerida de uma categoria (na linha da transação ou numa parte da
divisão), o sistema pergunta se deseja gravar a troca no dicionário. Confirmando:

- as próximas importações dessa categoria já vêm com a nova conta;
- as transações pendentes da lista que ainda estavam com a sugestão antiga são ajustadas na hora
  (as que você alterou manualmente ficam como estão).

Escolha **Agora não** para usar a conta só nesta transação.

## Passo a passo para importar

1. **Defina os filtros**: escolha o período desejado (data inicial e final), o tipo de
   transação (Todas, Receitas, Despesas ou Transferências) e o status (Pendentes ou Importadas).
2. **Revise as contas sugeridas**: na tabela de transações pendentes, verifique as contas
   contábeis de débito e crédito pré-selecionadas e as divisões sugeridas por categoria. Se
   necessário, selecione outra conta analítica na lista suspensa (e grave a troca no dicionário,
   se ela valer para as próximas vezes).
3. **Marque as transações**: selecione individualmente as caixas de seleção à esquerda ou
   marque a caixa no cabeçalho da tabela para selecionar todas as pendentes da página.
4. **Importe em lote**: clique no botão **Importar Selecionadas**. O sistema processará as
   partidas dobradas e confirmará a escrituração no [Livro Diário](lancamentos.md).

> [!NOTA]
> O sistema aplica chaves únicas de idempotência para cada transação (`saude:fin_receita:...`,
> `saude:fin_despesa:...` e `saude:fin_transferencia:...`). Mesmo que o botão seja
> clicado repetidamente ou a requisição reenviada, nenhuma transação é escriturada em duplicidade.

> [!ATENCAO]
> Transações ocorridas em períodos contábeis já fechados serão rejeitadas pelo validador. Caso
> seja indispensável importar lançamentos retroativos de um mês encerrado, consulte
> [Exercícios e períodos](periodos.md) para regras de reabertura.
