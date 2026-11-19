---
titulo: Contratos
resumo: Contratos de planos, convênios e prestação de serviços, com vigência, valores e aviso de vencimento.
ordem: 110
modulo: contratos
telas: [contratos, contrato-detail]
palavras_chave: [contrato, plano, convênio, vigência, vencimento, renovação]
---

Em **Principal › Contratos** ficam os contratos de planos de tratamento, convênios e prestação de serviços. Os contadores mostram
o **Total** e quantos estão **Ativos**, **Vencendo** e **Encerrados**, que também são as abas. Filtre ainda por
área/especialidade e por responsável.

Na lateral: **Contratos a Vencer**, **Mais Rentáveis** e **Maiores Prejuízos**. A rentabilidade é a soma das
receitas menos a soma das despesas ligadas ao contrato pelo campo **Contrato Vinculado**.

## Cadastrar

Clique em **Novo Contrato** e preencha **Informações do Contrato**:

| Campo | Observação |
|---|---|
| **Cliente** | busque pelo nome ou pelo documento |
| **Número do Contrato** | gerado automaticamente se ficar vazio |
| **Tipo de Contrato** | Mensalidade, Plano de tratamento, Convênio, Pacote de sessões, Consultoria ou Outro |
| **Objeto do Contrato** | resumo do que foi contratado |
| **Data de Início**, **Data de Término**, **Prazo de Vigência** | término vazio = prazo indeterminado |
| **Valor do Contrato** | |
| **Forma de Pgto** e **Dia de Vencimento** | mensal, trimestral, semestral, anual, parcela única ou por hora |
| **Status** | Ativo, Em negociação, Pendente, Suspenso, Encerrado ou Cancelado |
| **Área / Especialidade** | texto livre (ex.: Odontologia, Clínica geral) |
| **Renovação Automática** e **Aviso de Vencimento** | quantos dias antes avisar |

**Responsável** e **Visibilidade** seguem as mesmas regras de clientes: **Restrito** (você, o responsável e quem for liberado) ou **Público**
(todos os profissionais).

Receitas e despesas podem ser ligadas ao contrato pelo campo **Contrato Vinculado** (veja [Receitas](../financeiro/receitas.md)).

## Arquivos do contrato (Google Drive)

Na ficha do contrato, o cartão **Arquivos do Contrato** guarda o contrato assinado, os aditivos e os anexos no
Google Drive, na subpasta **Contratos** da pasta do cliente. Se o cliente ainda não tem pasta, o sistema a cria
no primeiro envio.

- Escolha a **categoria** (Contrato original, Aditivo, Anexo ou Outros) e envie por seleção ou arrastando.
- Aceita PDF, Word (DOC e DOCX), JPG, PNG e WEBP, até 20 MB por arquivo e 5 arquivos por envio.
- Quem edita o contrato envia; quem tem permissão de exclusão remove. O arquivo removido vai para a lixeira do
  Drive e pode ser recuperado por lá.
- Só vê e baixa os arquivos quem tem acesso ao contrato (responsável, profissionais liberados e administradores).
