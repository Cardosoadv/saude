<?php

namespace Tests\Unit\Services\Site;

use App\Controllers\ApiV2\Site\admin\NoticiaCategorias;
use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Libraries\Site\Exceptions\NaoEncontrado;
use App\Services\Site\NoticiaCategoriaService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * CRUD das categorias de notícias do site (API administrativa).
 */
class NoticiaCategoriaServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use ControllerTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private const TABELAS = ['site_news', 'site_news_categories', 'auditoria', 'settings'];

    protected function setUp(): void
    {
        parent::setUp();
        service('cache')->clean();
        (new \ReflectionProperty(\App\Repositories\BaseRepository::class, 'versions'))->setValue(null, []);

        $forge = Database::forge($this->db);
        foreach (self::TABELAS as $t) {
            $forge->dropTable($t, true);
        }

        $forge->addField([
            'id'               => ['type' => 'INTEGER', 'auto_increment' => true],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'slug'             => ['type' => 'VARCHAR', 'constraint' => 120],
            'meta_description' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->addUniqueKey('slug');
        $forge->createTable('site_news_categories');

        $forge->addField([
            'id'          => ['type' => 'INTEGER', 'auto_increment' => true],
            'category_id' => ['type' => 'INTEGER'],
            'title'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('site_news');

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
    }

    protected function tearDown(): void
    {
        $forge = Database::forge($this->db);
        foreach (self::TABELAS as $t) {
            $forge->dropTable($t, true);
        }
        parent::tearDown();
    }

    private function servico(): NoticiaCategoriaService
    {
        return new NoticiaCategoriaService();
    }

    /** @return array<string,string> */
    private function erros(callable $acao): array
    {
        try {
            $acao();
        } catch (ErroDeValidacao $e) {
            return $e->erros();
        }
        $this->fail('Esperava ErroDeValidacao.');
    }

    private function noticia(int $categoria, bool $excluida = false): void
    {
        $this->db->table('site_news')->insert([
            'category_id' => $categoria,
            'title'       => 'Notícia',
            'deleted_at'  => $excluida ? date('Y-m-d H:i:s') : null,
        ]);
    }

    public function testCriarGeraSlugDoNomeSemAcentoEUnico(): void
    {
        $a = $this->servico()->criar(['name' => '  Clínica Geral ', 'meta_description' => '']);
        $b = $this->servico()->criar(['name' => 'Clinica geral!']);

        $this->assertSame('Clínica Geral', $a['name']);
        $this->assertSame('clinica-geral', $a['slug']);
        $this->assertNull($a['meta_description']);
        $this->assertSame(0, $a['total_noticias']);
        $this->assertSame('clinica-geral-2', $b['slug']);
    }

    public function testCriarRecusaDadosInvalidos(): void
    {
        $this->servico()->criar(['name' => 'Civil', 'slug' => 'civil']);

        $erros = $this->erros(fn () => $this->servico()->criar([
            'name' => 'civil', 'slug' => 'Slug Ruim', 'meta_description' => str_repeat('x', 161),
        ]));
        $this->assertSame(['name', 'slug', 'meta_description'], array_keys($erros));

        $this->assertArrayHasKey('name', $this->erros(fn () => $this->servico()->criar(['name' => ''])));
        $this->assertArrayHasKey('name', $this->erros(fn () => $this->servico()->criar(['name' => str_repeat('a', 101)])));
        $this->assertArrayHasKey('slug', $this->erros(fn () => $this->servico()->criar(['name' => 'Outra', 'slug' => 'civil'])));
    }

    public function testAtualizarParcialMantemOMesmoNomeESlug(): void
    {
        $c = $this->servico()->criar(['name' => 'Trabalhista', 'meta_description' => 'Antiga']);

        $r = $this->servico()->atualizar($c['id'], ['name' => 'Trabalhista', 'meta_description' => 'Nova']);
        $this->assertSame('Nova', $r['meta_description']);
        $this->assertSame('trabalhista', $r['slug']);

        $r = $this->servico()->atualizar($c['id'], ['slug' => 'saude-ocupacional']);
        $this->assertSame('saude-ocupacional', $r['slug']);
        $this->assertSame('Trabalhista', $r['name']);

        $r = $this->servico()->atualizar($c['id'], ['name' => 'Saúde Ocupacional', 'slug' => '']);
        $this->assertSame('saude-ocupacional', $r['slug'], 'slug vazio regenera do nome, sem colidir consigo mesma');

        $outra = $this->servico()->criar(['name' => 'Tributário']);
        $this->assertArrayHasKey('name', $this->erros(fn () => $this->servico()->atualizar($outra['id'], ['name' => 'saúde ocupacional'])));
        $this->assertArrayHasKey('slug', $this->erros(fn () => $this->servico()->atualizar($outra['id'], ['slug' => 'saude-ocupacional'])));
    }

    public function testListarOrdenaPorNomeContaNoticiasEFiltra(): void
    {
        $civil = $this->servico()->criar(['name' => 'Civil']);
        $this->servico()->criar(['name' => 'Ambiental']);
        $this->noticia($civil['id']);
        $this->noticia($civil['id']);
        $this->noticia($civil['id'], excluida: true);

        $lista = $this->servico()->listar();
        $this->assertSame(['Ambiental', 'Civil'], array_column($lista, 'name'));
        $this->assertSame([0, 2], array_column($lista, 'total_noticias'));

        $this->assertSame(['Civil'], array_column($this->servico()->listar('civ'), 'name'));
        $this->assertSame(2, $this->servico()->obter($civil['id'])['total_noticias']);
    }

    public function testExcluirRecusaCategoriaComNoticias(): void
    {
        $c = $this->servico()->criar(['name' => 'Civil']);
        $this->noticia($c['id']);

        $erros = $this->erros(fn () => $this->servico()->excluir($c['id']));
        $this->assertStringContainsString('1 notícia', $erros['categoria']);
        $this->assertSame(1, $this->db->table('site_news_categories')->countAllResults());
    }

    public function testExcluirSemReferenciaApagaDeVezELiberaOSlug(): void
    {
        $c = $this->servico()->criar(['name' => 'Civil']);
        $this->servico()->excluir($c['id']);

        $this->assertSame(0, $this->db->table('site_news_categories')->countAllResults());
        $this->assertSame('civil', $this->servico()->criar(['name' => 'Civil'])['slug']);
    }

    public function testExcluirComNoticiaNaLixeiraRenomeiaOSlug(): void
    {
        $c = $this->servico()->criar(['name' => 'Civil']);
        $this->noticia($c['id'], excluida: true);
        $this->servico()->excluir($c['id']);

        $linha = $this->db->table('site_news_categories')->where('id', $c['id'])->get()->getRowArray();
        $this->assertNotNull($linha['deleted_at']);
        $this->assertSame('civil-excluida-' . $c['id'], $linha['slug']);
        $this->assertSame([], $this->servico()->listar());
        $this->assertSame('civil', $this->servico()->criar(['name' => 'Civil'])['slug']);

        $this->expectException(NaoEncontrado::class);
        $this->servico()->obter($c['id']);
    }

    public function testEscritasGeramAuditoria(): void
    {
        $c = $this->servico()->criar(['name' => 'Civil']);
        $this->servico()->atualizar($c['id'], ['name' => 'Cível']);
        $this->servico()->excluir($c['id']);

        $acoes = array_column($this->db->table('auditoria')->where('table_name', 'site_news_categories')->get()->getResultArray(), 'action_type');
        $this->assertSame(['CREATE', 'UPDATE', 'DELETE'], $acoes);
    }

    // ============================================================ API

    private function json(string $metodo, ?array $corpo = null, array $params = []): \CodeIgniter\Test\TestResponse
    {
        $this->resetServices();

        return $this->withBody($corpo === null ? '' : json_encode($corpo), 'json')
            ->controller(NoticiaCategorias::class)
            ->execute($metodo, ...$params);
    }

    private function corpo(\CodeIgniter\Test\TestResponse $r): array
    {
        return json_decode($r->getJSON(), true) ?? [];
    }

    public function testCategoriasPelaApi(): void
    {
        $r = $this->json('store', ['name' => '']);
        $this->assertSame(422, $r->response()->getStatusCode());
        $this->assertArrayHasKey('name', $this->corpo($r)['messages']);

        $r = $this->json('store', ['name' => 'Família']);
        $this->assertSame(201, $r->response()->getStatusCode());
        $id = $this->corpo($r)['data']['id'];
        $this->assertSame('familia', $this->corpo($r)['data']['slug']);

        $r = $this->json('index');
        $this->assertSame(['Família'], array_column($this->corpo($r)['data'], 'name'));

        $this->assertSame(200, $this->json('show', null, [$id])->response()->getStatusCode());
        $this->assertSame(404, $this->json('show', null, [99999])->response()->getStatusCode());

        $r = $this->json('update', ['meta_description' => 'Saúde da família.'], [$id]);
        $this->assertSame('Saúde da família.', $this->corpo($r)['data']['meta_description']);

        $this->noticia($id);
        $this->assertSame(422, $this->json('destroy', null, [$id])->response()->getStatusCode());
        $this->db->table('site_news')->truncate();
        $this->assertSame(200, $this->json('destroy', null, [$id])->response()->getStatusCode());
        $this->assertSame(404, $this->json('destroy', null, [$id])->response()->getStatusCode());
    }
}
