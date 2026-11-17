<?php

namespace Tests\Unit\Controllers;

use App\Controllers\Site;
use App\Database\Migrations\CreateSiteConteudoTables;
use App\Libraries\Site\Template\TemplateAssets;
use App\Libraries\Site\Template\TemplateInstaller;
use App\Models\NewsModel;
use App\Services\Site\SitePaginaService;
use App\Services\Site\SiteCache;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Config\Services;
use Config\SiteTemplate;

/**
 * Cache do site público e invalidação por evento (issue #653).
 */
class SiteCacheTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use ControllerTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->limpaCache();
        require_once APPPATH . 'Database/Migrations/Site/2026-10-07-120000_CreateSiteConteudoTables.php';

        $forge = Database::forge($this->db);
        (new CreateSiteConteudoTables($forge))->down();
        (new CreateSiteConteudoTables($forge))->up();

        $forge->dropTable('auditoria', true);
        $forge->addField([
            'id'            => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id'       => ['type' => 'INTEGER', 'null' => true],
            'table_name'    => ['type' => 'VARCHAR', 'constraint' => 100],
            'action_type'   => ['type' => 'VARCHAR', 'constraint' => 20],
            'dados_antigos' => ['type' => 'TEXT', 'null' => true],
            'dados_novos'   => ['type' => 'TEXT', 'null' => true],
            'ip_address'    => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('auditoria');

        // `user_id()` do Shield consulta a tabela de configurações do CI4 (settings).
        $forge->dropTable('settings', true);
        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'class'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'key'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'value'      => ['type' => 'TEXT', 'null' => true],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 31, 'default' => 'string'],
            'context'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('settings');

        $this->tmp = WRITEPATH . 'admin_test_' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($this->tmp, 0777, true);
        $config = new SiteTemplate();
        $config->diretorioAssets = $this->tmp . 'assets';
        (new TemplateInstaller(assets: new TemplateAssets($config)))->instalarCore('classico');

        $this->limpaCache();
        $this->moduloSite(true);
    }

    protected function tearDown(): void
    {
        $forge = Database::forge($this->db);
        (new CreateSiteConteudoTables($forge))->down();
        $forge->dropTable('auditoria', true);
        $forge->dropTable('settings', true);
        $this->apaga($this->tmp);
        $this->resetServices();
        parent::tearDown();
    }

    private function limpaCache(): void
    {
        service('cache')->clean();
        (new \ReflectionProperty(\App\Repositories\BaseRepository::class, 'versions'))->setValue(null, []);
    }

    private function apaga(string $dir): void
    {
        foreach (glob(rtrim($dir, '/') . '/{,.}*', GLOB_BRACE) ?: [] as $item) {
            if (in_array(basename($item), ['.', '..'], true)) {
                continue;
            }
            is_dir($item) ? $this->apaga($item) : @unlink($item);
        }
        @rmdir($dir);
    }


    /** O catálogo de módulos não existe no banco de teste: o serviço é substituído. */
    private function moduloSite(bool $ativo): void
    {
        Services::injectMock('modulos', new class ($ativo) extends \App\Services\ModulosService {
            public function __construct(private bool $ativo)
            {
            }

            public function isActive(string $slug): bool
            {
                return $slug !== 'site' || $this->ativo;
            }
        });
    }

    private function get(string $metodo, array $args = [], array $cabecalhos = [], array $query = [])
    {
        $this->response = Services::response(null, false);
        $req = Services::incomingrequest(null, false);
        $req->setGlobal('get', $query);
        foreach ($cabecalhos as $k => $v) {
            $req->setHeader($k, $v);
        }
        return $this->withRequest($req)->controller(Site::class)->execute($metodo, ...$args);
    }

    private function removeDireto(string $slug): void
    {
        // Direto no banco: sem models, sem eventos — só o cache pode responder depois.
        $this->db->table('site_secoes')->where('pagina_id', $this->db->table('site_paginas')->where('slug', $slug)->get()->getRow()->id)->delete();
        $this->db->table('site_paginas')->where('slug', $slug)->delete();
    }

    public function testPaginaEmCacheNaoConsultaOBanco(): void
    {
        $this->assertTrue($this->get('pagina', ['direito-civil'])->isOK());
        $this->removeDireto('direito-civil');

        $r = $this->get('pagina', ['direito-civil']);
        $this->assertTrue($r->isOK(), 'servida do cache, sem a página no banco');
        $this->assertStringContainsString('Direito Civil', $r->getBody());
    }

    public function testAlteracaoPublicadaAparecePorEvento(): void
    {
        $this->get('pagina', ['direito-civil']);
        $id = (int) $this->db->table('site_paginas')->where('slug', 'direito-civil')->get()->getRow()->id;

        (new SitePaginaService())->atualizar($id, ['meta_title' => 'Civil Renovado']);

        $this->assertStringContainsString('Civil Renovado', $this->get('pagina', ['direito-civil'])->getBody());
    }

    public function testLimparDescartaPaginasEmCache(): void
    {
        $this->get('pagina', ['direito-civil']);
        $this->removeDireto('direito-civil');
        SiteCache::limpar();

        $this->assertSame(404, $this->get('pagina', ['direito-civil'])->response()->getStatusCode());
    }

    public function testPreviewIgnoraOCacheENaoOGrava(): void
    {
        $this->db->table('site_paginas')->where('slug', 'direito-civil')->update(['status' => 'rascunho']);
        SiteCache::limpar();
        $id = (int) $this->db->table('site_paginas')->where('slug', 'direito-civil')->get()->getRow()->id;
        $token = (new \App\Libraries\Site\PreviewToken('segredo-de-teste'))->gerar($id);

        config('Encryption')->key = 'segredo-de-teste';
        $r = $this->get('pagina', ['direito-civil'], [], ['preview' => $token]);

        $this->assertSame(200, $r->response()->getStatusCode(), substr((string) $r->response()->getBody(), 0, 300));
        $this->assertSame('no-store', $r->response()->getHeaderLine('Cache-Control'));
        $this->assertNull(SiteCache::pagina('direito-civil'), 'rascunho não vai para o cache');
    }

    public function testEtagE304(): void
    {
        $r = $this->get('pagina', ['direito-civil']);
        $etag = $r->response()->getHeaderLine('ETag');

        $this->assertNotSame('', $etag);
        $this->assertSame('public, max-age=0, must-revalidate', $r->response()->getHeaderLine('Cache-Control'));

        $r2 = $this->get('pagina', ['direito-civil'], ['If-None-Match' => $etag]);
        $this->assertSame(304, $r2->response()->getStatusCode());
        $this->assertSame('', trim((string) $r2->response()->getBody()));
    }

    public function testGravarNoticiaDisparaOEvento(): void
    {
        $disparos = 0;
        \CodeIgniter\Events\Events::on('site.conteudo.alterado', static function () use (&$disparos): void {
            $disparos++;
        });

        $callbacks = (new \ReflectionClass(NewsModel::class))->getDefaultProperties();
        foreach (['afterInsert', 'afterUpdate', 'afterDelete'] as $cb) {
            $this->assertContains('notificaAlteracaoSite', $callbacks[$cb]);
        }
        $m = new NewsModel();
        $ref = new \ReflectionMethod($m, 'notificaAlteracaoSite');
        $ref->setAccessible(true);
        $ref->invoke($m, []);

        $this->assertGreaterThan(0, $disparos);
    }

    public function testModuloSiteDesligadoResponde404NoSitePublico(): void
    {
        $this->assertSame(200, $this->get('pagina', ['direito-civil'])->response()->getStatusCode());

        $this->moduloSite(false);

        try {
            $this->assertSame(404, $this->get('pagina', ['direito-civil'])->response()->getStatusCode(), 'mesmo com a página em cache');
        } finally {
            $this->moduloSite(true);
        }
    }
}
