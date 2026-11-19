# Host canônico do site público (issue #684)

O site tem **um único endereço canônico**: `https://exemplo.com.br/` (HTTPS, **sem www**, sem a subpasta `/clinica/`).
Toda outra variante responde **301 direto** (um só salto) para ele, preservando caminho e query:

| Pedido | Destino |
|---|---|
| `http://exemplo.com.br/pagina/x` | `https://exemplo.com.br/pagina/x` |
| `https://www.exemplo.com.br/` | `https://exemplo.com.br/` |
| `http://www.exemplo.com.br/noticias?page=2` | `https://exemplo.com.br/noticias?page=2` |
| `https://exemplo.com.br/clinica/pagina/x` | `https://exemplo.com.br/pagina/x` |

A API (`/clinica/api/...`) e o `/sistema/` **não** são redirecionados: só as páginas públicas do site
(`/`, `pagina/*`, `noticias*`, `area-cliente`, `painel-cliente`, `cliente`, `sitemap.xml`, `robots.txt`, `llms.txt`, `llms-full.txt`).

## Como está implementado

1. **`App::$publicURL`** (`.env`: `app.publicURL`) é a fonte da verdade. Canonical, `og:url`, menus, JSON-LD e `sitemap.xml` usam `site_url_publica()`.
2. **`.htaccess`** (da aplicação, em `/clinica/`): as regras da seção "Endereço canônico" calculam, sem fixar domínio, o redirecionamento
   para `https://<host sem www>/<caminho>`. Cobre http→https, www→sem www (agora **também em HTTPS**) e `/clinica/*`→`/*`.
3. **Filtro `hostcanonico`** (`App\Filters\HostCanonicoFilter`, aplicado às rotas públicas de `app/Routes/site.php`): mesma regra em PHP,
   rede de segurança quando o servidor ignora o `.htaccess` ou termina o TLS antes da aplicação. Só atua em GET/HEAD, só se `publicURL` estiver
   definida e nunca em `localhost`.

## Configuração de produção

No `.env` do servidor (não versionado):

```ini
app.baseURL   = 'https://exemplo.com.br/clinica/'
app.publicURL = 'https://exemplo.com.br/'
```

`baseURL` continua apontando para a subpasta enquanto a aplicação estiver instalada nela (a API e o `/sistema/` dependem disso).
`publicURL` é o endereço que visitantes e buscadores veem.

## Servir o site na raiz do domínio

O deploy grava a aplicação em `public_html/clinica/`. Para que `/robots.txt`, `/llms.txt` e `/llms-full.txt` existam **na raiz**, o
`.htaccess` de `public_html/` (fora deste repositório) deve repassar o site à aplicação de forma transparente (rewrite interno, sem redirecionar):

```apache
RewriteEngine On

# Mesmo bloco "Endereço canônico" do .htaccess da aplicação, sem a regra de /clinica/.

# Páginas públicas -> aplicação (rewrite interno; a URL do navegador não muda)
RewriteCond %{REQUEST_URI} !^/clinica/
RewriteCond %{REQUEST_URI} !^/sistema/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ clinica/$1 [L]
```

Como o rewrite é interno, `THE_REQUEST` continua sendo o pedido original (sem `/clinica/`), então a regra de `/clinica/*` só dispara em quem
digita a subpasta. Arquivos e pastas que já existem em `public_html/` (por exemplo `sistema/`) continuam sendo servidos diretamente.

## Verificação

```bash
for u in http://exemplo.com.br/ http://www.exemplo.com.br/ https://www.exemplo.com.br/ \
         https://exemplo.com.br/clinica/ https://exemplo.com.br/clinica/pagina/direito-civil; do
  curl -sI -o /dev/null -w "%{http_code} %{redirect_url}  <- $u\n" "$u"
done
```

Cada linha deve mostrar `301` apontando direto para `https://exemplo.com.br/...`; a URL canônica responde `200`.

## Search Console e GA4

- Criar (ou usar) a propriedade de **domínio** `exemplo.com.br` (cobre http/https e www/sem www) e reenviar `https://exemplo.com.br/sitemap.xml`.
- No GA4, usar o mesmo host no fluxo de dados da web.
