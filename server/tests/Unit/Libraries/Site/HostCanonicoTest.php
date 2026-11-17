<?php

namespace Tests\Unit\Libraries\Site;

use App\Libraries\Site\HostCanonico;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Host canônico do site público (issue #684): toda variante vai, num único 301, para a URL canônica.
 */
class HostCanonicoTest extends CIUnitTestCase
{
    private const PUBLICA = 'https://cardosoebruno.adv.br/';
    private const BASE = 'https://cardosoebruno.adv.br/ordojuris/';

    public static function variantes(): array
    {
        return [
            'http sem www'        => [false, 'cardosoebruno.adv.br', '/pagina/direito-civil', 'https://cardosoebruno.adv.br/pagina/direito-civil'],
            'https com www'       => [true, 'www.cardosoebruno.adv.br', '/', 'https://cardosoebruno.adv.br/'],
            'http com www'        => [false, 'www.cardosoebruno.adv.br', '/noticias?page=2', 'https://cardosoebruno.adv.br/noticias?page=2'],
            'subpasta na home'    => [true, 'cardosoebruno.adv.br', '/ordojuris/', 'https://cardosoebruno.adv.br/'],
            'subpasta sem barra'  => [true, 'cardosoebruno.adv.br', '/ordojuris', 'https://cardosoebruno.adv.br/'],
            'subpasta com path'   => [true, 'cardosoebruno.adv.br', '/ordojuris/pagina/x?a=1', 'https://cardosoebruno.adv.br/pagina/x?a=1'],
            'www, http e subpasta' => [false, 'www.cardosoebruno.adv.br', '/ordojuris/noticias/a', 'https://cardosoebruno.adv.br/noticias/a'],
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
        $this->assertNull(HostCanonico::destino(self::PUBLICA, self::BASE, true, 'cardosoebruno.adv.br', '/pagina/direito-civil?x=1'));
    }

    public function testPrefixoParecidoComASubpastaNaoEhRemovido(): void
    {
        $this->assertNull(HostCanonico::destino(self::PUBLICA, self::BASE, true, 'cardosoebruno.adv.br', '/ordojuris-novo/pagina'));
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
            'https://x.com/ordojuris/pagina/a',
            HostCanonico::destino('https://x.com/ordojuris/', 'https://x.com/ordojuris/', true, 'www.x.com', '/ordojuris/pagina/a'),
        );
    }
}
