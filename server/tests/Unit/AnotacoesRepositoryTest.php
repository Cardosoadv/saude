<?php

namespace Tests\Unit;

use App\Repositories\AnotacoesRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class AnotacoesRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = false;
    protected $migrateOnce = false;
    protected $refresh     = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) { $this->db->query('PRAGMA foreign_keys = OFF;'); }

        $this->db->query("DROP TABLE IF EXISTS db_entidades_anotacoes");

        // Create table manually for SQLite in-memory testing
        $this->db->query("CREATE TABLE IF NOT EXISTS db_entidades_anotacoes (
            id_anotacao INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            documento VARCHAR(20),
            titulo VARCHAR(255),
            anotacao TEXT,
            privacidade INTEGER,
            created_at DATETIME,
            updated_at DATETIME,
            deleted_at DATETIME DEFAULT NULL
        )");

        // Disable callbacks to avoid AuditoriaTrait issues
        model('EntidadesAnotacoesModel')->allowCallbacks(false);
    }

    public function testCreateAndFindById()
    {
        $repository = new AnotacoesRepository();
        
        $data = [
            'user_id' => 1,
            'documento' => '12345678900',
            'titulo' => 'Anotação de Teste',
            'anotacao' => 'Conteúdo da anotação',
            'privacidade' => 1
        ];

        $id = $repository->create($data);
        $this->assertIsInt($id);

        $anotacao = $repository->findById($id);
        $this->assertNotNull($anotacao);
        $this->assertEquals('Anotação de Teste', $anotacao['titulo']);
    }

    public function testGetByDocumento()
    {
        $repository = new AnotacoesRepository();
        
        $this->db->table('entidades_anotacoes')->insertBatch([
            [
                'user_id' => 1,
                'documento' => '111',
                'titulo' => 'Privada User 1',
                'privacidade' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'user_id' => 1,
                'documento' => '111',
                'titulo' => 'Publica',
                'privacidade' => 2,
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'user_id' => 2,
                'documento' => '111',
                'titulo' => 'Privada User 2',
                'privacidade' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);

        // User 1 should see their private + all public
        $results = $repository->getByDocumento(1, '111');
        $this->assertCount(2, $results);
        
        $titulos = array_column($results, 'titulo');
        $this->assertContains('Privada User 1', $titulos);
        $this->assertContains('Publica', $titulos);
        $this->assertNotContains('Privada User 2', $titulos);
    }

    public function testDelete()
    {
        $repository = new AnotacoesRepository();
        
        $id = $this->db->table('entidades_anotacoes')->insert([
            'user_id' => 1,
            'documento' => '222',
            'titulo' => 'Para Deletar'
        ]);

        $this->assertTrue($repository->delete($id));
        $this->assertNull($repository->findById($id));
    }
}
