<?php

namespace Tests\Unit\Repositories;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Repositories\CasosRepository;

class CasosRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    public function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) { $this->db->query('PRAGMA foreign_keys = OFF;'); }

        $this->db->query("DROP TABLE IF EXISTS db_casos");

        $this->db->query("CREATE TABLE db_casos (
            id_caso INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo TEXT,
            tipo TEXT,
            status TEXT,
            cliente_id INTEGER,
            deleted_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function testObterEstatisticasCorrectness()
    {
        // Seed data
        // 1. Ativo + Judicial
        $this->db->query("INSERT INTO db_casos (titulo, tipo, status) VALUES ('Caso 1', 'Judicial', 'Ativo')");
        // 2. Em Andamento + Judicial
        $this->db->query("INSERT INTO db_casos (titulo, tipo, status) VALUES ('Caso 2', 'Judicial', 'Em Andamento')");
        // 3. Encerrado + Extrajudicial
        $this->db->query("INSERT INTO db_casos (titulo, tipo, status) VALUES ('Caso 3', 'Extrajudicial', 'Encerrado')");
        // 4. Suspenso + Judicial
        $this->db->query("INSERT INTO db_casos (titulo, tipo, status) VALUES ('Caso 4', 'Judicial', 'Suspenso')");

        $repository = new CasosRepository();
        $stats = $repository->obterEstatisticas();

        // Expected:
        // total: 4
        // ativos (Ativo ou Em Andamento): 2
        // judiciais: 3
        // encerrados: 1

        $this->assertEquals(4, $stats['total'], "Total de casos incorreto");
        $this->assertEquals(2, $stats['ativos'], "Total de casos ativos incorreto");
        $this->assertEquals(3, $stats['judiciais'], "Total de casos judiciais incorreto");
        $this->assertEquals(1, $stats['encerrados'], "Total de casos encerrados incorreto");
    }

    public function testObterEstatisticasCumulativeBug()
    {
        // Seed data that would fail if filters are cumulative
        // If they are cumulative:
        // Query 1: total = 2
        // Query 2: where status in (Ativo, Em Andamento) -> Caso 1 -> count = 1
        // Query 3: AND tipo = 'Judicial' -> Caso 1 -> count = 1
        // Query 4: AND status = 'Encerrado' -> NONE -> count = 0 (Should be 1 if independent)

        $this->db->query("INSERT INTO db_casos (titulo, tipo, status) VALUES ('Caso Ativo Judicial', 'Judicial', 'Ativo')");
        $this->db->query("INSERT INTO db_casos (titulo, tipo, status) VALUES ('Caso Encerrado Extra', 'Extrajudicial', 'Encerrado')");

        $repository = new CasosRepository();
        $stats = $repository->obterEstatisticas();

        $this->assertEquals(2, $stats['total'], "Total incorreto");
        $this->assertEquals(1, $stats['ativos'], "Ativos incorreto");
        $this->assertEquals(1, $stats['judiciais'], "Judiciais incorreto");

        // This is where it's likely to fail if it's cumulative
        $this->assertEquals(1, $stats['encerrados'], "Encerrados incorreto - Possível bug de filtros cumulativos detectado");
    }

    public function testObterEstatisticasExcludesSoftDeleted()
    {
        // 1. Ativo (should be counted)
        $this->db->query("INSERT INTO db_casos (titulo, tipo, status, deleted_at) VALUES ('Ativo', 'Judicial', 'Ativo', NULL)");
        // 2. Encerrado (should be counted)
        $this->db->query("INSERT INTO db_casos (titulo, tipo, status, deleted_at) VALUES ('Encerrado', 'Extrajudicial', 'Encerrado', NULL)");
        // 3. Soft Deleted Ativo (should NOT be counted)
        $this->db->query("INSERT INTO db_casos (titulo, tipo, status, deleted_at) VALUES ('Deletado', 'Judicial', 'Ativo', '2025-01-01 10:00:00')");

        $repository = new CasosRepository();
        $stats = $repository->obterEstatisticas();

        $this->assertEquals(2, $stats['total'], "Total deve excluir deletados");
        $this->assertEquals(1, $stats['ativos'], "Ativos deve excluir deletados");
        $this->assertEquals(1, $stats['judiciais'], "Judiciais deve excluir deletados");
        $this->assertEquals(1, $stats['encerrados'], "Encerrados deve excluir deletados");
    }
}
