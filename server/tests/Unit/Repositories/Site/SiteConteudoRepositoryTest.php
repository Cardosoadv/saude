<?php

namespace Tests\Unit\Repositories\Site;

use App\Database\Migrations\CreateSiteConteudoTables;
use App\Models\Site\SiteMenuItemModel;
use App\Models\Site\SiteMenuModel;
use App\Models\Site\SitePaginaModel;
use App\Models\Site\SiteSecaoModel;
use App\Models\Site\SiteTemplateModel;
use App\Repositories\Site\SiteConfiguracaoRepository;
use App\Repositories\Site\SiteMenuRepository;
use App\Repositories\Site\SitePaginaRepository;
use App\Repositories\Site\SiteSecaoRepository;
use App\Repositories\Site\SiteTemplateRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * Migration e repositories do conteúdo do site (issue #643).
 * Os models são usados sem auditoria (callbacks desligados) para isolar o schema.
 */
class SiteConteudoRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private const TABELAS = ['site_menu_itens', 'site_menus', 'site_secoes', 'site_paginas', 'site_midias', 'site_templates', 'site_configuracoes'];

    protected function setUp(): void
    {
        parent::setUp();
        // Cache versionado persistente: sem limpar, um teste lê o estado do anterior.
        service('cache')->clean();
        (new \ReflectionProperty(\App\Repositories\BaseRepository::class, 'versions'))->setValue(null, []);
        require_once APPPATH . 'Database/Migrations/Site/2026-10-07-120000_CreateSiteConteudoTables.php';

        $this->db->query('PRAGMA foreign_keys = ON');
        $this->migration()->down();
        $this->migration()->up();
    }

    protected function tearDown(): void
    {
        $this->migration()->down();
        parent::tearDown();
    }

    private function migration(): CreateSiteConteudoTables
    {
        return new CreateSiteConteudoTables(Database::forge($this->db));
    }

    private function semAuditoria(string $modelo)
    {
        $m = new $modelo($this->db);
        foreach (['afterInsert', 'beforeUpdate', 'beforeDelete', 'afterDelete'] as $evento) {
            $this->setPrivateProperty($m, $evento, []);
        }
        return $m;
    }

    public function testMigrationCriaETodasAsTabelasEDesfaz(): void
    {
        foreach (self::TABELAS as $tabela) {
            $this->assertTrue($this->db->tableExists($tabela), $tabela);
        }

        $this->migration()->down();
        foreach (self::TABELAS as $tabela) {
            $this->assertFalse($this->db->tableExists($tabela), $tabela);
        }
    }

    public function testConfiguracaoCodificaTiposEAtualizaPelaChave(): void
    {
        $repo = new SiteConfiguracaoRepository($this->semAuditoria(\App\Models\Site\SiteConfiguracaoModel::class));

        $repo->definir('contato.enderecos', [['cidade' => 'BH']], 'json');
        $repo->definir('seo.indexar', true, 'bool');
        $repo->definir('seo.titulo_padrao', 'Um');
        $repo->definir('seo.titulo_padrao', 'Dois');

        $mapa = $repo->mapa();
        $this->assertSame([['cidade' => 'BH']], $mapa['contato.enderecos']);
        $this->assertTrue($mapa['seo.indexar']);
        $this->assertSame('Dois', $mapa['seo.titulo_padrao']);
        $this->assertSame(3, $this->db->table('site_configuracoes')->countAllResults());
        $this->assertSame('seo', $this->db->table('site_configuracoes')->where('chave', 'seo.titulo_padrao')->get()->getRow()->grupo);
    }

    public function testConfiguracaoRejeitaTipoInvalido(): void
    {
        $repo = new SiteConfiguracaoRepository($this->semAuditoria(\App\Models\Site\SiteConfiguracaoModel::class));

        $this->expectException(\InvalidArgumentException::class);
        $repo->definir('x.y', 'a', 'inexistente');
    }

    public function testApenasUmTemplateFicaAtivo(): void
    {
        $model = $this->semAuditoria(SiteTemplateModel::class);
        $a = $model->insert(['slug' => 'classico', 'nome' => 'Clássico', 'versao' => '1.0.0', 'origem' => 'core', 'ativo' => 1]);
        $b = $model->insert(['slug' => 'moderno', 'nome' => 'Moderno', 'versao' => '1.0.0', 'origem' => 'upload']);
        $repo = new SiteTemplateRepository($model);

        $this->assertSame('classico', $repo->getAtivo()['slug']);

        $this->assertTrue($repo->ativar((int) $b));
        $this->assertSame('moderno', $repo->getAtivo()['slug']);
        $this->assertSame(1, $this->db->table('site_templates')->where('ativo', 1)->countAllResults());

        $this->assertFalse($repo->ativar(9999));
        $this->assertSame('moderno', $repo->getAtivo()['slug'], 'id inexistente não desativa o atual');
        $this->assertNotNull($a);
    }

    public function testSlugDePaginaEUnicoEValidado(): void
    {
        $model = $this->semAuditoria(SitePaginaModel::class);

        $this->assertNotFalse($model->insert(['slug' => 'direito-civil', 'titulo' => 'Civil']), json_encode($model->errors()));
        $this->assertFalse($model->insert(['slug' => 'direito-civil', 'titulo' => 'Outra']));
        $this->assertFalse($model->insert(['slug' => 'Slug Inválido', 'titulo' => 'X']));
        $this->assertFalse($model->insert(['slug' => 'ok', 'titulo' => 'X', 'status' => 'arquivado']));
    }

    public function testPaginasPublicadasEHome(): void
    {
        $model = $this->semAuditoria(SitePaginaModel::class);
        $model->insert(['slug' => 'inicio', 'titulo' => 'Início', 'tipo' => 'home', 'status' => 'publicado', 'ordem' => 1]);
        $model->insert(['slug' => 'rascunho', 'titulo' => 'R', 'ordem' => 2]);
        $model->insert(['slug' => 'civil', 'titulo' => 'C', 'tipo' => 'area', 'status' => 'publicado', 'ordem' => 3]);
        $repo = new SitePaginaRepository($model);

        $this->assertSame(['inicio', 'civil'], array_column($repo->publicadas(), 'slug'));
        $this->assertSame('inicio', $repo->getHome()['slug']);
        $this->assertSame('civil', $repo->getBySlug('civil')['slug']);
    }

    public function testSecoesDecodificamDadosFiltramVisiveisEReordenam(): void
    {
        $paginas = $this->semAuditoria(SitePaginaModel::class);
        $p = $paginas->insert(['slug' => 'inicio', 'titulo' => 'Início']);
        $outra = $paginas->insert(['slug' => 'outra', 'titulo' => 'Outra']);

        $secoes = $this->semAuditoria(SiteSecaoModel::class);
        $s1 = $secoes->insert(['pagina_id' => $p, 'tipo' => 'hero', 'ordem' => 1, 'dados' => json_encode(['titulo' => 'Olá'])]);
        $s2 = $secoes->insert(['pagina_id' => $p, 'tipo' => 'cta', 'ordem' => 2, 'visivel' => 0]);
        $s3 = $secoes->insert(['pagina_id' => $p, 'tipo' => 'contato', 'ordem' => 3]);
        $alheia = $secoes->insert(['pagina_id' => $outra, 'tipo' => 'hero', 'ordem' => 1]);
        $this->assertFalse($secoes->insert(['pagina_id' => $p, 'tipo' => 'x', 'dados' => '{quebrado']));

        $repo = new SiteSecaoRepository($secoes);

        $this->assertSame(['Olá'], [$repo->porPagina((int) $p)[0]['dados']['titulo']]);
        $this->assertSame(['hero', 'contato'], array_column($repo->porPagina((int) $p, true), 'tipo'));

        $repo->reordenar((int) $p, [$s3, $s1, $s2, $alheia]);
        $this->assertSame(['contato', 'hero', 'cta'], array_column($repo->porPagina((int) $p), 'tipo'));
        $this->assertSame(1, (int) $secoes->find($alheia)['ordem'], 'seção de outra página não é alterada');
    }

    public function testExcluirPaginaRemoveSecoesPorCascata(): void
    {
        $paginas = $this->semAuditoria(SitePaginaModel::class);
        $p = $paginas->insert(['slug' => 'inicio', 'titulo' => 'Início']);
        $this->semAuditoria(SiteSecaoModel::class)->insert(['pagina_id' => $p, 'tipo' => 'hero']);

        $this->db->table('site_paginas')->where('id', $p)->delete();

        $this->assertSame(0, $this->db->table('site_secoes')->countAllResults());
    }

    public function testMenuMontaArvoreOrdenada(): void
    {
        $menu = $this->semAuditoria(SiteMenuModel::class)->insert(['local' => 'cabecalho']);
        $itens = $this->semAuditoria(SiteMenuItemModel::class);
        $areas = $itens->insert(['menu_id' => $menu, 'rotulo' => 'Áreas', 'tipo' => 'ancora', 'ancora' => 'expertise', 'ordem' => 2]);
        $itens->insert(['menu_id' => $menu, 'rotulo' => 'Início', 'tipo' => 'url', 'url' => '/', 'ordem' => 1]);
        $itens->insert(['menu_id' => $menu, 'parent_id' => $areas, 'rotulo' => 'Civil', 'tipo' => 'url', 'url' => '/pagina/direito-civil', 'ordem' => 1]);

        $arvore = (new SiteMenuRepository($this->semAuditoria(SiteMenuModel::class)))->arvore('cabecalho');

        $this->assertSame(['Início', 'Áreas'], array_column($arvore, 'rotulo'));
        $this->assertSame(['Civil'], array_column($arvore[1]['filhos'], 'rotulo'));
        $this->assertSame([], (new SiteMenuRepository($this->semAuditoria(SiteMenuModel::class)))->arvore('inexistente'));
    }
}
