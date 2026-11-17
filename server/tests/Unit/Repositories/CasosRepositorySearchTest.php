<?php

namespace Tests\Unit\Repositories;

use App\Repositories\CasosRepository;
use App\Models\CasosModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class CasosRepositorySearchTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Limpa tabelas antes de cada teste
        $this->db->query("DROP TABLE IF EXISTS db_casos");
        $this->db->query("DROP TABLE IF EXISTS db_clientes");

        // Create the necessary tables for the test in SQLite with prefix db_
        $this->db->query("CREATE TABLE db_casos (
            id_caso INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo TEXT,
            cliente_id INTEGER,
            tipo TEXT,
            descricao TEXT,
            status TEXT,
            created_at DATETIME,
            updated_at DATETIME,
            deleted_at DATETIME,
            processo_id INTEGER
        )");

        $this->db->query("CREATE TABLE db_clientes (
            id_cliente INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT
        )");

        // Insert some dummy data
        $this->db->table('clientes')->insert(['nome' => 'Cliente Teste']);
        $this->db->table('casos')->insert([
            'titulo' => 'Caso de Teste 1',
            'cliente_id' => 1,
            'tipo' => 'Judicial',
            'status' => 'Ativo',
            'descricao' => 'Descrição do caso 1'
        ]);
        $this->db->table('casos')->insert([
            'titulo' => 'Outro Caso',
            'cliente_id' => 1,
            'tipo' => 'Extrajudicial',
            'status' => 'Em Andamento',
            'descricao' => 'Descrição do outro caso'
        ]);

        CasosRepository::clearVersions();
        cache()->clean();
    }

    public function testSearch()
    {
        $repository = new CasosRepository();
        $results = $repository->search('Teste');

        $this->assertCount(1, $results);
        $this->assertEquals('Caso de Teste 1', $results[0]['titulo']);
    }

    public function testSearchByDescription()
    {
        $repository = new CasosRepository();
        $results = $repository->search('outro');

        $this->assertCount(1, $results);
        $this->assertEquals('Outro Caso', $results[0]['titulo']);
    }

    public function testSearchWithClienteId()
    {
        $repository = new CasosRepository();
        $results = $repository->search('Caso', 15, 1);

        $this->assertCount(2, $results);
    }
}
