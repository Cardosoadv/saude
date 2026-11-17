<?php

namespace Tests\Unit\Repositories;

use App\Repositories\Financeiro\DespesasRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class DespesasRepositoryTest extends CIUnitTestCase
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

        $this->db->query("DROP TABLE IF EXISTS db_fin_despesas");
        $this->db->query("DROP TABLE IF EXISTS db_fin_pgto_despesas");
        $this->db->query("DROP TABLE IF EXISTS db_fin_categorias");
        $this->db->query("DROP TABLE IF EXISTS db_fin_despesas_categorias");

        $this->db->query("CREATE TABLE IF NOT EXISTS db_fin_despesas (
            id_despesa INTEGER PRIMARY KEY AUTOINCREMENT,
            despesa VARCHAR(255),
            vencimento_dt DATETIME,
            valor DECIMAL(10,2),
            fornecedor INTEGER,
            contrato_id INTEGER,
            deleted_at DATETIME DEFAULT NULL,
            created_at DATETIME,
            updated_at DATETIME
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS db_fin_pgto_despesas (
            id_pgto_despesa INTEGER PRIMARY KEY AUTOINCREMENT,
            despesa_id INTEGER,
            valor DECIMAL(10,2),
            pagamento_despesa_dt DATETIME,
            deleted_at DATETIME DEFAULT NULL,
            created_at DATETIME,
            updated_at DATETIME
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS db_fin_categorias (
            id_categoria INTEGER PRIMARY KEY AUTOINCREMENT,
            categoria VARCHAR(255)
        )");

        $this->db->query("CREATE TABLE IF NOT EXISTS db_fin_despesas_categorias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            despesa_id INTEGER,
            categoria_id INTEGER,
            valor DECIMAL(15,2),
            created_at DATETIME,
            updated_at DATETIME
        )");

        $this->db->query("DROP TABLE IF EXISTS db_fin_fornecedores");
        $this->db->query("CREATE TABLE IF NOT EXISTS db_fin_fornecedores (
            id_fornecedor INTEGER PRIMARY KEY AUTOINCREMENT,
            nome VARCHAR(255)
        )");

        $this->db->query("DROP TABLE IF EXISTS db_contratos");
        $this->db->query("CREATE TABLE IF NOT EXISTS db_contratos (
            id_contrato INTEGER PRIMARY KEY AUTOINCREMENT,
            numero_contrato VARCHAR(255),
            objeto VARCHAR(255)
        )");

        model('Financeiro/FinanceiroDespesasModel')->allowCallbacks(false);
        model('Financeiro/FinanceiroPagtoDespesasModel')->allowCallbacks(false);
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->query('PRAGMA foreign_keys = OFF;');
            $this->db->query("DROP TABLE IF EXISTS db_fin_despesas");
            $this->db->query("DROP TABLE IF EXISTS db_fin_pgto_despesas");
            $this->db->query("DROP TABLE IF EXISTS db_fin_categorias");
            $this->db->query("DROP TABLE IF EXISTS db_fin_despesas_categorias");
            $this->db->query("DROP TABLE IF EXISTS db_fin_fornecedores");
            $this->db->query("DROP TABLE IF EXISTS db_contratos");
        }
        parent::tearDown();
    }

    public function testGetResumoEstatisticas()
    {
        $hoje = date('Y-m-d');
        $ontem = date('Y-m-d', strtotime('-1 day'));
        $amanha = date('Y-m-d', strtotime('+1 day'));
        $monday = date('Y-m-d', strtotime('monday this week'));
        $fimSemana = date('Y-m-d', strtotime('sunday this week'));

        // Garantir que 'Esta Semana' não caia no mesmo dia que 'Hoje' ou 'Ontem'
        $dataEstaSemana = $amanha;
        if ($dataEstaSemana > $fimSemana) {
            $dataEstaSemana = $monday;
            if ($dataEstaSemana == $hoje || $dataEstaSemana == $ontem) {
                $dataEstaSemana = date('Y-m-d', strtotime($fimSemana . ' -1 day'));
            }
        }

        $this->db->table('fin_despesas')->insertBatch([
            [
                'despesa' => 'Vencida',
                'vencimento_dt' => $ontem,
                'valor' => 100.00,
                'deleted_at' => null,
            ],
            [
                'despesa' => 'Hoje',
                'vencimento_dt' => $hoje,
                'valor' => 200.00,
                'deleted_at' => null,
            ],
            [
                'despesa' => 'Esta Semana',
                'vencimento_dt' => $dataEstaSemana,
                'valor' => 300.00,
                'deleted_at' => null,
            ],
            [
                'despesa' => 'Paga (Ignorar)',
                'vencimento_dt' => $ontem,
                'valor' => 500.00,
                'deleted_at' => null,
            ],
        ]);

        $idPaga = $this->db->table('fin_despesas')->where('despesa', 'Paga (Ignorar)')->get()->getRowArray()['id_despesa'];
        $this->db->table('fin_pgto_despesas')->insert([
            'despesa_id' => $idPaga,
            'valor' => 500.00,
            'pagamento_despesa_dt' => $hoje,
            'deleted_at' => null,
        ]);

        $repository = new DespesasRepository();
        $stats = $repository->getResumoEstatisticas();

        // Expecting:
        // Vencida: total_vencidas++, valor_vencidas += 100
        // Hoje: vence_hoje++, vence_semana++
        // Esta Semana: vence_semana++
        // Paga: Ignored.

        $expectedVencidasCount = 1; // 'Vencida'
        $expectedVencidasValor = 100.00;
        if ($dataEstaSemana < $hoje) {
            $expectedVencidasCount++;
            $expectedVencidasValor += 300.00;
        }

        $this->assertEquals($expectedVencidasCount, $stats['total_vencidas'], "Total de despesas vencidas incorreto. Hoje: $hoje. Stats: " . json_encode($stats));
        $this->assertEquals($expectedVencidasValor, $stats['valor_vencidas'], "Valor de despesas vencidas incorreto.");
        $this->assertEquals(1, $stats['vence_hoje'], "Total de despesas que vencem hoje incorreto. Hoje: $hoje. Stats: " . json_encode($stats));

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

    public function testDashboardQueriesCompatibility()
    {
        $hoje = date('Y-m-d');
        
        // Insere despesas
        $this->db->table('fin_despesas')->insertBatch([
            [
                'despesa' => 'Despesa 1',
                'vencimento_dt' => $hoje,
                'valor' => 150.00,
                'deleted_at' => null,
            ],
            [
                'despesa' => 'Despesa 2',
                'vencimento_dt' => $hoje,
                'valor' => 250.00,
                'deleted_at' => null,
            ],
        ]);

        $idDespesa = $this->db->table('fin_despesas')->where('despesa', 'Despesa 1')->get()->getRowArray()['id_despesa'];
        $this->db->table('fin_pgto_despesas')->insert([
            'despesa_id' => $idDespesa,
            'valor' => 150.00,
            'pagamento_despesa_dt' => $hoje,
            'deleted_at' => null,
        ]);

        $repository = new DespesasRepository();

        // 1. Invoca a consulta que poluia o builder anteriormente
        $resumo = $repository->getResumoNaoPagas([
            'data_inicio' => date('Y-m-01'),
            'data_fim' => date('Y-m-t'),
        ]);

        $this->assertIsArray($resumo);

        // 2. Invoca a consulta agrupada que sofria a colisão por conta da poluição
        $grafico = $repository->getDespesasPagasAgrupadasPorMes(date('Y-01-01'), date('Y-12-31'));

        $this->assertIsArray($grafico);
        $this->assertNotEmpty($grafico);
        $this->assertEquals(150.00, $grafico[0]['despesas']);
    }

    public function testCreateComRateioDeCategorias()
    {
        $this->db->table('fin_categorias')->insertBatch([
            ['id_categoria' => 1, 'categoria' => 'Aluguel'],
            ['id_categoria' => 2, 'categoria' => 'Energia'],
        ]);

        $repository = new DespesasRepository();

        $id = $repository->create([
            'despesa' => 'Conta de luz e aluguel',
            'vencimento_dt' => date('Y-m-d'),
            'valor' => 300.00,
            'fornecedor' => 1,
            'categorias' => [
                ['categoria_id' => 1, 'valor' => 200.00],
                ['categoria_id' => 2, 'valor' => 100.00],
            ],
        ]);

        $this->assertIsInt($id);

        $linhas = $this->db->table('fin_despesas_categorias')->where('despesa_id', $id)->get()->getResultArray();
        $this->assertCount(2, $linhas);

        $despesa = $repository->findById($id);
        $this->assertCount(2, $despesa['categorias']);
        $this->assertEquals('Aluguel, Energia', $despesa['nome_categoria']);
    }

    public function testUpdateSubstituiRateioDeCategorias()
    {
        $this->db->table('fin_categorias')->insertBatch([
            ['id_categoria' => 1, 'categoria' => 'Aluguel'],
            ['id_categoria' => 2, 'categoria' => 'Energia'],
            ['id_categoria' => 3, 'categoria' => 'Água'],
        ]);

        $repository = new DespesasRepository();
        $id = $repository->create([
            'despesa' => 'Conta mista',
            'vencimento_dt' => date('Y-m-d'),
            'valor' => 300.00,
            'fornecedor' => 1,
            'categorias' => [
                ['categoria_id' => 1, 'valor' => 200.00],
                ['categoria_id' => 2, 'valor' => 100.00],
            ],
        ]);

        $repository->update($id, [
            'categorias' => [
                ['categoria_id' => 3, 'valor' => 300.00],
            ],
        ]);

        $linhas = $this->db->table('fin_despesas_categorias')->where('despesa_id', $id)->get()->getResultArray();
        $this->assertCount(1, $linhas);
        $this->assertEquals(3, $linhas[0]['categoria_id']);
        $this->assertEquals(300.00, $linhas[0]['valor']);
    }
}

