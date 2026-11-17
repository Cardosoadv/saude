<?php

namespace Tests\Unit\Repositories\Financeiro;

use App\Repositories\Financeiro\ExtratoRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class ExtratoRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Create all necessary tables for ExtratoRepository
        $this->db->query("DROP TABLE IF EXISTS db_fin_pgto_despesas");
        $this->db->query("DROP TABLE IF EXISTS db_fin_pgto_receitas");
        $this->db->query("DROP TABLE IF EXISTS db_fin_despesas");
        $this->db->query("DROP TABLE IF EXISTS db_fin_receitas");
        $this->db->query("DROP TABLE IF EXISTS db_fin_transferencias");
        $this->db->query("DROP TABLE IF EXISTS db_fin_transferencias_rateios");
        $this->db->query("DROP TABLE IF EXISTS db_fin_pgto_despesas_rateio");
        $this->db->query("DROP TABLE IF EXISTS db_fin_pgto_receitas_rateio");
        $this->db->query("DROP TABLE IF EXISTS db_users");
        $this->db->query("DROP TABLE IF EXISTS db_fin_categorias");
        $this->db->query("DROP TABLE IF EXISTS db_fin_fornecedores");
        $this->db->query("DROP TABLE IF EXISTS db_clientes");
        $this->db->query("DROP TABLE IF EXISTS db_fin_despesas_categorias");
        $this->db->query("DROP TABLE IF EXISTS db_fin_receitas_categorias");
        $this->db->query("DROP TABLE IF EXISTS db_fin_contas");

        $this->db->query("CREATE TABLE db_fin_pgto_despesas (
            id_pgto_despesa INTEGER PRIMARY KEY AUTOINCREMENT,
            despesa_id INTEGER,
            conta_id INTEGER,
            valor DECIMAL(10,2),
            pagamento_despesa_dt DATE,
            deleted_at DATETIME DEFAULT NULL
        )");

        $this->db->query("CREATE TABLE db_fin_pgto_receitas (
            id_pgto_receita INTEGER PRIMARY KEY AUTOINCREMENT,
            receita_id INTEGER,
            conta_id INTEGER,
            valor DECIMAL(10,2),
            pagamento_receita_dt DATE,
            deleted_at DATETIME DEFAULT NULL
        )");

        $this->db->query("CREATE TABLE db_fin_despesas (
            id_despesa INTEGER PRIMARY KEY AUTOINCREMENT,
            despesa TEXT,
            fornecedor INTEGER,
            valor DECIMAL(10,2),
            deleted_at DATETIME DEFAULT NULL
        )");

        $this->db->query("CREATE TABLE db_fin_receitas (
            id_receita INTEGER PRIMARY KEY AUTOINCREMENT,
            receita TEXT,
            cliente_id INTEGER,
            valor DECIMAL(10,2),
            deleted_at DATETIME DEFAULT NULL
        )");

        $this->db->query("CREATE TABLE db_fin_despesas_categorias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            despesa_id INTEGER,
            categoria_id INTEGER,
            valor DECIMAL(15,2)
        )");

        $this->db->query("CREATE TABLE db_fin_receitas_categorias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            receita_id INTEGER,
            categoria_id INTEGER,
            valor DECIMAL(15,2)
        )");

        $this->db->query("CREATE TABLE db_fin_transferencias (
            id_transferencia INTEGER PRIMARY KEY AUTOINCREMENT,
            id_conta_origem INTEGER,
            id_conta_destino INTEGER,
            valor DECIMAL(10,2),
            data_transferencia DATE,
            transferencia TEXT,
            deleted_at DATETIME DEFAULT NULL
        )");

        $this->db->query("CREATE TABLE db_fin_transferencias_rateios (
            id_rateio INTEGER PRIMARY KEY AUTOINCREMENT,
            transferencia_id INTEGER,
            user_id INTEGER,
            valor DECIMAL(10,2)
        )");

        $this->db->query("CREATE TABLE db_fin_pgto_despesas_rateio (
            id_rateio INTEGER PRIMARY KEY AUTOINCREMENT,
            pagto_despesa_id INTEGER,
            user_id INTEGER,
            valor DECIMAL(10,2)
        )");

        $this->db->query("CREATE TABLE db_fin_pgto_receitas_rateio (
            id_rateio INTEGER PRIMARY KEY AUTOINCREMENT,
            pagto_receita_id INTEGER,
            user_id INTEGER,
            valor DECIMAL(10,2)
        )");

        $this->db->query("CREATE TABLE db_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT
        )");

        $this->db->query("CREATE TABLE db_fin_categorias (
            id_categoria INTEGER PRIMARY KEY AUTOINCREMENT,
            categoria TEXT,
            categoria_pai_id INTEGER DEFAULT NULL
        )");

        $this->db->query("CREATE TABLE db_fin_fornecedores (
            id_fornecedor INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT
        )");

        $this->db->query("CREATE TABLE db_clientes (
            id_cliente INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT
        )");

        $this->db->query("CREATE TABLE db_fin_contas (
            id_conta INTEGER PRIMARY KEY AUTOINCREMENT,
            conta TEXT
        )");

        // Seed basic data
        $this->db->table('users')->insertBatch([
            ['id' => 1, 'username' => 'User 1'],
            ['id' => 2, 'username' => 'User 2'],
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->query('PRAGMA foreign_keys = OFF;');
            $this->db->query("DROP TABLE IF EXISTS db_fin_pgto_despesas");
            $this->db->query("DROP TABLE IF EXISTS db_fin_pgto_receitas");
            $this->db->query("DROP TABLE IF EXISTS db_fin_despesas");
            $this->db->query("DROP TABLE IF EXISTS db_fin_receitas");
            $this->db->query("DROP TABLE IF EXISTS db_fin_transferencias");
            $this->db->query("DROP TABLE IF EXISTS db_fin_transferencias_rateios");
            $this->db->query("DROP TABLE IF EXISTS db_fin_pgto_despesas_rateio");
            $this->db->query("DROP TABLE IF EXISTS db_fin_pgto_receitas_rateio");
            $this->db->query("DROP TABLE IF EXISTS db_users");
            $this->db->query("DROP TABLE IF EXISTS db_fin_categorias");
            $this->db->query("DROP TABLE IF EXISTS db_fin_fornecedores");
            $this->db->query("DROP TABLE IF EXISTS db_clientes");
            $this->db->query("DROP TABLE IF EXISTS db_fin_despesas_categorias");
            $this->db->query("DROP TABLE IF EXISTS db_fin_receitas_categorias");
            $this->db->query("DROP TABLE IF EXISTS db_fin_contas");
        }
        parent::tearDown();
    }

    public function testGetDespesasPorContaPeriodo()
    {
        // 1. Setup Data
        $this->db->table('fin_despesas')->insert([
            'id_despesa' => 10,
            'despesa' => 'Internet',
            'fornecedor' => 1,
            'valor' => 150.00
        ]);
        $this->db->table('fin_fornecedores')->insert(['id_fornecedor' => 1, 'nome' => 'Vivo']);
        $this->db->table('fin_categorias')->insert(['id_categoria' => 1, 'categoria' => 'Infra']);
        $this->db->table('fin_despesas_categorias')->insert(['despesa_id' => 10, 'categoria_id' => 1, 'valor' => 150.00]);

        $this->db->table('fin_pgto_despesas')->insert([
            'id_pgto_despesa' => 100,
            'despesa_id' => 10,
            'conta_id' => 1,
            'valor' => 150.00,
            'pagamento_despesa_dt' => '2026-05-01'
        ]);

        $this->db->table('fin_pgto_despesas_rateio')->insertBatch([
            ['pagto_despesa_id' => 100, 'user_id' => 1, 'valor' => 100.00],
            ['pagto_despesa_id' => 100, 'user_id' => 2, 'valor' => 50.00],
        ]);

        // 2. Execute
        $repository = new ExtratoRepository();
        $result = $repository->getDespesasPorContaPeriodo(1, '2026-05-01', '2026-05-31');

        // 3. Verify
        $this->assertCount(1, $result);
        $this->assertEquals(-150.00, $result[0]['valor']);
        $this->assertEquals('Vivo', $result[0]['cliente_fornecedor']);
        $this->assertCount(2, $result[0]['rateio']);
        $this->assertEquals(-100.00, $result[0]['rateio'][0]['valor']);
        $this->assertEquals('User 1', $result[0]['rateio'][0]['nome']);
    }

    public function testGetReceitasPorContaPeriodo()
    {
        // 1. Setup Data
        $this->db->table('fin_receitas')->insert([
            'id_receita' => 20,
            'receita' => 'Honorários',
            'cliente_id' => 1,
            'valor' => 1000.00
        ]);
        $this->db->table('clientes')->insert(['id_cliente' => 1, 'nome' => 'Empresa X']);
        $this->db->table('fin_categorias')->insert(['id_categoria' => 2, 'categoria' => 'Serviços']);
        $this->db->table('fin_receitas_categorias')->insert(['receita_id' => 20, 'categoria_id' => 2, 'valor' => 1000.00]);

        $this->db->table('fin_pgto_receitas')->insert([
            'id_pgto_receita' => 200,
            'receita_id' => 20,
            'conta_id' => 1,
            'valor' => 1000.00,
            'pagamento_receita_dt' => '2026-05-05'
        ]);

        $this->db->table('fin_pgto_receitas_rateio')->insertBatch([
            ['pagto_receita_id' => 200, 'user_id' => 1, 'valor' => 700.00],
            ['pagto_receita_id' => 200, 'user_id' => 2, 'valor' => 300.00],
        ]);

        // 2. Execute
        $repository = new ExtratoRepository();
        $result = $repository->getReceitasPorContaPeriodo(1, '2026-05-01', '2026-05-31');

        // 3. Verify
        $this->assertCount(1, $result);
        $this->assertEquals(1000.00, $result[0]['valor']);
        $this->assertEquals('Empresa X', $result[0]['cliente_fornecedor']);
        $this->assertCount(2, $result[0]['rateio']);
        $this->assertEquals(700.00, $result[0]['rateio'][0]['valor']);
    }

    public function testGetTransferenciasPeriodo()
    {
        // 1. Setup Data
        $this->db->table('fin_contas')->insertBatch([
            ['id_conta' => 1, 'conta' => 'Conta Corrente'],
            ['id_conta' => 2, 'conta' => 'Conta Poupança'],
        ]);
        $this->db->table('fin_transferencias')->insert([
            'id_conta_origem' => 1,
            'id_conta_destino' => 2,
            'valor' => 500.00,
            'data_transferencia' => '2026-05-10',
            'transferencia' => 'Ajuste de Saldo'
        ]);

        // 2. Execute
        $repository = new ExtratoRepository();
        $saidas = $repository->getTransferenciasDePorContaPeriodo(1, '2026-05-01', '2026-05-31');
        $entradas = $repository->getTransferenciasParaPorContaPeriodo(2, '2026-05-01', '2026-05-31');

        // 3. Verify
        $this->assertCount(1, $saidas);
        $this->assertEquals(-500.00, $saidas[0]['valor']);
        // Contraparte da transferência (conta destino) deve preencher o Cliente/Fornecedor
        $this->assertEquals('Conta Poupança', $saidas[0]['cliente_fornecedor']);

        $this->assertCount(1, $entradas);
        $this->assertEquals(500.00, $entradas[0]['valor']);
        // Contraparte da transferência (conta origem) deve preencher o Cliente/Fornecedor
        $this->assertEquals('Conta Corrente', $entradas[0]['cliente_fornecedor']);
    }

    public function testGetAnteriores()
    {
        // Setup data before 2026-05-01
        $this->db->table('fin_pgto_despesas')->insert([
            'id_pgto_despesa' => 50,
            'conta_id' => 1,
            'valor' => 100.00,
            'pagamento_despesa_dt' => '2026-04-15'
        ]);
        $this->db->table('fin_pgto_receitas')->insert([
            'id_pgto_receita' => 60,
            'conta_id' => 1,
            'valor' => 500.00,
            'pagamento_receita_dt' => '2026-04-20'
        ]);

        $repository = new ExtratoRepository();
        $despesas = $repository->getDespesasAnteriores(1, '2026-05-01');
        $receitas = $repository->getReceitasAnteriores(1, '2026-05-01');

        $this->assertCount(1, $despesas);
        $this->assertEquals(-100.00, $despesas[0]['valor']);
        $this->assertCount(1, $receitas);
        $this->assertEquals(500.00, $receitas[0]['valor']);
    }

    public function testGetSaldosAnterioresAgrupados()
    {
        // 1. Setup Data
        // User 1 (Profissional)
        // User 2 (Profissional)

        // Receipt with rateio (User 1: 300, User 2: 200)
        $this->db->table('fin_pgto_receitas')->insert([
            'id_pgto_receita' => 1,
            'conta_id' => 1,
            'valor' => 500.00,
            'pagamento_receita_dt' => '2026-04-01'
        ]);
        $this->db->table('fin_pgto_receitas_rateio')->insertBatch([
            ['pagto_receita_id' => 1, 'user_id' => 1, 'valor' => 300.00],
            ['pagto_receita_id' => 1, 'user_id' => 2, 'valor' => 200.00],
        ]);

        // Receipt WITHOUT rateio (goes to 'geral')
        $this->db->table('fin_pgto_receitas')->insert([
            'id_pgto_receita' => 2,
            'conta_id' => 1,
            'valor' => 100.00,
            'pagamento_receita_dt' => '2026-04-02'
        ]);

        // Expense with rateio (User 1: 50)
        $this->db->table('fin_pgto_despesas')->insert([
            'id_pgto_despesa' => 1,
            'conta_id' => 1,
            'valor' => 50.00,
            'pagamento_despesa_dt' => '2026-04-03'
        ]);
        $this->db->table('fin_pgto_despesas_rateio')->insert([
            'pagto_despesa_id' => 1, 'user_id' => 1, 'valor' => 50.00
        ]);

        // Transfer Out (Saída) with rateio (User 2: 30)
        $this->db->table('fin_transferencias')->insert([
            'id_transferencia' => 1,
            'id_conta_origem' => 1,
            'id_conta_destino' => 2,
            'valor' => 30.00,
            'data_transferencia' => '2026-04-04'
        ]);
        $this->db->table('fin_transferencias_rateios')->insert([
            'transferencia_id' => 1, 'user_id' => 2, 'valor' => 30.00
        ]);

        // 2. Execute
        $repository = new ExtratoRepository();
        $result = $repository->getSaldosAnterioresAgrupados(1, '2026-05-01');

        // 3. Verify
        // Total: 500 (Rec1) + 100 (Rec2) - 50 (Desp1) - 30 (Transf1) = 520
        $this->assertEquals(520.00, $result['total']);

        // User 1: 300 (Rec1) - 50 (Desp1) = 250
        $this->assertEquals(250.00, $result['rateio'][1]['valor']);

        // User 2: 200 (Rec1) - 30 (Transf1) = 170
        $this->assertEquals(170.00, $result['rateio'][2]['valor']);

        // Geral: 100 (Rec2)
        $this->assertEquals(100.00, $result['rateio']['geral']['valor']);
    }

    /**
     * Regressão: um lançamento com rateio PARCIAL (que não soma 100% do valor,
     * ex.: uma parte fica sem atribuição explícita a nenhum profissional) não pode
     * fazer o "total" do saldo anterior perder a diferença.
     *
     * Antes da correção, o total era reconstruído somando apenas os valores
     * rateados (SUM(rateio.valor)) em vez do valor real da transação. Isso fazia
     * o extrato de um período que inclui essa transação no "saldo anterior"
     * divergir do extrato de um período que ainda não a inclui — exatamente o
     * bug relatado em produção: "Extrato Financeiro" de 01/07 a 31/08 e de
     * 01/08 a 31/08 fechando com saldos diferentes para o mesmo dinheiro real.
     */
    public function testGetSaldosAnterioresAgrupadosComRateioParcialUsaValorTotalDaTransacao()
    {
        // Receita de R$ 1.000,00 em julho, mas rateada só parcialmente (R$ 700,00
        // para o usuário 1). Os R$ 300,00 restantes não têm nenhuma linha de rateio.
        $this->db->table('fin_pgto_receitas')->insert([
            'id_pgto_receita' => 10,
            'conta_id' => 1,
            'valor' => 1000.00,
            'pagamento_receita_dt' => '2026-07-15'
        ]);
        $this->db->table('fin_pgto_receitas_rateio')->insert([
            'pagto_receita_id' => 10, 'user_id' => 1, 'valor' => 700.00
        ]);

        $repository = new ExtratoRepository();

        // Saldo anterior a 01/07: a receita de 15/07 ainda não entra.
        $saldoAnteriorJulho = $repository->getSaldosAnterioresAgrupados(1, '2026-07-01');
        $this->assertEquals(0.00, $saldoAnteriorJulho['total']);

        // Saldo anterior a 01/08: a receita de 15/07 já entra, com o VALOR CHEIO
        // (R$ 1.000,00), e não apenas os R$ 700,00 que foram rateados.
        $saldoAnteriorAgosto = $repository->getSaldosAnterioresAgrupados(1, '2026-08-01');
        $this->assertEquals(1000.00, $saldoAnteriorAgosto['total']);
    }
}
