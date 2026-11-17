<?php

namespace Tests\Unit\Libraries\Site;

use App\Libraries\Site\SeoNoticias;
use App\Libraries\Site\SiteContexto;
use CodeIgniter\Test\CIUnitTestCase;

class SeoNoticiasTest extends CIUnitTestCase
{
    private function seo(array $config): SeoNoticias
    {
        $contexto = $this->createMock(SiteContexto::class);
        $contexto->method('config')->willReturnCallback(fn (string $k, $padrao = null) => $config[$k] ?? $padrao);

        return new SeoNoticias($contexto);
    }

    public function testArtigoPrefereMetaTitleEMetaDescription(): void
    {
        $r = $this->seo(['seo.sufixo_titulo' => ' | X'])->artigo([
            'title' => 'Título', 'meta_title' => 'Título SEO', 'summary' => 'Resumo', 'meta_description' => 'Descrição SEO', 'slug' => 'a-b',
        ]);

        $this->assertSame('Título SEO | X', $r['titulo']);
        $this->assertSame('Descrição SEO', $r['descricao']);
        $this->assertSame(base_url('noticias/a-b'), $r['url']);
    }

    public function testArtigoSemMetaCaiNoTituloENoResumoLimitadoA160(): void
    {
        $r = $this->seo([])->artigo(['title' => 'Só título', 'meta_title' => '', 'summary' => str_repeat('é', 200), 'slug' => 's']);

        $this->assertSame('Só título', $r['titulo'], 'sem sufixo configurado, não inventa um');
        $this->assertSame(160, mb_strlen($r['descricao']));
    }

    public function testSemNenhumaDescricaoDeixaNuloParaOLayoutUsarOPadrao(): void
    {
        $this->assertNull($this->seo([])->artigo(['title' => 'T', 'slug' => 's'])['descricao']);
    }
}
