<?php

namespace Tests\Unit\Libraries\Site;

use App\Libraries\Site\Tema;
use CodeIgniter\Test\CIUnitTestCase;
use Config\SiteTemplate;

class TemaTest extends CIUnitTestCase
{
    private Tema $tema;
    private array $manifesto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tema = new Tema(new SiteTemplate());
        $this->manifesto = json_decode(file_get_contents(APPPATH . 'Templates/classico/template.json'), true)['tema'];
    }

    public function testSemPersonalizacaoNaoGeraCssEUsaAUrlDoManifesto(): void
    {
        $this->assertSame('', $this->tema->css($this->manifesto, []));
        $this->assertSame($this->manifesto['fontes_url'], $this->tema->urlFontes($this->manifesto, []));

        $t = $this->tema->tokens($this->manifesto, []);
        $this->assertSame(['padrao' => '#C9A84C', 'atual' => '#C9A84C', 'personalizado' => false], $t['cores']['gold']);
        $this->assertArrayHasKey('Lora', $t['fontes_disponiveis']);
    }

    public function testSoOsTokensPersonalizadosEntramNoCssComSeusCanaisRgb(): void
    {
        $css = $this->tema->css($this->manifesto, ['tema.cores.gold' => '#B08E35', 'tema.cores.cream' => '#fff', 'tema.fontes.serif' => 'Lora']);

        $this->assertSame(":root{--cream:#fff;--cream-rgb:255, 255, 255;--gold:#B08E35;--gold-rgb:176, 142, 53;--font-serif:'Lora', serif;}", $css);
        $this->assertStringNotContainsString('navy', $css);
    }

    public function testValoresPerigososOuDesconhecidosSaoIgnoradosNoCss(): void
    {
        $css = $this->tema->css($this->manifesto, [
            'tema.cores.gold'        => '#fff;} body{display:none',
            'tema.cores.navy-dark'   => 'red',
            'tema.cores.cream'       => 'url(javascript:alert(1))',
            'tema.cores.inexistente' => '#123456',
            'tema.fontes.serif'      => "Lora'; } body { color: red",
            'tema.fontes.sans'       => 'Comic Sans MS',
        ]);

        $this->assertSame('', $css);
    }

    public function testUrlDeFontesTrocaSoAFamiliaEscolhida(): void
    {
        $url = $this->tema->urlFontes($this->manifesto, ['tema.fontes.sans' => 'Open Sans']);

        $this->assertStringContainsString('family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600', $url, 'a que não mudou mantém os pesos originais');
        $this->assertStringContainsString('family=Open+Sans:wght@400;700', $url);
        $this->assertStringNotContainsString('Outfit', $url);
        $this->assertStringEndsWith('&display=swap', $url);

        $duas = $this->tema->urlFontes($this->manifesto, ['tema.fontes.serif' => 'Lora', 'tema.fontes.sans' => 'Inter']);
        $this->assertSame('https://fonts.googleapis.com/css2?family=Lora:wght@400;700&family=Inter:wght@400;700&display=swap', $duas);
    }

    public function testEscolherOPadraoNaoContaComoPersonalizacao(): void
    {
        $config = ['tema.cores.gold' => '#c9a84c', 'tema.fontes.sans' => 'Outfit'];

        $this->assertSame('', $this->tema->css($this->manifesto, $config));
        $this->assertSame($this->manifesto['fontes_url'], $this->tema->urlFontes($this->manifesto, $config));
        $this->assertFalse($this->tema->tokens($this->manifesto, $config)['cores']['gold']['personalizado']);
    }

    public function testRgbAceitaHexCurtoELongo(): void
    {
        $this->assertSame([201, 168, 76], $this->tema->rgb('#C9A84C'));
        $this->assertSame([255, 0, 170], $this->tema->rgb('#f0a'));
    }

    public function testCssDoTemplateEstaEmSincroniaComOManifesto(): void
    {
        $css = file_get_contents(APPPATH . 'Templates/classico/assets/css/site.css');
        preg_match('/:root\s*\{(.*?)\n\}/s', $css, $m);
        $raiz = $m[1];

        foreach ($this->manifesto['cores'] as $token => $hex) {
            $this->assertMatchesRegularExpression('/--' . preg_quote($token, '/') . ':\s*' . preg_quote($hex, '/') . ';/i', $raiz, "o :root do CSS deve declarar --{$token}: {$hex}");
        }
        foreach (['gold' => '#C9A84C', 'navy-dark' => '#0D1B2A', 'navy-medium' => '#1B263B', 'cream' => '#FAF9F6'] as $token => $hex) {
            $this->assertStringContainsString("--{$token}-rgb: " . implode(', ', $this->tema->rgb($hex)) . ';', $raiz, "canal RGB de {$token}");
        }
    }

    public function testCssNaoTemCoresDosTokensFixasForaDoRoot(): void
    {
        $proibidos = ['rgba(201, 168, 76', 'rgba(13, 27, 42', 'rgba(27, 38, 59', 'rgba(250, 249, 246', '#C9A84C', '#0D1B2A', '#1B263B', '#415A77', '#E5C365', '#B08E35', '#CBD5E1', '#0A131C'];

        foreach (glob(APPPATH . 'Templates/classico/assets/css/*.css') as $arquivo) {
            $conteudo = preg_replace('/:root\s*\{.*?\n\}/s', '', file_get_contents($arquivo), 1);
            foreach ($proibidos as $cor) {
                $this->assertStringNotContainsStringIgnoringCase($cor, $conteudo, basename($arquivo) . " tem {$cor} fixo; use var(--token)");
            }
        }
    }
}
