---
titulo: Fornecedores
resumo: Cadastro de fornecedores e parceiros, com dados bancários e PIX.
ordem: 120
modulo: fornecedores
telas: [fornecedores, fornecedor-detail]
palavras_chave: [fornecedor, parceiro, PIX, dados bancários]
---

Em **Gestão › Fornecedores** ficam os fornecedores e parceiros da clínica, usados nas
[despesas](../financeiro/despesas.md). A lista abre nas abas **Ativos**, **Inativos** e **Todos** (nessa ordem,
começando por **Ativos**); use as setas do teclado para trocar de aba. Filtre também por categoria.

## Cadastrar

Clique em **Novo Fornecedor**:

- **Dados Cadastrais**: **Tipo de Pessoa**, **Categoria**, **Nome Fantasia / Nome**, **CNPJ / CPF**,
  **Razão Social**, e-mail, telefone e endereço.
- **Dados Bancários & Pagamento**: **Banco**, **Agência**, **Conta** e **Chave PIX**.
- **Status**: **Ativo** ou **Inativo**.

> [!ATENCAO]
> Confira os dados bancários com o fornecedor antes de qualquer pagamento.

## Transformar em Cliente

Caso um fornecedor passe a ser cliente da clínica (ou tenha sido cadastrado na categoria errada), você pode transformá-lo diretamente:

1. Na listagem de fornecedores ou nos detalhes do fornecedor, clique no botão **Transformar em Cliente** (<i class="bi bi-arrow-left-right"></i>).
2. O sistema copia automaticamente todos os dados cadastrais (nome, razão social, documento, contatos e endereço).
3. Opcionalmente, marque a opção para **Desativar o cadastro de fornecedor** após a conversão, caso ele não vá mais prestar serviços.
4. Ao confirmar, o novo cliente é gerado e o sistema permite abrir a sua ficha imediatamente.
