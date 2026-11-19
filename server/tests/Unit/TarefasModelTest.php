<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Models\TarefasModel;

class TarefasModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = false;
    protected $basePath = 'app/Database';
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) { $this->db->query('PRAGMA foreign_keys = OFF;'); }

        $db = \Config\Database::connect();
        $prefix = $db->getPrefix();

        // Create missing tables for auditoria and settings
        $db->query("CREATE TABLE IF NOT EXISTS {$prefix}auditoria (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            table_name TEXT,
            action_type TEXT,
            dados_antigos TEXT,
            dados_novos TEXT,
            ip_address TEXT,
            created_at DATETIME,
            updated_at DATETIME
        )");

        $db->query("CREATE TABLE IF NOT EXISTS {$prefix}settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            class TEXT,
            key TEXT,
            value TEXT,
            type TEXT,
            context TEXT,
            created_at DATETIME,
            updated_at DATETIME
        )");
    }

    public function testCanCreateTaskLinkedToClient()
    {
        $model = new TarefasModel();

        $data = [
            'tarefa'      => 'Test Task Client',
            'prazo'       => date('Y-m-d'),
            'prioridade'  => 1,
            'detalhes'    => 'Details',
            'responsavel' => 1,
            'status'      => 1,
            'cliente_id'  => 1,
            'notificar'   => 0
        ];

        $id = $model->insert($data);
        $this->assertIsNumeric($id);

        $task = $model->find($id);
        $this->assertEquals(1, $task['cliente_id']);
    }
}
