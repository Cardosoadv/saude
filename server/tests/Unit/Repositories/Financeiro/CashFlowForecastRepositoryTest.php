<?php

namespace Tests\Unit\Repositories\Financeiro;

use App\Repositories\Financeiro\CashFlowForecastRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class CashFlowForecastRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->query('DROP TABLE IF EXISTS db_fin_pgto_despesas');
        $this->db->query('DROP TABLE IF EXISTS db_fin_pgto_receitas');
        $this->db->query('DROP TABLE IF EXISTS db_fin_despesas');
        $this->db->query('DROP TABLE IF EXISTS db_fin_receitas');
        $this->db->query('DROP TABLE IF EXISTS db_fin_despesas_categorias');
        $this->db->query('DROP TABLE IF EXISTS db_fin_receitas_categorias');
        $this->db->query('DROP TABLE IF EXISTS db_fin_categorias');

        $this->db->query('CREATE TABLE db_fin_pgto_despesas (
            id_pgto_despesa INTEGER PRIMARY KEY AUTOINCREMENT,
            despesa_id INTEGER,
            conta_id INTEGER,
            valor DECIMAL(15,2),
            pagamento_despesa_dt DATE,
            deleted_at DATETIME DEFAULT NULL
        )');

        $this->db->query('CREATE TABLE db_fin_pgto_receitas (
            id_pgto_receita INTEGER PRIMARY KEY AUTOINCREMENT,
            receita_id INTEGER,
            conta_id INTEGER,
            valor DECIMAL(15,2),
            pagamento_receita_dt DATE,
            deleted_at DATETIME DEFAULT NULL
        )');

        $this->db->query('CREATE TABLE db_fin_despesas (
            id_despesa INTEGER PRIMARY KEY AUTOINCREMENT,
            despesa TEXT,
            valor DECIMAL(15,2),
            vencimento_dt DATE,
            deleted_at DATETIME DEFAULT NULL
        )');

        $this->db->query('CREATE TABLE db_fin_receitas (
            id_receita INTEGER PRIMARY KEY AUTOINCREMENT,
            receita TEXT,
            valor DECIMAL(15,2),
            vencimento_dt DATE,
            deleted_at DATETIME DEFAULT NULL
        )');

        $this->db->query('CREATE TABLE db_fin_despesas_categorias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            despesa_id INTEGER,
            categoria_id INTEGER,
            valor DECIMAL(15,2)
        )');

        $this->db->query('CREATE TABLE db_fin_receitas_categorias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            receita_id INTEGER,
            categoria_id INTEGER,
            valor DECIMAL(15,2)
        )');

        $this->db->query('CREATE TABLE db_fin_categorias (
            id_categoria INTEGER PRIMARY KEY AUTOINCREMENT,
            categoria TEXT
        )');
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->query('DROP TABLE IF EXISTS db_fin_pgto_despesas');
            $this->db->query('DROP TABLE IF EXISTS db_fin_pgto_receitas');
            $this->db->query('DROP TABLE IF EXISTS db_fin_despesas');
            $this->db->query('DROP TABLE IF EXISTS db_fin_receitas');
            $this->db->query('DROP TABLE IF EXISTS db_fin_despesas_categorias');
            $this->db->query('DROP TABLE IF EXISTS db_fin_receitas_categorias');
            $this->db->query('DROP TABLE IF EXISTS db_fin_categorias');
        }
        parent::tearDown();
    }

    public function testGetTotaisMensaisDespesasAgrupaPorMesEFiltraPorConta(): void
    {
        $mesAtual = date('Y-m-d');
        $mesPassado = date('Y-m-d', strtotime('-1 month'));

        $this->db->table('fin_pgto_despesas')->insertBatch([
            ['despesa_id' => 1, 'conta_id' => 1, 'valor' => 100.00, 'pagamento_despesa_dt' => $mesAtual],
            ['despesa_id' => 2, 'conta_id' => 1, 'valor' => 50.00, 'pagamento_despesa_dt' => $mesAtual],
            ['despesa_id' => 3, 'conta_id' => 2, 'valor' => 999.00, 'pagamento_despesa_dt' => $mesAtual],
            ['despesa_id' => 4, 'conta_id' => 1, 'valor' => 30.00, 'pagamento_despesa_dt' => $mesPassado],
        ]);

        $repository = new CashFlowForecastRepository();
        $resultado = $repository->getTotaisMensaisDespesas(1, 3);

        $chaveAtual = date('Y-m');
        $chavePassada = date('Y-m', strtotime('-1 month'));

        $this->assertEquals(150.00, $resultado[$chaveAtual]);
        $this->assertEquals(30.00, $resultado[$chavePassada]);
    }

    public function testGetTotaisMensaisReceitasSemFiltroDeContaSomaTodasAsContas(): void
    {
        $mesAtual = date('Y-m-d');

        $this->db->table('fin_pgto_receitas')->insertBatch([
            ['receita_id' => 1, 'conta_id' => 1, 'valor' => 500.00, 'pagamento_receita_dt' => $mesAtual],
            ['receita_id' => 2, 'conta_id' => 2, 'valor' => 250.00, 'pagamento_receita_dt' => $mesAtual],
        ]);

        $repository = new CashFlowForecastRepository();
        $resultado = $repository->getTotaisMensaisReceitas(null, 3);

        $this->assertEquals(750.00, $resultado[date('Y-m')]);
    }

    public function testGetLancamentosComCategoriaResolveCategoriaPrincipalPorMaiorRateio(): void
    {
        $vencimento = date('Y-m-d');

        $this->db->table('fin_categorias')->insertBatch([
            ['id_categoria' => 1, 'categoria' => 'Aluguel'],
            ['id_categoria' => 2, 'categoria' => 'Manutenção'],
        ]);

        $this->db->table('fin_despesas')->insert([
            'id_despesa' => 10,
            'despesa' => 'Aluguel do escritório',
            'valor' => 5000.00,
            'vencimento_dt' => $vencimento,
        ]);

        // Rateio em duas categorias: a de maior valor deve ser a categoria principal.
        $this->db->table('fin_despesas_categorias')->insertBatch([
            ['despesa_id' => 10, 'categoria_id' => 2, 'valor' => 500.00],
            ['despesa_id' => 10, 'categoria_id' => 1, 'valor' => 4500.00],
        ]);

        $repository = new CashFlowForecastRepository();
        $resultado = $repository->getLancamentosComCategoria('despesa', 3);

        $this->assertCount(1, $resultado);
        $this->assertEquals(10, $resultado[0]['id']);
        $this->assertEquals(5000.00, $resultado[0]['valor']);
        $this->assertEquals(1, $resultado[0]['categoria_id']);
        $this->assertEquals('Aluguel', $resultado[0]['categoria_nome']);
    }

    public function testGetLancamentosComCategoriaIgnoraLancamentosForaDaJanela(): void
    {
        $this->db->table('fin_categorias')->insert(['id_categoria' => 1, 'categoria' => 'Aluguel']);

        $this->db->table('fin_receitas')->insert([
            'id_receita' => 20,
            'receita' => 'Honorários antigos',
            'valor' => 1000.00,
            'vencimento_dt' => date('Y-m-d', strtotime('-10 months')),
        ]);
        $this->db->table('fin_receitas_categorias')->insert(['receita_id' => 20, 'categoria_id' => 1, 'valor' => 1000.00]);

        $repository = new CashFlowForecastRepository();
        $resultado = $repository->getLancamentosComCategoria('receita', 3);

        $this->assertCount(0, $resultado);
    }
}
