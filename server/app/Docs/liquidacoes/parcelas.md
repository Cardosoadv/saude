---
titulo: Parcelas e pagamentos
resumo: Gerar o parcelamento, baixar e cancelar parcelas, lançar honorários no Financeiro e receber avisos de vencimento.
ordem: 30
palavras_chave: [parcela, parcelamento, entrada, baixa, pagamento, comprovante, vencimento, atraso, cobrança, honorários, sucumbência, alvará]
---

O quadro **Parcelas e pagamentos**, no [detalhe da liquidação](detalhe.md), controla o recebimento do crédito.
No topo aparecem o **Valor atualizado**, o **Pago**, o **Saldo a receber**, o valor **Em atraso** (se houver) e a
**Próxima parcela**.

## Gerar o parcelamento

1. Clique em **Gerar parcelamento**.
2. Informe o **Número de parcelas** (1 a 360), a **Periodicidade** (semanal, quinzenal, mensal, bimestral,
   trimestral, semestral ou anual) e o **Primeiro vencimento**.
3. Se houver entrada, informe a **Entrada (R$, opcional)** e, se for o caso, a **Data da entrada**. Em branco, a
   entrada vence com a primeira parcela. Ela aparece na tabela como **Entrada**.
4. Clique em **Gerar**.

O sistema divide o valor atualizado. Os centavos que sobram da divisão ficam na última parcela. Quando o dia do
vencimento não existe no mês, como 31 em fevereiro, vale o último dia do mês. Se a liquidação estava
**Homologada**, ela passa para **Em pagamento**.

### Refazer o parcelamento

Se o valor mudou depois de um recálculo, o quadro avisa que o plano de parcelas difere do valor atualizado. Para
acompanhar o novo valor, clique em **Refazer parcelamento** e marque **Substituir as parcelas em aberto por este
plano**.

> [!NOTA]
> Com alguma parcela já paga, não é possível refazer o plano. Nesse caso, cancele as parcelas em aberto que não
> valem mais.

## Baixar uma parcela

1. Na linha da parcela, clique no ícone de baixa (**Baixar**).
2. Informe a **Data do pagamento**, que não pode ser futura, e a **Forma**: PIX, transferência, depósito
   judicial, alvará, boleto ou outra.
3. Se quiser, escreva uma **Observação** e use **Anexar comprovante** para enviar o comprovante.
4. Clique em **Confirmar baixa**.

A parcela é baixada pelo valor inteiro; não há baixa parcial. O comprovante vai para a pasta do cliente no
Google Drive e fica no ícone de clipe da linha. Se o envio ao Google Drive falhar, a parcela não é baixada:
tente de novo.

Quando a última parcela em aberto é baixada, a liquidação passa para **Quitada**.

## Cancelar uma parcela

O ícone **Cancelar** da linha cancela uma parcela pendente ou vencida, depois de confirmar. Parcela paga não pode
ser cancelada. A parcela cancelada continua na tabela, para registro.

## Honorários no Financeiro

Se em **Dados gerais** estiver marcado **Lançar os honorários no Financeiro a cada parcela baixada**, cada baixa
cria as receitas de honorários do escritório no Financeiro:

- **sucumbência**: a parte da parcela que corresponde aos itens de **Honorários** da liquidação, na mesma
  proporção que eles têm no valor total;
- **contratuais**: o percentual de **Honorários contratuais (%)** sobre o restante da parcela.

Exemplo: numa liquidação de R$ 110.000,00 com R$ 10.000,00 de honorários de sucumbência e 30% de contratuais,
uma parcela de R$ 11.000,00 gera R$ 1.000,00 de sucumbência e R$ 3.000,00 de contratuais (30% de R$ 10.000,00).

Na baixa, o campo **Conta que recebeu** indica a conta do Financeiro. Com a conta, a receita já entra como
recebida; sem ela, fica como receita a receber. Se o Financeiro recusar o lançamento, por exemplo porque o mês
está fechado, a baixa inteira é desfeita. Veja [Fechamento do mês](../financeiro/fechamento.md).

> [!ATENCAO]
> A natureza da receita, o repasse ao cliente e a tributação devem ser conferidos pelo advogado e pelo contador.

## Avisos de vencimento

Uma rotina diária do sistema:

- marca como **Vencida** a parcela pendente cujo vencimento passou;
- avisa por e-mail o responsável pelo processo e abre uma tarefa de cobrança para ele;
- avisa uma vez, com alguns dias de antecedência, das parcelas que vão vencer.

Mesmo antes da rotina rodar, a tela já mostra em vermelho a parcela com vencimento passado.
