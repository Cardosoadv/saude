---
titulo: Integração com IA
resumo: Conectar o Claude, o Gemini e outros assistentes de IA ao sistema, agindo com as suas permissões.
ordem: 40
palavras_chave: [IA, MCP, Claude, Gemini, ChatGPT, token, conector, OAuth]
---

A aba **Configurações › Integração IA (MCP)** conecta assistentes de IA ao Ordo Juris. A IA passa a agir
**estritamente como você**: vê e altera só o que as suas permissões permitem, e nenhuma ação dela pode ser
atribuída a outro usuário.

## Claude.ai (conector nativo)

1. Copie a URL do endpoint MCP no cartão **Conector nativo do Claude.ai**.
2. No claude.ai, abra **Configurações › Conectores › Adicionar conector personalizado**.
3. Cole a URL em **URL do servidor MCP remoto** e deixe **ID do Cliente OAuth** e **Client Secret** em branco.
4. O Claude abre uma tela de autorização do Ordo Juris: confirme.

Nesse caso **não é preciso gerar token**.

## Gemini e outros conectores com Client ID/Secret

Alguns conectores, como o conector personalizado do Gemini, pedem credenciais OAuth prontas em vez de se
registrarem sozinhos:

1. Na tela de conector do Gemini, copie a **URL de redirecionamento** (callback) que ele exibe.
2. No cartão **Gemini e outros conectores OAuth**, informe um **Nome do conector** e cole essa URL em
   **URL de redirecionamento (callback)**. Clique em **Criar credenciais**.
3. Copie para o Gemini os campos exibidos: **URL do servidor MCP**, **Client ID**, **Client Secret**,
   **URL de autorização**, **URL do token** e **Escopo**.
4. Ao conectar, o Gemini abre a tela de autorização do Ordo Juris: confirme.

> [!ATENCAO]
> O **Client Secret** aparece uma única vez. Se perdê-lo, remova o conector na tabela do cartão e crie outro.
> Remover o conector não desconecta quem já autorizou: para isso, revogue o token correspondente
> (ex.: "Gemini (OAuth)") na tabela de tokens.

## Outros assistentes (token pessoal)

Para clientes de IA sem suporte a conectores OAuth (Claude Desktop, ChatGPT etc.):

1. Em **Gerar novo token de IA**, dê um **Nome do token** (ex.: "Claude no notebook") e clique em **Gerar Token**.
2. Copie o token e cole na configuração do assistente.

A tabela mostra cada token com a data de criação, o **Último uso** e quando **Expira em**.

> [!ATENCAO]
> O token dá acesso ao sistema em seu nome. Não o compartilhe. Se perder o controle dele, use **Revogar token**
> na tabela: ele para de funcionar na hora.
