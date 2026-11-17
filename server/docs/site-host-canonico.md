# Host canônico do site público (issue #684)

O site tem **um único endereço canônico**: `https://cardosoebruno.adv.br/` (HTTPS, **sem www**, sem a subpasta `/ordojuris/`).
Toda outra variante responde **301 direto** (um só salto) para ele, preservando caminho e query:

| Pedido | Destino |
|---|---|
| `http://cardosoebruno.adv.br/pagina/x` | `https://cardosoebruno.adv.br/pagina/x` |
| `https://www.cardosoebruno.adv.br/` | `https://cardosoebruno.adv.br/` |
| `http://www.cardosoebruno.adv.br/noticias?page=2` | `https://cardosoebruno.adv.br/noticias?page=2` |
| `https://cardosoebruno.adv.br/ordojuris/pagina/x` | `https://cardosoebruno.adv.br/pagina/x` |

A API (`/ordojuris/api/...`) e o `/sistema/` **não** são redirecionados: só as páginas públicas do site
(`/`, `pagina/*`, `noticias*`, `area-cliente`, `painel-cliente`, `cliente`, `sitemap.xml`, `robots.txt`, `llms.txt`, `llms-full.txt`).

## Como está implementado

1. **`App::$publicURL`** (`.env`: `app.publicURL`) é a fonte da verdade. Canonical, `og:url`, menus, JSON-LD e `sitemap.xml` usam `site_url_publica()`.
2. **`.htaccess`** (da aplicação, em `/ordojuris/`): as regras da seção "Endereço canônico" calculam, sem fixar domínio, o redirecionamento
   para `https://<host sem www>/<caminho>`. Cobre http→https, www→sem www (agora **também em HTTPS**) e `/ordojuris/*`→`/*`.
3. **Filtro `hostcanonico`** (`App\Filters\HostCanonicoFilter`, aplicado às rotas públicas de `app/Routes/site.php`): mesma regra em PHP,
   rede de segurança quando o servidor ignora o `.htaccess` ou termina o TLS antes da aplicação. Só atua em GET/HEAD, só se `publicURL` estiver
   definida e nunca em `localhost`.

## Configuração de produção

No `.env` do servidor (não versionado):

```ini
app.baseURL   = 'https://cardosoebruno.adv.br/ordojuris/'
app.publicURL = 'https://cardosoebruno.adv.br/'
```

`baseURL` continua apontando para a subpasta enquanto a aplicação estiver instalada nela (a API e o `/sistema/` dependem disso).
`publicURL` é o endereço que visitantes e buscadores veem.

## Servir o site na raiz do domínio

O deploy grava a aplicação em `public_html/ordojuris/`. Para que `/robots.txt`, `/llms.txt` e `/llms-full.txt` existam **na raiz**, o
`.htaccess` de `public_html/` (fora deste repositório) deve repassar o site à aplicação de forma transparente (rewrite interno, sem redirecionar):

```apache
RewriteEngine On

# Mesmo bloco "Endereço canônico" do .htaccess da aplicação, sem a regra de /ordojuris/.

# Páginas públicas -> aplicação (rewrite interno; a URL do navegador não muda)
RewriteCond %{REQUEST_URI} !^/ordojuris/
RewriteCond %{REQUEST_URI} !^/sistema/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ ordojuris/$1 [L]
```

Como o rewrite é interno, `THE_REQUEST` continua sendo o pedido original (sem `/ordojuris/`), então a regra de `/ordojuris/*` só dispara em quem
digita a subpasta. Arquivos e pastas que já existem em `public_html/` (por exemplo `sistema/`) continuam sendo servidos diretamente.

## Verificação

```bash
for u in http://cardosoebruno.adv.br/ http://www.cardosoebruno.adv.br/ https://www.cardosoebruno.adv.br/ \
         https://cardosoebruno.adv.br/ordojuris/ https://cardosoebruno.adv.br/ordojuris/pagina/direito-civil; do
  curl -sI -o /dev/null -w "%{http_code} %{redirect_url}  <- $u\n" "$u"
done
```

Cada linha deve mostrar `301` apontando direto para `https://cardosoebruno.adv.br/...`; a URL canônica responde `200`.

## Search Console e GA4

- Criar (ou usar) a propriedade de **domínio** `cardosoebruno.adv.br` (cobre http/https e www/sem www) e reenviar `https://cardosoebruno.adv.br/sitemap.xml`.
- No GA4, usar o mesmo host no fluxo de dados da web.
