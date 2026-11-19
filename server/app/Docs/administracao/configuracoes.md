---
titulo: Parâmetros e manutenção
resumo: Parâmetros do sistema, integração com o Google, limpeza de cache e atualização do banco de dados.
ordem: 30
palavras_chave: [parâmetro, cache, google, drive, pasta, sincronização, fila, migrations, banco de dados]
---

## Parâmetros do sistema

A aba **Sistema** lista os parâmetros (**Parâmetro (Chave)**, **Valor** e **Descrição**) usados pelo sistema.
Use **Nova Configuração** para criar e o lápis para editar.

> [!ATENCAO]
> Mude um parâmetro só quando souber o efeito: um valor errado pode desligar integrações.

## Integração Google

O cartão **Integração Google** liga o sistema ao Google Drive. Clique em **Autenticar com Google** e autorize
o acesso na conta da clínica. Isso só é preciso na primeira configuração ou quando o Google pedir nova
autorização.

A autorização inclui a leitura da pasta do Drive, para listar também os arquivos colocados direto no Drive. Se o cartão avisar que só mostra os arquivos enviados pelo sistema, clique
em **Autenticar com Google** de novo e aceite todas as permissões pedidas.

## Sincronização de pastas do Drive

A aba **Google Drive** (só para administradores) gerencia a criação das pastas: cada cliente tem uma pasta na
raiz do Drive. Os pedidos de criação entram numa fila
e são processados em lotes.

O topo mostra o resumo da fila e os botões:

- **Enfileirar quem não tem pasta**: pede a criação das pastas que ainda não existem.
- **Processar próximo lote**: cria as pastas pendentes. Também dá para agendar o comando `php spark drive:sync`.
- **Reprocessar falhas**: tenta de novo os pedidos que deram erro.
- **Destravar**: devolve à fila os pedidos que ficaram parados em processamento.
- **Limpar concluídos**: remove da lista os pedidos já atendidos.
- **Atualizar**: recarrega os números.

As sub-abas listam a **fila** e os **clientes**, com busca e filtro por status ou por **Sem pasta** /
**Com pasta**. Marque linhas para **Enfileirar**, **Sincronizar novamente**, **Reprocessar** ou **Remover da fila**
em lote. O nome do cliente abre a ficha em nova guia, e **Abrir pasta** leva ao Drive.

> [!ATENCAO]
> Se aparecer o aviso de que o parâmetro `GOOGLE_DRIVE_FOLDER_ID` não está configurado, nada é processado:
> defina-o na aba **Sistema**.

### Pasta do financeiro (comprovantes)

Os comprovantes de pagamento ficam em uma pasta própria do Drive, separada das pastas de clientes, e
organizados em subpastas por mês (`AAAA-MM`, pela data do pagamento). Informe o ID (ou o link) dessa pasta no
parâmetro `GOOGLE_DRIVE_FINANCEIRO_FOLDER_ID`, na aba **Sistema**. Sem ele, não é possível anexar comprovantes.

Para conferir se o sistema consegue gravar na pasta, use o teste de gravação do financeiro
(`POST /api/v2/financeiro/comprovantes/testar-pasta`): ele cria e apaga um arquivo de teste.

## Manutenção de Cache

Se o sistema estiver lento ou não mostrar uma alteração recente, clique em **Limpar Cache do Backend**.
Nenhum dado é apagado: o sistema só volta a montar as informações a partir do banco.

## Banco de Dados

A aba **Banco de Dados** mostra as atualizações da estrutura do banco (*migrations*): quantas foram
**Aplicadas**, quantas estão **Pendentes** e o **Último lote**.

**Executar migrations pendentes** aplica, em ordem, as que faltam. Se uma falhar, o lote inteiro é desfeito.

> [!ATENCAO]
> Faça um backup do banco antes de executar as migrations em produção.
