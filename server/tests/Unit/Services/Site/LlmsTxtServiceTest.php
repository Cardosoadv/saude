<?php

namespace Tests\Unit\Services\Site;

use App\Repositories\Site\SitePaginaRepository;
use App\Services\NewsService;
use App\Services\Site\LlmsTxtService;
use App\Services\Site\SiteConfiguracaoService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * /llms.txt: resumo da clínica em markdown (llmstxt.org) montado das configurações, páginas e notícias publicadas (issue #686).
 */
class LlmsTxtServiceTest extends CIUnitTestCase
{
    private function pagina(string $slug, string $titulo, string $tipo, array $extra = []): array
    {
        return $extra + [
            'id' => 1, 'slug' => $slug, 'titulo' => $titulo, 'tipo' => $tipo, 'status' => 'publicado',
            'exibir_sitemap' => 1, 'meta_description' => null,
        ];
    }

    private function servico(array $config, array $paginas, array $artigos = []): LlmsTxtService
    {
        $configuracoes = $this->createMock(SiteConfiguracaoService::class);
        $configuracoes->method('obter')->willReturnCallback(fn (string $k, $padrao = null) => $config[$k] ?? $padrao);
        $repo = $this->createMock(SitePaginaRepository::class);
        $repo->method('publicadas')->willReturn($paginas);
        $noticias = $this->createMock(NewsService::class);
        $noticias->method('getAll')->willReturn($artigos);

        return new LlmsTxtService($configuracoes, $repo, $noticias);
    }

    private function config(): array
    {
        return [
            'identidade.nome'      => 'Clínica Saúde',
            'seo.descricao_padrao' => 'Assessoria jurídica em Belo Horizonte.',
            'identidade.cnpj'      => '12.345.678/0001-90',
            'contato.telefone'     => '(11) 3000-0000',
            'contato.email'        => 'contato@exemplo.adv.br',
            'contato.enderecos'    => [['resumo' => 'Rua A, 1, Belo Horizonte / MG']],
        ];
    }

    public function testMontaNomeResumoAreasInstitucionalEArtigos(): void
    {
        $paginas = [
            $this->pagina('inicio', 'Início', 'home'),
            $this->pagina('consultas', 'Consultas', 'area', ['meta_description' => 'Inventários e divórcios.']),
            $this->pagina('politica-de-privacidade', 'Política de Privacidade', 'pagina'),
        ];
        $artigos = [['title' => 'Novo artigo', 'slug' => 'novo-artigo', 'summary' => '<p>Resumo   do artigo</p>']];

        $md = $this->servico($this->config(), $paginas, $artigos)->gerar();

        $this->assertStringStartsWith("# Clínica Saúde\n\n> Assessoria jurídica em Belo Horizonte. CNPJ: 12.345.678/0001-90\n", $md);
        $this->assertStringContainsString("## Áreas de atuação\n- [Consultas](" . base_url('pagina/consultas') . "): Inventários e divórcios.\n", $md);
        $this->assertStringContainsString('- [Política de Privacidade](' . base_url('pagina/politica-de-privacidade') . ")\n", $md);
        $this->assertStringContainsString('- [Contato](' . base_url('#contato') . '): (11) 3000-0000 · contato@exemplo.adv.br · Rua A, 1, Belo Horizonte / MG', $md);
        $this->assertStringContainsString("## Artigos\n- [Novo artigo](" . base_url('noticias/novo-artigo') . "): Resumo do artigo\n", $md);
        $this->assertStringEndsWith("\n", $md);
        $this->assertStringNotContainsString("\n\n\n", $md);
    }

    public function testSoListaPaginasIndexaveis(): void
    {
        $paginas = [
            $this->pagina('consultas', 'Consultas', 'area'),
            $this->pagina('oculta', 'Oculta', 'area', ['exibir_sitemap' => 0]),
            $this->pagina('area-cliente', 'Área do Cliente', 'sistema'),
            $this->pagina('painel-cliente', 'Painel', 'pagina'),
            $this->pagina('noticias', 'Notícias', 'sistema'),
        ];

        $md = $this->servico($this->config(), $paginas)->gerar();

        $this->assertStringContainsString('Consultas', $md);
        foreach (['Oculta', 'Área do Cliente', 'Painel', '/painel-cliente', '/area-cliente', '/sistema/'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $md);
        }
    }

    public function testResumoEditavelTemPrioridadeESemArtigosASecaoNaoAparece(): void
    {
        $config = $this->config() + ['seo.llms_resumo' => 'Resumo escolhido pela clínica.'];

        $md = $this->servico($config, [])->gerar();

        $this->assertStringContainsString('> Resumo escolhido pela clínica. CNPJ: 12.345.678/0001-90', $md);
        $this->assertStringNotContainsString('## Artigos', $md);
        $this->assertStringNotContainsString('## Áreas de atuação', $md);
        $this->assertStringEndsWith("## Optional\n- [Conteúdo completo](" . base_url('llms-full.txt') . '): Texto integral das páginas e dos artigos mais recentes.' . "\n", $md);
    }

    public function testTituloComColchetesNaoQuebraOLink(): void
    {
        $md = $this->servico($this->config(), [], [['title' => 'Guia [2026]', 'slug' => 'guia', 'summary' => '']])->gerar();

        $this->assertStringContainsString('- [Guia (2026)](' . base_url('noticias/guia') . ")\n", $md);
    }

    public function testRotaDoLlmsTxtExisteNaRaizComOFiltroDeHostCanonico(): void
    {
        $routes = new \CodeIgniter\Router\RouteCollection(service('locator'), config('Modules'), config('Routing'));
        require APPPATH . 'Routes/site.php';

        $this->assertArrayHasKey('llms.txt', $routes->getRoutes('GET'));
        $this->assertContains('hostcanonico', (array) ($routes->getRoutesOptions('llms.txt', 'GET')['filter'] ?? []));
    }
}
