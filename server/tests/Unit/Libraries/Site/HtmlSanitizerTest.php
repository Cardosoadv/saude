<?php

namespace Tests\Unit\Libraries\Site;

use App\Libraries\Site\HtmlSanitizer;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class HtmlSanitizerTest extends CIUnitTestCase
{
    public function testMantemOFormatoPermitido(): void
    {
        $html = '<h2>Título</h2><p>Um <strong>forte</strong> e <em>itálico</em><br>quebra.</p><ul><li>a</li></ul>'
              . '<blockquote>cit</blockquote>';

        // h2 permitido, texto acentuado preservado
        $this->assertSame($html, HtmlSanitizer::richText($html));
        $this->assertSame('Ação <em>boa</em>', HtmlSanitizer::richText('Ação <em>boa</em>'));
    }

    #[DataProvider('perigosos')]
    public function testRemoveConteudoPerigoso(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, HtmlSanitizer::richText($entrada));
    }

    public static function perigosos(): array
    {
        return [
            'script some com o conteúdo'  => ['<p>a</p><script>alert(1)</script>', '<p>a</p>'],
            'style some'                  => ['<style>body{display:none}</style>x', 'x'],
            'iframe some'                 => ['<iframe src="//x"></iframe>ok', 'ok'],
            'evento removido'             => ['<p onclick="x()" style="color:red" class="a">t</p>', '<p>t</p>'],
            'tag desconhecida vira texto' => ['<font color="red">t</font><div>d</div>', 'td'],
            'imagem é removida'           => ['<img src=x onerror=alert(1)>t', 't'],
            'comentário some'             => ['a<!-- x -->b', 'ab'],
            'svg some'                    => ['<svg onload=alert(1)><circle/></svg>t', 't'],
        ];
    }

    #[DataProvider('links')]
    public function testHrefDeLinks(string $href, bool $mantem): void
    {
        $saida = HtmlSanitizer::richText('<a href="' . $href . '" target="_blank">x</a>');

        $this->assertSame($mantem, str_contains($saida, 'href='), $saida);
        $this->assertStringNotContainsString('target', $saida);
        $this->assertStringNotContainsStringIgnoringCase('javascript', $saida);
    }

    public static function links(): array
    {
        return [
            'https'            => ['https://exemplo.com/a?b=1', true],
            'mailto'           => ['mailto:a@b.com', true],
            'tel'              => ['tel:+5531999999999', true],
            'relativo'         => ['/pagina/direito-civil', true],
            'âncora'           => ['#contato', true],
            'javascript'       => ['javascript:alert(1)', false],
            'javascript com espaço' => [' javascript:alert(1)', false],
            'data'             => ['data:text/html,<script>', false],
            'protocolo relativo' => ['//evil.com', false],
            'vazio'            => ['', false],
        ];
    }

    public function testLinkExternoGanhaRel(): void
    {
        $this->assertStringContainsString('rel="noopener noreferrer"', HtmlSanitizer::richText('<a href="https://x.com">x</a>'));
        $this->assertStringNotContainsString('rel=', HtmlSanitizer::richText('<a href="/x">x</a>'));
    }

    public function testInlineSoAceitaEmEStrong(): void
    {
        $this->assertSame('Defesa <em>Estratégica</em> e <strong>forte</strong>',
            HtmlSanitizer::inline('Defesa <em>Estratégica</em> e <strong class="x">forte</strong>'));
        $this->assertSame('ab', HtmlSanitizer::inline('<p>a</p><a href="/x">b</a>'));
        $this->assertSame('t', HtmlSanitizer::inline('<script>x</script>t'));
    }

    public function testEntradaVaziaOuNula(): void
    {
        $this->assertSame('', HtmlSanitizer::richText(null));
        $this->assertSame('', HtmlSanitizer::richText("  \n "));
    }

    public function testNaoReinterpretaEntidadesEscapadas(): void
    {
        $this->assertSame('&lt;script&gt;x&lt;/script&gt;', HtmlSanitizer::richText('&lt;script&gt;x&lt;/script&gt;'));
    }
}
