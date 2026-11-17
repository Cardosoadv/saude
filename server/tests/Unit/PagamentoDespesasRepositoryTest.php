<?php

namespace Tests\Unit;

use App\Repositories\Financeiro\PagamentoDespesasRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class PagamentoDespesasRepositoryTest extends CIUnitTestCase
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

        $this->db->query("DROP TABLE IF EXISTS db_fin_pgto_despesas");
        $this->db->query("DROP TABLE IF EXISTS db_fin_contas");

        $this->db->query("CREATE TABLE IF NOT EXISTS db_fin_pgto_despesas (
            id_pgto_despesa INTEGER PRIMARY KEY AUTOINCREMENT,
            despesa_id INTEGER,
            conta_id INTEGER,
            valor DECIMAL(10,2),
            pagamento_despesa_dt DATETIME,
            deleted_at DATETIME DEFAULT NULL,
            created_at DATETIME,
            updated_at DATETIME
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS db_fin_contas (
            id_conta INTEGER PRIMARY KEY AUTOINCREMENT,
            conta TEXT
        )");

        model('Financeiro/FinanceiroPagtoDespesasModel')->allowCallbacks(false);
    }

    public function testGetEstatisticasMes()
    {
        // Insert test data for October 2023
        $this->db->table('fin_pgto_despesas')->insertBatch([
            [
                'despesa_id' => 1,
                'valor' => 100.00,
                'pagamento_despesa_dt' => '2023-10-05',
                'deleted_at' => null
            ],
            [
                'despesa_id' => 2,
                'valor' => 200.00,
                'pagamento_despesa_dt' => '2023-10-15',
                'deleted_at' => null
            ],
            [
                'despesa_id' => 3,
                'valor' => 300.00,
                'pagamento_despesa_dt' => '2023-10-25',
                'deleted_at' => null
            ],
            // Different month
            [
                'despesa_id' => 4,
                'valor' => 500.00,
                'pagamento_despesa_dt' => '2023-11-05',
                'deleted_at' => null
            ],
            // Deleted record
            [
                'despesa_id' => 5,
                'valor' => 1000.00,
                'pagamento_despesa_dt' => '2023-10-10',
                'deleted_at' => '2023-10-11'
            ]
        ]);

        $repository = new PagamentoDespesasRepository();
        $stats = $repository->getEstatisticasMes('2023-10');

        $this->assertEquals(600.00, $stats['total_pago']);
        $this->assertEquals(3, $stats['count']);
        $this->assertEquals(200.00, $stats['media']);
    }

    public function testGetResumoPorConta()
    {
        // Insert accounts
        $this->db->table('fin_contas')->insertBatch([
            ['id_conta' => 1, 'conta' => 'Conta Corrente'],
            ['id_conta' => 2, 'conta' => 'Cofre'],
        ]);

        // Insert payments for October 2023
        $this->db->table('fin_pgto_despesas')->insertBatch([
            [
                'despesa_id' => 1,
                'conta_id' => 1,
                'valor' => 100.00,
                'pagamento_despesa_dt' => '2023-10-05',
                'deleted_at' => null
            ],
            [
                'despesa_id' => 2,
                'conta_id' => 1,
                'valor' => 200.00,
                'pagamento_despesa_dt' => '2023-10-15',
                'deleted_at' => null
            ],
            [
                'despesa_id' => 3,
                'conta_id' => 2,
                'valor' => 300.00,
                'pagamento_despesa_dt' => '2023-10-25 23:59:59', // Test timestamp on last day
                'deleted_at' => null
            ],
            // Different month
            [
                'despesa_id' => 4,
                'conta_id' => 2,
                'valor' => 500.00,
                'pagamento_despesa_dt' => '2023-11-01',
                'deleted_at' => null
            ],
        ]);

        $repository = new PagamentoDespesasRepository();
        $resumo = $repository->getResumoPorConta('2023-10-01', '2023-11-01');

        $this->assertCount(2, $resumo);

        // Account 1
        $conta1 = array_filter($resumo, fn($item) => $item['id_conta'] == 1);
        $conta1 = array_shift($conta1);
        $this->assertEquals(300.00, (float)$conta1['total']);
        $this->assertEquals(2, (int)$conta1['quantidade']);

        // Account 2
        $conta2 = array_filter($resumo, fn($item) => $item['id_conta'] == 2);
        $conta2 = array_shift($conta2);
        $this->assertEquals(300.00, (float)$conta2['total']);
        $this->assertEquals(1, (int)$conta2['quantidade']);
    }

    public function testGetEstatisticasMesVazio()
    {
        $repository = new PagamentoDespesasRepository();
        $stats = $repository->getEstatisticasMes('2023-12');

        $this->assertEquals(0, $stats['total_pago']);
        $this->assertEquals(0, $stats['count']);
        $this->assertEquals(0, $stats['media']);
    }
}
