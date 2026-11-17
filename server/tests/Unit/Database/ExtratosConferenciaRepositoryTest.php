<?php

namespace Tests\Unit\Database;

use App\Repositories\Financeiro\ComprovantesRepository;
use App\Repositories\Financeiro\ExtratosImportacoesRepository;
use App\Repositories\Financeiro\ExtratosMovimentosRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Consultas da conferência de saldo e das pendências de comprovante vindas do extrato,
 * contra tabelas mínimas (SQLite). Só dados fictícios.
 */
class ExtratosConferenciaRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private const TABELAS = ['fin_extratos_importacoes', 'fin_extratos_movimentos', 'fin_pgto_comprovantes', 'fin_pgto_despesas', 'fin_pgto_receitas', 'fin_contas'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->derrubar();

        $this->sql('CREATE TABLE {T:fin_extratos_importacoes} (id_importacao INTEGER PRIMARY KEY, conta_id INTEGER, status TEXT, saldo_final_arquivo INTEGER, saldo_final_data TEXT, deleted_at TEXT)');
        $this->sql('CREATE TABLE {T:fin_extratos_movimentos} (id_movimento INTEGER PRIMARY KEY, conta_id INTEGER, data TEXT, valor_centavos INTEGER, status TEXT, pagamento_tipo TEXT, pagamento_id INTEGER, deleted_at TEXT)');
        $this->sql('CREATE TABLE {T:fin_contas} (id_conta INTEGER PRIMARY KEY, conta TEXT)');
        $this->sql('CREATE TABLE {T:fin_pgto_despesas} (id_pgto_despesa INTEGER PRIMARY KEY, pagamento_despesa_dt TEXT, valor REAL, conta_id INTEGER, deleted_at TEXT)');
        $this->sql('CREATE TABLE {T:fin_pgto_receitas} (id_pgto_receita INTEGER PRIMARY KEY, pagamento_receita_dt TEXT, valor REAL, conta_id INTEGER, deleted_at TEXT)');
        $this->sql('CREATE TABLE {T:fin_pgto_comprovantes} (id_comprovante INTEGER PRIMARY KEY, tipo TEXT, pagamento_id INTEGER, deleted_at TEXT)');
    }

    protected function tearDown(): void
    {
        $this->derrubar();
        parent::tearDown();
    }

    private function sql(string $sql): void
    {
        $this->db->query(preg_replace_callback('/\{T:(\w+)\}/', fn ($m) => $this->db->prefixTable($m[1]), $sql));
    }

    private function derrubar(): void
    {
        foreach (self::TABELAS as $t) {
            $this->db->query('DROP TABLE IF EXISTS ' . $this->db->prefixTable($t));
        }
    }

    private function importacao(int $id, int $conta, string $status, ?int $saldo, ?string $data): void
    {
        $this->db->table('fin_extratos_importacoes')->insert([
            'id_importacao' => $id, 'conta_id' => $conta, 'status' => $status, 'saldo_final_arquivo' => $saldo, 'saldo_final_data' => $data,
        ]);
    }

    private function movimento(int $id, int $conta, string $data, string $status, ?string $tipo = null, ?int $pagamento = null, ?string $excluido = null): void
    {
        $this->db->table('fin_extratos_movimentos')->insert([
            'id_movimento' => $id, 'conta_id' => $conta, 'data' => $data, 'valor_centavos' => -1000,
            'status' => $status, 'pagamento_tipo' => $tipo, 'pagamento_id' => $pagamento, 'deleted_at' => $excluido,
        ]);
    }

    public function testUltimoSaldoNoPeriodoPegaOMaisRecenteConcluidoDaConta(): void
    {
        $this->importacao(1, 1, 'concluida', 10000, '2026-10-15');
        $this->importacao(2, 1, 'concluida', 20000, '2026-10-30');
        $this->importacao(3, 1, 'processando', 30000, '2026-10-31');   // não concluída
        $this->importacao(4, 2, 'concluida', 40000, '2026-10-31');     // outra conta
        $this->importacao(5, 1, 'concluida', 50000, '2026-11-02');     // fora do mês
        $this->importacao(6, 1, 'concluida', 60000, null);             // sem data do saldo
        $this->importacao(7, 1, 'concluida', null, '2026-10-31');      // sem saldo

        $r = (new ExtratosImportacoesRepository())->ultimoSaldoNoPeriodo(1, '2026-10-01', '2026-10-31');

        $this->assertSame(2, (int) $r['id_importacao']);
        $this->assertSame(20000, (int) $r['saldo_final_arquivo']);
        $this->assertNull((new ExtratosImportacoesRepository())->ultimoSaldoNoPeriodo(3, '2026-10-01', '2026-10-31'));
    }

    public function testPendentesAteADataEContagemPorStatus(): void
    {
        $this->movimento(1, 1, '2026-10-05', 'pendente');
        $this->movimento(2, 1, '2026-10-30', 'pendente');
        $this->movimento(3, 1, '2026-10-31', 'pendente');                       // depois da data do saldo
        $this->movimento(4, 1, '2026-10-06', 'ignorado');
        $this->movimento(5, 1, '2026-10-07', 'conciliado', 'despesa', 10);
        $this->movimento(6, 1, '2026-10-08', 'lancado', 'receita', 20);
        $this->movimento(7, 2, '2026-10-09', 'pendente');                       // outra conta
        $this->movimento(8, 1, '2026-10-09', 'pendente', null, null, '2026-10-10'); // excluído
        $this->movimento(9, 1, '2026-09-30', 'pendente');                       // fora do mês

        $repo = new ExtratosMovimentosRepository();

        $this->assertSame([9, 1, 2], array_map(static fn ($m) => (int) $m['id_movimento'], $repo->listarPendentesAte(1, '2026-10-30')));

        $porStatus = $repo->contarPorStatus('2026-10-01', '2026-10-31');
        $this->assertSame(['pendente' => 3, 'conciliado' => 1, 'lancado' => 1, 'ignorado' => 1], $porStatus[1]);
        $this->assertSame(['pendente' => 1, 'conciliado' => 0, 'lancado' => 0, 'ignorado' => 0], $porStatus[2]);
        $this->assertSame([1], array_keys($repo->contarPorStatus('2026-10-01', '2026-10-31', 1)));
    }

    public function testPendenciasDeComprovanteIndicamQuantasVieramDoExtrato(): void
    {
        $this->sql("INSERT INTO {T:fin_contas} VALUES (1, 'Banco Modelo')");
        $this->sql("INSERT INTO {T:fin_pgto_despesas} VALUES (10, '2026-10-05', 100.00, 1, NULL)");   // do extrato, sem comprovante
        $this->sql("INSERT INTO {T:fin_pgto_despesas} VALUES (11, '2026-10-06', 50.00, 1, NULL)");    // manual, sem comprovante
        $this->sql("INSERT INTO {T:fin_pgto_despesas} VALUES (12, '2026-10-07', 70.00, 1, NULL)");    // do extrato, com comprovante
        $this->sql("INSERT INTO {T:fin_pgto_despesas} VALUES (13, '2026-10-08', 20.00, 1, NULL)");    // vínculo desfeito (pendente)
        $this->sql("INSERT INTO {T:fin_pgto_receitas} VALUES (20, '2026-10-09', 300.00, 1, NULL)");   // do extrato, lançado
        $this->sql("INSERT INTO {T:fin_pgto_comprovantes} VALUES (1, 'despesa', 12, NULL)");
        $this->movimento(1, 1, '2026-10-05', 'conciliado', 'despesa', 10);
        $this->movimento(2, 1, '2026-10-07', 'lancado', 'despesa', 12);
        $this->movimento(3, 1, '2026-10-08', 'pendente', 'despesa', 13);
        $this->movimento(4, 1, '2026-10-09', 'lancado', 'receita', 20);

        $linhas = (new ComprovantesRepository())->pendenciasDoMes(2026, 10);
        $porTipo = array_column($linhas, null, 'tipo');

        $this->assertSame(3, $porTipo['despesa']['total']);        // 10, 11 e 13
        $this->assertSame(1, $porTipo['despesa']['do_extrato']);   // só a 10
        $this->assertSame(1, $porTipo['receita']['total']);
        $this->assertSame(1, $porTipo['receita']['do_extrato']);
    }
}
