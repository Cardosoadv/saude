<?php

namespace Tests\Unit\Services\Site;

use App\Repositories\Site\SitePaginaRepository;
use App\Repositories\Site\SiteSecaoRepository;
use App\Services\NewsService;
use App\Services\Site\LlmsFullService;
use App\Services\Site\SiteConfiguracaoService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * /llms-full.txt: texto integral das páginas e artigos publicados em markdown (issue #687).
 */
class LlmsFullServiceTest extends CIUnitTestCase
{
    private function pagina(int $id, string $slug, string $titulo, string $tipo, array $extra = []): array
    {
        return $extra + ['id' => $id, 'slug' => $slug, 'titulo' => $titulo, 'tipo' => $tipo, 'status' => 'publicado', 'exibir_sitemap' => 1, 'meta_description' => null];
    }

    /** @param array<int, list<array>> $secoes [pagina_id => seções] */
    private function servico(array $paginas, array $secoes = [], array $artigos = []): LlmsFullService
    {
        $config = [
            'identidade.nome' => 'Cardoso & Bruno', 'seo.descricao_padrao' => 'Assessoria jurídica.', 'identidade.cnpj' => '12.345.678/0001-90',
            'contato.telefone' => '(31) 9.9224-6996', 'contato.email' => 'contato@exemplo.adv.br', 'contato.enderecos' => [['resumo' => 'Rua A, 1, Belo Horizonte / MG']],
        ];
        $configuracoes = $this->createMock(SiteConfiguracaoService::class);
        $configuracoes->method('obter')->willReturnCallback(fn (string $k, $padrao = null) => $config[$k] ?? $padrao);
        $repo = $this->createMock(SitePaginaRepository::class);
        $repo->method('publicadas')->willReturn($paginas);
        $noticias = $this->createMock(NewsService::class);
        $noticias->method('getAll')->willReturn($artigos);
        $repoSecoes = $this->createMock(SiteSecaoRepository::class);
        $repoSecoes->method('porPagina')->willReturnCallback(fn (int $id) => $secoes[$id] ?? []);

        return new LlmsFullService($configuracoes, $repo, $noticias, $repoSecoes);
    }

    public function testIncluiDadosInstitucionaisETextoDasPaginasEDosArtigos(): void
    {
        $paginas = [$this->pagina(2, 'direito-civil', 'Direito Civil', 'area')];
        $secoes = [2 => [
            ['tipo' => 'hero_area', 'dados' => ['titulo' => 'Direito <em>Civil</em>']],
            ['tipo' => 'intro_area', 'dados' => ['lead' => 'Assessoria completa.', 'corpo' => '<p>Nossa <strong>equipe</strong>.</p>']],
            ['tipo' => 'lateral_outras_areas', 'dados' => ['titulo' => 'Outras Áreas']],
        ]];
        $artigos = [['title' => 'Guia', 'slug' => 'guia', 'summary' => 'Resumo', 'content' => '<h2>Sub</h2><p>Texto <script>x()</script>do artigo</p>', 'published_at' => '2026-10-01 10:00:00']];

        $md = $this->servico($paginas, $secoes, $artigos)->gerar();

        $this->assertStringStartsWith("# Cardoso & Bruno\n\n> Assessoria jurídica. CNPJ: 12.345.678/0001-90\n\n## Dados institucionais\n", $md);
        foreach (['- Razão social: Cardoso & Bruno', '- Endereço: Rua A, 1, Belo Horizonte / MG', '- Telefone: (31) 9.9224-6996', '- E-mail: contato@exemplo.adv.br', '- Áreas atendidas: Direito Civil'] as $linha) {
            $this->assertStringContainsString($linha . "\n", $md);
        }
        $this->assertStringContainsString("## Direito Civil\n\nURL: " . base_url('pagina/direito-civil') . "\n\n### Direito Civil\n\nAssessoria completa.\n\nNossa **equipe**.\n", $md);
        $this->assertStringNotContainsString('Outras Áreas', $md);
        $this->assertStringContainsString("## Artigos\n\n### Guia — 2026-10-01\n\nURL: " . base_url('noticias/guia') . "\n\n##### Sub\n\nTexto do artigo", $md);
        $this->assertStringNotContainsString('x()', $md);
        $this->assertStringEndsWith("\n", $md);
    }

    public function testExcluiRascunhosAreaDoClienteEPaginasNoindex(): void
    {
        $paginas = [
            $this->pagina(1, 'direito-civil', 'Direito Civil', 'area'),
            $this->pagina(2, 'oculta', 'Página Oculta', 'area', ['exibir_sitemap' => 0]),
            $this->pagina(3, 'area-cliente', 'Área do Cliente', 'sistema'),
            $this->pagina(4, 'painel-cliente', 'Painel do Cliente', 'pagina'),
            $this->pagina(5, 'noticias', 'Artigos e Notícias', 'sistema'),
        ];
        $secoes = [2 => [['tipo' => 'rich_text', 'dados' => ['corpo' => '<p>Segredo da página oculta</p>']]], 4 => [['tipo' => 'rich_text', 'dados' => ['corpo' => '<p>Dados do painel</p>']]]];

        $md = $this->servico($paginas, $secoes)->gerar();

        $this->assertStringContainsString('## Direito Civil', $md);
        foreach (['Oculta', 'Segredo', 'Área do Cliente', 'Painel', 'Dados do painel', 'Artigos e Notícias'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $md);
        }
    }

    public function testPerguntasFrequentesVaoParaUmBlocoSo(): void
    {
        $paginas = [$this->pagina(1, 'direito-civil', 'Direito Civil', 'area')];
        $secoes = [1 => [['tipo' => 'faq', 'dados' => ['titulo' => 'Dúvidas', 'itens' => [['pergunta' => 'Quanto custa?', 'resposta' => 'Depende do caso.']]]]]];

        $md = $this->servico($paginas, $secoes)->gerar();

        $this->assertStringContainsString("## Perguntas frequentes\n\n### Dúvidas\n\n- **Quanto custa?**: Depende do caso.", $md);
        $this->assertSame(1, substr_count($md, 'Quanto custa?'));
    }

    public function testRotaDoLlmsFullExisteNaRaizComOFiltroDeHostCanonico(): void
    {
        $routes = new \CodeIgniter\Router\RouteCollection(service('locator'), config('Modules'), config('Routing'));
        require APPPATH . 'Routes/site.php';

        $this->assertArrayHasKey('llms-full.txt', $routes->getRoutes('GET'));
        $this->assertContains('hostcanonico', (array) ($routes->getRoutesOptions('llms-full.txt', 'GET')['filter'] ?? []));
    }
}
