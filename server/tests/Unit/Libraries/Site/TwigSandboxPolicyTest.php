<?php

namespace Tests\Unit\Libraries\Site;

use App\Libraries\Site\Template\TwigSandboxPolicy;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Twig\Sandbox\SecurityError;

class TwigSandboxPolicyTest extends CIUnitTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = WRITEPATH . 'cache_twig_' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function renderiza(string $tpl, array $ctx = []): string
    {
        file_put_contents($this->dir . 't.twig', $tpl);
        return TwigSandboxPolicy::ambiente($this->dir)->render('t.twig', $ctx);
    }

    public function testRenderizaOQueEstaNaListaBranca(): void
    {
        $out = $this->renderiza('{% for i in itens %}<li>{{ i.titulo|upper }}</li>{% endfor %}{{ x|default("-") }}', [
            'itens' => [['titulo' => 'a'], ['titulo' => 'b']],
        ]);

        $this->assertSame('<li>A</li><li>B</li>-', $out);
    }

    public function testEscapaHtmlPorPadrao(): void
    {
        $this->assertSame('&lt;b&gt;', $this->renderiza('{{ v }}', ['v' => '<b>']));
    }

    #[DataProvider('bloqueados')]
    public function testBloqueiaTagFiltroEFuncaoForaDaLista(string $tpl): void
    {
        $this->expectException(SecurityError::class);
        $this->renderiza($tpl, ['v' => 'x']);
    }

    public static function bloqueados(): array
    {
        return [
            'função source'   => ['{{ source("t.twig") }}'],
            'função constant' => ['{{ constant("PHP_VERSION") }}'],
            'função random'   => ['{{ random(5) }}'],
            'filtro map'      => ['{{ [1]|map(x => x) }}'],
            'filtro sort'     => ['{{ [2,1]|sort }}'],
            'tag macro'       => ['{% macro m() %}x{% endmacro %}'],
        ];
    }

    public function testNaoChamaMetodoDeObjetoPhp(): void
    {
        $this->expectException(SecurityError::class);
        $this->renderiza('{{ o.getTimestamp() }}', ['o' => new \DateTime()]);
    }

    public function testIncludeNaoSaiDoDiretorioDoTemplate(): void
    {
        $this->expectException(\Twig\Error\LoaderError::class);
        $this->renderiza('{% include "../../../../etc/passwd" %}');
    }
}
