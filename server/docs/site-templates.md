# Templates do site público

Formato de um template instalável (épico #641, issue #644). Um template é **tema visual +
conteúdo inicial**; o conteúdo do escritório vive no banco e não pertence ao template.

## Origem e motor de views

| Origem | Motor | Como entra |
|---|---|---|
| `core`, `cli` | `php` (views do CodeIgniter) ou `twig` | Deploy (`app/Templates/<slug>`) ou `php spark site:template:install` |
| `upload` (painel) | **só `twig`**, em sandbox | `.zip` enviado pelo painel |

Regra do instalador (#650): pacote enviado pelo painel que contenha qualquer arquivo `.php`, ou
cujo manifesto declare `"motor": "php"`, é **rejeitado**. A política do sandbox está em
`App\Libraries\Site\Template\TwigSandboxPolicy`.

## Estrutura do pacote

```
<slug>/
  template.json          # manifesto (obrigatório)
  layouts/main.(php|twig)
  sections/<tipo>.(php|twig)   # um partial por tipo de seção
  pages/                       # telas especiais: noticias_list, noticias_detail, area_cliente…
  assets/                      # css, js, img, fonts
  seed/content.json            # páginas, seções, menus e configurações de exemplo
  seed/media/
  preview.png
```

## `template.json`

Exemplo completo: [`app/Templates/classico/template.json`](../app/Templates/classico/template.json).
Esquema para editores: [`site-template.schema.json`](site-template.schema.json).
A validação oficial é `App\Libraries\Site\Template\ManifestValidator`.

| Campo | Obrigatório | Regra |
|---|---|---|
| `slug` | sim | minúsculas, números e hífen; até 60 |
| `nome` | sim | até 100 caracteres |
| `versao` | sim | `X.Y.Z` |
| `autor`, `descricao` | não | até 100 / 255 |
| `ordojuris` | não* | `>=X.Y.Z`; se a instalação for mais antiga, o template é recusado. *Sem ele, só um aviso. |
| `motor` | sim | `php` ou `twig` |
| `layouts` | sim | lista de nomes de layout (mínimo 1) |
| `menus` | não | locais de menu que o template usa |
| `tema.cores` | não | token → `#RGB`/`#RRGGBB`. Viram variáveis CSS (`--gold`) e podem ser sobrescritas no painel (#651) |
| `tema.fontes` | não | token → nome de família (letras, números, espaço) |
| `textos` | não | chave com pontos → texto padrão de interface (`contato.botao`) |
| `paginas_obrigatorias` | sim | deve incluir `home` |
| `secoes` | sim | tipos de seção (abaixo) |

### Tipos de seção e campos

```json
"secoes": {
  "hero": {
    "rotulo": "Destaque principal",
    "campos": {
      "titulo": { "tipo": "texto_inline", "obrigatorio": true },
      "botoes": { "tipo": "lista", "itens": { "rotulo": { "tipo": "texto" }, "link": { "tipo": "link" } } }
    }
  }
}
```

| `tipo` do campo | Valor guardado em `dados` | Observações |
|---|---|---|
| `texto` | string de uma linha | `max` (padrão 1000) |
| `texto_inline` | string de uma linha | só `<em>` e `<strong>` |
| `textarea` | string | `max` (padrão 5000) |
| `rich_text` | HTML | `max` (padrão 100000); sanitizado na gravação e na renderização (#645, #648) |
| `imagem` | `int` (id em `site_midias`) | existência verificada por quem grava |
| `icone` | string `bi-…` | nome do Bootstrap Icons |
| `link` | `{tipo:"pagina",pagina_id}` \| `{tipo:"ancora",ancora}` \| `{tipo:"url",url}` | `url`: `http(s)`, `mailto:`, `tel:` ou caminho iniciado por `/` |
| `opcao` | string | exige `opcoes`; `padrao` deve ser uma delas |
| `bool` | `true`/`false` | |
| `lista` | array de objetos | exige `itens` (campos de cada item); sem listas dentro de listas; até 100 itens |
| `referencia_menu` | string | local de menu |
| `dinamico` | — (não é gravado) | exige `fonte`: `ultimas_noticias` ou `areas`; o conteúdo vem do provedor de dados (#645) |

Propriedades comuns: `obrigatorio` (bool), `max` (inteiro ≥ 1), `padrao` (valor aplicado quando o
campo falta, ver abaixo).

## Validação dos dados de uma seção

`SecaoDadosValidator::validar($campos, $dados)` devolve erros por caminho de campo, como
`botoes.1.rotulo`, que a API retorna em 422 e a SPA liga ao input. Campos que o tipo não declara
geram **aviso** (não bloqueiam e o dado não é apagado).

## Trocar de template

Ativar um template **nunca altera nem apaga conteúdo**. `Compatibilidade::analisar()` monta o plano:

| Situação da seção | Ação |
|---|---|
| O `tipo` existe no novo template | `manter`: aplica o `padrao` dos campos novos e revalida; erros e avisos vão no relatório |
| O `tipo` não existe no novo template | `ocultar`: `visivel = 0`, `dados` intactos. Se o tipo voltar a existir, o editor pode reexibi-la |

Menus e configurações do site não mudam.

## Sandbox Twig (templates enviados pelo painel)

Permitido (lista branca em `TwigSandboxPolicy`):

- Tags: `if`, `for`, `set`, `include`, `block`, `extends`, `embed`, `apply`, `with`
- Filtros: `escape`/`e`, `raw`, `default`, `length`, `first`, `last`, `join`, `split`, `upper`, `lower`, `capitalize`, `title`, `trim`, `nl2br`, `striptags`, `slice`, `merge`, `keys`, `date`, `number_format`, `url_encode`, `json_encode`
- Funções: `range`, `cycle`, `max`, `min`, `site_config`, `site_menu`, `site_texto`, `asset`
- Métodos e propriedades de objetos PHP: **nenhum**. O renderizador entrega só arrays e escalares.

Qualquer outra tag, filtro ou função (por exemplo `source`, `constant`, `random`, `sort`, `map`) lança
`SecurityError` na renderização. O carregador lê só do diretório do template; `include`/`extends`
de um caminho fora dele falham com `LoaderError`.

## Renderização (o que o template recebe)

O renderizador (`App\Libraries\Site\SiteRenderService`) monta a página: seções visíveis em ordem →
partial `sections/<tipo>` de cada uma → `layouts/<layout da página>`. Seção cujo tipo o manifesto
não declara, ou sem partial no pacote, é **ignorada** (aviso no log); a página continua.

**Partial de seção** recebe:

| Variável | Conteúdo |
|---|---|
| `dados` | os campos da seção, já preparados (abaixo) |
| `secao` | `{id, tipo, ancora}` |
| `pagina` | `{id, slug, titulo, tipo}` |

**Layout** recebe `conteudo` (HTML das seções), `pagina`, `seo` (`{titulo, descricao, url}`) e `rascunho` (bool).

Preparo dos `dados` por tipo de campo:

| Tipo | O template recebe |
|---|---|
| `texto`, `textarea`, `opcao`, `bool`… | o valor; **o template escapa** (`esc()` no PHP; autoescape no Twig) |
| `rich_text`, `texto_inline` | HTML já sanitizado (`HtmlSanitizer`); pode ser impresso sem escape (`|raw` no Twig) |
| `link` | o objeto com `href` resolvido, ou `href = null` se a página não existe, está em rascunho ou a URL é insegura |
| `imagem` | `{url, alt, largura, altura}` ou `null` |
| `lista` | lista de itens, cada um preparado com as mesmas regras |
| `dinamico` | lista vinda do provedor (`ultimas_noticias`: `titulo, resumo, slug, data, href`; `areas`: `id, nome`) |

**Helpers / funções Twig** (mesmos nomes): `site_config(chave, padrao)`, `site_menu(local)` (árvore com
`rotulo, href, destaque, filhos`; itens de página em rascunho não aparecem), `site_texto(chave, padrao)`
(o texto editado pelo escritório vence o `textos` do manifesto) e `asset(caminho)` no Twig /
`site_asset(caminho)` no PHP (URL em `templates/<slug>/assets/`).

**Carrossel (Clássico):** a seção `carrossel` usa o Bootstrap Carousel já carregado pelo shell. Campos: `sobretitulo`,
`titulo`, `autoplay` (bool, padrão ligado), `intervalo` (`4`/`6`/`8`/`10` s) e `itens` (`imagem`, `titulo`, `texto`, `link`,
`link_rotulo`). Item sem imagem e sem título é ignorado; com um só item, setas e indicadores somem. O autoplay pausa ao
passar o mouse e respeita `prefers-reduced-motion`. Textos de interface: `carrossel.*`.

**Carrossel de textos (Clássico):** `pilares_carrossel` mostra os mesmos cards de `pilares` (`icone`, `titulo`, `texto`) em
uma faixa deslizante: 3 por vez no desktop, 2 no tablet e 1 no celular. Funciona por rolagem com encaixe (`scroll-snap`),
então também desliza com o dedo e o teclado sem JavaScript; as setas e o `autoplay` (padrão desligado; pausa com mouse ou
foco e não roda com `prefers-reduced-motion`) são um acréscimo. Textos de interface: `pilares_carrossel.*`.

**HTML permitido em `rich_text`** (mesma lista do editor da SPA, em `useDocumentoEditor.ts`):
`p, br, b, strong, i, em, u, s, strike, ul, ol, li, a[href], h1, h2, h3, blockquote`. Tags fora da
lista perdem a marcação e mantêm o texto; `script`, `style`, `iframe`, `svg` e afins somem com o
conteúdo; todo atributo é removido, exceto `href` em `<a>` (http, https, mailto, tel, caminho
relativo ou âncora). Links externos ganham `rel="noopener noreferrer"`.

**Escolha do motor:** templates de origem `upload` sempre rodam em Twig sandbox, mesmo que o
manifesto diga `php` ou o pacote traga `.php`.

**Prévia de rascunho:** `?preview=<token>`. O token é assinado (HMAC), vale para uma única página e
expira (`Config\SiteTemplate::$validadePreview`). A resposta sai com `Cache-Control: no-store` e
`X-Robots-Tag: noindex`.

## Layouts e `partes`

Além de `conteudo` (todas as seções concatenadas), o layout recebe `partes`: lista de
`{tipo, ancora, html}` na ordem da página. Layouts que distribuem seções por região usam isso. O
layout `area` do Clássico, por exemplo, põe `hero_area` no topo, `lateral_outras_areas` na coluna da
direita e as demais na coluna do texto.

Layouts do Clássico podem herdar de `shell/base.php` com `$this->extend('shell/base')` (seções
`styles`, `content` e `scripts`). Um partial de **seção** não consegue definir seções do layout: se
precisar de JavaScript próprio, imprime o `<script>` no próprio partial.

`seo.titulo`: se a página tem `meta_title`, ele é usado como está; sem ele, vale `titulo` +
`seo.sufixo_titulo`.

## Conteúdo inicial (`seed/content.json`)

```json
{
  "configuracoes": { "contato.telefone": { "valor": "(31) 9.9224-6996", "tipo": "string" } },
  "paginas": [
    {
      "slug": "inicio", "titulo": "Início", "tipo": "home", "layout": "main", "ordem": 0,
      "status": "publicado", "meta_title": "…", "meta_description": "…",
      "exibir_sitemap": true, "prioridade_sitemap": 1.0, "changefreq": "daily",
      "secoes": [
        { "tipo": "cards_areas", "ancora": "expertise",
          "dados": { "titulo": "…", "itens": [ { "titulo": "…", "link": { "tipo": "pagina", "slug": "direito-civil" } } ] } }
      ]
    }
  ],
  "menus": { "cabecalho": [ { "rotulo": "Início", "tipo": "pagina", "pagina": "inicio" } ] }
}
```

- Links para páginas do próprio seed usam `slug` (`{tipo:"pagina", slug}`); na importação viram `pagina_id`.
- Cada seção é validada pelo schema do manifesto; seção inválida **não é criada** e aparece em `erros`.
- Chaves de configuração seguem `grupo.nome`; `tipo` é `string`, `text`, `json`, `imagem`, `cor` ou `bool`.

`SeedImporter` **nunca sobrescreve**: só cria configurações, páginas (por slug) e menus (local
inexistente ou vazio) que ainda não existem. Rodar de novo não muda nada.

## Instalação do template do pacote (core)

`TemplateInstaller::instalarCore('<slug>')` valida o manifesto, registra/atualiza o template em
`site_templates`, **ativa-o só se não houver outro ativo**, publica os `assets/` e, se o site não
tem nenhuma página, importa o seed. A migration `InstalaTemplateClassico` faz isso para o
`classico`, que é o site original. Em instalações que já têm conteúdo, nada é alterado.

**Assets:** são copiados (só extensões de css, js, imagens e fontes; nunca `.php`; sem links
simbólicos) para `public/templates/<slug>/assets/` (`Config\SiteTemplate::$diretorioAssets`), e a URL
sai de `site_asset()` / `asset()`. Esta pasta é gerada e está no `.gitignore`. Um arquivo `.versao`
registra o que foi publicado: depois de um deploy com versão nova do template, o primeiro acesso
republica sozinho.

## SEO, canônica, JSON-LD e sitemap

O layout recebe `seo`: `titulo`, `descricao`, `url` (a **URL canônica** da página, sem `?preview=` nem
outros parâmetros), `palavras` (`seo.palavras_chave`) e `imagem` (`og:image`: a da página, senão
`seo.og_image`). O Clássico imprime `<title>`, `description`, `keywords`, `canonical` e `og:*`.

- **Título:** `meta_title` da página, como está; sem ele, `titulo` + `seo.sufixo_titulo`.
- **Descrição:** `meta_description` da página, senão `seo.descricao_padrao`.
- **Google Analytics:** o script só sai se `integracoes.ga_id` estiver preenchido. No Clássico ele vem com o
  **Google Consent Mode v2**: `gtag('consent', 'default', …)` nega `analytics_storage` e os sinais de anúncio antes
  do `gtag('config')`, então o GA4 não grava cookies até o visitante clicar em "Aceitar estatísticas" no aviso de
  cookies. A escolha fica no `localStorage` (`cookies_consentimento` = `aceito`/`recusado`); o link
  "Preferências de cookies" do rodapé (`data-cookies-abrir`) reabre o aviso. Recusar depois de aceitar apaga os
  cookies `_ga*`. Sem `ga_id` e sem `ads_id`, nem o aviso nem o link aparecem (o site só usa cookies necessários).
- **Google Ads (conversões):** `integracoes.ads_id` (`AW-…`) carrega a tag e o `gtag('config')` da conta. Duas conversões
  são disparadas no navegador, cada uma só se o rótulo (a parte depois da barra em `AW-…/RÓTULO`) estiver preenchido:
  `integracoes.ads_conversao_formulario` (envio do formulário de contato com sucesso) e
  `integracoes.ads_conversao_whatsapp` (clique em link `wa.me` ou `api.whatsapp.com/send?phone=…`; os links de
  compartilhar notícia não contam). Ao aceitar o aviso de cookies, o Consent Mode libera também `ad_storage`,
  `ad_user_data` e `ad_personalization`.

**LGPD (#692):** o formulário de contato exige a ciência da Política de Privacidade (checkbox `aceite_privacidade`,
validado também em `POST /api/v2/site/contact`) e grava em `site_crm_contacts` a data/hora do aceite
(`privacidade_aceite_em`) e a versão vigente (`privacidade_versao`, da configuração `lgpd.politica_versao` —
atualize-a sempre que mudar o texto da política). A seção `dados_controlador` imprime razão social
(`identidade.nome`), CNPJ (`identidade.cnpj`), endereço, e-mail e o encarregado (`lgpd.encarregado_nome` e
`lgpd.encarregado_email`), omitindo o que estiver vazio. As páginas `politica-de-privacidade` e `termos-de-uso`
vêm no seed e entram no menu `rodape` e no sitemap.
- **JSON-LD:** `site_jsonld()` (e a função Twig `site_jsonld()`) devolve o `<script type="application/ld+json">`
  de um `LegalService` com `identidade.nome`, `contato.telefone`, `contato.email`, `redes.*` (só `http(s)`)
  e as unidades de `contato.enderecos` que tenham `rua`/`cidade`/`uf`/`cep`. Campo ausente é omitido; sem
  nome do escritório, não gera nada.

**Sitemap (`/sitemap.xml`):** páginas publicadas com `exibir_sitemap = 1` (usando `prioridade_sitemap` e
`changefreq` de cada uma; `lastmod` é a data mais recente entre a página e suas seções) + notícias
publicadas. Âncoras (`#contato`) não entram. Criar, alterar ou excluir página ou seção dispara o evento
`site.paginas.alterado`, que apaga o cache do sitemap; ele é regenerado no próximo acesso.

**Telas de notícias:** o título do artigo é `meta_title` (ou título) + `seo.sufixo_titulo`, e a descrição é
`meta_description` (ou o resumo, até 160 caracteres).

## Telas especiais (`pages/`)

Algumas rotas não são "páginas de conteúdo", mas também vêm do template (`pages/<tela>.php` ou
`pages/<tela>.twig`), com os dados da rota:

| Rota | Tela | Variáveis (além de `pagina`, `seo`, `noindex`) |
|---|---|---|
| `/noticias` | `noticias_lista` | `articles`, `searchQuery`, `currentPage`, `lastPage`, `total` |
| `/noticias/{slug}` | `noticias_artigo` | `article`, `related` (`seo.titulo/descricao/url` do artigo) |
| `/area-cliente` | `area_cliente` | — |
| `/painel-cliente` (e `/cliente`) | `painel_cliente` | — |

- **SEO editável:** cada tela tem uma **página de sistema** (`tipo = sistema`) com o slug `noticias`,
  `area-cliente` ou `painel-cliente`, editável como qualquer página (título, meta título e descrição,
  imagem). Sem a linha no banco, a tela usa o título padrão e continua funcionando. Páginas de sistema
  não abrem em `/pagina/{slug}` (404) e o sitemap usa a rota própria (`/noticias`).
- **`noindex`:** página de sistema com `exibir_sitemap = 0` (área e painel do cliente) sai com
  `<meta name="robots" content="noindex, nofollow">`.
- **Tela ausente:** se o template ativo não tem `pages/<tela>`, usa-se a do Clássico (aviso no log).
- **Textos:** rótulos, placeholders e mensagens (inclusive as de JavaScript) são `textos.*` do
  manifesto (`noticias.*`, `cliente.*`, `painel.*`), editáveis pelo escritório. Textos de JavaScript
  chegam como um objeto `T` impresso com `json_encode(..., JSON_HEX_TAG | JSON_HEX_AMP)`; textos com
  marcação (`noticias.hero_titulo`) passam por `HtmlSanitizer::inline()`.
- **Filtros rápidos de notícias:** lista de termos em `noticias.filtros` (configuração JSON, grupo
  `noticias`); cada botão apenas preenche a busca por texto.
- **Manifesto:** para templates `core`/`cli` vale o `template.json` do disco (muda a cada deploy); para
  os enviados pelo painel, o gravado no banco na instalação.

**URL das páginas** (`UrlPagina`): home = raiz; sistema = `/{slug}`; demais = `/pagina/{slug}`.

**Mídias:** `site_midia(id)` monta a URL a partir de `Config\SiteTemplate::$prefixoUploads`
(`public/uploads/site`); o logo também alimenta o e-mail do link de acesso do cliente
(`emails/cliente_magic_link`), que usa o nome do escritório em vez de texto fixo.

## Tema: cores e fontes sobrescrevíveis

O template declara os tokens em `template.json → tema`:

```json
"tema": {
  "cores":  { "gold": "#C9A84C", "navy-dark": "#0D1B2A" },
  "fontes": { "serif": "Cormorant Garamond", "sans": "Outfit" },
  "fontes_url": "https://fonts.googleapis.com/css2?family=…&display=swap"
}
```

- **Contrato com o CSS:** cada cor `x` é a variável `--x` no `:root` do CSS do template; cada fonte `y` é
  `--font-y`. Para usar a cor com transparência, o CSS usa `rgba(var(--x-rgb), 0.5)` (o `:root` define os
  canais RGB padrão e o tema os recalcula ao trocar a cor). **Não escreva a cor em hexadecimal/rgba fora do
  `:root`**: um teste (`TemaTest`) falha se o CSS do Clássico tiver cor de token fixa.
- **Personalização:** o escritório grava `tema.cores.<token>` (hexadecimal `#RGB`/`#RRGGBB`) e
  `tema.fontes.<token>` (uma família da lista `Config\SiteTemplate::$fontesPermitidas`). Escolher o valor
  padrão não conta como personalização; valor vazio volta ao padrão.
- **Saída no HTML:** `site_tema_css()` devolve `:root{...}` **só dos tokens trocados** (revalidado: hexadecimal
  estrito, fonte da lista, token do manifesto; seguro para imprimir sem escape) e deve vir **depois** do CSS do
  template; `site_fontes_url()` devolve a URL do Google Fonts (a do manifesto se nada mudou; senão, a família
  escolhida com pesos 400 e 700, que todas as da lista têm). As duas existem como funções Twig.
- **API:** `GET /template/tema` (padrão × atual de cada token, `css` e `fontes_disponiveis`),
  `PUT|PATCH /template/tema` com `{"cores": {...}, "fontes": {...}}` (`null` restaura um token, erros por
  `cores.gold`), `DELETE /template/tema` (restaura tudo). Também funciona por `PUT /configuracoes`.

## Instalar, ativar, exportar e remover templates (pacote `.zip`)

**API** (`api/v2/site/admin/templates`, só `superadmin` até as permissões da #654) e **comandos**:

| Ação | API | Comando |
|---|---|---|
| Listar | `GET /templates` | `php spark site:template:list` |
| Instalar `.zip` (campo multipart `arquivo`) | `POST /templates` | `php spark site:template:install <zip\|pasta> [--activate]` |
| Ativar | `POST /templates/{slug}/ativar` | `php spark site:template:activate <slug>` |
| Páginas de exemplo | `POST /templates/{slug}/paginas-exemplo` | — |
| Exportar | `GET /templates/{slug}/exportar[?conteudo=1]` | `php spark site:template:export <slug> <zip> [--with-content]` |
| Remover | `DELETE /templates/{slug}` | — |

**Instalar não ativa** e **ativar não apaga nem sobrescreve conteúdo**. Uma instalação com o mesmo `slug` de um
template enviado é uma atualização (arquivos trocados por inteiro, ativo continua ativo); versão mais antiga só
gera aviso. Slug de template do sistema (`origem = core`) nunca é sobrescrito.

**O que é recusado** (nada é gravado, nenhuma pasta fica para trás; o 422 traz `messages` por arquivo e `avisos`):

- Zip: caminho com `..`, absoluto ou com `\`, link simbólico, arquivo criptografado, mais de 500 arquivos, mais de
  5 MB por arquivo ou 30 MB no total (contados byte a byte na extração, não pelo que o zip declara), zip inválido.
- Nomes: só `[A-Za-z0-9._-]`, sem começar por ponto; nome que contenha `.php`, `.phtml`, `.phar`, `.html`, `.svg`,
  `.htaccess`, `.sh`, `.exe`… em qualquer posição (cobre `x.php.png`).
- Estrutura: na raiz só `template.json`, `preview.(png|jpg|webp)`, `README`, `LICENSE`; pastas
  `layouts/ sections/ pages/ shell/ partials/` (só `.twig`), `assets/` (css, js, imagens, fontes; **sem SVG**) e
  `seed/` (`content.json` e imagens em `seed/media/`).
- Conteúdo: arquivo de texto com `<?php`/`<?=`; `template.json` inválido; `motor` diferente de `twig`; layout
  declarado sem arquivo; seed com seção que não passa pelo schema ou de tipo inexistente.
- Twig: cada `.twig` é **pré-renderizado no sandbox** (funções do site vazias): erro de sintaxe, tag/filtro/função
  fora da lista branca e `include` fora do pacote recusam o pacote.

Avisos (não bloqueiam): partial de seção ou tela ausente (a do Clássico é usada), sem prévia, sem conteúdo inicial.
Pela **linha de comando** o pacote pode trazer views `.php` (e `motor: php`); pelo painel, nunca.

**Ativar** devolve o relatório: seções de tipo que o novo template não tem ficam no banco, **sem mudar `visivel`**
(o renderizador as ignora e elas voltam a aparecer se o tipo voltar; `acao: incompativel`); seções compatíveis
ganham o valor padrão dos campos novos (só acrescenta) e as que ficaram com erro vêm como `revisar`. Também avisa de
páginas cujo layout o template não tem (são exibidas com o primeiro layout do template) e de menus vazios. Só em
site **sem nenhuma página** o conteúdo inicial do template é importado.

**Páginas de exemplo** cria, como rascunho, as páginas do seed cujo slug ainda não existe; nunca toca em páginas,
configurações nem menus existentes.

**Exportar** gera o `.zip` do template; com `conteudo=1`, o `seed/content.json` é montado do site atual (links por
slug; ids de mídia e configurações de imagem ficam de fora, porque não valem em outra instalação).

## Cache do site (#653)

- **Leituras do banco** (configurações, menus, páginas, seções, template ativo) ficam no cache versionado dos repositórios.
- **HTML renderizado** da home, das páginas (`/pagina/{slug}`) e das telas fixas da área do cliente é guardado por
  `Config\SiteTemplate::$ttlPagina` (padrão 3600 s; `0` desliga). Prévia de rascunho e usuário logado ignoram o cache
  (não leem nem gravam). Lista e artigo de notícias não são guardados, pois dependem da busca e da paginação.
- **Invalidação**: toda escrita de conteúdo (APIs de páginas, seções, menus, configurações, mídias, tema, templates, e
  gravação de notícias) dispara `Events::trigger('site.conteudo.alterado')` via `SiteCache::limpar()`. O ouvinte
  (`app/Config/Events.php`) limpa os repositórios e o sitemap e troca a "versão" das páginas guardadas, de modo que a
  alteração aparece na requisição seguinte.
- **Cabeçalhos**: páginas públicas saem com `ETag` e `Cache-Control: public, max-age=0, must-revalidate` (responde `304`
  quando o `If-None-Match` confere); usuário logado recebe `private, no-cache` e a prévia, `no-store`.
- **Linha de comando**: `php spark site:cache:clear`. Use após alterar arquivos de template direto no disco.

## Permissões, módulo e auditoria (#654)

| Permissão | Libera | Padrão |
|---|---|---|
| `site.conteudo` | páginas, seções, menus, mídias e notícias (`GET template/schema` também aceita `site.configuracoes`) | superadmin, admin |
| `site.publicar` | `POST paginas/{id}/publicar` e `/despublicar` | superadmin, admin |
| `site.configuracoes` | `configuracoes` e `template/tema` (dados do escritório, SEO, integrações, tema) | superadmin, admin |
| `site.templates` | `templates*` (instalar, ativar, exportar, remover) | só superadmin |

Cada rota de `api/v2/site/admin` leva os filtros `auth`, `modulo:site` e `permission:<acima>`. Quem tem a permissão por
grupo ou por usuário (Shield) pode receber qualquer uma delas separadamente. `advogado` e `estagiario` não recebem
nenhuma por padrão.

**Módulo "Site"** (slug `site`): entra no catálogo de módulos (ativo por padrão; migration `CadastraModuloSite`). Desligado,
a API administrativa responde 403 `modulo_inativo` e o site público inteiro responde 404.

**Auditoria**: os models do site usam `AuditoriaTrait`. Instalar, atualizar, ativar e remover templates gravam também um
registro em `auditoria` (`table_name = site_templates`; `dados_novos.acao` = `INSTALAR_TPL`, `ATUALIZAR_TPL`, `ATIVAR_TPL`
ou `REMOVER_TPL`, com slug, versão, origem e, na ativação, o relatório de seções e avisos). A coluna `action_type` é um
ENUM (CREATE/UPDATE/DELETE), por isso a ação exata fica no JSON.

**Testes/CI**: `.github/workflows/tests-site.yml` roda a suíte do site em PR e em push na `Beta`. Não há teste
Playwright: o repositório não tem Playwright; a renderização de `/`, das áreas, de `/noticias` e de `/area-cliente`
com o seed do Clássico é coberta por `TemplateClassicoTest` (paridade com o HTML legado).
