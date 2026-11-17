<?php

namespace Tests\Unit\Database;

use App\Repositories\Financeiro\ConciliacaoCandidatosRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Consultas da conciliação contra tabelas mínimas (SQLite). Só dados fictícios.
 */
class ConciliacaoCandidatosRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private const TABELAS = ['fin_extratos_movimentos', 'fin_transferencias', 'fin_pgto_receitas', 'fin_receitas', 'fin_pgto_despesas', 'fin_despesas', 'fin_fornecedores', 'clientes'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->derrubar();

        $this->sql('CREATE TABLE {T:fin_fornecedores} (id_fornecedor INTEGER PRIMARY KEY, nome TEXT)');
        $this->sql('CREATE TABLE {T:clientes} (id_cliente INTEGER PRIMARY KEY, nome TEXT)');
        $this->sql('CREATE TABLE {T:fin_despesas} (id_despesa INTEGER PRIMARY KEY, despesa TEXT, vencimento_dt TEXT, valor REAL, fornecedor INTEGER, comentario TEXT, deleted_at TEXT)');
        $this->sql('CREATE TABLE {T:fin_pgto_despesas} (id_pgto_despesa INTEGER PRIMARY KEY, pagamento_despesa_dt TEXT, valor REAL, despesa_id INTEGER, conta_id INTEGER, comentario TEXT, deleted_at TEXT)');
        $this->sql('CREATE TABLE {T:fin_receitas} (id_receita INTEGER PRIMARY KEY, receita TEXT, vencimento_dt TEXT, valor REAL, cliente_id INTEGER, comentario TEXT, deleted_at TEXT)');
        $this->sql('CREATE TABLE {T:fin_pgto_receitas} (id_pgto_receita INTEGER PRIMARY KEY, pagamento_receita_dt TEXT, valor REAL, receita_id INTEGER, conta_id INTEGER, comentario TEXT, deleted_at TEXT)');
        $this->sql('CREATE TABLE {T:fin_transferencias} (id_transferencia INTEGER PRIMARY KEY, transferencia TEXT, data_transferencia TEXT, id_conta_origem INTEGER, id_conta_destino INTEGER, valor REAL, comentarios TEXT, deleted_at TEXT)');
        $this->sql('CREATE TABLE {T:fin_extratos_movimentos} (id_movimento INTEGER PRIMARY KEY, conta_id INTEGER, data TEXT, valor_centavos INTEGER, status TEXT, pagamento_tipo TEXT, pagamento_id INTEGER, deleted_at TEXT)');
    }

    protected function tearDown(): void
    {
        $this->derrubar();
        parent::tearDown();
    }

    /** Executa SQL trocando {T:tabela} pelo nome com o prefixo da conexão de teste. */
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

    public function testPagamentoDeDespesaLivreRespeitaContaValorJanelaEVinculo(): void
    {
        $this->sql("INSERT INTO {T:fin_fornecedores} VALUES (1, 'Papelaria Modelo')");
        $this->sql("INSERT INTO {T:fin_despesas} VALUES (1, 'Material', '2026-10-01', 150.10, 1, NULL, NULL)");
        $this->sql("INSERT INTO {T:fin_pgto_despesas} VALUES (10, '2026-10-10', 150.10, 1, 1, NULL, NULL)");   // serve
        $this->sql("INSERT INTO {T:fin_pgto_despesas} VALUES (11, '2026-10-10', 150.10, 1, 2, NULL, NULL)");   // outra conta
        $this->sql("INSERT INTO {T:fin_pgto_despesas} VALUES (12, '2026-10-10', 150.11, 1, 1, NULL, NULL)");   // valor
        $this->sql("INSERT INTO {T:fin_pgto_despesas} VALUES (13, '2026-10-30', 150.10, 1, 1, NULL, NULL)");   // data
        $this->sql("INSERT INTO {T:fin_pgto_despesas} VALUES (14, '2026-10-10', 150.10, 1, 1, NULL, '2026-10-11')"); // excluído
        $this->sql("INSERT INTO {T:fin_pgto_despesas} VALUES (15, '2026-10-11', 150.10, 1, 1, NULL, NULL)");   // vinculado
        $this->sql("INSERT INTO {T:fin_extratos_movimentos} VALUES (1, 1, '2026-10-11', -15010, 'conciliado', 'despesa', 15, NULL)");

        $r = (new ConciliacaoCandidatosRepository($this->db))->pagamentosDespesaLivres(1, 15010, '2026-10-07', '2026-10-13');

        $this->assertCount(1, $r);
        $this->assertSame(10, $r[0]['id']);
        $this->assertSame(15010, $r[0]['valor_centavos']);
        $this->assertSame('Papelaria Modelo', $r[0]['contraparte']);
    }

    public function testDespesaEmAbertoIgnoraAsJaPagas(): void
    {
        $this->sql("INSERT INTO {T:fin_despesas} VALUES (1, 'Aluguel', '2026-10-10', 90.00, NULL, NULL, NULL)");
        $this->sql("INSERT INTO {T:fin_despesas} VALUES (2, 'Paga', '2026-10-10', 90.00, NULL, NULL, NULL)");
        $this->sql("INSERT INTO {T:fin_pgto_despesas} VALUES (1, '2026-10-10', 90.00, 2, 1, NULL, NULL)");

        $r = (new ConciliacaoCandidatosRepository($this->db))->despesasEmAberto(9000, '2026-10-07', '2026-10-13');

        $this->assertSame([1], array_column($r, 'id'));
    }

    public function testReceitaEmAbertoEPagamentoDeReceita(): void
    {
        $this->sql("INSERT INTO {T:clientes} VALUES (1, 'Cliente Modelo')");
        $this->sql("INSERT INTO {T:fin_receitas} VALUES (1, 'Honorários', '2026-10-10', 200.00, 1, NULL, NULL)");
        $this->sql("INSERT INTO {T:fin_receitas} VALUES (2, 'Recebida', '2026-10-09', 200.00, 1, NULL, NULL)");
        $this->sql("INSERT INTO {T:fin_pgto_receitas} VALUES (5, '2026-10-09', 200.00, 2, 1, NULL, NULL)");
        $repo = new ConciliacaoCandidatosRepository($this->db);

        $this->assertSame([1], array_column($repo->receitasEmAberto(20000, '2026-10-07', '2026-10-13'), 'id'));
        $this->assertSame([5], array_column($repo->pagamentosReceitaLivres(1, 20000, '2026-10-07', '2026-10-13'), 'id'));
    }

    public function testTransferenciaLivreEParDeMovimentos(): void
    {
        $this->sql("INSERT INTO {T:fin_transferencias} VALUES (1, 'Reserva', '2026-10-10', 1, 2, 500.00, NULL, NULL)");
        $this->sql("INSERT INTO {T:fin_transferencias} VALUES (2, 'Já vinculada', '2026-10-10', 1, 2, 500.00, NULL, NULL)");
        $this->sql("INSERT INTO {T:fin_extratos_movimentos} VALUES (1, 1, '2026-10-10', -50000, 'conciliado', 'transferencia', 2, NULL)");
        $this->sql("INSERT INTO {T:fin_extratos_movimentos} VALUES (2, 2, '2026-10-11', 50000, 'pendente', NULL, NULL, NULL)");
        $this->sql("INSERT INTO {T:fin_extratos_movimentos} VALUES (3, 2, '2026-10-11', 50000, 'conciliado', 'transferencia', 2, NULL)");
        $repo = new ConciliacaoCandidatosRepository($this->db);

        $this->assertSame([1], array_column($repo->transferenciasLivres(1, 2, 50000, '2026-10-07', '2026-10-13', 1), 'id'));
        $this->assertSame([2], array_column($repo->movimentosParaTransferencia(1, 50000, '2026-10-07', '2026-10-13'), 'id_movimento'));
    }

    public function testObterPagamentoETransferencia(): void
    {
        $this->sql("INSERT INTO {T:fin_pgto_despesas} VALUES (1, '2026-10-10', 12.34, NULL, 3, NULL, NULL)");
        $this->sql("INSERT INTO {T:fin_transferencias} VALUES (1, 'T', '2026-10-10', 1, 2, 5.00, NULL, NULL)");
        $repo = new ConciliacaoCandidatosRepository($this->db);

        $this->assertSame(1234, $repo->obterPagamento('despesa', 1)['valor_centavos']);
        $this->assertNull($repo->obterPagamento('receita', 1));
        $this->assertSame(500, $repo->obterTransferencia(1)['valor_centavos']);
    }
}
