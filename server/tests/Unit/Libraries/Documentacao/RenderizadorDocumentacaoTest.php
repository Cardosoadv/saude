<?php

namespace Tests\Unit\Libraries\Documentacao;

use App\Libraries\Documentacao\RenderizadorDocumentacao;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Motor de renderização da documentação interna (issue #702).
 */
class RenderizadorDocumentacaoTest extends CIUnitTestCase
{
    private const IMAGENS = 'https://exemplo.test/api/v2/documentacao/imagens/';

    private function render(string $md, string $slug = 'processos/cadastro'): array
    {
        return (new RenderizadorDocumentacao(self::IMAGENS))->renderizar($md, $slug);
    }

    public function testMarkdownBasicoEGfm(): void
    {
        $html = $this->render("Texto **forte** e ~~riscado~~.\n\n| A | B |\n|:--|--:|\n| 1 | 2 |\n\n- [x] feito\n")['html'];

        $this->assertStringContainsString('<strong>forte</strong>', $html);
        $this->assertStringContainsString('<del>riscado</del>', $html);
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<th align="left">A</th>', $html);
        $this->assertStringContainsString('<input checked disabled type="checkbox">', $html);
    }

    public function testCorpoVazio(): void
    {
        $this->assertSame(['html' => '', 'sumario' => [], 'avisos' => []], $this->render("   \n"));
    }

    public function testAncorasESumario(): void
    {
        $r = $this->render("## Partes do Processo\n\n### Ação (CNJ)\n\n#### Detalhe\n\n## Partes do processo\n");

        $this->assertSame([
            ['nivel' => 2, 'texto' => 'Partes do Processo', 'ancora' => 'partes-do-processo'],
            ['nivel' => 3, 'texto' => 'Ação (CNJ)', 'ancora' => 'acao-cnj'],
            ['nivel' => 2, 'texto' => 'Partes do processo', 'ancora' => 'partes-do-processo-2'],
        ], $r['sumario']);
        $this->assertStringContainsString('<h4 id="detalhe">', $r['html']);
        $this->assertStringContainsString('<h2 id="partes-do-processo-2">', $r['html']);
    }

    #[DataProvider('links')]
    public function testDestinoDosLinks(string $href, ?string $esperado): void
    {
        $this->assertSame($esperado, RenderizadorDocumentacao::destinoDoLink($href, 'processos/cadastro'));
    }

    public static function links(): array
    {
        return [
            'mesma seção'           => ['partes.md', '#/documentacao/processos/partes'],
            'outra seção com âncora' => ['../financeiro/extrato.md#filtros', '#/documentacao/financeiro/extrato#filtros'],
            'capa de outra seção'   => ['../financeiro/index.md', '#/documentacao/financeiro'],
            'âncora na página'      => ['#partes', '#/documentacao/processos/cadastro#partes'],
            'tela da SPA'           => ['#/processos', '#/processos'],
            'https'                 => ['https://www.cnj.jus.br', 'https://www.cnj.jus.br'],
            'mailto'                => ['mailto:suporte@exemplo.test', 'mailto:suporte@exemplo.test'],
            'sai de app/Docs'       => ['../../segredo.md', null],
            'arquivo que não é .md' => ['planilha.xlsx', null],
            'javascript'            => ['javascript:alert(1)', null],
            'data'                  => ['data:text/html,x', null],
        ];
    }

    public function testLinksNoHtml(): void
    {
        $html = $this->render('[partes](partes.md) [cnj](https://www.cnj.jus.br) [x](planilha.xlsx)')['html'];

        $this->assertStringContainsString('<a href="#/documentacao/processos/partes">partes</a>', $html);
        $this->assertStringContainsString('<a href="https://www.cnj.jus.br" target="_blank" rel="noopener noreferrer">cnj</a>', $html);
        $this->assertStringContainsString('<a>x</a>', $html, 'link não aceito perde o href e mantém o texto');
    }

    public function testImagens(): void
    {
        $html = $this->render("![Menu aberto](img/menu.webp)\n\n![fora](../../config.php)\n\n![externa](https://x.test/a.png)")['html'];

        $this->assertStringContainsString('<img src="' . self::IMAGENS . 'processos/img/menu.webp" alt="Menu aberto" loading="lazy">', $html);
        $this->assertStringNotContainsString('config.php', $html);
        $this->assertStringNotContainsString('x.test', $html);
        $this->assertStringContainsString('fora', $html, 'imagem recusada vira o texto alternativo');
    }

    public function testAvisosDoQueFoiDescartado(): void
    {
        $r = $this->render("[x](planilha.xlsx) [ok](partes.md)\n\n![](img/sem-alt.png) ![fora](../../a.png)");

        $this->assertSame([
            'Link recusado: planilha.xlsx',
            'Imagem sem texto alternativo: img/sem-alt.png',
            'Imagem recusada (use <secao>/img/arquivo.webp): ../../a.png',
        ], $r['avisos'], 'na ordem em que aparecem');
        $this->assertSame([], $this->render('[ok](partes.md) ![alt](img/a.png)')['avisos']);
    }

    public function testLarguraEAlturaLidasDoArquivo(): void
    {
        $raiz = sys_get_temp_dir() . '/docs-img-' . bin2hex(random_bytes(4));
        mkdir("{$raiz}/processos/img", 0777, true);
        imagewebp(imagecreatetruecolor(800, 450), "{$raiz}/processos/img/tela.webp");
        imagepng(imagecreatetruecolor(1700, 100), "{$raiz}/processos/img/larga.png");

        try {
            $r = (new RenderizadorDocumentacao(self::IMAGENS, $raiz))
                ->renderizar("![Tela](img/tela.webp) ![Larga](img/larga.png) ![Falta](img/falta.webp)", 'processos/cadastro');
        } finally {
            array_map('unlink', glob("{$raiz}/processos/img/*"));
            rmdir("{$raiz}/processos/img");
            rmdir("{$raiz}/processos");
            rmdir($raiz);
        }

        $this->assertStringContainsString('src="' . self::IMAGENS . 'processos/img/tela.webp" alt="Tela" loading="lazy" width="800" height="450"', $r['html']);
        $this->assertStringContainsString('alt="Falta" loading="lazy">', $r['html'], 'sem arquivo, sem dimensões');
        $this->assertSame(['Imagem com 1700 px de largura (máximo 1600): img/larga.png'], $r['avisos']);
    }

    public function testImagemNaCapaUsaAPastaDaSecao(): void
    {
        $html = $this->render('![a](img/b.png)', 'processos')['html'];

        $this->assertStringContainsString(self::IMAGENS . 'processos/img/b.png', $html);
    }

    #[DataProvider('avisos')]
    public function testAvisos(string $marcador, string $classe, string $titulo): void
    {
        $html = $this->render("> [!{$marcador}]\n> Cuidado com o **prazo**.\n")['html'];

        $this->assertStringContainsString("<aside class=\"doc-aviso doc-aviso--{$classe}\" role=\"note\">", $html);
        $this->assertStringContainsString("<p class=\"doc-aviso-titulo\">{$titulo}</p>", $html);
        $this->assertStringContainsString('Cuidado com o <strong>prazo</strong>.', $html);
        $this->assertStringNotContainsString('[!', $html);
        $this->assertStringNotContainsString('blockquote', $html);
    }

    public static function avisos(): array
    {
        return [
            'nota'    => ['NOTA', 'nota', 'Nota'],
            'dica'    => ['DICA', 'dica', 'Dica'],
            'atenção' => ['ATENCAO', 'atencao', 'Atenção'],
        ];
    }

    public function testCitacaoComumContinuaCitacao(): void
    {
        $html = $this->render("> Uma citação.\n")['html'];

        $this->assertStringContainsString('<blockquote>', $html);
    }

    #[DataProvider('xss')]
    public function testBloqueiaXss(string $md, string $proibido): void
    {
        $html = $this->render($md)['html'];

        $this->assertStringNotContainsStringIgnoringCase($proibido, $html);
    }

    public static function xss(): array
    {
        return [
            'script cru'           => ['<script>alert(1)</script>', '<script'],
            'img com onerror'      => ['<img src=x onerror=alert(1)>', '<img'],
            'link javascript'      => ['[x](javascript:alert(1))', 'javascript:'],
            'link javascript caso' => ['[x](JaVaScRiPt:alert(1))', 'javascript:'],
            'imagem javascript'    => ['![x](javascript:alert(1))', 'javascript:'],
            'autolink javascript'  => ['<javascript:alert(1)>', 'href="javascript'],
            'iframe'               => ['<iframe src="//x"></iframe>', '<iframe'],
            'atributo no título'   => ['## T {onclick=alert(1)}', 'onclick="'],
            'html em bloco'        => ["<div onmouseover=\"x()\">\n\noi\n\n</div>", '<div'],
        ];
    }

    public function testSlugificar(): void
    {
        $this->assertSame('cadastro-de-acoes-cnj', RenderizadorDocumentacao::slugificar('Cadastro de Ações (CNJ)'));
        $this->assertSame('', RenderizadorDocumentacao::slugificar('!!!'));
    }
}
