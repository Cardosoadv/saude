<?php

namespace Tests\Unit\Libraries\Site;

use App\Libraries\Site\RobotsTxt;
use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * robots.txt do site público (issue #685).
 */
class RobotsTxtTest extends CIUnitTestCase
{
    private const SITEMAP = 'https://exemplo.com.br/sitemap.xml';

    public function testBloqueiaAreasPrivadasEInformaOSitemapAbsoluto(): void
    {
        $txt = RobotsTxt::gerar(self::SITEMAP);

        $this->assertStringStartsWith("User-agent: *\nAllow: /\n", $txt);
        foreach (['/api/', '/area-cliente', '/painel-cliente', '/cliente', '/sistema/', '/*?preview='] as $caminho) {
            $this->assertStringContainsString("Disallow: {$caminho}\n", $txt);
        }
        $this->assertStringEndsWith("Sitemap: " . self::SITEMAP . "\n", $txt);
    }

    public function testMantemOsRastreadoresDeIaLiberados(): void
    {
        $txt = RobotsTxt::gerar(self::SITEMAP);

        foreach (['GPTBot', 'Google-Extended', 'PerplexityBot', 'ClaudeBot'] as $robo) {
            $this->assertStringContainsString("User-agent: {$robo}\nAllow: /\n", $txt);
        }
    }

    public function testRegrasExtrasValidasEntramAntesDoSitemap(): void
    {
        $txt = RobotsTxt::gerar(self::SITEMAP, "User-agent: Bingbot\r\nCrawl-delay: 5");

        $this->assertStringContainsString("\nUser-agent: Bingbot\nCrawl-delay: 5\n\nSitemap:", $txt);
    }

    public function testRegrasExtrasInvalidasSaoIgnoradasNaSaida(): void
    {
        $txt = RobotsTxt::gerar(self::SITEMAP, "Disallow: /x\nSitemap: https://outro.com/s.xml");

        $this->assertStringNotContainsString('outro.com', $txt);
        $this->assertStringNotContainsString('Disallow: /x', $txt);
    }

    public static function extras(): array
    {
        return [
            'vazio'                    => ['', null],
            'bloco válido'             => ["# comentário\nUser-agent: Bingbot\nDisallow: /privado", null],
            'sem user-agent no início' => ["Disallow: /x", 'Linha 1: comece por uma linha User-agent.'],
            'diretiva proibida'        => ["User-agent: *\nSitemap: https://x.com/s.xml", 'Linha 2: use apenas User-agent, Allow, Disallow ou Crawl-delay.'],
            'texto solto'              => ["User-agent: *\nqualquer coisa", 'Linha 2: use apenas User-agent, Allow, Disallow ou Crawl-delay.'],
            'grande demais'            => ["User-agent: *\n" . str_repeat('Disallow: /a', 400), 'Máximo de 2000 caracteres.'],
            'caractere de controle'    => ["User-agent: *\x00x\nDisallow: /", 'Contém caracteres de controle.'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('extras')]
    public function testValidacaoDoTextoExtra(string $texto, ?string $erro): void
    {
        $this->assertSame($erro, RobotsTxt::erroNoExtra($texto));
    }

    public function testRotaDoRobotsTxtExisteNaRaizComOFiltroDeHostCanonico(): void
    {
        $routes = new RouteCollection(service('locator'), config('Modules'), config('Routing'));
        require APPPATH . 'Routes/site.php';

        $this->assertArrayHasKey('robots.txt', $routes->getRoutes('GET'));
        $this->assertContains('hostcanonico', (array) ($routes->getRoutesOptions('robots.txt', 'GET')['filter'] ?? []));
    }
}
