<?php

declare(strict_types=1);

namespace App\Libraries\Site;

use App\Libraries\Site\Motor\MotorDeView;
use App\Libraries\Site\Motor\PhpMotor;
use App\Libraries\Site\Motor\TwigMotor;
use App\Libraries\Site\Template\TemplateAssets;
use App\Repositories\Site\SitePaginaRepository;
use App\Repositories\Site\SiteSecaoRepository;
use App\Services\Site\SiteTemplateService;
use Config\SiteTemplate;

/**
 * Monta uma página do site a partir do banco e do template ativo:
 * página → seções visíveis → partial de cada tipo → layout.
 *
 * Retorna `null` quando não há como atender (sem template ativo, página
 * inexistente ou não publicada); quem chama decide entre 404 e fallback.
 */
class SiteRenderService
{
    public function __construct(
        private ?SiteTemplateService $templates = null,
        private ?SitePaginaRepository $paginas = null,
        private ?SiteSecaoRepository $secoes = null,
        private ?SiteContexto $contexto = null,
        private ?SecaoPreparador $preparador = null,
        private ?PreviewToken $preview = null,
        private ?SiteTemplate $config = null,
        private ?TemplateAssets $assets = null,
    ) {
        helper('site');
        $this->templates ??= new SiteTemplateService();
        $this->paginas   ??= new SitePaginaRepository();
        $this->secoes    ??= new SiteSecaoRepository();
        $this->contexto  ??= SiteContexto::atual();
        $this->preparador ??= new SecaoPreparador($this->contexto, new DadosDinamicos());
        $this->preview   ??= new PreviewToken();
        $this->config    ??= config(SiteTemplate::class);
        $this->assets    ??= new TemplateAssets($this->config);
    }

    /** Há template ativo e a página inicial está publicada? */
    public function disponivel(): bool
    {
        $home = $this->paginas->getHome();

        return $this->templates->ativo() !== null && $home !== null && $home['status'] === 'publicado';
    }

    public function renderHome(?string $tokenPreview = null): ?string
    {
        return $this->renderiza($this->paginas->getHome(), $tokenPreview);
    }

    public function renderPagina(string $slug, ?string $tokenPreview = null): ?string
    {
        $pagina = $this->paginas->getBySlug($slug);

        // Páginas de sistema (notícias, área do cliente) têm rota própria; não abrem em /pagina/{slug}.
        return ($pagina !== null && $pagina['tipo'] === 'sistema') ? null : $this->renderiza($pagina, $tokenPreview);
    }

    /**
     * Tela especial do template (`pages/<tela>`): lista e artigo de notícias, área e painel do
     * cliente. O SEO vem da página de sistema de mesmo `$slug` (editável como qualquer página);
     * se ela não existir ainda, usa `$tituloPadrao`. `$seo` sobrepõe título, descrição e URL
     * (artigo de notícia). Páginas de sistema fora do sitemap saem com `noindex`.
     *
     * Se o template ativo não tem a tela, usa a do Clássico, para o endereço nunca ficar sem resposta.
     *
     * @param array<string, mixed> $dados Variáveis da tela (artigos, termo de busca…)
     * @param array{titulo?:string, descricao?:?string, url?:string} $seo
     */
    public function renderTela(string $tela, string $slug, array $dados, string $tituloPadrao, array $seo = []): ?string
    {
        $template = $this->templates->ativo();
        if ($template === null) {
            return null;
        }

        $pagina = $this->paginas->getBySlug($slug) ?? [
            'id' => 0, 'slug' => $slug, 'titulo' => $tituloPadrao, 'tipo' => 'sistema', 'layout' => 'main',
            'meta_title' => null, 'meta_description' => null, 'og_image_id' => null, 'status' => 'publicado',
            'exibir_sitemap' => 0,
        ];
        $vars = $dados + [
            'pagina'   => $this->paginaParaView($pagina),
            'seo'      => array_merge($this->seo($pagina), array_filter($seo, static fn ($v) => $v !== null)),
            'rascunho' => false,
            'noindex'  => !(int) $pagina['exibir_sitemap'],
        ];

        $html = $this->telaDoTemplate($template, $tela, $vars);
        if ($html === null && $template['slug'] !== 'classico') {
            log_message('warning', "Site: o template '{$template['slug']}' não tem a tela '{$tela}'; usando a do Clássico.");
            $html = $this->telaDoTemplate(['slug' => 'classico', 'origem' => 'core', 'versao' => '', 'manifest' => null], $tela, $vars);
        }

        return $html;
    }

    private function telaDoTemplate(array $template, string $tela, array $vars): ?string
    {
        [$diretorio, , $motor] = $this->prepara($template);

        return $motor->pagina($diretorio, $tela, $vars);
    }

    /**
     * Resolve diretório, manifesto e motor do template, publica os assets dos templates do pacote
     * da aplicação e registra o template no contexto (textos e URL de assets).
     *
     * @return array{0:string, 1:array, 2:MotorDeView}
     */
    private function prepara(array $template): array
    {
        $diretorio = $this->templates->diretorio($template);
        $manifesto = $this->templates->manifesto($template);
        $motor     = $this->motor($this->templates->motor($template, $manifesto));

        // Templates do pacote da aplicação (core/cli) publicam os próprios assets sozinhos
        // após um deploy; os enviados pelo painel foram publicados na instalação (#650).
        if ($template['origem'] !== 'upload') {
            try {
                $this->assets->garantir($template['slug'], (string) ($manifesto['versao'] ?? $template['versao']), $diretorio);
            } catch (\Throwable $e) {
                // Sem permissão de escrita a página ainda renderiza (só sem os assets novos).
                log_message('error', "Site: não foi possível publicar os assets de '{$template['slug']}'. " . $e->getMessage());
            }
        }

        $this->contexto->usarTemplate($template['slug'], $manifesto['textos'] ?? [], $manifesto['tema'] ?? []);

        return [$diretorio, $manifesto, $motor];
    }

    private function renderiza(?array $pagina, ?string $tokenPreview): ?string
    {
        $template = $this->templates->ativo();
        if ($pagina === null || $template === null) {
            return null;
        }

        $rascunhoLiberado = $tokenPreview !== null
            && $this->preview->valido($tokenPreview, (int) $pagina['id']);
        if ($pagina['status'] !== 'publicado' && !$rascunhoLiberado) {
            return null;
        }

        [$diretorio, $manifesto, $motor] = $this->prepara($template);
        $defs = $manifesto['secoes'] ?? [];

        $html   = '';
        $partes = [];
        foreach ($this->secoes->porPagina((int) $pagina['id'], true) as $secao) {
            if (!isset($defs[$secao['tipo']])) {
                log_message('warning', "Site: seção '{$secao['tipo']}' (#{$secao['id']}) não existe no template '{$template['slug']}'; ignorada.");
                continue;
            }

            $parcial = $motor->secao($diretorio, $secao['tipo'], [
                'dados'  => $this->preparador->preparar($defs[$secao['tipo']]['campos'] ?? [], $secao['dados']),
                'secao'  => ['id' => (int) $secao['id'], 'tipo' => $secao['tipo'], 'ancora' => $secao['ancora']],
                'pagina' => $this->paginaParaView($pagina),
            ]);

            if ($parcial === null) {
                log_message('warning', "Site: o template '{$template['slug']}' não tem o partial da seção '{$secao['tipo']}'; ignorada.");
                continue;
            }
            $html    .= $parcial;
            $partes[] = ['tipo' => $secao['tipo'], 'ancora' => $secao['ancora'], 'html' => $parcial];
        }

        $varsDoLayout = [
            'conteudo' => $html,
            'partes'   => $partes,
            'pagina'   => $this->paginaParaView($pagina),
            'seo'      => $this->seo($pagina),
            'rascunho' => $pagina['status'] !== 'publicado',
        ];
        $layout = $motor->layout($diretorio, (string) ($pagina['layout'] ?: 'main'), $varsDoLayout);

        // Depois de trocar de template, a página pode apontar para um layout que o novo não tem:
        // usa o primeiro layout do template em vez de devolver o conteúdo sem moldura.
        $reserva = (string) (($manifesto['layouts'] ?? [])[0] ?? '');
        if ($layout === null && $reserva !== '' && $reserva !== $pagina['layout']) {
            log_message('warning', "Site: o template '{$template['slug']}' não tem o layout '{$pagina['layout']}'; usando '{$reserva}'.");
            $layout = $motor->layout($diretorio, $reserva, $varsDoLayout);
        }

        if ($layout === null) {
            log_message('error', "Site: o template '{$template['slug']}' não tem o layout '{$pagina['layout']}'; devolvendo só o conteúdo.");
            return $html;
        }

        return $layout;
    }

    private function motor(string $nome): MotorDeView
    {
        return $nome === 'twig' ? new TwigMotor($this->contexto) : new PhpMotor();
    }

    /** Só o que o template precisa; sem objetos, para servir também ao Twig. */
    private function paginaParaView(array $pagina): array
    {
        return [
            'id'     => (int) $pagina['id'],
            'slug'   => $pagina['slug'],
            'titulo' => $pagina['titulo'],
            'tipo'   => $pagina['tipo'],
        ];
    }

    /** SEO da página. `url` é a canônica (sem `?preview=` nem outros parâmetros). */
    private function seo(array $pagina): array
    {
        // `meta_title` preenchido é usado como está; sem ele, título + sufixo do site.
        $titulo = $pagina['meta_title']
            ?: $pagina['titulo'] . (string) $this->contexto->config('seo.sufixo_titulo', '');

        return [
            'titulo'    => $titulo,
            'descricao' => (string) ($pagina['meta_description'] ?: $this->contexto->config('seo.descricao_padrao', '')),
            'url'       => $this->contexto->urlDaPagina($pagina),
            'palavras'  => (string) $this->contexto->config('seo.palavras_chave', ''),
            'imagem'    => $this->imagemSeo($pagina),
        ];
    }

    /** `og:image`: a da página, senão a padrão do site (id em `seo.og_image`). */
    private function imagemSeo(array $pagina): ?string
    {
        $midia = $this->contexto->midia($pagina['og_image_id'] ?? null)
            ?? $this->contexto->midia($this->contexto->config('seo.og_image'));

        return $midia['url'] ?? null;
    }
}
