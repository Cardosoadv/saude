# API administrativa do site (contrato para a SPA)

Contrato dos endpoints que as telas do editor do site (repositório `CognitioTechny/sistema`, épico
[sistema#162](https://github.com/CognitioTechny/sistema/issues/162)) consomem. 
Os exemplos de páginas, seções, menus, configurações e tema foram gerados executando os serviços reais sobre o
template Clássico instalado (URLs e datas variam); os de mídias e templates foram montados a partir do código e dos
testes, sem execução HTTP. O contrato do template em si está em [`site-templates.md`](site-templates.md).

- [Convenções](#convenções) · [Permissões](#permissões) · [Schema do template](#schema-do-template-e-campos-da-seção)
- [Configurações](#configurações) · [Mídias](#mídias) · [Páginas](#páginas) · [Seções](#seções) · [Menus](#menus)
- [Categorias de notícias](#categorias-de-notícias)
- [Tema](#tema) · [Templates](#templates) · [Preview de rascunho](#preview-de-rascunho) · [Rich text](#rich-text)
- [Mapa back → front](#mapa-back--front)

## Convenções

Base: `/api/v2/site/admin`. Autenticação Shield (a mesma sessão/token do restante da API). Corpo em JSON
(`Content-Type: application/json`), exceto envio de arquivos (multipart).

| Código | Quando | Corpo |
|---|---|---|
| 200 / 201 | sucesso (201 em criação) | `{"data": …, "avisos": […]}`; `avisos` só existe nas rotas que o citam |
| 401 | sem login | resposta do filtro `auth` |
| 403 | sem a permissão da rota, ou módulo `site` desligado | `{"code": "modulo_inativo", …}` no segundo caso |
| 404 | página/seção/mídia/template inexistente; também `template/schema` sem template ativo | `{"status":404,"error":404,"messages":{"error":"…"}}` |
| 422 | validação | `{"status":422,"error":422,"messages":{"<caminho>":"mensagem"}}` e, quando há, `"avisos": []` |
| 500 | erro inesperado | mensagem genérica; o detalhe só vai para o log |

**Erros 422 vêm por caminho de campo**, para a SPA ligar cada mensagem ao input:

- campos simples: `slug`, `titulo`, `contato.email`;
- dados de seção: prefixo `dados.` e índice de lista a partir de 0: `dados.titulo`, `dados.botoes.1.rotulo`;
- menu: `itens.0.rotulo`, `itens.0.filhos.2.url`;
- publicar página: `secoes.<id da seção>.<campo>`, com o caminho do campo sem o prefixo `dados.` (ex.: `secoes.34.titulo`);
- pacote de template: uma chave por arquivo/problema.

`avisos` (lista de strings) **não bloqueiam**; mostre-os como alerta depois de salvar.
Datas em `Y-m-d H:i:s` (fuso do servidor). Booleanos de linha de banco chegam como `0`/`1` (`visivel`, `exibir_sitemap`).

## Permissões

| Permissão | Rotas |
|---|---|
| `site.conteudo` | notícias e suas categorias, páginas (exceto publicar/despublicar), seções, mídias, menus |
| `site.publicar` | `POST paginas/{id}/publicar`, `POST paginas/{id}/despublicar` |
| `site.configuracoes` | `configuracoes`, `template/tema` |
| `site.conteudo` **ou** `site.configuracoes` | `GET template/schema` |
| `site.templates` | `templates*` (só superadmin por padrão) |

A SPA deve esconder telas/botões conforme `can('site.…')` do usuário e tratar 403 mesmo assim.

## Schema do template e campos da seção

`GET /template/schema` → `{"data": {template, layouts, menus, secoes, textos, tema}}`:

```json
{ "template": {"slug": "classico", "nome": "Clássico", "versao": "1.2.0"},
  "layouts": ["main", "area"],
  "menus": ["cabecalho", "rodape", "lateral_areas"],
  "secoes": {
    "hero": {
      "rotulo": "Destaque principal",
      "campos": {
        "sobretitulo": {"tipo": "texto", "max": 120},
        "titulo": {"tipo": "texto_inline", "obrigatorio": true},
        "subtitulo": {"tipo": "textarea"},
        "botoes": {"tipo": "lista", "itens": {
          "rotulo": {"tipo": "texto", "obrigatorio": true, "max": 40},
          "link": {"tipo": "link", "obrigatorio": true},
          "estilo": {"tipo": "opcao", "opcoes": ["primario", "contorno"], "padrao": "primario"}}}}}},
  "textos": {"contato.nome": "Nome completo *", "…": "…"},
  "tema": {"cores": {"gold": "#C9A84C", "…": "…"}, "fontes": {"serif": "Cormorant Garamond", "sans": "Outfit"}} }
```

- `layouts`: valores aceitos em `layout` da página. `menus`: locais aceitos em `PUT /menus/{local}`.
- `secoes`: tipos que podem ser criados; cada um com `rotulo` e `campos`. O **formulário da seção é gerado a partir
  de `campos`**, na ordem declarada.
- `textos`: textos de interface do template (chave → valor padrão); edite via `PUT /configuracoes` com `textos.<chave>`.
- Propriedades comuns de campo: `obrigatorio`, `max`, `padrao`.

**Tipo do campo → componente da SPA → valor em `dados`**

| `tipo` | Componente sugerido | Valor enviado em `dados.<campo>` |
|---|---|---|
| `texto` | input de uma linha (`max`) | string |
| `texto_inline` | input de uma linha que aceita só `<em>` e `<strong>` | string (ex.: `"Direito <em>Civil</em>"`) |
| `textarea` | textarea (`max`) | string |
| `rich_text` | `DocumentoEditor.vue` | HTML (ver [Rich text](#rich-text)) |
| `imagem` | seletor da biblioteca de mídias | `int` (id da mídia) ou `null` |
| `icone` | seletor de ícones Bootstrap Icons | string `bi-…` |
| `link` | seletor de destino (página / âncora / URL) | `{"tipo":"pagina","pagina_id":2}`, `{"tipo":"ancora","ancora":"contato"}` ou `{"tipo":"url","url":"https://…"}` (`http(s)`, `mailto:`, `tel:` ou caminho iniciado por `/`) |
| `opcao` | select (`opcoes`; `padrao` é uma delas) | string |
| `bool` | switch | `true`/`false` |
| `lista` | lista repetível com adicionar/remover/reordenar; cada item usa os campos de `itens` (sem listas aninhadas; até 100 itens) | array de objetos |
| `referencia_menu` | select dos locais em `menus` | string |
| `dinamico` | não editável — mostrar aviso "preenchido automaticamente" | não enviar (a fonte é `ultimas_noticias` ou `areas`) |

Regras de gravação: `PUT` de uma seção com `dados` **substitui o conteúdo inteiro** (envie todos os campos);
campos que o tipo não declara geram aviso e não são apagados; valores padrão são aplicados a campos ausentes.

## Configurações

`GET /configuracoes` → `{"data": {grupo: [{chave, tipo, grupo, valor}, …]}}`. Grupos: `identidade`, `contato`,
`redes`, `seo`, `integracoes`, `lgpd`, `tema`, `textos`, `noticias`. `tipo`: `string`, `text`, `json`, `imagem` (valor = id
de mídia), `cor`, `bool`.

```json
{"data": {"contato": [
  {"chave": "contato.email", "tipo": "string", "grupo": "contato", "valor": "contato@exemplo.com.br"},
  {"chave": "contato.enderecos", "tipo": "json", "grupo": "contato", "valor": [{"titulo": "Belo Horizonte / MG", "cidade": "Belo Horizonte", "uf": "MG"}]}]}}
```

`PUT|PATCH /configuracoes` com `{"<grupo>.<nome>": valor, …}`; só as chaves enviadas mudam e a resposta é o mesmo
formato do `GET` (já atualizado). Chave nova exige o tipo: envie `{"valor": …, "tipo": "string"}` no lugar do valor
simples; o tipo de uma chave existente não muda. Em `textos.*` e `tema.*`, valor vazio/`null` **remove** a
personalização (volta ao padrão do template). Tudo ou nada: com qualquer erro nada é gravado.

Erros (422): `{"contato.email": "E-mail inválido."}`; `{"chave.desconhecida": "Grupo desconhecido. Use: identidade, contato, …"}`;
`{"textos.x": "Este texto não existe no template ativo."}`.

## Mídias

- `GET /midias?page=1&per_page=24&busca=texto` (`per_page` ≤ 100) →
  `{"data": [mídia…], "pager": {"page":1,"per_page":24,"total":57,"last_page":3}}` (note: aqui `pager` fica ao lado de `data`).
- `GET /midias/{id}` → `{"data": mídia}`; 404 se não existe (a SPA usa para mostrar a miniatura da imagem já escolhida).
- `POST /midias` — **multipart**: `arquivo` (obrigatório) e `alt` (opcional, até 255) → 201 `{"data": mídia}`.
- `PUT|PATCH /midias/{id}` — `{"alt": "texto"}` (só o texto alternativo muda) → `{"data": mídia}`.
- `GET /midias/{id}/usos` → `{"data": [{"tipo":"configuracao","chave":"seo.og_image"}, {"tipo":"pagina","pagina_id":2,"titulo":"…"}, {"tipo":"secao","secao_id":7,"secao_tipo":"hero","pagina_id":2,"titulo":"…"}]}`.
- `DELETE /midias/{id}` → 200 `{"id":…, "message":"Mídia excluída."}`; **422** em `midia` se estiver em uso (a mensagem lista onde).

Objeto mídia: `id, arquivo, nome_original, mime, tamanho, largura, altura, alt, url, miniatura_url, created_at, …`.
Aceita JPEG, PNG, GIF e WebP (tipo conferido pelo conteúdo, não pela extensão; SVG é recusado); até 5 MB e 8000 px
de lado. Erros 422 em `arquivo` (tipo, tamanho, dimensões, falha de envio) e `alt`.

## Páginas

Objeto página: `id, slug, titulo, tipo (home|pagina|area|sistema), layout, meta_title, meta_description,
og_image_id, status (rascunho|publicado), publicado_em, ordem, exibir_sitemap, prioridade_sitemap (0–1),
changefreq, url, …`; a listagem acrescenta `total_secoes` e o detalhe acrescenta `secoes` (cada uma com
`compativel`: `false` quando o template ativo não tem o tipo).

- `GET /paginas?status=&tipo=&busca=` → `{"data": [página…]}` (sem paginação).
- `GET /paginas/{id}` → `{"data": página com secoes}`.
- `POST /paginas` — `{slug, titulo, tipo: "pagina"|"area", layout?, meta_title?, meta_description?, og_image_id?, exibir_sitemap?, prioridade_sitemap?, changefreq?, ordem?}` →
  201 `{"data": página}`. **Nasce sempre em `rascunho`** (`status` é ignorado) e o layout padrão vem do tipo.
- `PUT|PATCH /paginas/{id}` — mesmos campos (`tipo` e `status` não mudam aqui) → `{"data": página, "avisos": []}`.
  Mudar o slug de página publicada devolve aviso (o endereço antigo deixa de funcionar, sem redirecionamento);
  páginas de sistema têm slug fixo.
- `DELETE /paginas/{id}` — exclusão definitiva (as seções vão junto). Home e páginas de sistema: 422 em `pagina`.
- `POST /paginas/{id}/publicar` → `{"data": página, "avisos": []}`. Valida todas as seções **visíveis**; erro 422 com
  `secoes.<id>.<campo>`. Seções de tipo inexistente no template só geram aviso. Página de sistema: 422.
- `POST /paginas/{id}/despublicar` → `{"data": página}` (volta a `rascunho`). Home e sistema: 422.
- `POST /paginas/{id}/duplicar` → 201 `{"data": página}` (cópia em rascunho com slug livre).
- `GET /paginas/{id}/preview-token` → `{"data": {"token", "url", "expira_em"}}` (ver [Preview](#preview-de-rascunho)).

Erros 422 típicos: `slug` (formato `a-z0-9-` ou duplicado), `titulo` (obrigatório), `tipo`, `layout` (não existe no
template), `og_image_id` (mídia inexistente), `meta_description` (até 160), `prioridade_sitemap`.

## Seções

Objeto seção: `{id, pagina_id, tipo, ancora, ordem, visivel, dados, created_at, updated_at}`.

- `POST /paginas/{id}/secoes` — `{tipo, dados, ancora?, visivel?}` → 201 `{"data": seção, "avisos": []}`; entra no fim da página.
- `PUT|PATCH /secoes/{id}` — `{dados?, ancora?, visivel?}` → `{"data": seção, "avisos": []}`. `tipo` não muda (422 em `tipo`).
  Seção de tipo que o template ativo não tem só aceita `visivel` e `ancora`.
- `PUT /paginas/{id}/secoes/ordem` — `{"ids": [3, 1, 2]}` (todas as seções da página, na nova ordem) → `{"data": [seção…]}`.
- `DELETE /secoes/{id}` → 200 `{"id":…, "message":"Seção excluída."}`.

Exemplo de 422 (`POST` de um `hero` com título vazio e o segundo botão incompleto):

```json
{"status": 422, "error": 422, "messages": {
  "dados.titulo": "Campo obrigatório.",
  "dados.botoes.1.rotulo": "Campo obrigatório.",
  "dados.botoes.1.link": "Campo obrigatório.",
  "dados.botoes.1.estilo": "Valor não está entre as opções: primario, contorno."}}
```

Para rascunho de seção em construção, grave com `visivel: false`; ela só é validada na publicação se estiver visível.
`tipo` desconhecido: 422 `{"tipo": "O tipo 'x' não existe no template ativo."}`. `ancora`: minúsculas, números,
hífen e sublinhado, única na página.

## Menus

- `GET /menus` → `{"data": {"cabecalho": [item…], "rodape": […], "lateral_areas": […]}}`.
- `PUT /menus/{local}` com `{"itens": [...]}` **substitui a árvore inteira** → `{"data": [item…], "avisos": []}`.

Item (envio): `{rotulo (≤100), tipo: "pagina"|"url"|"ancora", pagina_id | url | ancora, destaque?: bool, filhos?: [item…]}`.
Só **um nível** de submenu e até 50 itens. Na leitura cada item traz também `id, menu_id, parent_id, ordem` e `filhos`.

```json
{"itens": [
  {"rotulo": "Serviços", "tipo": "pagina", "pagina_id": 2},
  {"rotulo": "Notícias", "tipo": "url", "url": "/noticias", "destaque": false},
  {"rotulo": "Contato", "tipo": "ancora", "ancora": "contato"}]}
```

422: `{"itens.0.rotulo": "Informe o rótulo (até 100 caracteres).", "itens.0.pagina_id": "Página não encontrada.", "itens.1.url": "URL inválida (use http, https, mailto, tel ou um caminho iniciado por /).", "itens.0.filhos.0.filhos": "O menu aceita só um nível de submenu."}`
e, para local inexistente: `{"local": "O template ativo não tem o menu 'x'. Use: cabecalho, rodape, lateral_areas."}`.

## Categorias de notícias

Os campos seguem as colunas da tabela (`name`, `slug`, `meta_description`), como a API de notícias.
Objeto categoria: `{id, name, slug, meta_description, total_noticias, created_at, updated_at}`;
`total_noticias` conta as notícias não excluídas.

- `GET /noticias/categorias?busca=` → `{"data": [categoria…]}` por nome (sem paginação). O formulário de notícia usa a mesma rota.
- `GET /noticias/categorias/{id}` → `{"data": categoria}`.
- `POST /noticias/categorias` — `{name, slug?, meta_description?}` → 201 `{"data": categoria}`. Sem `slug`, ele nasce do
  nome sem acentos (`Direito Previdenciário` → `direito-previdenciario`), com `-2`, `-3`… se já existir.
- `PUT|PATCH /noticias/categorias/{id}` — os mesmos campos; só os enviados mudam. `slug` enviado vazio é gerado de novo a partir do nome.
- `DELETE /noticias/categorias/{id}` → 200 `{"id":…, "message":"Categoria excluída."}`; **422** em `categoria` se ela tiver notícias.

422: `name` (obrigatório, até 100, único sem diferenciar maiúsculas), `slug` (formato `a-z0-9-`, até 120, único),
`meta_description` (até 160). Qualquer escrita invalida o cache do site, pois o nome da categoria aparece nas notícias.

## Tema

- `GET /template/tema` → `{"data": {"cores": {token: {padrao, atual, personalizado}}, "fontes": {"serif": {…}, "sans": {…}}, "fontes_disponiveis": {"Lora": "serif", …}}}`.
- `PUT|PATCH /template/tema` — `{"cores": {"gold": "#B08E35"}, "fontes": {"serif": "Lora"}}` → `{"data": <mesmo formato do GET>}`.
  Só as chaves enviadas mudam; valor vazio restaura o padrão do token. Cor: hexadecimal `#RGB`/`#RRGGBB`. Fonte: uma das
  `fontes_disponiveis` (lista fechada). Erros 422 por token, ex.: `{"cores.gold": "Cor deve ser hexadecimal (#RGB ou #RRGGBB)."}`;
  `cores`/`fontes` precisam ser objetos `{token: valor}`.
- `DELETE /template/tema` → restaura todas as cores e fontes (mesmo formato do GET).

Os tokens são os de `schema.tema`; o site público revalida cores e fontes na saída.

## Templates

Exige `site.templates`. Detalhes de segurança e regras em [`site-templates.md`](site-templates.md#instalar-ativar-exportar-e-remover-templates-pacote-zip).

- `GET /templates` → `{"data": [{slug, nome, versao, autor, descricao, origem (core|upload|cli), ativo, motor, removivel, preview_url}]}`.
- `POST /templates` — **multipart**, campo `arquivo` (`.zip`). **Não ativa.** →
  201 `{"data": {slug, nome, versao, origem, ativo}, "atualizado": false, "avisos": ["…"]}`; `atualizado: true` quando
  o slug já era um template enviado. Pacote recusado: **422** `{"messages": {"<arquivo ou campo>": "motivo"}, "avisos": […]}`
  (nada fica gravado). Avisos possíveis: partial/tela ausente, sem prévia, versão mais antiga que a instalada.
- `POST /templates/{slug}/ativar` →
  ```json
  {"data": {"template": "moderno", "ativado": true,
    "secoes": [{"id": 12, "tipo": "faq", "pagina": "Sobre", "pagina_id": 4, "acao": "incompativel", "erros": []},
               {"id": 15, "tipo": "hero", "pagina": "Início", "pagina_id": 1, "acao": "revisar", "erros": {"titulo": "Campo obrigatório."}}],
    "avisos": ["A página \"Sobre\" usa o layout 'area', que o template não tem; será exibida com 'main'."],
    "seed": null}}
  ```
  `acao: "incompativel"` = o template novo não tem o tipo (a seção permanece no banco, sem ser exibida);
  `"revisar"` = a seção ficou inválida no novo schema. **Nada é apagado nem tem `visivel` alterado.** `seed` só vem
  preenchido quando o site não tinha nenhuma página (conteúdo inicial importado). Mostre `secoes` e `avisos` num diálogo
  de relatório com links para as páginas (`pagina_id`).
- `GET /templates/{slug}/paginas-exemplo` → `{"data": {"criar": [{"slug": "inicio-moderno", "titulo": "Início", "secoes": 5}], "ignoradas": ["sobre-moderno"]}}`: o que a criação faria, **sem criar nada** (para confirmar antes). 422 em `template` se o template não traz conteúdo inicial.
- `POST /templates/{slug}/paginas-exemplo` → `{"data": {"configuracoes": [], "paginas": ["slug-criado"], "paginas_ignoradas": ["slug-que-ja-existia"], "menus": [], "erros": {}}}`; cria como rascunho as páginas do seed
  cujo slug ainda não existe. 422 em `template` se o template não traz conteúdo inicial.
- `GET /templates/{slug}/exportar[?conteudo=1]` → download `application/zip` (não é JSON).
- `DELETE /templates/{slug}` → 200 `{"slug":…, "message":"Template removido."}`; 422 em `template` para o ativo ou o do sistema.

## Preview de rascunho

1. `GET /paginas/{id}/preview-token` (requer `site.conteudo`) devolve `{"token", "url", "expira_em"}`; validade de 1 h.
2. A SPA abre `url` num `<iframe>` (ou nova aba). `url` já é `…/pagina/<slug>?preview=<token>` (ou `/?preview=…` para a home).
3. O servidor só mostra o rascunho se o token for válido **para aquela página** e não tiver expirado; a resposta sai com
   `Cache-Control: no-store` e `X-Robots-Tag: noindex`. Prévia e usuário logado não usam o cache do site.
4. Depois de salvar uma seção, recarregue o iframe (o token continua válido até expirar). Peça outro token ao expirar.

Observação: o site público e o painel precisam poder se enquadrar (a SPA no mesmo domínio ou com `frame-ancestors`
liberado); isto não foi testado aqui.

## Rich text

O campo `rich_text` é HTML sanitizado no servidor na gravação e na renderização. Tags permitidas:
`p, br, b, strong, i, em, u, s, strike, ul, ol, li, a[href], h1, h2, h3, blockquote`
(`HtmlSanitizer::TAGS_RICH`). Em `texto_inline` só `em` e `strong`. A lista é a mesma de `useDocumentoEditor.ts` da SPA:
**se uma mudar, a outra tem de acompanhar.** Atributos e URLs perigosas (`javascript:`) são removidos; o HTML salvo pode
vir diferente do enviado, então atualize o editor com o valor devolvido pela API.

## Mapa back → front

| Backend | Endpoints | Tela no `sistema` |
|---|---|---|
| #648 configurações | `configuracoes`, `template/schema` (textos) | [sistema#164](https://github.com/CognitioTechny/sistema/issues/164) |
| #649 mídias | `midias*` | [sistema#165](https://github.com/CognitioTechny/sistema/issues/165) |
| #648 páginas | `paginas*` | [sistema#166](https://github.com/CognitioTechny/sistema/issues/166) |
| #648 seções + #645 preview | `secoes*`, `paginas/{id}/secoes*`, `preview-token`, `template/schema` | [sistema#167](https://github.com/CognitioTechny/sistema/issues/167) |
| #648 menus | `menus*` | [sistema#168](https://github.com/CognitioTechny/sistema/issues/168) |
| #651 tema | `template/tema` | [sistema#169](https://github.com/CognitioTechny/sistema/issues/169) |
| #650 templates | `templates*` | [sistema#170](https://github.com/CognitioTechny/sistema/issues/170) |
| #163 (base) | `template/schema` → componentes por tipo de campo | [sistema#163](https://github.com/CognitioTechny/sistema/issues/163) |
