<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\ContratosRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class ContratosRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = false;
    protected $migrateOnce = false;
    protected $refresh     = false;
    protected $namespace   = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) { $this->db->query('PRAGMA foreign_keys = OFF;'); }

        $this->db->query("DROP TABLE IF EXISTS db_contratos");

        $this->db->query("CREATE TABLE IF NOT EXISTS db_contratos (
            id_contrato INTEGER PRIMARY KEY AUTOINCREMENT,
            numero_contrato VARCHAR(50),
            cliente_id INTEGER,
            status VARCHAR(20),
            data_inicio DATE,
            data_fim DATE,
            valor DECIMAL(15,2),
            deleted_at DATETIME DEFAULT NULL,
            created_at DATETIME,
            updated_at DATETIME
        )");

        // Disable callbacks for AuditoriaTrait to avoid errors
        model('App\Models\ContratosModel')->allowCallbacks(false);
    }

    public function testGetEstatisticas()
    {
        $hoje = date('Y-m-d');
        $ontem = date('Y-m-d', strtotime('-1 day'));
        $amanha = date('Y-m-d', strtotime('+1 day'));

        $this->db->table('contratos')->insertBatch([
            [
                'status' => 'Ativo',
                'valor' => 1000.00,
                'data_fim' => $amanha,
                'deleted_at' => null
            ],
            [
                'status' => 'Ativo',
                'valor' => 2000.00,
                'data_fim' => null,
                'deleted_at' => null
            ],
            [
                'status' => 'Encerrado', // Not Ativo
                'valor' => 500.00,
                'data_fim' => $ontem,
                'deleted_at' => null
            ],
            [
                'status' => 'Ativo',
                'valor' => 1500.00,
                'data_fim' => $ontem, // Vencido (Ativo but data_fim < hoje)
                'deleted_at' => null
            ],
            [
                'status' => 'Ativo',
                'valor' => 9999.00,
                'data_fim' => $amanha,
                'deleted_at' => date('Y-m-d H:i:s') // Deleted
            ],
            [
                'status' => 'Ativo',
                'valor' => 300.00,
                'data_fim' => '1900-01-01', // Should not be considered vencido based on repo logic (> 1900-01-01)
                'deleted_at' => null
            ]
        ]);

        $repository = new ContratosRepository();
        $stats = $repository->getEstatisticas();

        // Total non-deleted: 5
        $this->assertEquals(5, $stats['total'], 'Should count 5 total active contracts');

        // Ativos: 4 (Ativo, Ativo, Ativo, Ativo) - The third one was 'Encerrado'
        $this->assertEquals(4, $stats['ativos'], 'Should count 4 contracts with status Ativo');

        // Vencidos: 1 (The one with data_fim = yesterday and status Ativo - wait, repo logic for vencidos is data_fim < today)
        // Let's re-read repo logic for vencidos:
        /*
            case 'vencido':
                $builder->groupStart()
                    ->where('contratos.data_fim IS NOT NULL')
                    ->where('contratos.data_fim >', '1900-01-01')
                    ->where('contratos.data_fim <', date('Y-m-d'))
                    ->groupEnd();
                break;
        */
        // My seed:
        // 1. data_fim = $amanha (not vencido)
        // 2. data_fim = null (not vencido)
        // 3. status = 'Encerrado', data_fim = $ontem (vencido by date)
        // 4. status = 'Ativo', data_fim = $ontem (vencido by date)
        // 5. deleted (not counted)
        // 6. data_fim = '1900-01-01' (not vencido because not > 1900-01-01)

        // So vencidos should be 2 (records 3 and 4)
        $this->assertEquals(2, $stats['vencidos'], 'Should count 2 expired contracts');

        // Valor Total: 1000 + 2000 + 500 + 1500 + 300 = 5300
        $this->assertEquals(5300.00, $stats['valor_total'], 'Should calculate correct total value');
    }
}
