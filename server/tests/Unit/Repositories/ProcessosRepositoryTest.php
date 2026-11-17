<?php

namespace Tests\Unit\Repositories;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Repositories\ProcessosRepository;

class ProcessosRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;
    protected $basePath = 'app/Database';
    protected $namespace = 'App';

    public function setUp(): void
    {
        parent::setUp();

        // Force logout to prevent foreign key errors from leftover auth sessions in other tests
        if (function_exists('auth')) {
            auth()->logout();
        }

        // Ensure user 1 exists for AuditoriaTrait foreign key checks
        $this->db->table('users')->ignore(true)->insert([
            'id' => 1,
            'username' => 'system',
            'active' => 1
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testBuscarTodosProcessosReturnsCorrectStructure()
    {
        // Seed data
        $this->db->query("INSERT INTO db_processos (id_processo, numero_processo, titulo_processo, cliente_id) VALUES (1, '12345', 'Teste', 1)");
        $this->db->query("INSERT INTO db_clientes (id_cliente, nome) VALUES (1, 'Cliente Teste')");
        $this->db->query("INSERT INTO db_processos_partes (id_parte, nome) VALUES (1, 'Parte A'), (2, 'Parte P')");
        $this->db->query("INSERT INTO db_processos_partes_dos_processos (id_parte, id_processo, polo) VALUES (1, 1, 'A'), (2, 1, 'P')");

        $repository = new \App\Repositories\ProcessSyncRepository();
        $processos = $repository->buscarTodosProcessos();

        $this->assertCount(1, $processos);
        $this->assertEquals(1, $processos[0]['id_processo']);
        $this->assertCount(1, $processos[0]['poloAtivo']);
        $this->assertCount(1, $processos[0]['poloPassivo']);
        $this->assertEquals('Parte A', $processos[0]['poloAtivo'][0]['nome']);
        $this->assertEquals('Parte P', $processos[0]['poloPassivo'][0]['nome']);
    }

    public function testBuscarTodosProcessosHandlesEmpty()
    {
        $repository = new \App\Repositories\ProcessSyncRepository();
        $processos = $repository->buscarTodosProcessos();
        $this->assertEmpty($processos);
    }

    public function testGetProcessosAgrupadosPorMes()
    {
        // Seed data with different dates
        $this->db->query("INSERT INTO db_processos (numero_processo, created_at) VALUES ('1', '2025-01-15 10:00:00')");
        $this->db->query("INSERT INTO db_processos (numero_processo, created_at) VALUES ('2', '2025-01-20 12:00:00')");
        $this->db->query("INSERT INTO db_processos (numero_processo, created_at) VALUES ('3', '2025-02-05 09:00:00')");

        $repository = new \App\Repositories\ProcessStatisticsRepository();
        $result = $repository->getProcessosAgrupadosPorMes('created_at', '2025-01-01', '2025-12-31');

        $this->assertCount(2, $result);

        // Month 01/2025
        $this->assertEquals('01/2025', $result[0]['mes']);
        $this->assertEquals(2, $result[0]['processos']);

        // Month 02/2025
        $this->assertEquals('02/2025', $result[1]['mes']);
        $this->assertEquals(1, $result[1]['processos']);
    }

    public function testSalvarPartesBatch()
    {
        $this->db->transOff();
        $repository = new \App\Repositories\ProcessPartiesRepository();

        // 1. Setup - Insert a process
        $this->db->query("INSERT INTO db_processos (id_processo, numero_processo) VALUES (10, '12345678901234567890')");

        // 2. Initial state - No parts
        $partes = $this->db->table('processos_partes_dos_processos')->where('id_processo', 10)->get()->getResultArray();
        $this->assertCount(0, $partes);

        // 3. Batch save parts
        $poloAtivo = [['nome' => 'Autor 1'], ['nome' => 'Autor 2']];
        $poloPassivo = [['nome' => 'Reu 1']];
        $repository->salvarPartesBatch(10, $poloAtivo, $poloPassivo);

        // 4. Verify in pivot table
        $partes = $this->db->table('processos_partes_dos_processos')->where('id_processo', 10)->get()->getResultArray();
        $this->assertCount(3, $partes);

        // Verify polo counts
        $ativos = array_filter($partes, fn($p) => $p['polo'] === 'A');
        $passivos = array_filter($partes, fn($p) => $p['polo'] === 'P');
        $this->assertCount(2, $ativos);
        $this->assertCount(1, $passivos);

        // 5. Verify names in db_processos_partes
        $nomesSalvos = $this->db->table('processos_partes')->get()->getResultArray();
        $nomes = array_column($nomesSalvos, 'nome');
        $this->assertContains('Autor 1', $nomes);
        $this->assertContains('Autor 2', $nomes);
        $this->assertContains('Reu 1', $nomes);

        // 6. Test updating parts (should replace old ones)
        $novoPoloAtivo = [['nome' => 'Autor 3']];
        $novoPoloPassivo = [['nome' => 'Reu 1']]; // Existing part
        $repository->salvarPartesBatch(10, $novoPoloAtivo, $novoPoloPassivo);

        $partesFinal = $this->db->table('processos_partes_dos_processos')->where('id_processo', 10)->get()->getResultArray();
        $this->assertCount(2, $partesFinal);

        $ativosFinal = array_filter($partesFinal, fn($p) => $p['polo'] === 'A');
        $this->assertCount(1, $ativosFinal);
        $this->assertEquals('Autor 3', $this->db->table('processos_partes')->where('id_parte', $ativosFinal[array_key_first($ativosFinal)]['id_parte'])->get()->getRowArray()['nome']);
    }

    public function testGetNavigationIds()
    {
        $repository = new ProcessosRepository();

        // Seed data
        $this->db->query("INSERT INTO db_processos (id_processo, numero_processo) VALUES (1, 'N1'), (2, 'N2'), (3, 'N3')");

        // Test middle process
        $nav = $repository->getNavigationIds(2);
        $this->assertEquals(3, $nav['prev']); // ID > 2 ASC
        $this->assertEquals(1, $nav['next']); // ID < 2 DESC

        // Test first process (ID 3, since it's ordered ID DESC in list usually)
        $navFirst = $repository->getNavigationIds(3);
        $this->assertNull($navFirst['prev']);
        $this->assertEquals(2, $navFirst['next']);

        // Test last process
        $navLast = $repository->getNavigationIds(1);
        $this->assertEquals(2, $navLast['prev']);
        $this->assertNull($navLast['next']);
    }

    public function testGetNavigationIdsWithClienteFilter()
    {
        $repository = new ProcessosRepository();

        // Seed data
        $this->db->query("INSERT INTO db_processos (id_processo, numero_processo, cliente_id) VALUES (1, 'N1', 1), (2, 'N2', 2), (3, 'N3', 1)");

        // Navigation for client 1
        $nav = $repository->getNavigationIds(1, 1);
        $this->assertEquals(3, $nav['prev']);
        $this->assertNull($nav['next']);

        $navMid = $repository->getNavigationIds(2, 2);
        $this->assertNull($navMid['prev']);
        $this->assertNull($navMid['next']);
    }
}
