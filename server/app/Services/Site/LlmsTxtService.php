<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Libraries\Site\AreasRestritas;
use App\Libraries\Site\Cnpj;
use App\Libraries\Site\UrlPagina;
use App\Repositories\Site\SitePaginaRepository;
use App\Services\NewsService;

/**
 * Monta o /llms.txt (https://llmstxt.org): um resumo em markdown da clínica para assistentes
 * de IA, gerado do banco: configurações (`identidade.*`, `contato.*`, `seo.*`), páginas publicadas
 * e últimas notícias. Páginas em rascunho, fora do sitemap (noindex), a área e o painel do cliente
 * e o `/sistema/` nunca aparecem.
 */
class LlmsTxtService
{
    /** Quantas notícias entram na lista. */
    public const MAX_ARTIGOS = 20;

    protected SiteConfiguracaoService $configuracoes;
    protected SitePaginaRepository $paginas;
    protected NewsService $noticias;

    public function __construct(
        ?SiteConfiguracaoService $configuracoes = null,
        ?SitePaginaRepository $paginas = null,
        ?NewsService $noticias = null,
    ) {
        $this->configuracoes = $configuracoes ?? new SiteConfiguracaoService();
        $this->paginas       = $paginas ?? new SitePaginaRepository();
        $this->noticias      = $noticias ?? new NewsService();
    }

    public function gerar(): string
    {
        helper('site');

        $linhas = ['# ' . $this->nome(), ''];

        $resumo = $this->resumo();
        if ($resumo !== '') {
            $linhas[] = '> ' . $resumo;
            $linhas[] = '';
        }

        $institucional = array_merge($this->itensDasPaginas('pagina'), [$this->itemContato()]);

        $linhas = array_merge(
            $linhas,
            $this->secao('Áreas de atuação', $this->itensDasPaginas('area')),
            $this->secao('Institucional', $institucional),
            $this->secao('Artigos', $this->itensDosArtigos()),
            $this->secao('Optional', $this->itensOpcionais()),
        );

        return rtrim(implode("\n", $linhas)) . "\n";
    }

    /** Itens da seção `## Optional`: o conteúdo completo, em /llms-full.txt. */
    private function itensOpcionais(): array
    {
        return [$this->item('Conteúdo completo', site_url_publica('llms-full.txt'), 'Texto integral das páginas e dos artigos mais recentes.')];
    }

    /** @param list<string> $itens @return list<string> a seção, ou nada se não tem itens */
    private function secao(string $titulo, array $itens): array
    {
        return $itens === [] ? [] : array_merge(["## {$titulo}"], $itens, ['']);
    }

    protected function nome(): string
    {
        return $this->linha((string) $this->configuracoes->obter('identidade.nome', ''), 'Site');
    }

    /** Parágrafo de apresentação: `seo.llms_resumo`, senão a descrição padrão de SEO, senão o texto do rodapé; com CNPJ e registro profissional. */
    protected function resumo(): string
    {
        $texto = '';
        foreach (['seo.llms_resumo', 'seo.descricao_padrao', 'identidade.sobre_rodape'] as $chave) {
            $texto = $this->linha((string) $this->configuracoes->obter($chave, ''));
            if ($texto !== '') {
                break;
            }
        }

        $registro        = [];
        $registroClinica = (string) $this->configuracoes->obter('identidade.registro_profissional', '');
        foreach (['CNPJ' => Cnpj::formatar((string) $this->configuracoes->obter('identidade.cnpj', '')), 'Registro' => $registroClinica] as $rotulo => $valor) {
            $valor = $this->linha($valor);
            if ($valor !== '') {
                $registro[] = "{$rotulo}: {$valor}";
            }
        }

        return trim($texto . ($registro !== [] ? ' ' . implode(' · ', $registro) : ''));
    }

    /** @return list<string> */
    private function itensDasPaginas(string $tipo): array
    {
        $itens = [];
        foreach ($this->paginas->publicadas() as $pagina) {
            if ($pagina['tipo'] !== $tipo || !$this->indexavel($pagina)) {
                continue;
            }
            $itens[] = $this->item($pagina['titulo'], UrlPagina::de($pagina), (string) ($pagina['meta_description'] ?? ''));
        }

        return $itens;
    }

    protected function indexavel(array $pagina): bool
    {
        return (int) $pagina['exibir_sitemap'] === 1 && !AreasRestritas::ehRestrita((string) $pagina['slug']);
    }

    private function itemContato(): string
    {
        $partes = [];
        foreach (['contato.telefone', 'contato.telefone2', 'contato.email'] as $chave) {
            $valor = $this->linha((string) $this->configuracoes->obter($chave, ''));
            if ($valor !== '') {
                $partes[] = $valor;
            }
        }
        $enderecos = $this->configuracoes->obter('contato.enderecos', []);
        foreach (is_array($enderecos) ? $enderecos : [] as $endereco) {
            $resumo = $this->linha((string) ($endereco['resumo'] ?? ''));
            if ($resumo !== '') {
                $partes[] = $resumo;
            }
        }

        return $this->item('Contato', site_url_publica('#contato'), implode(' · ', $partes));
    }

    /** @return list<string> */
    private function itensDosArtigos(): array
    {
        $artigos = $this->noticias->getAll(
            ['title', 'slug', 'summary'],
            ['status' => 'published'],
            'published_at',
            'desc',
            [],
            self::MAX_ARTIGOS,
        );

        $itens = [];
        foreach ($artigos as $artigo) {
            $itens[] = $this->item((string) $artigo['title'], site_url_publica('noticias/' . $artigo['slug']), (string) ($artigo['summary'] ?? ''));
        }

        return $itens;
    }

    /** `- [Título](url): descrição` (a descrição é opcional). */
    protected function item(string $titulo, string $url, string $descricao = ''): string
    {
        $titulo = str_replace(['[', ']'], ['(', ')'], $this->linha($titulo, 'Sem título'));
        $descricao = $this->linha($descricao);

        return "- [{$titulo}]({$url})" . ($descricao !== '' ? ": {$descricao}" : '');
    }

    /** Texto numa linha só, sem tags HTML nem espaços repetidos. */
    protected function linha(string $texto, string $padrao = ''): string
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $texto !== '' ? $texto : $padrao;
    }
}
