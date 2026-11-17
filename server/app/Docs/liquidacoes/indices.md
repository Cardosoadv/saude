---
titulo: Índices econômicos
resumo: As séries de correção e juros usadas nas liquidações, a atualização pelo Banco Central e a importação por CSV.
ordem: 50
permissao: admin.settings
palavras_chave: [índice, IPCA, IPCA-E, INPC, IGP-M, Selic, Banco Central, SGS, série, competência, lacuna, CSV]
---

As liquidações corrigem valores e calculam juros a partir de séries de índices guardadas no sistema. A tela fica
em **Configurações › Índices econômicos** e só aparece para quem tem a permissão `admin.settings`.

## Séries cadastradas

A tabela mostra, para cada índice, a **Fonte** (Banco Central, manual ou CSV), a **Periodicidade** (mensal ou
diária), a **Última competência** gravada e a **Situação**:

| Situação | Significado |
|---|---|
| **Completo** | a série não tem meses faltando |
| **Atrasado** | a série ainda não cobre o mês corrente |
| **Lacunas** | faltam meses no meio da série. Clique em **Ver lacunas** para ver quais |

> [!ATENCAO]
> Um mês faltando no meio do período impede o cálculo de qualquer liquidação que use aquele índice naquele
> período. Já o mês mais recente, ainda não divulgado, fica fora da correção com um aviso na memória de cálculo.

## Atualizar pelo Banco Central

O sistema busca os índices no Banco Central uma vez por dia. Para buscar na hora, clique em
**Atualizar pelo Banco Central**. Só entram valores novos, e o último mês já gravado é buscado de novo para pegar
eventuais revisões.

Quando um índice recebe valores novos, as liquidações em aberto que usam esse índice são recalculadas sozinhas.
Veja [Recálculo](detalhe.md#recalculo).

## Importar CSV

Para completar uma série à mão, use o botão **CSV** na linha do índice e escolha o arquivo. O arquivo tem duas
colunas, `competencia` e `valor`, com cabeçalho opcional:

```
competencia;valor
2025-01;0,16
2025-02;1,31
```

- O valor é a **variação percentual** do período: `0,16` significa 0,16%.
- A competência aceita `AAAA-MM`, `AAAA-MM-DD`, `MM/AAAA` ou `DD/MM/AAAA`.
- Com vírgula decimal, separe as colunas por ponto e vírgula.

Qualquer linha inválida cancela a importação inteira; corrija o arquivo e importe de novo.

> [!NOTA]
> Confira no site do Banco Central o código da série e a data de divulgação de cada índice antes de usá-lo nos
> cálculos.
