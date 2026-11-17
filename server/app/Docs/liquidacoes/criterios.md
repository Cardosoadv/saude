---
titulo: Critérios de cálculo
resumo: Correção monetária, juros, troca de índice por data, competências, abatimentos, percentuais, critério padrão, comparação e sugestão por IA.
ordem: 20
palavras_chave: [critério, correção monetária, índice, IPCA, IPCA-E, INPC, IGP-M, Selic, juros de mora, pro rata, termo inicial, abatimento, competência, impugnação, IA]
---

O **critério** diz ao sistema como atualizar um item: qual índice corrige o valor, desde quando, quais juros
incidem e se há trocas de critério ao longo do tempo. Ele é preenchido na janela do item (veja
[Itens do cálculo](detalhe.md#itens-do-calculo)) e no critério padrão da liquidação.

> [!ATENCAO]
> Índice, taxa, termo inicial e base de cada item dependem do título judicial, da natureza do crédito e da lei
> vigente em cada período. O sistema só aplica o que for informado: confira cada critério com o advogado
> responsável.

## Critério padrão

O quadro **Critério padrão**, à direita do detalhe, guarda o critério comum a vários itens. Clique em
**Editar**, preencha e use **Salvar e recalcular**.

Num item, marque **Usar o critério padrão da liquidação** para aproveitá-lo. O que você preencher no próprio item
substitui só o campo correspondente do padrão. Assim, um item pode usar o índice padrão e ter outro termo inicial
de juros.

## Correção monetária

| Campo | O que é |
|---|---|
| **Índice** | a série de correção, ou **Sem correção** |
| **Termo inicial** | a data a partir da qual o valor é corrigido |
| **Mês inicial** e **Mês final** | como tratar o primeiro e o último mês do período |

Para o mês inicial e o final:

- **Mês cheio**: aplica a variação inteira do mês;
- **Pro rata dia**: aplica só a fração dos dias do mês que estão dentro do período;
- **Excluir o mês**: não aplica a variação daquele mês.

Se o índice do último mês ainda não foi divulgado, esse mês fica fora da correção e a memória de cálculo avisa.

## Juros

| Campo | O que é |
|---|---|
| **Tipo** | **Sem juros**, **Simples** ou **Compostos** |
| **Incidem sobre** | o **Valor corrigido** ou o **Valor nominal** |
| **Taxa mensal (%)** | taxa fixa, por exemplo 1 |
| **ou índice** | uma série no lugar da taxa fixa, como a Selic |
| **Termo inicial dos juros** | em branco, os juros correm desde o vencimento de cada competência |

O período de juros conta os meses fechados mais os dias restantes divididos por 30, do termo inicial até a
data-base. A data-base não entra na conta.

## Troca de critério por data

Use **Adicionar troca** quando o critério muda no meio do período, por exemplo IPCA-E até uma alteração legal e
Selic depois. Em cada troca, informe a data e o novo índice e/ou os novos juros. **Mantém a correção** e
**Mantém os juros** conservam o que vinha antes.

A troca vale a partir do primeiro dia do mês da data informada, e cada mês usa um único índice.

## Competências

Quando o principal é devido em várias datas, como verbas mensais, use **Adicionar competência** e informe a data
e o valor de cada uma. Cada valor é corrigido desde a sua própria data. Sem competências, o item é tratado como um
valor único, o valor base, na data do termo inicial da correção.

## Abatimentos

Pagamentos parciais e depósitos já feitos entram em **Adicionar abatimento**, com a data e o valor (positivo).
O sistema corrige cada abatimento desde a sua data e o desconta do item.

## Multa e honorários

Os itens **Multa** e **Honorários** são calculados por percentual:

| **Calculado sobre** | Base |
|---|---|
| **Principal corrigido (sem juros)** | itens de principal, custas e outros, já corrigidos |
| **Principal corrigido com juros** | os mesmos itens com os juros, mais os itens de juros |
| **Total dos demais itens** | todos os itens; nos honorários, inclui também a multa |

A multa é calculada antes dos honorários, para que os honorários possam incidir sobre ela.

## Comparar critérios

O botão **Comparar critérios** de um item calcula esse item na data-base com até quatro critérios diferentes,
sem gravar nada. Serve para medir o impacto de uma impugnação ou de uma tese alternativa.

1. Dê um nome a cada cenário e preencha o critério dele. Use **Novo cenário** para acrescentar outro.
2. Clique em **Comparar**.
3. A tabela mostra o valor de cada cenário e a **Diferença para o 1º**.

Para adotar um cenário, edite o item e preencha o mesmo critério.

## Sugestão de critérios por IA

O quadro **Sugestão de critérios por IA**, à direita do detalhe, lê um trecho da decisão e propõe o critério.

1. Abra o quadro e cole em **Trecho da decisão** a parte da sentença ou do acórdão que trata da correção, dos
   juros, da multa e dos honorários (até 30.000 caracteres).
2. Clique em **Sugerir critérios**.
3. Confira o **Critério sugerido**, os **Trechos que fundamentam a sugestão**, as observações e os avisos, como
   índice não cadastrado ou juros sem taxa.
4. Se estiver correto, marque **Conferi a decisão e confirmo que este critério está correto** e clique em
   **Aplicar como critério padrão da liquidação**.
5. Use **Recalcular** para atualizar os valores.

A sugestão substitui o critério padrão atual e fica registrada nas observações. Os percentuais de multa e de
honorários citados na decisão não viram itens sozinhos: cadastre-os como itens de **Multa** e **Honorários**.

> [!ATENCAO]
> O trecho colado é enviado ao provedor de IA configurado no sistema. A IA apenas sugere; nada é gravado até
> você confirmar.
