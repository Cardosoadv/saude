<?php

namespace Tests\Unit;

use App\Repositories\Financeiro\ReceitasRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class ReceitasRepositoryTest extends CIUnitTestCase
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

        $this->db->query("DROP TABLE IF EXISTS db_fin_receitas");
        $this->db->query("DROP TABLE IF EXISTS db_fin_pgto_receitas");
        $this->db->query("DROP TABLE IF EXISTS db_clientes");
        $this->db->query("DROP TABLE IF EXISTS db_fin_categorias");
        $this->db->query("DROP TABLE IF EXISTS db_fin_receitas_categorias");

        // Create tables manually for SQLite in-memory testing
        $this->db->query("CREATE TABLE IF NOT EXISTS db_clientes (
            id_cliente INTEGER PRIMARY KEY AUTOINCREMENT,
            nome VARCHAR(255)
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS db_fin_categorias (
            id_categoria INTEGER PRIMARY KEY AUTOINCREMENT,
            categoria VARCHAR(255)
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS db_fin_receitas (
            id_receita INTEGER PRIMARY KEY AUTOINCREMENT,
            receita VARCHAR(255),
            vencimento_dt DATETIME,
            valor DECIMAL(10,2),
            cliente_id INTEGER,
            contrato_id INTEGER,
            deleted_at DATETIME DEFAULT NULL,
            created_at DATETIME,
            updated_at DATETIME
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS db_fin_pgto_receitas (
            id_pgto_receita INTEGER PRIMARY KEY AUTOINCREMENT,
            receita_id INTEGER,
            valor DECIMAL(10,2),
            pagamento_receita_dt DATETIME,
            deleted_at DATETIME DEFAULT NULL,
            created_at DATETIME,
            updated_at DATETIME
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS db_fin_receitas_categorias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            receita_id INTEGER,
            categoria_id INTEGER,
            valor DECIMAL(15,2),
            created_at DATETIME,
            updated_at DATETIME
        )");

        $this->db->query("DROP TABLE IF EXISTS db_contratos");
        $this->db->query("CREATE TABLE IF NOT EXISTS db_contratos (
            id_contrato INTEGER PRIMARY KEY AUTOINCREMENT,
            numero_contrato VARCHAR(255),
            objeto VARCHAR(255)
        )");

        // Disable callbacks to avoid AuditoriaTrait issues if table is missing
        model('Financeiro/FinanceiroReceitasModel')->allowCallbacks(false);
        model('Financeiro/FinanceiroPagtoReceitasModel')->allowCallbacks(false);
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->query('PRAGMA foreign_keys = OFF;');
            $this->db->query("DROP TABLE IF EXISTS db_fin_receitas");
            $this->db->query("DROP TABLE IF EXISTS db_fin_pgto_receitas");
            $this->db->query("DROP TABLE IF EXISTS db_clientes");
            $this->db->query("DROP TABLE IF EXISTS db_fin_categorias");
            $this->db->query("DROP TABLE IF EXISTS db_fin_receitas_categorias");
            $this->db->query("DROP TABLE IF EXISTS db_contratos");
        }
        parent::tearDown();
    }

    public function testContarReceitasNaoRecebidas()
    {
        // Insert test data
        $this->db->table('fin_receitas')->insertBatch([
            [
                'receita' => 'Receita 1',
                'vencimento_dt' => '2023-10-01',
                'valor' => 1000.00,
                'cliente_id' => 1,
                'deleted_at' => null,
            ],
            [
                'receita' => 'Receita 2',
                'vencimento_dt' => '2023-10-05',
                'valor' => 2000.00,
                'cliente_id' => 1,
                'deleted_at' => null,
            ],
            [
                'receita' => 'Receita Recebida',
                'vencimento_dt' => '2023-10-10',
                'valor' => 3000.00,
                'cliente_id' => 1,
                'deleted_at' => null,
            ],
        ]);

        $idReceitaRecebida = $this->db->insertID();

        // Mark 'Receita Recebida' as paid
        $this->db->table('fin_pgto_receitas')->insert([
            'receita_id' => $idReceitaRecebida,
            'valor' => 3000.00,
            'pagamento_receita_dt' => '2023-10-10',
            'deleted_at' => null,
        ]);

        $repository = new ReceitasRepository();

        // Test count
        $count = $repository->contarReceitasNaoRecebidas();
        $this->assertEquals(2, $count, 'Should count 2 unpaid receipts');
    }

    public function testListarReceitasNaoRecebidas()
    {
        // Insert text data
        $this->db->table('fin_receitas')->insertBatch([
            [
                'receita' => 'Receita A',
                'vencimento_dt' => '2025-01-01',
                'valor' => 500.00,
                'cliente_id' => 2,
                'deleted_at' => null,
            ],
            [
                'receita' => 'Receita B',
                'vencimento_dt' => '2025-01-02',
                'valor' => 600.00,
                'cliente_id' => 2,
                'deleted_at' => null,
            ],
        ]);

        $repository = new ReceitasRepository();
        $list = $repository->listarReceitasNaoPagas();

        $this->assertCount(2, $list);
        $this->assertEquals('Receita A', $list[0]['receita']);
    }

    public function testGetReceitasPagasAgrupadasPorMes()
    {
        // Seed data
        $this->db->table('fin_pgto_receitas')->insertBatch([
            [
                'receita_id' => 1,
                'valor' => 100.00,
                'pagamento_receita_dt' => '2025-01-15',
                'deleted_at' => null,
            ],
            [
                'receita_id' => 2,
                'valor' => 200.00,
                'pagamento_receita_dt' => '2025-01-20',
                'deleted_at' => null,
            ],
            [
                'receita_id' => 3,
                'valor' => 150.00,
                'pagamento_receita_dt' => '2025-02-05',
                'deleted_at' => null,
            ],
        ]);

        $repository = new ReceitasRepository();
        $result = $repository->getReceitasPagasAgrupadasPorMes('2025-01-01', '2025-12-31');

        $this->assertCount(2, $result);

        $this->assertEquals('01/2025', $result[0]['mes']);
        $this->assertEquals(300.00, $result[0]['receitas']);

        $this->assertEquals('02/2025', $result[1]['mes']);
        $this->assertEquals(150.00, $result[1]['receitas']);
    }

    public function testGetResumoEstatisticas()
    {
        $hoje = date('Y-m-d');
        $ontem = date('Y-m-d', strtotime('-1 day'));
        $amanha = date('Y-m-d', strtotime('+1 day'));
        $monday = date('Y-m-d', strtotime('monday this week'));
        $fimSemana = date('Y-m-d', strtotime('sunday this week'));

        // Ensure 'Esta Semana' doesn't fall on the same day as 'Hoje' or 'Ontem'
        $dataEstaSemana = $amanha;
        if ($dataEstaSemana > $fimSemana) {
            $dataEstaSemana = $monday;
            if ($dataEstaSemana == $hoje || $dataEstaSemana == $ontem) {
                $dataEstaSemana = date('Y-m-d', strtotime($fimSemana . ' -1 day'));
            }
        }

        $this->db->table('fin_receitas')->truncate();
        $this->db->table('fin_pgto_receitas')->truncate();

        $this->db->table('fin_receitas')->insertBatch([
            [
                'receita' => 'Atrasada',
                'vencimento_dt' => $ontem,
                'valor' => 100.00,
                'deleted_at' => null,
            ],
            [
                'receita' => 'Hoje',
                'vencimento_dt' => $hoje,
                'valor' => 200.00,
                'deleted_at' => null,
            ],
            [
                'receita' => 'Esta Semana',
                'vencimento_dt' => $dataEstaSemana,
                'valor' => 300.00,
                'deleted_at' => null,
            ],
            [
                'receita' => 'Paga (Ignorar)',
                'vencimento_dt' => $ontem,
                'valor' => 500.00,
                'deleted_at' => null,
            ],
        ]);

        $idPaga = $this->db->table('fin_receitas')->where('receita', 'Paga (Ignorar)')->get()->getRowArray()['id_receita'];
        $this->db->table('fin_pgto_receitas')->insert([
            'receita_id' => $idPaga,
            'valor' => 500.00,
            'pagamento_receita_dt' => $hoje,
            'deleted_at' => null,
        ]);

        $repository = new ReceitasRepository();
        $stats = $repository->getResumoEstatisticas();

        // Expecting:
        // Atrasada: total_atrasadas++, valor_receber += 100
        // Hoje: vence_hoje++, vence_semana++, valor_receber += 200
        // Esta Semana: vence_semana++, valor_receber += 300 (assuming $wednesday != $hoje)
        // Paga: Ignored.

        $expectedValor = 100.00 + 200.00 + 300.00;
        $this->assertEquals($expectedValor, $stats['valor_receber']);

        $expectedAtrasadas = ($dataEstaSemana < $hoje) ? 2 : 1;
        $this->assertEquals($expectedAtrasadas, $stats['total_atrasadas']);

        $expectedVenceHoje = ($dataEstaSemana == $hoje) ? 2 : 1;
        $this->assertEquals($expectedVenceHoje, $stats['vence_hoje']);

        // Count how many of (ontem, hoje, dataEstaSemana) are in this week.
        $venceSemanaCount = 0;
        if ($ontem >= $monday && $ontem <= $fimSemana) {
            $venceSemanaCount++;
        }
        if ($hoje >= $monday && $hoje <= $fimSemana) {
            $venceSemanaCount++;
        }
        if ($dataEstaSemana >= $monday && $dataEstaSemana <= $fimSemana) {
            $venceSemanaCount++;
        }

        $this->assertEquals($venceSemanaCount, $stats['vence_semana']);
    }

    public function testCreateComRateioDeCategorias()
    {
        $this->db->table('fin_categorias')->insertBatch([
            ['id_categoria' => 1, 'categoria' => 'Honorários'],
            ['id_categoria' => 2, 'categoria' => 'Consultoria'],
        ]);

        $repository = new ReceitasRepository();

        $id = $repository->create([
            'receita' => 'Honorários + consultoria',
            'vencimento_dt' => date('Y-m-d'),
            'valor' => 500.00,
            'categorias' => [
                ['categoria_id' => 1, 'valor' => 350.00],
                ['categoria_id' => 2, 'valor' => 150.00],
            ],
        ]);

        $this->assertIsInt($id);

        $linhas = $this->db->table('fin_receitas_categorias')->where('receita_id', $id)->get()->getResultArray();
        $this->assertCount(2, $linhas);

        $receita = $repository->findById($id);
        $this->assertCount(2, $receita['categorias']);
        $this->assertEquals('Honorários, Consultoria', $receita['nome_categoria']);
    }

    public function testUpdateSubstituiRateioDeCategorias()
    {
        $this->db->table('fin_categorias')->insertBatch([
            ['id_categoria' => 1, 'categoria' => 'Honorários'],
            ['id_categoria' => 2, 'categoria' => 'Consultoria'],
        ]);

        $repository = new ReceitasRepository();
        $id = $repository->create([
            'receita' => 'Receita mista',
            'vencimento_dt' => date('Y-m-d'),
            'valor' => 500.00,
            'categorias' => [
                ['categoria_id' => 1, 'valor' => 350.00],
                ['categoria_id' => 2, 'valor' => 150.00],
            ],
        ]);

        $repository->update($id, [
            'categorias' => [
                ['categoria_id' => 2, 'valor' => 500.00],
            ],
        ]);

        $linhas = $this->db->table('fin_receitas_categorias')->where('receita_id', $id)->get()->getResultArray();
        $this->assertCount(1, $linhas);
        $this->assertEquals(2, $linhas[0]['categoria_id']);
        $this->assertEquals(500.00, $linhas[0]['valor']);
    }
}
