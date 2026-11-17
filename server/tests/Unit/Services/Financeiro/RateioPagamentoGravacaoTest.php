<?php

namespace Tests\Unit\Services\Financeiro;

use App\Repositories\Financeiro\PagamentoDespesasRateioRepository;
use App\Repositories\Financeiro\PagamentoDespesasRepository;
use App\Repositories\Financeiro\PagamentoReceitasRateioRepository;
use App\Repositories\Financeiro\PagamentoReceitasRepository;
use App\Services\Financeiro\PagamentoDespesasService;
use App\Services\Financeiro\PagamentoReceitasService;
use App\Services\Financeiro\SaldoMensalService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * O rateio do pagamento precisa chegar ao banco também quando o id do sócio vem como inteiro
 * (como no lançamento pelo extrato), não só como texto (como na tela). SQLite em memória, dados fictícios.
 */
class RateioPagamentoGravacaoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();
        $forge = Database::forge($this->db);
        foreach (['despesas', 'receitas'] as $t) {
            $forge->dropTable("fin_pgto_{$t}_rateio", true);
            $forge->dropTable("fin_pgto_{$t}", true);
        }
        foreach (['despesa' => 'despesas', 'receita' => 'receitas'] as $um => $t) {
            $forge->addField([
                "id_pgto_{$um}" => ['type' => 'INT', 'auto_increment' => true],
                "{$um}_id" => ['type' => 'INT', 'null' => true],
                'conta_id' => ['type' => 'INT', 'null' => true],
                'valor' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                "pagamento_{$um}_dt" => ['type' => 'DATE', 'null' => true],
                'comentario' => ['type' => 'TEXT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addPrimaryKey("id_pgto_{$um}");
            $forge->createTable("fin_pgto_{$t}");
            $forge->addField([
                'id_rateio' => ['type' => 'INT', 'auto_increment' => true],
                "pagto_{$um}_id" => ['type' => 'INT'],
                'user_id' => ['type' => 'INT', 'null' => true],
                'valor' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            ]);
            $forge->addPrimaryKey('id_rateio');
            $forge->createTable("fin_pgto_{$t}_rateio");
        }
    }

    protected function tearDown(): void
    {
        $forge = Database::forge($this->db);
        foreach (['despesas', 'receitas'] as $t) {
            $forge->dropTable("fin_pgto_{$t}_rateio", true);
            $forge->dropTable("fin_pgto_{$t}", true);
        }
        parent::tearDown();
    }

    /** Sem auditoria (precisa de usuário logado) nem fechamento de mês. */
    private function modelo(string $nome)
    {
        return model($nome)->allowCallbacks(false);
    }

    public function testDespesaGravaRateioComIdInteiro(): void
    {
        $svc = new PagamentoDespesasService(
            new PagamentoDespesasRepository($this->modelo('Financeiro/FinanceiroPagtoDespesasModel')),
            null,
            null,
            null,
            new PagamentoDespesasRateioRepository($this->modelo('Financeiro/FinanceiroPagtoDespesasRateioModel')),
            $this->createMock(SaldoMensalService::class)
        );

        $id = $svc->create([
            'despesa_id' => 1, 'conta_id' => 1, 'valor' => 100.0, 'pagamento_despesa_dt' => '2026-10-10',
            'rateio' => [['id' => 1, 'valor' => 60.0], ['id' => 2, 'valor' => 40.0]],
        ]);

        $linhas = $this->db->table('fin_pgto_despesas_rateio')->where('pagto_despesa_id', $id)->orderBy('user_id')->get()->getResultArray();
        $this->assertCount(2, $linhas);
        $this->assertEqualsWithDelta(60.0, (float) $linhas[0]['valor'], 0.001);
        $this->assertEqualsWithDelta(40.0, (float) $linhas[1]['valor'], 0.001);
    }

    public function testReceitaGravaRateioComIdInteiro(): void
    {
        $svc = new PagamentoReceitasService(
            new PagamentoReceitasRepository($this->modelo('Financeiro/FinanceiroPagtoReceitasModel')),
            null,
            new PagamentoReceitasRateioRepository($this->modelo('Financeiro/FinanceiroPagtoReceitasRateioModel')),
            $this->createMock(SaldoMensalService::class)
        );

        $id = $svc->create([
            'receita_id' => 1, 'conta_id' => 1, 'valor' => 100.0, 'pagamento_receita_dt' => '2026-10-10',
            'rateio' => [['id' => 1, 'valor' => 60.0], ['id' => 2, 'valor' => 40.0]],
        ]);

        $linhas = $this->db->table('fin_pgto_receitas_rateio')->where('pagto_receita_id', $id)->orderBy('user_id')->get()->getResultArray();
        $this->assertCount(2, $linhas);
        $this->assertEqualsWithDelta(60.0, (float) $linhas[0]['valor'], 0.001);
    }

    public function testRateioComIdTextoDaTelaContinuaGravando(): void
    {
        $svc = new PagamentoDespesasService(
            new PagamentoDespesasRepository($this->modelo('Financeiro/FinanceiroPagtoDespesasModel')),
            null,
            null,
            null,
            new PagamentoDespesasRateioRepository($this->modelo('Financeiro/FinanceiroPagtoDespesasRateioModel')),
            $this->createMock(SaldoMensalService::class)
        );

        $id = $svc->create([
            'despesa_id' => 1, 'conta_id' => 1, 'valor' => '100,00', 'pagamento_despesa_dt' => '2026-10-10',
            'rateio' => [['id' => '3', 'valor' => '100,00']],
        ]);

        $this->assertSame(1, $this->db->table('fin_pgto_despesas_rateio')->where('pagto_despesa_id', $id)->countAllResults());
    }
}
