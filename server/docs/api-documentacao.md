# API da documentação interna (contrato para a SPA)

Contrato dos endpoints de leitura do manual do sistema, consumidos pelas telas do repositório
`CognitioTechny/sistema` (épico [sistema#191](https://github.com/CognitioTechny/sistema/issues/191)).
 O formato das páginas está em [`documentacao-formato.md`](documentacao-formato.md).

Os exemplos foram gerados executando `DocumentacaoService` sobre as páginas de `app/Docs` (datas variam).

- [Convenções](#convenções) · [Visibilidade](#visibilidade)
- [Índice](#índice) · [Página](#página) · [Busca](#busca) · [Ajuda por tela](#ajuda-por-tela) · [Imagens](#imagens)
- [HTML das páginas](#html-das-páginas) · [Links dentro do conteúdo](#links-dentro-do-conteúdo)
- [Mapa back → front](#mapa-back--front)

## Convenções

Base: `/api/v2/documentacao`. Só leitura (`GET`). Autenticação Shield, com a mesma sessão do restante da API:
o `apiFetch` já envia os cookies (`credentials: 'include'`).

Todas as rotas usam os filtros `permission:documentacao.access` e `modulo:documentacao`.

| Código | Quando | Corpo |
|---|---|---|
| 200 | sucesso | `{"success": true, "data": …}` |
| 304 | imagem sem alteração (`If-None-Match` igual ao `ETag`) | vazio |
| 401 / 403 | sem login ou sem `documentacao.access` | resposta do filtro do Shield, igual às demais rotas `permission:` |
| 403 | módulo `documentacao` desativado | `{"success": false, "code": "modulo_inativo", "modulo": "documentacao", "message": "…"}` |
| 404 | página, tela ou imagem inexistente, **ou invisível** para o usuário | `{"success": false, "message": "…"}` |

## Visibilidade

Uma página só aparece no índice, na página, na busca e na ajuda por tela quando:

1. o módulo declarado em `modulo` (próprio ou herdado da capa da seção) está **ativo**; e
2. o usuário tem a permissão declarada em `permissao` (própria ou herdada), quando houver.

Uma página invisível responde **404**, como se não existisse. A seção some do índice quando nada nela é visível.
Não há nada a esconder na SPA além do que a API já filtrou.

## Índice

`GET /api/v2/documentacao`: seções e páginas visíveis, já ordenadas (`ordem`, depois o título).

```json
{
    "success": true,
    "data": [
        {
            "slug": "primeiros-passos",
            "titulo": "Primeiros passos",
            "resumo": "O que é o Saúde, como entrar no sistema e onde encontrar cada módulo.",
            "capa": true,
            "paginas": [
                {
                    "slug": "primeiros-passos/navegacao",
                    "titulo": "Navegação pelo sistema",
                    "resumo": "Menu lateral, barra superior e endereço de cada tela."
                }
            ]
        }
    ]
}
```

- `capa: true`: a seção tem página de capa e o slug dela é o próprio `slug` da seção.
  Com `capa: false`, o título da seção é só um agrupador, sem link.
- `paginas` não inclui a capa. Cada item traz também `status` (`publicado` ou `rascunho`); rascunhos só
  aparecem para quem tem `documentacao.editar` (veja [Páginas no banco e rascunhos](#páginas-no-banco-e-rascunhos)).

## Página

`GET /api/v2/documentacao/paginas/{slug}`: o slug pode ter `/` (`paginas/primeiros-passos/navegacao`).

```json
{
    "success": true,
    "data": {
        "slug": "primeiros-passos",
        "secao": "primeiros-passos",
        "capa": true,
        "origem": "arquivo",
        "status": "publicado",
        "id": null,
        "titulo": "Primeiros passos",
        "resumo": "O que é o Saúde, como entrar no sistema e onde encontrar cada módulo.",
        "html": "<p>O Saúde reúne em um só lugar …</p>\n<h2 id=\"entrar-no-sistema\">Entrar no sistema</h2>\n…<aside class=\"doc-aviso doc-aviso--dica\" role=\"note\"><p class=\"doc-aviso-titulo\">Dica</p>\n<p>Esqueceu a senha? …</p>\n</aside>…<a href=\"#/documentacao/primeiros-passos/navegacao\">Navegação pelo sistema</a>…",
        "sumario": [
            { "nivel": 2, "texto": "Entrar no sistema", "ancora": "entrar-no-sistema" },
            { "nivel": 2, "texto": "O que você vê depende do seu perfil", "ancora": "o-que-voce-ve-depende-do-seu-perfil" },
            { "nivel": 2, "texto": "Próximos passos", "ancora": "proximos-passos" }
        ],
        "anterior": null,
        "proxima": { "slug": "primeiros-passos/navegacao", "titulo": "Navegação pelo sistema" },
        "atualizado_em": "2026-10-13T06:54:21-03:00"
    }
}
```

| Campo | Descrição |
|---|---|
| `titulo` | título da página: exiba como `<h1>`, pois o `html` não traz o título |
| `origem`, `status`, `id` | `arquivo` (app/Docs) ou `banco` (criada pela interface); `publicado` ou `rascunho`; `id` da página no banco, ou `null` |
| `html` | corpo já **sanitizado** pelo backend; pode ir direto para `v-html` |
| `sumario` | títulos `h2` e `h3`, na ordem; `ancora` é o `id` do título no `html` |
| `anterior`, `proxima` | vizinhas na ordem de leitura do índice (capa da seção e depois as páginas dela), entre as visíveis; `null` nas pontas |
| `atualizado_em` | ISO 8601, data de modificação do arquivo |

## Busca

`GET /api/v2/documentacao/busca?q=menu`

```json
{
    "success": true,
    "data": [
        {
            "slug": "primeiros-passos/navegacao",
            "titulo": "Navegação pelo sistema",
            "secao": "Primeiros passos",
            "trecho": "Menu lateral O menu à esquerda agrupa as telas por assunto: Principal, Financeiro, Contábil, Gestão e Site. Só aparecem os grupos e as telas a que o seu perfil…"
        },
        {
            "slug": "primeiros-passos",
            "titulo": "Primeiros passos",
            "secao": "Primeiros passos",
            "trecho": "…O grupo define quais telas aparecem no menu e o que pode ser alterado. Quando um módulo é desativado pelo administrador, ele some do menu e da documentação. Pr…"
        }
    ]
}
```

- Não diferencia maiúsculas nem acentos (`numero` encontra `número`).
- Cada termo (separado por espaço) precisa aparecer na página. Termos com menos de 2 caracteres são ignorados.
  `q` vazia ou só com termos curtos devolve `[]`. A consulta é cortada em 100 caracteres.
- Ordem por relevância: título (peso 10) > palavras-chave (5) > resumo (3) > corpo (1). Até 20 resultados.
- `secao` é o **título** da seção. `trecho` é **texto puro**, de até ~160 caracteres em volta do primeiro termo,
  com `…` quando foi cortado. Para destacar os termos, escape o texto e marque na SPA.

## Ajuda por tela

`GET /api/v2/documentacao/tela/{currentView}`: a primeira página visível, na ordem do índice, que lista a tela
em `telas`. A resposta é igual à de [Página](#página). Quando nenhuma página atende, responde 404 e a SPA
esconde o botão de ajuda.

```
GET /api/v2/documentacao/tela/dashboard  →  200, data.slug = "primeiros-passos"
GET /api/v2/documentacao/tela/extrato    →  404
```

## Imagens

`GET /api/v2/documentacao/imagens/{secao}/img/{arquivo}`: arquivo de `app/Docs/<secao>/img/`.

- Só `png`, `jpg`, `jpeg`, `gif`, `webp` e `avif`. Qualquer outro caminho responde 404.
- `Cache-Control: private, max-age=86400` e `ETag`.
- O `html` das páginas já traz a URL absoluta, com largura e altura lidas do arquivo
  (`<img src="https://…/api/v2/documentacao/imagens/clientes/img/tela.webp" alt="…" loading="lazy" width="800" height="450">`).
  Mantenha `height: auto` no CSS para a imagem não distorcer quando encolher.
  Como a rota exige login, a imagem carrega com o cookie da sessão; não há nada a fazer na SPA.

## Páginas no banco e rascunhos

Além dos arquivos de `app/Docs`, há páginas criadas pela interface, guardadas no banco:

- uma página do banco **publicada** com o mesmo slug de um arquivo **substitui** o arquivo para todos;
- enquanto é **rascunho**, ela só aparece para quem tem `documentacao.editar`, no lugar da versão publicada.
  Para os demais vale o arquivo (ou a página não existe, se for nova);
- excluir a página do banco faz voltar a valer o arquivo.

A SPA pode marcar rascunhos (`status: "rascunho"`) no índice e no título, para o editor não confundir com a
versão publicada.

## Edição (admin)

Base: `/api/v2/documentacao/admin`, com os filtros `permission:documentacao.editar` e `modulo:documentacao`. As respostas seguem o padrão do editor do site: `{"data": …}` e, nas gravações, `"avisos": [...]`.
Os erros de validação vêm por campo em `messages`, com código 422; uma página inexistente responde 404.

| Método | Rota | Corpo | Resposta |
|---|---|---|---|
| GET | `paginas` | — | lista das duas fontes (abaixo) |
| GET | `paginas/{id}` | — | página do banco completa |
| POST | `paginas` | página (abaixo) | 201, `{data, avisos}`; nasce como **rascunho** |
| PUT | `paginas/{id}` | página | `{data, avisos}`; **não muda o status** |
| POST | `paginas/{id}/publicar` / `despublicar` | — | `{data}` |
| DELETE | `paginas/{id}` | — | exclusão definitiva; volta a valer o arquivo de mesmo slug, se houver |
| POST | `paginas/de-arquivo/{slug}` | — | 201, cópia editável (rascunho) de uma página de arquivo |
| POST | `preview` | `{markdown, slug}` | `{data: {html, sumario, avisos}}`, sem gravar |
| POST | `imagens` | multipart: `arquivo`, `secao` | 201, `{data: {caminho, markdown, largura, altura}}` |

**Item da lista** (`GET paginas`):

```json
{ "id": 7, "slug": "clientes/cadastro", "titulo": "Cadastro de clientes", "secao": "processos",
  "origem": "banco", "status": "rascunho", "tem_arquivo": true, "atualizado_em": "2026-10-14T10:20:00-03:00" }
```

Páginas de arquivo sem cópia no banco vêm com `"origem": "arquivo"`, `"id": null` e `"status": "publicado"`.
Para editá-las, primeiro crie a cópia com `de-arquivo`.

**Página** (corpo do POST/PUT e `data` das respostas):

| Campo | Regra |
|---|---|
| `slug` | `secao` (capa) ou `secao/pagina`: minúsculas, números e hífen; único |
| `titulo` | obrigatório, até 120 |
| `resumo`, `ordem`, `modulo`, `permissao`, `telas`, `palavras_chave` | as mesmas regras do cabeçalho dos arquivos ([formato](documentacao-formato.md)) |
| `markdown` | corpo da página, até 200 mil caracteres |

A resposta acrescenta `id`, `status`, `tem_arquivo` (há arquivo de mesmo slug, que esta página substitui ao ser
publicada), `publicado_em`, `criado_em`, `atualizado_em`, `criado_por` e `atualizado_por`.

**Avisos** não impedem salvar: link ou imagem descartados pelo motor, imagem sem texto alternativo e tela de
`telas` já usada por outra página.

**Imagens**: PNG, JPG, GIF ou WebP de até 5 MB. Imagem com mais de 1600 px de largura é reduzida. O arquivo
fica em `writable/uploads/documentacao/<secao>/img/`, fora do deploy, e é servido pela mesma rota `imagens/`.
Use o `markdown` devolvido (`![Descreva a imagem](img/…)`) em uma página da mesma seção, trocando o texto
alternativo.

Toda criação, alteração (inclusive publicar e despublicar) e exclusão fica na tabela de **auditoria**.

## HTML das páginas

Tags que podem aparecer: `p br hr h1–h6 strong em del code pre blockquote aside ul ol li a img table thead tbody tr th td input`.
Nenhum atributo de evento, `style` ou `class` arbitrário chega à SPA.

Classes geradas pelo backend, para a folha de estilos da SPA:

| Seletor | Origem |
|---|---|
| `aside.doc-aviso` | bloco de aviso (`> [!NOTA]`, `> [!DICA]`, `> [!ATENCAO]`) |
| `.doc-aviso--nota`, `.doc-aviso--dica`, `.doc-aviso--atencao` | tipo do aviso |
| `p.doc-aviso-titulo` | primeira linha do aviso, com o texto "Nota", "Dica" ou "Atenção" |
| `li > input[type=checkbox][disabled]` | listas de tarefas (`- [x]`), sem classe própria |
| `th[align]`, `td[align]` | alinhamento de colunas de tabela (`left`, `center`, `right`) |
| `code.language-*` | bloco de código com linguagem |
| `h1`–`h6[id]` | todos os títulos têm `id` (âncora) |

## Links dentro do conteúdo

| `href` no HTML | O que a SPA faz |
|---|---|
| `#/documentacao/{slug}` | abre a página sem recarregar |
| `#/documentacao/{slug}#{ancora}` | abre a página e rola até o título com `id="{ancora}"`. **Atenção**: são dois `#`; tudo após o segundo é a âncora |
| `#/…` (outra tela, ex.: `#/clientes`) | navegação normal do hash da SPA |
| `https://…`, `mailto:` | já vêm com `target="_blank" rel="noopener noreferrer"` |
| `<a>` sem `href` | link recusado pelo backend; é só texto |

Como `window.location.hash` muda nos dois primeiros casos, basta tratar o prefixo `#/documentacao` no roteamento
por hash do `App.vue` e separar a âncora pelo segundo `#`.

## Mapa back → front

| Tela / componente (sistema) | Endpoint |
|---|---|
| Leitor: índice lateral | `GET /api/v2/documentacao` |
| Leitor: conteúdo, sumário, anterior/próxima | `GET /api/v2/documentacao/paginas/{slug}` |
| Campo de busca | `GET /api/v2/documentacao/busca?q=` |
| Botão "?" de ajuda contextual (Docs 2) | `GET /api/v2/documentacao/tela/{currentView}` |
| Regra de acesso (`acessoTelas.ts`) | módulo `documentacao` + permissão `documentacao.access` |
| Lista de edição (Docs 3) | `GET admin/paginas`, `POST admin/paginas/de-arquivo/{slug}` |
| Editor com pré-visualização (Docs 3) | `GET/POST/PUT/DELETE admin/paginas…`, `POST admin/preview`, `POST admin/imagens` |
| Regra de acesso do editor | módulo `documentacao` + permissão `documentacao.editar` |
