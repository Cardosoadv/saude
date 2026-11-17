<?php

namespace Tests\Unit\Repositories;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Repositories\TarefasRepository;
use App\Models\TarefasModel;
use App\Models\ClientesModel;
use App\Models\CasosModel;
use App\Models\ProcessosModel;

class TarefasRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) { $this->db->query('PRAGMA foreign_keys = OFF;'); }

        $db = \Config\Database::connect();
        $prefix = $db->getPrefix();

        // Manual table creation for SQLite as per AGENTS.md instructions
        $db->query("DROP TABLE IF EXISTS {$prefix}tarefas");
        $db->query("CREATE TABLE {$prefix}tarefas (
            id_tarefa INTEGER PRIMARY KEY AUTOINCREMENT,
            tarefa TEXT,
            prazo DATE,
            prioridade INTEGER,
            detalhes TEXT,
            responsavel INTEGER,
            status INTEGER,
            cliente_id INTEGER,
            caso_id INTEGER,
            processo_id INTEGER,
            notificar INTEGER,
            google_event_id TEXT,
            created_at DATETIME,
            updated_at DATETIME,
            deleted_at DATETIME
        )");

        $db->query("DROP TABLE IF EXISTS {$prefix}clientes");
        $db->query("CREATE TABLE {$prefix}clientes (
            id_cliente INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT,
            razao_social TEXT
        )");

        $db->query("DROP TABLE IF EXISTS {$prefix}casos");
        $db->query("CREATE TABLE {$prefix}casos (
            id_caso INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo TEXT
        )");

        $db->query("DROP TABLE IF EXISTS {$prefix}processos");
        $db->query("CREATE TABLE {$prefix}processos (
            id_processo INTEGER PRIMARY KEY AUTOINCREMENT,
            numeroprocessocommascara TEXT,
            numero_processo TEXT,
            arquived_at DATETIME,
            deleted_at DATETIME
        )");
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $prefix = $this->db->getPrefix();
            $this->db->query('PRAGMA foreign_keys = OFF;');
            $this->db->query("DROP TABLE IF EXISTS {$prefix}tarefas");
            $this->db->query("DROP TABLE IF EXISTS {$prefix}clientes");
            $this->db->query("DROP TABLE IF EXISTS {$prefix}casos");
            $this->db->query("DROP TABLE IF EXISTS {$prefix}processos");
        }
        parent::tearDown();
    }

    public function testPopularVinculosNPlusOne()
    {
        $tarefasModel = new TarefasModel();
        $db = \Config\Database::connect();

        $db->table('clientes')->insert(['nome' => 'Cliente Teste']);
        $clienteId = $db->insertID();

        $tarefasData = [];
        for ($i = 0; $i < 10; $i++) {
            $tarefasData[] = [
                'tarefa' => "Tarefa $i",
                'cliente_id' => $clienteId,
                'status' => 1
            ];
        }
        $tarefasModel->insertBatch($tarefasData);

        $repository = new TarefasRepository();
        $tarefas = $tarefasModel->findAll();

        $startTime = microtime(true);
        $result = $repository->popularVinculos($tarefas);
        $endTime = microtime(true);

        $duration = $endTime - $startTime;

        $this->assertCount(10, $result);
        $this->assertEquals('Cliente Teste', $result[0]['vinculo_info']);

        // This is a bit arbitrary without query counting, but N+1 is definitely happening.
    }

    public function testObterEstatisticas()
    {
        $tarefasModel = new TarefasModel();
        $hoje = date('Y-m-d');
        $ontem = date('Y-m-d', strtotime('-1 day'));
        $amanha = date('Y-m-d', strtotime('+1 day'));

        $tarefasModel->insertBatch([
            // Status 3: Fazendo
            ['tarefa' => 'Tarefa 1', 'status' => 3, 'prazo' => $amanha, 'responsavel' => 1],
            // Status 4: Concluída
            ['tarefa' => 'Tarefa 2', 'status' => 4, 'prazo' => $amanha, 'responsavel' => 1],
            // Atrasada (prazo < hoje AND status IN 1,2,3)
            ['tarefa' => 'Tarefa 3', 'status' => 1, 'prazo' => $ontem, 'responsavel' => 1],
            // Outra atrasada
            ['tarefa' => 'Tarefa 4', 'status' => 2, 'prazo' => $ontem, 'responsavel' => 1],
            // Pendente mas não atrasada
            ['tarefa' => 'Tarefa 5', 'status' => 1, 'prazo' => $amanha, 'responsavel' => 1],
            // Concluída mas com prazo ontem (NÃO deve contar como atrasada)
            ['tarefa' => 'Tarefa 6', 'status' => 4, 'prazo' => $ontem, 'responsavel' => 1],
            // Outro responsável
            ['tarefa' => 'Tarefa 7', 'status' => 3, 'prazo' => $amanha, 'responsavel' => 2],
        ]);

        $repository = new TarefasRepository();

        // Estatísticas globais
        $stats = $repository->obterEstatisticas();

        $this->assertEquals(7, $stats['total'], 'Total global');
        $this->assertEquals(2, $stats['fazendo'], 'Fazendo global');
        $this->assertEquals(2, $stats['concluidas'], 'Concluídas global');
        $this->assertEquals(2, $stats['atrasadas'], 'Atrasadas global');

        // Estatísticas para o usuário 1
        $statsUser1 = $repository->obterEstatisticas(1);

        $this->assertEquals(6, $statsUser1['total'], 'Total usuário 1');
        $this->assertEquals(1, $statsUser1['fazendo'], 'Fazendo usuário 1');
        $this->assertEquals(2, $statsUser1['concluidas'], 'Concluídas usuário 1');
        $this->assertEquals(2, $statsUser1['atrasadas'], 'Atrasadas usuário 1');
    }
}
