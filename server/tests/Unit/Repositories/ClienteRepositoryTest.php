<?php

namespace Tests\Unit\Repositories;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Repositories\ClienteRepository;

class ClienteRepositoryTest extends CIUnitTestCase
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

    public function testBuscarEstatisticasReturnsCorrectTotals()
    {
        // Seed data
        // 1 Active Person
        $this->db->query("INSERT INTO db_clientes (nome, tipo_cliente, ativo) VALUES ('Cliente A', 'F', 1)");
        // 1 Inactive Person
        $this->db->query("INSERT INTO db_clientes (nome, tipo_cliente, ativo) VALUES ('Cliente B', 'F', 0)");
        // 1 Active Entity
        $this->db->query("INSERT INTO db_clientes (nome, tipo_cliente, ativo) VALUES ('Cliente C', 'J', 1)");
        // 1 Inactive Entity
        $this->db->query("INSERT INTO db_clientes (nome, tipo_cliente, ativo) VALUES ('Cliente D', 'J', 0)");

        $repository = new ClienteRepository();
        $stats = $repository->buscarEstatisticas();

        $this->assertEquals(4, $stats['total'], 'Total count should be 4');
        $this->assertEquals(2, $stats['ativos'], 'Active count should be 2');
        $this->assertEquals(2, $stats['pessoa_fisica'], 'Person (F) count should be 2');
        $this->assertEquals(2, $stats['pessoa_juridica'], 'Entity (J) count should be 2');
    }

    public function testBuscarEstatisticasHandlesEmptyTable()
    {
        $repository = new ClienteRepository();
        $stats = $repository->buscarEstatisticas();

        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['ativos']);
        $this->assertEquals(0, $stats['pessoa_fisica']);
        $this->assertEquals(0, $stats['pessoa_juridica']);
    }
}
