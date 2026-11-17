<?php

namespace Tests\Unit\Libraries\Site;

use App\Libraries\Site\VariantesMidia;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Variantes responsivas das mídias e `<picture>` do template (issue #694).
 */
class VariantesMidiaTest extends CIUnitTestCase
{
    public function testLargurasFicamAbaixoDoOriginalEIncluemOProprioOriginal(): void
    {
        $this->assertSame([320, 640, 700], VariantesMidia::larguras(700));
        $this->assertSame([320, 640, 960, 1280, 1920], VariantesMidia::larguras(6000));
        $this->assertSame([200], VariantesMidia::larguras(200));
    }

    public function testNomeDaVariante(): void
    {
        $this->assertSame('2026/10/abc-w640.webp', VariantesMidia::nome('2026/10/abc.png', 640, 'webp'));
    }

    public function testGeraEListaAsVariantes(): void
    {
        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            $this->markTestSkipped('GD com WebP indisponível.');
        }

        $dir = WRITEPATH . 'variantes_test_' . uniqid();
        mkdir($dir, 0777, true);
        $img = imagecreatetruecolor(800, 400);
        imagepng($img, $dir . '/hash.png');

        $criados = VariantesMidia::gerar($dir . '/hash.png', 'hash.png', 'image/png', 800, 400);
        $this->assertNotEmpty($criados);

        $existentes = VariantesMidia::existentes($dir, 'hash.png', 800);
        $this->assertSame([320, 640, 800], array_keys($existentes['webp']));

        VariantesMidia::apagar($dir, 'hash.png');
        $this->assertSame([], VariantesMidia::existentes($dir, 'hash.png', 800));

        @unlink($dir . '/hash.png');
        @rmdir($dir);
    }

    public function testSiteImagemComPictureDimensoesELazy(): void
    {
        helper('site');
        $html = site_imagem([
            'url' => 'https://x.test/a.png', 'alt' => 'Foto', 'largura' => 800, 'altura' => 400,
            'srcset' => ['webp' => 'https://x.test/a-w320.webp 320w'],
        ], ['class' => 'foto', 'sizes' => '50vw']);

        $this->assertStringStartsWith('<picture><source type="image/webp"', $html);
        $this->assertStringContainsString('width="800" height="400"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('decoding="async"', $html);

        $topo = site_imagem(['url' => 'https://x.test/a.png', 'largura' => 10, 'altura' => 5, 'srcset' => []], ['loading' => 'eager', 'fetchpriority' => 'high']);
        $this->assertStringStartsWith('<img', $topo);
        $this->assertStringContainsString('fetchpriority="high"', $topo);
        $this->assertStringNotContainsString('loading=', $topo);
        $this->assertSame('', site_imagem(null));
    }
}
