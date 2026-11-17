---
titulo: Detalhe da liquidação
resumo: Dados gerais, itens do cálculo, memória de cálculo, recálculo, atualização até hoje e histórico.
ordem: 10
telas: [liquidacao-detail]
palavras_chave: [itens, principal, juros, multa, honorários, custas, data-base, recalcular, atualizar até hoje, memória de cálculo, histórico]
---

O detalhe abre ao clicar numa liquidação da [lista](index.md) ou do cartão **Liquidação** do processo. No topo
ficam o número da liquidação, a situação, o processo (clique para abri-lo) e o tipo. À direita, o quadro
**Valores** mostra o **Valor atualizado**, o **Recebido**, o **Saldo a receber** e a data do **Último cálculo**.

Sem a permissão `liquidacoes.edit`, a tela abre só para leitura: os campos ficam travados e os botões de alterar
não aparecem.

## Dados gerais

| Campo | O que é |
|---|---|
| **Tipo** | por cálculo, por arbitramento ou pelo procedimento comum |
| **Situação** | a fase da liquidação. Veja [Situações](index.md#situacoes) |
| **Data-base** | até quando os valores são atualizados. Mudar a data recalcula tudo |
| **Data da liquidação** e **Homologação** | datas de registro, para controle |
| **Honorários contratuais (%)** | percentual do escritório sobre o que o cliente recebe, usado ao baixar parcelas |
| **Lançar os honorários no Financeiro a cada parcela baixada** | cria as receitas de honorários na baixa. Veja [Honorários no Financeiro](parcelas.md#honorarios-no-financeiro) |
| **Observações** | anotações livres; as sugestões de IA aplicadas também ficam registradas aqui |

Clique em **Salvar dados** para gravar. **Excluir liquidação** apaga a liquidação com todos os itens, parcelas e
documentos e não pode ser desfeito. Esse botão só aparece para quem tem `liquidacoes.delete`.

## Itens do cálculo

O quadro **Itens e critérios de cálculo** lista o que compõe o crédito. Comece pelo principal e depois inclua
juros, multa e honorários.

1. Clique em **Adicionar item**.
2. Escolha o **Tipo**: **Principal**, **Juros**, **Multa**, **Honorários**, **Custas** ou **Outros**.
3. Informe a **Descrição** (ex.: "Diferenças salariais") e o **Valor base (R$)**, o valor devido na data do
   vencimento, sem correção.
4. Preencha o critério de cálculo. Veja [Critérios de cálculo](criterios.md).
5. Para conferir antes de gravar, clique em **Calcular agora**: o valor na data-base aparece ao lado, sem ser
   gravado.
6. Clique em **Salvar e recalcular**.

**Multa** e **Honorários** não têm valor base: são um percentual sobre os demais itens, e o valor aparece depois
de salvar.

Um item **sem critério** é manual: vale o valor informado, sem correção. Na tabela, a coluna **Critério** mostra o
resumo do que foi aplicado, e o rodapé mostra o total, com a data-base.

Em cada item:

- o ícone de caderno abre a **memória de cálculo** logo abaixo da linha;
- **Comparar critérios** simula o mesmo item com critérios diferentes. Veja
  [Comparar critérios](criterios.md#comparar-criterios);
- o lápis edita e a lixeira exclui o item. O total é recalculado em seguida.

## Memória de cálculo

A memória mostra como cada valor foi obtido: a data-base e, para cada parcela do item, o índice, o período, o
fator acumulado mês a mês, o valor corrigido e os juros. Os avisos do cálculo também aparecem nela, como um
mês final de índice que ainda não foi divulgado.

Se o item ainda não foi calculado, a memória pede que você defina um critério e use **Recalcular**.

## Recálculo

- **Recalcular** refaz o cálculo de todos os itens com a data-base atual.
- **Atualizar até hoje** move a data-base para a data de hoje e recalcula. O sistema pede confirmação.

Criar ou alterar um item, ou mudar o critério padrão ou a data-base, já recalcula a liquidação sem você pedir.

Quando um índice recebe valores novos, o sistema recalcula sozinho as liquidações em aberto que usam esse
índice. As liquidações **Homologada**, **Em pagamento** e **Quitada** ficam de fora, para não mudar um valor já
fixado; nelas aparece um aviso no topo. Use **Recalcular** ou **Atualizar até hoje** se precisar atualizar.

> [!ATENCAO]
> Se faltar o valor de algum mês do índice no meio do período, o sistema recusa o cálculo e informa os meses
> que faltam. Complete a série em [Índices econômicos](indices.md).

## Histórico de recálculos

Cada recálculo que muda o valor de um item fica registrado em **Histórico de recálculos**: a data, o item, o
valor anterior e o novo e o motivo, por exemplo "Recálculo manual".

## Arredondamento

Os valores são calculados com 12 casas decimais e arredondados ao centavo, com a metade arredondada para cima.
O arredondamento é feito no valor corrigido de cada parcela, nos juros de cada parcela e em cada item de
percentual.
