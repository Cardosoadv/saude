<?php

namespace Tests\Unit\Repositories;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Repositories\Relatorios\LegalOpsRepository;

class LegalOpsRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    public function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) { $this->db->query('PRAGMA foreign_keys = OFF;'); }

        $this->db->query("DROP TABLE IF EXISTS db_processos");

        $this->db->query("CREATE TABLE db_processos (
            id_processo INTEGER PRIMARY KEY AUTOINCREMENT,
            nomeOrgao TEXT,
            resultado TEXT,
            risco TEXT,
            valorCausa DECIMAL(15,2),
            valorCondenacao DECIMAL(15,2),
            dataDistribuicao DATE,
            data_encerramento DATE,
            encerrado INTEGER DEFAULT 0,
            cliente_id INTEGER,
            deleted_at DATETIME
        )");
    }

    public function testGetPerformancePorOrgao()
    {
        // Seed data
        $this->db->query("INSERT INTO db_processos (nomeOrgao, resultado) VALUES ('1ª Vara Cível', 'Sucesso')");
        $this->db->query("INSERT INTO db_processos (nomeOrgao, resultado) VALUES ('1ª Vara Cível', 'Sucesso Parcial')");
        $this->db->query("INSERT INTO db_processos (nomeOrgao, resultado) VALUES ('1ª Vara Cível', 'Derrota')");
        $this->db->query("INSERT INTO db_processos (nomeOrgao, resultado) VALUES ('2ª Vara Cível', 'Derrota')");
        $this->db->query("INSERT INTO db_processos (nomeOrgao, resultado) VALUES ('2ª Vara Cível', 'Não Finalizado')"); // Should be ignored

        $repository = new LegalOpsRepository();
        $result = $repository->getPerformancePorOrgao();

        $this->assertCount(2, $result);

        // 1ª Vara Cível should have 2 successes and 1 defeat
        $this->assertEquals('1ª Vara Cível', $result[0]['label']);
        $this->assertEquals(2, $result[0]['sucessos']);
        $this->assertEquals(1, $result[0]['derrotas']);

        // 2ª Vara Cível should have 0 successes and 1 defeat
        $this->assertEquals('2ª Vara Cível', $result[1]['label']);
        $this->assertEquals(0, $result[1]['sucessos']);
        $this->assertEquals(1, $result[1]['derrotas']);
    }

    public function testGetDistribuicaoRisco()
    {
        $this->db->query("INSERT INTO db_processos (risco) VALUES ('Provável')");
        $this->db->query("INSERT INTO db_processos (risco) VALUES ('Provável')");
        $this->db->query("INSERT INTO db_processos (risco) VALUES ('Possível')");

        $repository = new LegalOpsRepository();
        $result = $repository->getDistribuicaoRisco();

        $this->assertCount(2, $result);

        $provavel = array_filter($result, fn($r) => $r['labels'] === 'Provável');
        $possivel = array_filter($result, fn($r) => $r['labels'] === 'Possível');

        $this->assertEquals(2, reset($provavel)['values']);
        $this->assertEquals(1, reset($possivel)['values']);
    }

    public function testGetDistribuicaoRiscoComFiltroCliente()
    {
        $this->db->query("INSERT INTO db_processos (risco, cliente_id) VALUES ('Provável', 1)");
        $this->db->query("INSERT INTO db_processos (risco, cliente_id) VALUES ('Provável', 2)");
        $this->db->query("INSERT INTO db_processos (risco, cliente_id) VALUES ('Possível', 1)");

        $repository = new LegalOpsRepository();

        // Test for client 1
        $result1 = $repository->getDistribuicaoRisco(1);
        $this->assertCount(2, $result1);

        $provavel1 = array_filter($result1, fn($r) => $r['labels'] === 'Provável');
        $this->assertEquals(1, reset($provavel1)['values']);

        // Test for client 2
        $result2 = $repository->getDistribuicaoRisco(2);
        $this->assertCount(1, $result2);
        $this->assertEquals('Provável', $result2[0]['labels']);
        $this->assertEquals(1, $result2[0]['values']);
    }
}
