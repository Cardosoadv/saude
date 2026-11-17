<?php

declare(strict_types=1);

namespace App\Libraries\Site;

use App\Repositories\Site\SiteMenuRepository;
use App\Repositories\Site\SiteMidiaRepository;
use App\Repositories\Site\SitePaginaRepository;
use App\Services\Site\SiteConfiguracaoService;

/**
 * Dados do site disponíveis a qualquer template durante uma renderização:
 * configurações, menus e textos de interface. É o que está por trás dos helpers
 * `site_config()`, `site_menu()`, `site_texto()` e `site_asset()` e das
 * funções equivalentes do Twig.
 */
class SiteContexto
{
    private static ?self $atual = null;

    /** @var array<string, string> Textos padrão do manifesto do template ativo */
    private array $textosPadrao = [];

    private string $slugTemplate = '';

    /** @var array<string, mixed> `tema` do manifesto do template ativo */
    private array $tema = [];

    public function __construct(
        private ?SiteConfiguracaoService $configuracoes = null,
        private ?SiteMenuRepository $menus = null,
        private ?SitePaginaRepository $paginas = null,
        private ?SiteMidiaRepository $midias = null,
    ) {
        $this->configuracoes ??= new SiteConfiguracaoService();
        $this->menus ??= new SiteMenuRepository();
        $this->paginas ??= new SitePaginaRepository();
        $this->midias ??= new SiteMidiaRepository();
    }

    public static function atual(): self
    {
        return self::$atual ??= new self();
    }

    public static function definir(?self $contexto): void
    {
        self::$atual = $contexto;
    }

    /** @param array<string, string> $textosPadrao */
    public function usarTemplate(string $slug, array $textosPadrao, array $tema = []): void
    {
        $this->slugTemplate = $slug;
        $this->textosPadrao = $textosPadrao;
        $this->tema         = $tema;
    }

    /** CSS (`:root{...}`) dos tokens de cor e fonte que a clínica personalizou; '' se nenhum. */
    public function temaCss(): string
    {
        return (new Tema())->css($this->tema, $this->configuracoes->todas());
    }

    /** URL do Google Fonts com as famílias em uso, ou null se o template não declara fontes. */
    public function fontesUrl(): ?string
    {
        return (new Tema())->urlFontes($this->tema, $this->configuracoes->todas());
    }

    public function config(string $chave, mixed $padrao = null): mixed
    {
        $valor = $this->configuracoes->obter($chave);

        return ($valor === null || $valor === '') ? $padrao : $valor;
    }

    /** Texto de interface: o da clínica (`textos.<chave>`), senão o do template, senão $padrao. */
    public function texto(string $chave, string $padrao = ''): string
    {
        $editado = $this->configuracoes->obter('textos.' . $chave);

        return (string) (($editado !== null && $editado !== '') ? $editado : ($this->textosPadrao[$chave] ?? $padrao));
    }

    /** URL de um arquivo em `assets/` do template ativo. */
    public function asset(string $caminho): string
    {
        $caminho = ltrim(str_replace('..', '', $caminho), '/');

        return site_url_publica(trim(config(\Config\SiteTemplate::class)->prefixoAssets, '/') . "/{$this->slugTemplate}/assets/{$caminho}");
    }

    /**
     * Mídia da biblioteca (configurações do tipo `imagem` guardam o id).
     *
     * @return array{url:string, alt:string, largura:?int, altura:?int, srcset:array<string, string>}|null
     */
    public function midia(mixed $id): ?array
    {
        $id = is_numeric($id) ? (int) $id : 0;
        $midia = $id > 0 ? $this->midias->findById($id) : null;
        if (!$midia) {
            return null;
        }

        $config   = config(\Config\SiteTemplate::class);
        $prefixo  = trim($config->prefixoUploads, '/');
        $arquivo  = ltrim((string) $midia['arquivo'], '/');
        $variantes = [];
        foreach (VariantesMidia::existentes($config->diretorioUploads, $arquivo, (int) $midia['largura']) as $formato => $porLargura) {
            $variantes[$formato] = implode(', ', array_map(
                static fn (int $w, string $rel): string => site_url_publica("{$prefixo}/{$rel}") . " {$w}w",
                array_keys($porLargura),
                $porLargura
            ));
        }

        return [
            'url'     => site_url_publica("{$prefixo}/{$arquivo}"),
            'alt'     => (string) ($midia['alt'] ?? ''),
            'largura' => $midia['largura'],
            'altura'  => $midia['altura'],
            'srcset'  => $variantes,
        ];
    }

    /**
     * Itens do menu como árvore, já com `href` resolvido.
     *
     * @return list<array<string, mixed>>
     */
    public function menu(string $local): array
    {
        $porId = [];
        foreach ($this->paginas->findAll() as $pagina) {
            $porId[$pagina['id']] = $pagina;
        }

        return $this->resolveItens($this->menus->arvore($local), $porId);
    }

    /** @param array<int, array> $paginas */
    private function resolveItens(array $itens, array $paginas): array
    {
        $saida = [];
        foreach ($itens as $item) {
            $href = match ($item['tipo']) {
                'pagina' => isset($paginas[$item['pagina_id']]) ? $this->urlDaPagina($paginas[$item['pagina_id']]) : null,
                'ancora' => site_url_publica('#' . $item['ancora']),
                default  => $this->urlExterna((string) $item['url']),
            };

            // Item que aponta para página inexistente ou em rascunho não aparece.
            if ($item['tipo'] === 'pagina' && (!isset($paginas[$item['pagina_id']]) || $paginas[$item['pagina_id']]['status'] !== 'publicado')) {
                continue;
            }
            if ($href === null) {
                continue;
            }

            $saida[] = [
                'rotulo'   => $item['rotulo'],
                'href'     => $href,
                'destaque' => (bool) $item['destaque'],
                'filhos'   => $this->resolveItens($item['filhos'] ?? [], $paginas),
            ];
        }
        return $saida;
    }

    /**
     * Caminho no disco do logo da clínica (para e-mails, que embutem a imagem):
     * o logo da biblioteca de mídias; sem ele, o logo do template ativo.
     */
    public function logoArquivo(): ?string
    {
        $config = config(\Config\SiteTemplate::class);

        $candidatos = [];
        if (($arquivo = $this->arquivoDaMidia($this->config('identidade.logo'))) !== null) {
            $candidatos[] = $arquivo;
        }
        if ($this->slugTemplate !== '') {
            $candidatos[] = rtrim($config->diretorioAssets, '/\\') . "/{$this->slugTemplate}/assets/img/logo.png";
        }

        foreach ($candidatos as $caminho) {
            if (is_file($caminho)) {
                return $caminho;
            }
        }
        return null;
    }

    /**
     * Caminho no disco do logo dos e-mails: o logo próprio para e-mail (`identidade.logo_email`),
     * para quando o e-mail usa outro fundo que o site; sem ele, o logo da clínica.
     */
    public function logoEmailArquivo(): ?string
    {
        $arquivo = $this->arquivoDaMidia($this->config('identidade.logo_email'));

        return $arquivo !== null && is_file($arquivo) ? $arquivo : $this->logoArquivo();
    }

    /** Caminho no disco de uma mídia da biblioteca, pelo id guardado na configuração. */
    private function arquivoDaMidia(mixed $id): ?string
    {
        $midia = is_numeric($id) ? $this->midias->findById((int) $id) : null;
        if (!$midia) {
            return null;
        }

        return rtrim(config(\Config\SiteTemplate::class)->diretorioUploads, '/\\') . '/' . ltrim($midia['arquivo'], '/');
    }

    /**
     * Tag JSON-LD (um `@graph` por página), ou '' se o site ainda não tem nome configurado.
     *
     * @param array<string, mixed> $seo `titulo`, `url`, `imagem` da página
     * @param array<string, mixed> $pagina `titulo` e `tipo` da página
     * @param array<string, mixed>|null $artigo Linha de `site_news` quando a tela é um artigo
     */
    public function jsonLd(array $seo = [], array $pagina = [], ?array $artigo = null): string
    {
        $logo   = $this->midia($this->config('identidade.logo'))['url'] ?? null;
        $imagem = isset($seo['imagem']) && $seo['imagem'] !== '' ? (string) $seo['imagem'] : null;

        $ctx = ['imagem' => $imagem, 'areas' => $this->titulosDasAreas()];
        if (!empty($seo['url'])) {
            $ctx['pagina'] = ['titulo' => (string) ($pagina['titulo'] ?? ''), 'url' => (string) $seo['url'], 'tipo' => (string) ($pagina['tipo'] ?? ''), 'slug' => (string) ($pagina['slug'] ?? '')];
        }
        if ($artigo !== null && !empty($seo['url'])) {
            $ctx['artigo'] = [
                'titulo'     => (string) ($artigo['title'] ?? ''),
                'url'        => (string) $seo['url'],
                'resumo'     => (string) ($artigo['summary'] ?? $seo['descricao'] ?? ''),
                'publicado'  => $artigo['published_at'] ?? $artigo['created_at'] ?? null,
                'modificado' => $artigo['updated_at'] ?? null,
                'imagem'     => $this->urlExterna((string) ($artigo['featured_image'] ?? '')) ?: $imagem,
            ];
        }

        return JsonLd::script(JsonLd::grafo($this->configuracoes->todas(), site_url_publica(), $logo, $ctx));
    }

    /** @return list<string> */
    private function titulosDasAreas(): array
    {
        $titulos = [];
        foreach ($this->paginas->publicadas() as $p) {
            if ($p['tipo'] === 'area') {
                $titulos[] = (string) $p['titulo'];
            }
        }

        return $titulos;
    }

    public function urlDaPagina(array $pagina): string
    {
        return UrlPagina::de($pagina);
    }

    /** Caminho iniciado por `/` vira URL do site; o resto só passa se for seguro. */
    public function urlExterna(string $url): ?string
    {
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return site_url_publica(ltrim($url, '/'));
        }

        return HtmlSanitizer::hrefSeguro($url) ? $url : null;
    }
}
