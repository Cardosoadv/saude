---
titulo: Cadastro de processos
resumo: Como cadastrar um processo, preencher partes, valores e risco, e vincular cliente e contrato.
ordem: 10
telas: [process-form]
palavras_chave: [novo processo, cadastrar, CNJ, valor da causa, risco, CPC 25]
---

Clique em **Novo Processo** na lista (ou em **Ações Rápidas** do Painel). O formulário se chama
**Dados Principais do Processo**.

## Identificação

| Campo | Como preencher |
|---|---|
| **Número do Processo (CNJ)** | no formato `0000000-00.0000.0.00.0000`, com pontos e traços |
| **Link da Pasta** | endereço da pasta do processo no Google Drive (ou outro repositório) |
| **Título do Processo** | identificação curta, ex.: "Ação Ordinária contra Empresa X" |
| **Classe Processual** | ex.: "Procedimento Comum Cível" |
| **Juízo / Órgão Julgador** | ex.: "2ª Vara Cível da Comarca de…" |
| **Ramo do Direito** | escolha na lista; os ramos podem ter sub-ramos |

## Cliente e contrato

- **Cliente**: digite ao menos 2 letras do nome ou do CPF/CNPJ e escolha na lista.
- **Contrato Vinculado**: busque pelo número ou pelo nome (opcional).

## Partes

Use **Adicionar Autor** e **Adicionar Réu** para incluir as partes do **Polo Ativo** e do **Polo Passivo**.
Depois de salvo, as partes também podem ser ajustadas no [detalhe](partes-e-movimentacoes.md#partes).

## Valores, risco e resultado

| Campo | Observação |
|---|---|
| **Valor da Causa** e **Valor da Condenação** | em reais |
| **Provisão CPC 25** | valor provisionado conforme o risco, para o relatório contábil |
| **Data de Distribuição** | |
| **Risco** | **Remoto**, **Possível** ou **Provável** |
| **Resultado** | **Não Finalizado**, **Sucesso**, **Sucesso Parcial** ou **Derrota** |
| **Data da Revisão** | quando o risco foi revisto pela última vez |
| **Encerrado** e **Data de Encerramento** | marque quando o processo terminar |

Use **Comentário / Observações** para o que não couber nos campos. Clique em **Salvar Processo**.

> [!NOTA]
> Para definir ou trocar o responsável pelo processo, use o cartão **Responsável** no detalhe.
