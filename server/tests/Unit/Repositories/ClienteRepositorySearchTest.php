<?php

namespace Tests\Unit\Repositories;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Repositories\ClienteRepository;

class ClienteRepositorySearchTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    public function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) { $this->db->query('PRAGMA foreign_keys = OFF;'); }

        $this->db->query("DROP TABLE IF EXISTS db_clientes");
        $this->db->query("CREATE TABLE db_clientes (
            id_cliente INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT,
            tipo_cliente TEXT,
            ativo INTEGER,
            documento TEXT,
            email TEXT,
            usuario_id INTEGER,
            data_nascimento TEXT,
            created_at DATETIME
        )");
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->query('PRAGMA foreign_keys = OFF;');
            $this->db->query("DROP TABLE IF EXISTS db_clientes");
        }
        parent::tearDown();
    }

    public function testSearchReturnsCorrectResults()
    {
        // Seed data
        $this->db->query("INSERT INTO db_clientes (nome, documento) VALUES ('Fulano de Tal', '12345678901')");
        $this->db->query("INSERT INTO db_clientes (nome, documento) VALUES ('Beltrano da Silva', '98765432100')");
        $this->db->query("INSERT INTO db_clientes (nome, documento) VALUES ('Cicrano de Souza', '11122233344')");

        $repository = new ClienteRepository();

        // Test search by name
        $results = $repository->search('Fulano');
        $this->assertCount(1, $results);
        $this->assertEquals('Fulano de Tal', $results[0]['nome']);

        // Test search by document
        $results = $repository->search('98765');
        $this->assertCount(1, $results);
        $this->assertEquals('Beltrano da Silva', $results[0]['nome']);

        // Test search with multiple results
        $results = $repository->search('de');
        $this->assertCount(2, $results); // 'Fulano de Tal' and 'Cicrano de Souza'
    }

    public function testSearchRespectsLimit()
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->db->query("INSERT INTO db_clientes (nome, documento) VALUES ('Cliente $i', '$i')");
        }

        $repository = new ClienteRepository();
        $results = $repository->search('Cliente', 5);
        $this->assertCount(5, $results);
    }
}
