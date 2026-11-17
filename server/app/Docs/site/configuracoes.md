---
titulo: Configurações e aparência
resumo: Identidade, contato, WhatsApp, SEO, Google Analytics e Ads, privacidade (LGPD), textos, cores e fontes.
ordem: 40
permissao: site.configuracoes
telas: [site-configuracoes, site-aparencia]
palavras_chave: [configurações do site, contato, SEO, cores, fontes, aparência, endereço, LGPD, privacidade, cookies, WhatsApp, Google Ads, Analytics, CNPJ, robots, llms]
---

## Configurações do Site

**Site › Configurações do Site** reúne, em abas, identidade, contato, redes sociais, SEO, integrações,
privacidade (LGPD) e os textos fixos do site público.

- **Identidade**: nome, logos, favicon, cor dos documentos e **CNPJ**. O CNPJ aparece no rodapé do site (com a
  razão social e o registro no conselho profissional) e na Política de Privacidade, como dado do controlador. Se os e-mails
  pedem um logo diferente do site (por exemplo, outras cores), escolha-o em **Logo dos e-mails**; vazio usa o
  **Logo**.
- **Contato**: telefone, e-mail e **Unidades** (cada endereço da clínica, com **Adicionar unidade**).
  Marque **Transformar os telefones em link do WhatsApp** para que, no site, o telefone abra uma conversa no
  WhatsApp.
- **SEO**: título e descrição, com a **Prévia no Google** e o limite de caracteres de cada campo.
- **Integrações**: **ID do Google Analytics** (`G-XXXXXXXXXX`) e **ID da conta do Google Ads**
  (`AW-123456789`). Para medir conversões do Ads, informe também o rótulo de **Conversão: formulário de
  contato** (conta o envio do formulário com sucesso) e de **Conversão: clique no WhatsApp**. Campo vazio
  desativa o rastreamento correspondente.
- **Privacidade (LGPD)**: **Encarregado (DPO)**, **E-mail do encarregado** e **Versão da Política de
  Privacidade**.
- **Textos**: os textos do template podem ser personalizados; **Restaurar padrão** volta ao original.

Os dados da clínica também são usados nos documentos gerados pelo sistema.
Clique em **Salvar alterações**.

> [!NOTA]
> Os rastreamentos do Google Analytics e do Google Ads só gravam cookies depois que o visitante aceita no
> aviso de cookies do site.

## Privacidade e LGPD no site público

O template Clássico traz páginas prontas de **Política de Privacidade** e **Termos de Uso**, ligadas no rodapé.
Elas usam os dados de **Identidade** (nome e CNPJ) e de **Privacidade (LGPD)** (encarregado). Confira esses
campos antes de publicar.

- **Aviso de cookies**: o visitante aceita ou recusa as estatísticas, e pode mudar a escolha depois em
  **Preferências de cookies**, no rodapé.
- **Formulário de contato**: exige o aceite da Política de Privacidade. O aceite é gravado junto com a
  versão da política em vigor.
- Ao mudar o texto da política, atualize a **Versão da Política de Privacidade** (formato `AAAA-MM-DD`), para
  que os próximos aceites registrem a versão nova.

## O que o site publica sozinho

Sem nenhuma configuração, o site público também:

- publica o `robots.txt` e o `sitemap.xml`, orientando os buscadores (as áreas restritas, como a Área do
  Cliente, ficam fora);
- publica `/llms.txt` e `/llms-full.txt`, com o resumo e o conteúdo do site em formato próprio para
  assistentes de IA;
- inclui dados estruturados (JSON-LD) em cada página, para o Google entender a clínica e o conteúdo;
- redireciona qualquer endereço alternativo (sem `www`, com `http` ou com a subpasta do sistema) para o
  endereço oficial do site.

## Aparência do Site

**Site › Aparência do Site** muda as **Cores** e as **Fontes** do template ativo; o conteúdo não muda.

- Cada cor e fonte mostra o padrão do template; **Restaurar padrão do template** volta todas.
- O aviso de **Contraste insuficiente** não impede de salvar, mas indica texto difícil de ler.
- A **Prévia da página inicial** mostra o que já foi salvo: clique em **Salvar aparência** para ver a mudança.
