<?php

namespace Tests\Unit\Libraries\Site;

use App\Libraries\Site\HostCanonico;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Host canônico do site público (issue #684): toda variante vai, num único 301, para a URL canônica.
 */
class HostCanonicoTest extends CIUnitTestCase
{
    private const PUBLICA = 'https://exemplo.com.br/';
    private const BASE = 'https://exemplo.com.br/clinica/';

    public static function variantes(): array
    {
        return [
            'http sem www'        => [false, 'exemplo.com.br', '/pagina/direito-civil', 'https://exemplo.com.br/pagina/direito-civil'],
            'https com www'       => [true, 'www.exemplo.com.br', '/', 'https://exemplo.com.br/'],
            'http com www'        => [false, 'www.exemplo.com.br', '/noticias?page=2', 'https://exemplo.com.br/noticias?page=2'],
            'subpasta na home'    => [true, 'exemplo.com.br', '/clinica/', 'https://exemplo.com.br/'],
            'subpasta sem barra'  => [true, 'exemplo.com.br', '/clinica', 'https://exemplo.com.br/'],
            'subpasta com path'   => [true, 'exemplo.com.br', '/clinica/pagina/x?a=1', 'https://exemplo.com.br/pagina/x?a=1'],
            'www, http e subpasta' => [false, 'www.exemplo.com.br', '/clinica/noticias/a', 'https://exemplo.com.br/noticias/a'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('variantes')]
    public function testVariantesVaoParaAUrlCanonica(bool $https, string $host, string $uri, string $esperado): void
    {
        $this->assertSame($esperado, HostCanonico::destino(self::PUBLICA, self::BASE, $https, $host, $uri));
    }

    public function testRotasPublicasPassamPeloFiltroDeHostCanonico(): void
    {
        $routes = new \CodeIgniter\Router\RouteCollection(service('locator'), config('Modules'), config('Routing'));
        require APPPATH . 'Routes/site.php';

        foreach (['/', 'pagina/([^/]+)', 'noticias', 'noticias/([^/]+)', 'area-cliente', 'painel-cliente', 'cliente', 'sitemap.xml'] as $rota) {
            $this->assertContains('hostcanonico', (array) ($routes->getRoutesOptions($rota, 'GET')['filter'] ?? []), $rota);
        }
    }

    public function testUrlCanonicaNaoRedireciona(): void
    {
        $this->assertNull(HostCanonico::destino(self::PUBLICA, self::BASE, true, 'exemplo.com.br', '/pagina/direito-civil?x=1'));
    }

    public function testPrefixoParecidoComASubpastaNaoEhRemovido(): void
    {
        $this->assertNull(HostCanonico::destino(self::PUBLICA, self::BASE, true, 'exemplo.com.br', '/clinica-novo/pagina'));
    }

    public function testSemPublicUrlNaoRedireciona(): void
    {
        $this->assertNull(HostCanonico::destino('', self::BASE, false, 'www.qualquer.com', '/'));
    }

    public function testDesenvolvimentoLocalNaoRedireciona(): void
    {
        $this->assertNull(HostCanonico::destino(self::PUBLICA, self::BASE, false, 'localhost:8080', '/'));
    }

    public function testPublicUrlComMesmaSubpastaDaBaseMantemOCaminho(): void
    {
        $this->assertSame(
            'https://x.com/clinica/pagina/a',
            HostCanonico::destino('https://x.com/clinica/', 'https://x.com/clinica/', true, 'www.x.com', '/clinica/pagina/a'),
        );
    }
}
