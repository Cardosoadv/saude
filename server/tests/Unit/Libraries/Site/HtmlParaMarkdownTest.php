<?php

namespace Tests\Unit\Libraries\Site;

use App\Libraries\Site\HtmlParaMarkdown;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Conversão de HTML em markdown limpo para o /llms-full.txt (issue #687).
 */
class HtmlParaMarkdownTest extends CIUnitTestCase
{
    public function testConverteParagrafosEnfaseEListas(): void
    {
        $md = HtmlParaMarkdown::converter('<p>Olá <strong>mundo</strong> e <em>você</em> &amp; todos</p><ul><li>Um</li><li>Dois<ul><li>Sub</li></ul></li></ul><ol><li>A</li><li>B</li></ol>');

        $this->assertSame("Olá **mundo** e *você* & todos\n\n- Um\n- Dois\n  - Sub\n\n1. A\n2. B", $md);
    }

    public function testTitulosFicamAbaixoDoNivelMinimo(): void
    {
        $this->assertSame("#### Sub\n\nTexto", HtmlParaMarkdown::converter('<h1>Sub</h1><p>Texto</p>', 4));
        $this->assertSame("###### Fundo", HtmlParaMarkdown::converter('<h5>Fundo</h5>', 4));
    }

    public function testDescartaScriptsEstilosEAtributos(): void
    {
        $md = HtmlParaMarkdown::converter('<script>alert(1)</script><style>p{color:red}</style><p onclick="x()" style="color:red">Texto</p><iframe src="https://x"></iframe>');

        $this->assertSame('Texto', $md);
    }

    public function testLinksRelativosViramAbsolutosEOsPerigososSoMantemOTexto(): void
    {
        $md = HtmlParaMarkdown::converter('<p><a href="/pagina/x">aqui</a> <a href="https://ex.com/a">fora</a> <a href="javascript:alert(1)">ruim</a> <a href="#topo">âncora</a></p>');

        $this->assertSame('[aqui](' . base_url('pagina/x') . ') [fora](https://ex.com/a) ruim âncora', $md);
    }

    public function testTextoPuroEVazio(): void
    {
        $this->assertSame('Texto & simples', HtmlParaMarkdown::converter('Texto &amp; simples'));
        $this->assertSame('', HtmlParaMarkdown::converter('   '));
    }

    public function testCitacao(): void
    {
        $this->assertSame('> Citação', HtmlParaMarkdown::converter('<blockquote><p>Citação</p></blockquote>'));
    }
}
