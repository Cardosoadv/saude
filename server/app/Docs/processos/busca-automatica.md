---
titulo: Busca automática no DJEN
resumo: Cadastrar números de processo para o sistema procurar as publicações no DJEN.
ordem: 40
telas: [busca-automatica]
palavras_chave: [DJEN, monitoramento, busca automática, publicação, diário, importar, CSV, JSON, lote]
---

Em **Processos › Busca Automática** você cadastra números de processo para o sistema consultar, de tempos
em tempos, as publicações no **DJEN** (Diário de Justiça Eletrônico Nacional). É útil para processos que
ainda não estão na carteira ou que você quer acompanhar.

## Cadastrar

Clique em **Novo Processo** (ou **Cadastrar Processo**), informe o **Número do Processo (CNJ)** e a
**Observação** e clique em **Cadastrar**.

A **Observação** é obrigatória: use-a para justificar por que o processo entrou na busca automática (por
exemplo, cliente em prospecção, processo da parte contrária, pedido de um sócio). Ela aparece na coluna
**Observação** da lista, entra na busca por texto e pode ser alterada pelo botão de lápis na linha do processo.

## Situações

| Situação | Significado |
|---|---|
| **Aguardando busca** | ainda não foi consultado |
| **Localizado** | encontrado; a coluna **Processo Vinculado** leva ao processo |
| **Sem novidades** | consultado, sem publicação nova |
| **Falha na última consulta** | o DJEN não respondeu; o sistema tenta de novo depois |

Os contadores mostram quantos estão **Cadastrados**, **Pendentes** e **Localizados**. Filtre pela situação, pela
**Última tentativa** (hoje, últimos 7 ou 30 dias, nunca consultado) ou busque pelo número ou pela observação.

## Buscar agora

**Buscar Agora** consulta todos imediatamente. Para um só processo, use o botão de busca na linha dele.

Além disso, a sincronização automática do DJEN (a mesma que traz as intimações pela OAB) também consulta os
processos da busca automática que ainda não foram localizados, uma vez por dia para cada processo. Não é preciso
clicar em **Buscar Agora** para que eles sejam verificados.

## Importar em lote (CSV ou JSON)

Para cadastrar vários processos de uma vez, clique em **Importar em Lote**. No modal você pode baixar um
**exemplo CSV** e um **exemplo JSON** para usar como modelo.

- **CSV**: colunas `numero_processo` e `observacao`, separadas por `;` (padrão do Excel) ou `,`. Observações
  que contenham o separador devem ficar entre aspas. Sem cabeçalho, a 1ª coluna é o número e a 2ª a observação.
- **JSON**: uma lista de objetos `{"numero_processo": "...", "observacao": "..."}`, solta ou dentro da chave
  `"processos"`.
- O número pode vir com ou sem máscara. No Excel, formate a coluna do número como **Texto** para ele não virar
  notação científica.
- A **Observação padrão** do modal é usada nas linhas que vierem sem observação.
- Limite: 1.000 processos e 2 MB por arquivo.

Clique em **Validar arquivo** para ver, antes de gravar, quantos registros são válidos e quais serão deixados de
fora e por quê: número com menos de 20 dígitos ou dígito verificador errado, observação ausente, processo repetido
no arquivo ou já cadastrado na busca automática. Depois clique em **Importar** para gravar só os válidos. A
importação vale apenas para a busca automática; ela não cria processos na carteira.
