---
titulo: Liquidações
resumo: Cálculo do crédito executado, com correção, juros, multa e honorários, parcelamento e recebimentos.
ordem: 15
modulo: processos
telas: [liquidacoes]
palavras_chave: [liquidação, cumprimento de sentença, execução, cálculo, atualização, crédito, condenação]
---

Uma **liquidação** registra quanto vale o crédito de um processo e acompanha o recebimento dele. Ela reúne o
cálculo atualizado (principal, juros, multa, honorários e custas), o parcelamento, a baixa de cada parcela e os
documentos que fundamentam o cálculo.

Cada liquidação pertence a um processo. Um processo pode ter mais de uma, por exemplo um cálculo apresentado
e outro refeito depois da impugnação.

## Lista de liquidações

Abra **Processos › Liquidações**. As abas filtram pela situação: **Todas**, **Rascunho**, **Em cálculo**,
**Apresentadas**, **Homologadas**, **Em pagamento** e **Quitadas**. A busca da barra superior procura pelo
número do processo ou pelo nome do cliente.

Cada linha mostra o processo, o tipo, a situação, o **Valor atualizado**, o **Saldo a receber** e a data da
**Próxima parcela**, em vermelho quando já venceu. Clique na linha para abrir o
[detalhe da liquidação](detalhe.md).

A liquidação também aparece no detalhe do processo, no cartão **Liquidação**, com a situação, o valor e o saldo
de cada uma.

## Criar uma liquidação

1. Clique em **Nova liquidação**, na lista ou no cartão **Liquidação** do processo.
2. Na lista, digite pelo menos duas letras do número, do título ou de uma parte e escolha o **Processo**. Pelo
   cartão do processo, ele já vem preenchido.
3. Escolha o **Tipo**: **Por cálculo**, **Por arbitramento** ou **Pelo procedimento comum**.
4. Informe a **Data-base do cálculo**, a data até a qual os valores serão atualizados.
5. Clique em **Criar liquidação**. O sistema abre o detalhe para você lançar os itens.

## Situações

| Situação | Quando usar |
|---|---|
| **Rascunho** | o cálculo ainda está sendo montado |
| **Em cálculo** | os itens e critérios estão sendo conferidos |
| **Apresentada** | o cálculo foi juntado ao processo |
| **Impugnada** | a parte contrária contestou o cálculo |
| **Homologada** | o juízo homologou o valor |
| **Em pagamento** | há parcelas sendo recebidas |
| **Quitada** | todas as parcelas foram pagas |

Você muda a situação em **Dados gerais**, no detalhe. Duas mudanças são automáticas: gerar o parcelamento de
uma liquidação **Homologada** a passa para **Em pagamento**, e baixar a última parcela em aberto a passa para
**Quitada**.

> [!NOTA]
> A partir de **Homologada**, os valores ficam congelados: índices novos não recalculam a liquidação sozinhos.
> Veja [Recálculo](detalhe.md#recalculo).

## Quem pode usar

| Para fazer | Permissão |
|---|---|
| Ver liquidações, a memória de cálculo e os relatórios | `liquidacoes.access` |
| Ver só as liquidações dos processos do próprio cliente | `liquidacoes.exclusive` |
| Criar | `liquidacoes.create` |
| Editar itens, critérios, parcelas e documentos; recalcular | `liquidacoes.edit` |
| Excluir | `liquidacoes.delete` |
| Manter os índices econômicos | `admin.settings` |

Administradores e advogados já recebem todas as permissões de liquidação. A privacidade do processo também vale:
se você não vê o processo, não vê as liquidações dele. Veja [Quem vê cada processo](../processos/privacidade.md).

## Nesta seção

- [Detalhe da liquidação](detalhe.md): dados gerais, itens, recálculo e histórico.
- [Critérios de cálculo](criterios.md): correção, juros, trocas de índice, competências, abatimentos e percentuais.
- [Parcelas e pagamentos](parcelas.md): parcelamento, baixa, honorários no Financeiro e avisos de vencimento.
- [Documentos e relatórios](documentos.md): arquivos no Google Drive, PDF da memória de cálculo e planilha.
- [Índices econômicos](indices.md): as séries usadas na correção e nos juros.
