<?php

namespace Tests\Unit\Database;

use App\Repositories\Financeiro\ConciliacaoCandidatosRepository;
use App\Repositories\Financeiro\ExtratosMovimentosRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * Gravação condicional do vínculo, devolução de movimentos para pendente ao excluir o pagamento e leitura
 * de lançamentos em aberto, contra tabelas mínimas (SQLite). Só dados fictícios.
 */
class ExtratosLancamentoRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private const TABELAS = ['fin_extratos_movimentos', 'fin_pgto_despesas', 'fin_despesas', 'fin_pgto_receitas', 'fin_receitas'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->derrubar();

        $this->sql('CREATE TABLE {T:fin_despesas} (id_despesa INTEGER PRIMARY KEY, despesa TEXT, vencimento_dt TEXT, valor REAL, fornecedor INTEGER, comentario TEXT, deleted_at TEXT)');
        $this->sql('CREATE TABLE {T:fin_pgto_despesas} (id_pgto_despesa INTEGER PRIMARY KEY, pagamento_despesa_dt TEXT, valor REAL, despesa_id INTEGER, conta_id INTEGER, comentario TEXT, deleted_at TEXT)');
        $this->sql('CREATE TABLE {T:fin_receitas} (id_receita INTEGER PRIMARY KEY, receita TEXT, vencimento_dt TEXT, valor REAL, cliente_id INTEGER, comentario TEXT, deleted_at TEXT)');
        $this->sql('CREATE TABLE {T:fin_pgto_receitas} (id_pgto_receita INTEGER PRIMARY KEY, pagamento_receita_dt TEXT, valor REAL, receita_id INTEGER, conta_id INTEGER, comentario TEXT, deleted_at TEXT)');
        $this->sql('CREATE TABLE {T:fin_extratos_movimentos} (id_movimento INTEGER PRIMARY KEY, conta_id INTEGER, data TEXT, valor_centavos INTEGER, status TEXT DEFAULT \'pendente\', pagamento_tipo TEXT, pagamento_id INTEGER, motivo_ignorado TEXT, conciliado_por INTEGER, conciliado_em TEXT, updated_at TEXT, deleted_at TEXT)');
        $this->sql('CREATE UNIQUE INDEX {T:uq_mov_pag} ON {T:fin_extratos_movimentos} (conta_id, pagamento_tipo, pagamento_id)');
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

    private function movimento(int $id, string $status, ?string $tipo = null, ?int $pagamento = null, int $conta = 1): void
    {
        $this->db->table('fin_extratos_movimentos')->insert([
            'id_movimento' => $id, 'conta_id' => $conta, 'data' => '2026-10-10', 'valor_centavos' => -1000,
            'status' => $status, 'pagamento_tipo' => $tipo, 'pagamento_id' => $pagamento,
        ]);
    }

    private function linha(int $id): array
    {
        return $this->db->table('fin_extratos_movimentos')->where('id_movimento', $id)->get()->getRowArray();
    }

    public function testMarcarLancadoSoGravaUmaVez(): void
    {
        $this->movimento(1, 'pendente');
        $repo = new ExtratosMovimentosRepository();
        $dados = ['status' => 'lancado', 'pagamento_tipo' => 'despesa', 'pagamento_id' => 10, 'conciliado_por' => 9];

        $this->assertTrue($repo->marcarLancado(1, $dados));
        $this->assertFalse($repo->marcarLancado(1, ['pagamento_id' => 11] + $dados), 'segunda tentativa não encontra o movimento pendente');

        $l = $this->linha(1);
        $this->assertSame(['lancado', 'despesa', '10'], [$l['status'], $l['pagamento_tipo'], (string) $l['pagamento_id']]);
    }

    public function testMarcarLancadoNaoSobrescreveMovimentoConciliadoIgnoradoOuInexistente(): void
    {
        $this->movimento(1, 'conciliado', 'despesa', 5);
        $this->movimento(2, 'ignorado');
        $repo  = new ExtratosMovimentosRepository();
        $dados = ['status' => 'lancado', 'pagamento_tipo' => 'despesa', 'pagamento_id' => 10];

        $this->assertFalse($repo->marcarLancado(1, $dados));
        $this->assertFalse($repo->marcarLancado(2, $dados));
        $this->assertFalse($repo->marcarLancado(99, $dados));
        $this->assertSame('conciliado', $this->linha(1)['status']);
    }

    public function testMarcarLancadoComPagamentoJaVinculadoEhBarradoPeloIndiceUnico(): void
    {
        $this->movimento(1, 'lancado', 'despesa', 10);
        $this->movimento(2, 'pendente');

        $this->assertFalse((new ExtratosMovimentosRepository())->marcarLancado(2, ['status' => 'lancado', 'pagamento_tipo' => 'despesa', 'pagamento_id' => 10]));
        $this->assertSame('pendente', $this->linha(2)['status']);
    }

    public function testLiberarPorPagamentosDevolveParaPendenteELimpaOVinculo(): void
    {
        $this->movimento(1, 'lancado', 'despesa', 10);
        $this->movimento(2, 'conciliado', 'despesa', 11);
        $this->movimento(3, 'lancado', 'despesa', 12);            // outro pagamento: fica
        $this->movimento(4, 'lancado', 'receita', 10);            // outro tipo: fica
        $this->movimento(5, 'ignorado');

        $liberados = (new ExtratosMovimentosRepository())->liberarPorPagamentos('despesa', [10, 11]);

        sort($liberados);
        $this->assertSame([1, 2], $liberados);
        foreach ([1, 2] as $id) {
            $l = $this->linha($id);
            $this->assertSame('pendente', $l['status']);
            $this->assertNull($l['pagamento_tipo']);
            $this->assertNull($l['pagamento_id']);
        }
        $this->assertSame('lancado', $this->linha(3)['status']);
        $this->assertSame('lancado', $this->linha(4)['status']);
        $this->assertSame('ignorado', $this->linha(5)['status']);
        $this->assertNotNull($this->db->table('fin_extratos_movimentos')->where('id_movimento', 1)->get()->getRow(), 'o movimento nunca é apagado');
    }

    public function testLiberarTransferenciaDevolveOsDoisLadosELiberaOVinculoParaNovoLancamento(): void
    {
        $this->movimento(1, 'lancado', 'transferencia', 7, 1);
        $this->movimento(2, 'lancado', 'transferencia', 7, 2);
        $repo = new ExtratosMovimentosRepository();

        $this->assertCount(2, $repo->liberarPorPagamentos('transferencia', [7]));
        $this->assertSame([], $repo->liberarPorPagamentos('transferencia', [7]), 'repetir não altera nada');

        $this->assertTrue($repo->marcarLancado(1, ['status' => 'lancado', 'pagamento_tipo' => 'transferencia', 'pagamento_id' => 7]), 'o vínculo ficou livre');
    }

    public function testLiberarSemIdsNaoFazNada(): void
    {
        $this->movimento(1, 'lancado', 'despesa', 10);

        $this->assertSame([], (new ExtratosMovimentosRepository())->liberarPorPagamentos('despesa', []));
        $this->assertSame('lancado', $this->linha(1)['status']);
    }

    public function testObterEmAbertoInformaSeJaTemPagamento(): void
    {
        $this->sql("INSERT INTO {T:fin_despesas} VALUES (1, 'Material', '2026-10-01', 150.10, 1, NULL, NULL)");
        $this->sql("INSERT INTO {T:fin_despesas} VALUES (2, 'Aluguel', '2026-10-05', 900.00, 1, NULL, NULL)");
        $this->sql("INSERT INTO {T:fin_pgto_despesas} VALUES (10, '2026-10-10', 900.00, 2, 1, NULL, NULL)");
        $this->sql("INSERT INTO {T:fin_pgto_despesas} VALUES (11, '2026-10-10', 150.10, 1, 1, NULL, '2026-10-11 10:00:00')");   // excluído
        $this->sql("INSERT INTO {T:fin_receitas} VALUES (1, 'Honorários', '2026-10-02', 500.00, 1, NULL, NULL)");

        $repo = new ConciliacaoCandidatosRepository(Database::connect());

        $aberta = $repo->obterEmAberto('despesa', 1);
        $this->assertSame([1, 15010, false], [$aberta['id'], $aberta['valor_centavos'], $aberta['paga']], 'pagamento excluído não conta');
        $this->assertTrue($repo->obterEmAberto('despesa', 2)['paga']);
        $this->assertSame(50000, $repo->obterEmAberto('receita', 1)['valor_centavos']);
        $this->assertNull($repo->obterEmAberto('despesa', 99));
    }
}
