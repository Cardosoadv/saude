<?php

namespace Tests\Unit\Services;

use App\Database\Migrations\CreateSiteConteudoTables;
use App\Models\SitemapModel;
use App\Models\Site\SitePaginaModel;
use App\Models\Site\SiteSecaoModel;
use App\Repositories\SitemapRepository;
use App\Repositories\Site\SitePaginaRepository;
use App\Repositories\Site\SiteSecaoRepository;
use App\Services\NewsService;
use App\Services\SitemapService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use Config\Database;

/**
 * Sitemap gerado a partir das páginas do site (issue #647).
 */
class SitemapServiceSiteTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();
        service('cache')->clean();
        (new \ReflectionProperty(\App\Repositories\BaseRepository::class, 'versions'))->setValue(null, []);
        require_once APPPATH . 'Database/Migrations/Site/2026-10-07-120000_CreateSiteConteudoTables.php';

        $migration = new CreateSiteConteudoTables(Database::forge($this->db));
        $migration->down();
        $migration->up();

        $forge = Database::forge($this->db);
        $forge->dropTable('site_sitemap', true);
        $forge->addField([
            'id'            => ['type' => 'INTEGER', 'auto_increment' => true],
            'url'           => ['type' => 'VARCHAR', 'constraint' => 255],
            'last_modified' => ['type' => 'DATETIME'],
            'priority'      => ['type' => 'VARCHAR', 'constraint' => 5],
            'changefreq'    => ['type' => 'VARCHAR', 'constraint' => 20],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('site_sitemap');
    }

    protected function tearDown(): void
    {
        (new CreateSiteConteudoTables(Database::forge($this->db)))->down();
        Database::forge($this->db)->dropTable('site_sitemap', true);
        parent::tearDown();
    }

    /** Model de página/seção sem auditoria; `$notifica` mantém só o aviso de alteração. */
    private function model(string $classe, bool $notifica = false)
    {
        $m = new $classe($this->db);
        $ref = fn (string $p, array $v) => $this->setPrivateProperty($m, $p, $v);
        $ref('afterInsert', $notifica ? ['notificaAlteracaoSite'] : []);
        $ref('beforeUpdate', []);
        $ref('afterUpdate', $notifica ? ['notificaAlteracaoSite'] : []);
        $ref('beforeDelete', []);
        $ref('afterDelete', $notifica ? ['notificaAlteracaoSite'] : []);

        return $m;
    }

    private function servico(array $noticias = []): SitemapService
    {
        $news = $this->createMock(NewsService::class);
        $news->method('getAll')->willReturn($noticias);

        return new SitemapService(
            new SitemapRepository(new SitemapModel($this->db)),
            $news,
            new MockCache(),
            new SitePaginaRepository($this->model(SitePaginaModel::class)),
            new SiteSecaoRepository($this->model(SiteSecaoModel::class))
        );
    }

    public function testListaSoPaginasPublicadasQueApareceNoSitemapSemAncoras(): void
    {
        $paginas = $this->model(SitePaginaModel::class);
        $paginas->insert(['slug' => 'inicio', 'titulo' => 'Início', 'tipo' => 'home', 'status' => 'publicado', 'prioridade_sitemap' => 1.0, 'changefreq' => 'daily', 'ordem' => 1]);
        $paginas->insert(['slug' => 'direito-civil', 'titulo' => 'Civil', 'tipo' => 'area', 'status' => 'publicado', 'ordem' => 2]);
        $paginas->insert(['slug' => 'oculta', 'titulo' => 'Oculta', 'status' => 'publicado', 'exibir_sitemap' => 0, 'ordem' => 3]);
        $paginas->insert(['slug' => 'rascunho', 'titulo' => 'Rascunho', 'ordem' => 4]);
        $paginas->insert(['slug' => 'noticias', 'titulo' => 'Notícias', 'tipo' => 'sistema', 'status' => 'publicado', 'ordem' => 5]);
        $paginas->insert(['slug' => 'area-cliente', 'titulo' => 'Área do Cliente', 'tipo' => 'sistema', 'status' => 'publicado', 'exibir_sitemap' => 0, 'ordem' => 6]);

        $links = $this->servico([['slug' => 'artigo-1', 'updated_at' => '2026-05-01 10:00:00']])->getSitemapLinks();

        $this->assertSame(
            [base_url(), base_url('pagina/direito-civil'), base_url('noticias'), base_url() . 'noticias/artigo-1'],
            array_column($links, 'url')
        );
        $this->assertSame('1.0', $links[0]['priority']);
        $this->assertSame('daily', $links[0]['changefreq']);
        $this->assertSame('0.8', $links[1]['priority']);
        $this->assertSame('monthly', $links[1]['changefreq']);
        foreach ($links as $l) {
            $this->assertStringNotContainsString('#', $l['url']);
        }
    }

    public function testAreaEPainelDoClienteNuncaEntramNoSitemap(): void
    {
        $paginas = $this->model(SitePaginaModel::class);
        $paginas->insert(['slug' => 'area-cliente', 'titulo' => 'Área do Cliente', 'tipo' => 'sistema', 'status' => 'publicado', 'exibir_sitemap' => 1, 'ordem' => 1]);
        $paginas->insert(['slug' => 'painel-cliente', 'titulo' => 'Painel', 'tipo' => 'sistema', 'status' => 'publicado', 'exibir_sitemap' => 1, 'ordem' => 2]);
        $paginas->insert(['slug' => 'noticias', 'titulo' => 'Notícias', 'tipo' => 'sistema', 'status' => 'publicado', 'ordem' => 3]);

        $links = $this->servico()->getSitemapLinks();

        $this->assertSame([base_url('noticias')], array_column($links, 'url'));
    }

    public function testLastmodConsideraAAlteracaoDasSecoes(): void
    {
        $paginas = $this->model(SitePaginaModel::class);
        $id = $paginas->insert(['slug' => 'inicio', 'titulo' => 'Início', 'tipo' => 'home', 'status' => 'publicado']);
        $this->db->table('site_paginas')->where('id', $id)->update(['updated_at' => '2026-01-01 00:00:00']);
        $secoes = $this->model(SiteSecaoModel::class);
        $secoes->insert(['pagina_id' => $id, 'tipo' => 'hero']);
        $this->db->table('site_secoes')->update(['updated_at' => '2026-03-10 12:00:00']);

        $links = $this->servico()->getSitemapLinks();

        $this->assertSame('2026-03-10 12:00:00', $links[0]['last_modified']);
    }

    public function testSemPaginasPublicadasSoSobramAsNoticias(): void
    {
        $links = $this->servico([['slug' => 'a', 'updated_at' => '2026-05-01 10:00:00']])->getSitemapLinks();

        $this->assertSame([base_url() . 'noticias/a'], array_column($links, 'url'));
    }

    public function testAlterarPaginaOuSecaoInvalidaOCacheDoSitemap(): void
    {
        $cache = service('cache');
        $paginas = $this->model(SitePaginaModel::class, notifica: true);
        $secoes = $this->model(SiteSecaoModel::class, notifica: true);

        $cache->save('sitemap', [['url' => 'velho']], 600);
        $id = $paginas->insert(['slug' => 'inicio', 'titulo' => 'Início', 'tipo' => 'home']);
        $this->assertNull($cache->get('sitemap'), 'criar página');

        $cache->save('sitemap', [['url' => 'velho']], 600);
        $paginas->update($id, ['status' => 'publicado']);
        $this->assertNull($cache->get('sitemap'), 'publicar');

        $cache->save('sitemap', [['url' => 'velho']], 600);
        $secaoId = $secoes->insert(['pagina_id' => $id, 'tipo' => 'hero']);
        $this->assertNull($cache->get('sitemap'), 'criar seção');

        $cache->save('sitemap', [['url' => 'velho']], 600);
        $secoes->delete($secaoId);
        $this->assertNull($cache->get('sitemap'), 'excluir seção');

        $cache->save('sitemap', [['url' => 'velho']], 600);
        $paginas->update($id, ['status' => 'rascunho']);
        $this->assertNull($cache->get('sitemap'), 'despublicar');
    }
}
