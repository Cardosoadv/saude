<?php

namespace Tests\Unit;

use App\Repositories\Financeiro\PagamentoReceitasRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class PagamentoReceitasRepositoryTest extends CIUnitTestCase
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

        $this->db->query("DROP TABLE IF EXISTS db_fin_pgto_receitas");

        $this->db->query("CREATE TABLE IF NOT EXISTS db_fin_pgto_receitas (
            id_pgto_receita INTEGER PRIMARY KEY AUTOINCREMENT,
            receita_id INTEGER,
            valor DECIMAL(10,2),
            pagamento_receita_dt DATETIME,
            deleted_at DATETIME DEFAULT NULL,
            created_at DATETIME,
            updated_at DATETIME
        )");

        model('Financeiro/FinanceiroPagtoReceitasModel')->allowCallbacks(false);
    }

    public function testGetEstatisticasMes()
    {
        // Insert test data for October 2023
        $this->db->table('fin_pgto_receitas')->insertBatch([
            [
                'receita_id' => 1,
                'valor' => 100.00,
                'pagamento_receita_dt' => '2023-10-05',
                'deleted_at' => null
            ],
            [
                'receita_id' => 2,
                'valor' => 200.00,
                'pagamento_receita_dt' => '2023-10-15',
                'deleted_at' => null
            ],
            [
                'receita_id' => 3,
                'valor' => 300.00,
                'pagamento_receita_dt' => '2023-10-25',
                'deleted_at' => null
            ],
            // Different month
            [
                'receita_id' => 4,
                'valor' => 500.00,
                'pagamento_receita_dt' => '2023-11-05',
                'deleted_at' => null
            ],
            // Deleted record
            [
                'receita_id' => 5,
                'valor' => 1000.00,
                'pagamento_receita_dt' => '2023-10-10',
                'deleted_at' => '2023-10-11'
            ]
        ]);

        $repository = new PagamentoReceitasRepository();
        $stats = $repository->getEstatisticasMes('2023-10');

        $this->assertEquals(600.00, $stats['total_recebido']);
        $this->assertEquals(3, $stats['count']);
        $this->assertEquals(200.00, $stats['media']);
    }

    public function testGetEstatisticasMesVazio()
    {
        $repository = new PagamentoReceitasRepository();
        $stats = $repository->getEstatisticasMes('2023-12');

        $this->assertEquals(0, $stats['total_recebido']);
        $this->assertEquals(0, $stats['count']);
        $this->assertEquals(0, $stats['media']);
    }
}
