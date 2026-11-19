# Arquivos de SEO/GEO na raiz do site (issues #685, #686 e #687)

Todos são rotas dinâmicas (`app/Routes/site.php`), na raiz do domínio, atrás do filtro `hostcanonico`
([`site-host-canonico.md`](site-host-canonico.md)). Dependem de o site ser servido na raiz.

| Rota | Conteúdo | Cache |
|---|---|---|
| `/robots.txt` | `text/plain`. Libera o site e os rastreadores de IA; bloqueia `/api/`, `/area-cliente`, `/painel-cliente`, `/cliente`, `/sistema/` e `?preview=`; informa o `Sitemap:` absoluto. | 1 dia |
| `/llms.txt` | `text/markdown` no formato [llmstxt.org](https://llmstxt.org): nome, resumo (com CNPJ e registro profissional se configurados), áreas de atuação, institucional, últimos 20 artigos e link para o `/llms-full.txt`. | cache das páginas |
| `/llms-full.txt` | `text/markdown` com dados institucionais, o texto integral de cada página publicada (seções convertidas para markdown), as perguntas frequentes e os últimos 50 artigos. | cache das páginas |

`/llms.txt` e `/llms-full.txt` ficam no mesmo cache das páginas renderizadas (`SiteCache`), invalidado por `site.conteudo.alterado`
(qualquer escrita de página, seção, menu, configuração ou notícia). Nunca entram: rascunhos, páginas fora do sitemap (`exibir_sitemap = 0`),
área e painel do cliente, `/sistema/` e qualquer dado de cliente.

## Configurações opcionais (grupo `seo`)

- `seo.robots_extra` (texto, até 2000 caracteres): regras extras do `robots.txt`. Só `User-agent`, `Allow`, `Disallow` e `Crawl-delay`,
  começando por um `User-agent`. Texto inválido é recusado ao salvar e ignorado na saída.
- `seo.llms_resumo` (texto, até 600 caracteres): parágrafo de apresentação do `/llms.txt`. Sem ele, usa `seo.descricao_padrao` e, depois, `identidade.sobre_rodape`.
- `identidade.cnpj` e `identidade.registro_profissional` (opcionais) aparecem no resumo e nos dados institucionais. `contato.horario` aparece nos dados institucionais.
