---
titulo: Importar extrato bancário
resumo: Importar o arquivo OFX do banco, conciliar com lançamentos existentes ou lançar o que falta e conferir o saldo.
ordem: 45
telas: [extrato-importacao, extrato-movimentos]
palavras_chave: [OFX, importar extrato, conciliação, conciliar, lançar, movimentos, regras, conferência de saldo, extrato do banco]
---

Esta página explica como trazer o extrato do banco para o sistema e casar cada linha com o que a clínica já
lançou. Não confunda com a tela **Extrato**, que mostra os lançamentos do próprio sistema
([Extrato e categorias](extrato.md)).

O caminho tem três etapas: **importar** o arquivo, **conciliar ou lançar** cada movimento e **conferir o saldo**.

## O que você precisa

- O arquivo do extrato no formato **OFX**, baixado no internet banking (procure por "exportar extrato" e
  escolha OFX). PDF e planilha não servem.
- As permissões do módulo Financeiro. Quem tem `financeiro.*` (administrador e profissional) já recebe todas; para
  os demais perfis, o administrador concede em **Usuários**:

| Para fazer | Permissão |
|---|---|
| Ver o histórico, os movimentos, a conferência e as regras | `financeiro.access` |
| Importar extratos | `financeiro.extratos_importar` |
| Conciliar, desfazer e ignorar | `financeiro.extratos_conciliar` |
| Lançar e gerenciar regras | `financeiro.extratos_lancar` |
| Anexar comprovante a um movimento | `financeiro.edit` |

Sem a permissão, o botão correspondente não aparece.

## 1. Importar o arquivo

Em **Financeiro › Importar Extrato**:

1. Escolha a **Conta** a que o arquivo pertence.
2. Arraste o arquivo `.ofx` para a área indicada ou use **Escolher arquivo**. O tamanho máximo padrão é 5 MB.
3. Clique em **Ver prévia**. Nada é gravado nesta etapa.
4. Confira a prévia e clique em **Importar**.

### Como ler a prévia

A prévia mostra a conta escolhida, o período do arquivo e quatro números:

| Número | Significado |
|---|---|
| **Lidos** | linhas encontradas no arquivo |
| **Novos** | movimentos que ainda não estavam no sistema e serão importados |
| **Já importados** | movimentos que o sistema já tem; serão ignorados |
| **Conflitos** | mesmo identificador de um movimento já importado, mas com data ou valor diferentes; nada é alterado |

Exemplo fictício: um arquivo de outubro com 30 linhas, dos quais 15 já tinham sido importados antes, aparece
como **30 lidos, 15 novos, 15 já importados, 0 conflitos**.

### Importar duas vezes é seguro

O sistema reconhece cada operação do banco. Enviar de novo o mesmo arquivo, ou um arquivo de outro período que
repita dias já importados, **não cria movimentos duplicados**. Reenviar o mesmo arquivo mostra
**0 novos** e "já importados" no lugar. Um movimento que você apagou não volta numa nova importação.

### Alertas

| Alerta | O que significa | Precisa confirmar? |
|---|---|---|
| Arquivo já enviado antes | o mesmo arquivo já foi importado; nada se repete | Não |
| Período já coberto | todo o período do arquivo já tinha sido importado | Não |
| Conflitos | há movimentos com o mesmo identificador, mas data ou valor diferentes | Não |
| Mês fechado | há movimentos novos em mês já fechado; entram só como histórico e não podem ser lançados | Não |
| Possível reemissão de identificadores | metade ou mais do arquivo tem identificador novo, mas data, valor e descrição iguais aos de movimentos já importados. O banco pode ter reemitido o extrato | **Sim** |

Quando o alerta exige confirmação, o sistema abre um aviso antes de importar. Em caso de dúvida, volte e peça
ajuda a quem administra o sistema antes de confirmar: confirmar pode duplicar movimentos.

### Erros comuns

- **"O arquivo é de outra conta"**: banco, agência ou número do arquivo não conferem com o cadastro da conta
  escolhida. Escolha a conta certa ou corrija o cadastro. Nada é gravado e o botão **Importar** não aparece.
  O dígito verificador não atrapalha: agência "0001" no cadastro aceita "0001-9" no arquivo, e conta com ou sem
  dígito (separado ou colado) também. Já números diferentes, ou com um zero a mais ou a menos, são recusados.
- **Importação em andamento**: outra pessoa está importando para a mesma conta. Aguarde alguns instantes.
- **Arquivo vazio, grande demais ou que não é OFX**: a tela avisa antes de enviar.

### Resultado e histórico

Depois de importar, a tela mostra quantos movimentos foram lidos, novos, ignorados por já existirem e em
conflito. O botão **Conciliar movimentos** leva à próxima etapa. Mais abaixo, o **Histórico de importações**
lista data, conta, período, totais e quem importou.

## 2. Conciliar e lançar

Em **Financeiro › Movimentos do Extrato**, escolha a conta, o período e o **Status** (o padrão é **Pendente**).
Cada movimento importado nasce **Pendente** e termina em um destes estados:

| Status | Significado |
|---|---|
| **Pendente** | ainda não foi tratado |
| **Conciliado** | foi ligado a uma despesa paga, receita recebida ou transferência que já existia |
| **Lançado** | gerou um lançamento novo a partir do extrato |
| **Ignorado** | foi descartado, com motivo registrado |

### Conciliar com um lançamento existente

Use quando a clínica já lançou o pagamento ou o recebimento e falta só ligar à linha do banco.

1. Em um movimento pendente, clique em **Conciliar**.
2. O sistema lista os candidatos com o mesmo valor e data próxima (padrão de até 3 dias), com uma pontuação.
   O selo **nome confere** aparece quando o nome também bate.
3. Clique em **Conciliar** no candidato certo.

Se existir um lançamento **em aberto** com o mesmo valor, o botão **Quitar** dá baixa nele e concilia de uma vez.
Um pagamento só pode estar ligado a um movimento.

### Lançar o que ainda não existe

1. Em um movimento pendente, clique em **Lançar**. Saída vira despesa paga; entrada vira receita recebida.
2. Revise a **Descrição**. Em despesa, escolha o **Fornecedor** (ou informe o nome de um novo) e a **Categoria**.
   Em receita, o cliente e a categoria são opcionais.
3. Se quiser, preencha o **Rateio entre sócios** em percentual. A soma precisa fechar 100%.
4. Marque **Lembrar esta classificação** para criar uma regra que preencha o mesmo lançamento nos próximos extratos.
5. Clique em **Lançar**. O botão fica desabilitado durante o envio; mesmo um clique duplo não gera dois pagamentos.

### Ignorar e desfazer

- **Ignorar**: para o que não deve virar lançamento (por exemplo, uma tarifa já refletida em outro lugar).
  O motivo é obrigatório e fica visível na linha.
- **Desfazer**: devolve um movimento **Conciliado** ou **Ignorado** para **Pendente**. O pagamento ligado **não** é apagado.
- Movimento **Lançado**: para desfazer, exclua o pagamento lançado; o movimento volta para **Pendente** sozinho.

### Comprovante

Em movimentos **Conciliados** ou **Lançados** de despesa ou receita, o botão **Comprovante** anexa PDF, JPG, PNG ou
WEBP ao pagamento, com os mesmos limites dos demais comprovantes (10 MB por arquivo, 5 por envio).

### Em lote

- **Conciliar sugestões seguras**: o sistema mostra apenas os movimentos com valor e data exatos e um único
  candidato. Você confere a lista e confirma. Transferências nunca entram no lote.
- **Lançar selecionados pela regra**: marque até 50 movimentos pendentes. Só entram os que têm uma regra completa
  (em despesa, fornecedor e categoria); os demais aparecem no resultado com o motivo, para você lançar um a um.

O resultado mostra o que deu certo e o que foi recusado, item por item. Um item recusado não impede os outros.

### Mês fechado

Em mês já fechado, conciliar, desfazer e lançar são recusados e a tela mostra o motivo. Para tratar o movimento,
reabra o mês em [Fechamento do mês](fechamento.md).

## 3. Conferir o saldo

A aba **Conferência de saldo** compara, para o mês escolhido, o **saldo do banco** (informado no arquivo OFX) com o
**saldo do sistema** na mesma data:

| Situação | O que fazer |
|---|---|
| **Confere** | nada |
| **Diferença explicada pelos pendentes** | concilie ou lance os movimentos pendentes listados |
| **Divergente** | há diferença que os pendentes não explicam; revise lançamentos duplicados, esquecidos ou com valor errado |
| **Sem saldo do banco** | nenhum arquivo importado traz o saldo naquele mês, ou foi importado antes de o sistema guardar essa data |

A conferência é só informativa e não bloqueia nada.

## Regras de classificação

A aba **Regras de classificação** lista as regras da conta. Cada uma diz: "quando o movimento tiver tal texto,
sugira este fornecedor ou cliente, esta categoria e este rateio". Elas só **sugerem**; nada é lançado sem você.
Para criar uma regra, marque **Lembrar esta classificação** ao lançar. Para apagar, use o ícone de lixeira
(precisa da permissão de lançar). Excluir uma regra não altera lançamentos já feitos.

## No fechamento do mês

A tela [Fechamento do mês](fechamento.md) avisa quantos movimentos do extrato seguem **pendentes**, com atalho
para a tela de movimentos. O aviso é informativo e não impede o fechamento.

## Privacidade

O sistema guarda os movimentos (data, valor, descrição e nome da contraparte), mas **não guarda o arquivo OFX**,
só uma impressão digital dele para reconhecer reenvios. Descrição e nome da contraparte não vão para os registros
de auditoria.

> [!NOTA]
> O administrador pode ajustar, em **Configurações** (aba Sistema), o tamanho máximo do arquivo
> (`EXTRATOS_MAX_MB`, de 1 a 20 MB), a janela de dias das sugestões (`EXTRATOS_JANELA_DIAS`, de 0 a 30) e os
> limites de lote (`EXTRATOS_LIMITE_LOTE`, de 1 a 200, e `EXTRATOS_LIMITE_LOTE_CONCILIACAO`, de 1 a 1000).
