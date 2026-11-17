<?php

namespace Tests\Unit\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * A migration da conciliação cria o índice único do vínculo: um pagamento só
 * pode estar em um movimento. Só dados fictícios.
 */
class ExtratosMovimentosVinculoUnicoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();

        require_once APPPATH . 'Database/Migrations/Financeiro/2026-11-03-120000_CreateFinanceiroExtratos.php';
        require_once APPPATH . 'Database/Migrations/Financeiro/2026-11-03-130000_AddConciliacaoExtratosMovimentos.php';

        $this->derrubarTabelas();
        (new \App\Database\Migrations\CreateFinanceiroExtratos(Database::forge($this->db)))->up();
        (new \App\Database\Migrations\AddConciliacaoExtratosMovimentos(Database::forge($this->db)))->up();
    }

    protected function tearDown(): void
    {
        $this->derrubarTabelas();
        parent::tearDown();
    }

    private function derrubarTabelas(): void
    {
        $forge = Database::forge($this->db);
        $forge->dropTable('fin_extratos_movimentos', true);
        $forge->dropTable('fin_extratos_importacoes', true);
    }

    private function inserir(int $conta, string $chave, ?string $tipo, ?int $pagamento): bool
    {
        try {
            return (bool) $this->db->table('fin_extratos_movimentos')->insert([
                'conta_id'       => $conta,
                'importacao_id'  => 1,
                'chave'          => $chave,
                'chave_tipo'     => 'fitid',
                'data'           => '2026-10-05',
                'valor_centavos' => -1050,
                'pagamento_tipo' => $tipo,
                'pagamento_id'   => $pagamento,
                'created_at'     => '2026-10-06 10:00:00',
                'updated_at'     => '2026-10-06 10:00:00',
            ]);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException) {
            return false; // o índice único barrou
        }
    }

    public function testMesmoPagamentoEmDoisMovimentosEhRejeitado(): void
    {
        $this->assertTrue($this->inserir(1, str_repeat('a', 64), 'despesa', 10));
        $this->assertFalse($this->inserir(1, str_repeat('b', 64), 'despesa', 10));
    }

    public function testMovimentosSemVinculoNaoSeBloqueiam(): void
    {
        $this->assertTrue($this->inserir(1, str_repeat('a', 64), null, null));
        $this->assertTrue($this->inserir(1, str_repeat('b', 64), null, null));
    }

    public function testTipoDiferenteEmesmoIdSaoPagamentosDiferentes(): void
    {
        $this->assertTrue($this->inserir(1, str_repeat('a', 64), 'despesa', 10));
        $this->assertTrue($this->inserir(1, str_repeat('b', 64), 'receita', 10));
    }

    public function testTransferenciaTemUmLadoEmCadaConta(): void
    {
        $this->assertTrue($this->inserir(1, str_repeat('a', 64), 'transferencia', 5));
        $this->assertTrue($this->inserir(2, str_repeat('b', 64), 'transferencia', 5));
        $this->assertFalse($this->inserir(1, str_repeat('c', 64), 'transferencia', 5));
    }

    public function testMigrationCriaAColunaDoMotivoEDesce(): void
    {
        $this->assertTrue($this->db->fieldExists('motivo_ignorado', 'fin_extratos_movimentos'));

        (new \App\Database\Migrations\AddConciliacaoExtratosMovimentos(Database::forge($this->db)))->down();

        $this->assertFalse($this->db->fieldExists('motivo_ignorado', 'fin_extratos_movimentos'));
    }
}
