<?php

namespace Tests\Unit\Database;

use App\Models\Financeiro\FinanceiroExtratosMovimentosModel;
use App\Repositories\Financeiro\ExtratosMovimentosRepository;
use App\Services\Financeiro\ChaveMovimentoExtrato;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * A migration dos extratos cria o índice único (conta_id, chave), que é a
 * garantia final de idempotência. Só dados fictícios.
 */
class ExtratosMovimentosIndiceUnicoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();

        require_once APPPATH . 'Database/Migrations/Financeiro/2026-11-03-120000_CreateFinanceiroExtratos.php';

        $this->derrubarTabelas();
        (new \App\Database\Migrations\CreateFinanceiroExtratos(Database::forge($this->db)))->up();
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

    private function linha(int $conta, string $chave, array $extra = []): array
    {
        return $extra + [
            'conta_id'       => $conta,
            'importacao_id'  => 1,
            'chave'          => $chave,
            'chave_tipo'     => 'fitid',
            'fitid'          => 'FIT-1',
            'data'           => '2026-10-05',
            'valor_centavos' => -1050,
            'created_at'     => '2026-10-06 10:00:00',
            'updated_at'     => '2026-10-06 10:00:00',
        ];
    }

    public function testMigrationCriaAsDuasTabelas(): void
    {
        $this->assertTrue($this->db->tableExists('fin_extratos_importacoes'));
        $this->assertTrue($this->db->tableExists('fin_extratos_movimentos'));
    }

    public function testInserirDuasVezesContaEChaveIguaisFalhaNoBanco(): void
    {
        $chave = ChaveMovimentoExtrato::chavePorFitid('999', '1', '123456', 'FIT-1');
        $tabela = $this->db->table('fin_extratos_movimentos');

        $this->assertTrue($tabela->insert($this->linha(1, $chave)));

        // Com DBDebug ligado (o padrão nos testes) o driver lança em vez de devolver false.
        try {
            $this->assertFalse($this->db->table('fin_extratos_movimentos')->insert($this->linha(1, $chave)));
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(1, $this->db->table('fin_extratos_movimentos')->countAllResults());
    }

    public function testMesmaChaveEmContasDiferentesEPermitida(): void
    {
        $chave = ChaveMovimentoExtrato::chavePorFitid('999', '1', '123456', 'FIT-1');

        $this->assertTrue($this->db->table('fin_extratos_movimentos')->insert($this->linha(1, $chave)));
        $this->assertTrue($this->db->table('fin_extratos_movimentos')->insert($this->linha(2, $chave)));
    }

    public function testLinhaExcluidaContinuaOcupandoAChave(): void
    {
        $chave = ChaveMovimentoExtrato::chavePorFitid('999', '1', '123456', 'FIT-1');
        $this->db->table('fin_extratos_movimentos')->insert($this->linha(1, $chave, ['deleted_at' => '2026-10-07 09:00:00']));

        $repo = new ExtratosMovimentosRepository(new FinanceiroExtratosMovimentosModel());

        $this->assertArrayHasKey($chave, $repo->buscarPorChaves(1, [$chave]));
        $this->assertNull($repo->inserirSeNovo($this->linha(1, $chave)));
    }

    public function testRepositorioInsereUmaVezESinalizaDuplicata(): void
    {
        $chave = ChaveMovimentoExtrato::chavePorFitid('999', '1', '123456', 'FIT-2');
        $repo  = new ExtratosMovimentosRepository(new FinanceiroExtratosMovimentosModel());

        $dados = $this->linha(1, $chave, ['fitid' => 'FIT-2']);
        unset($dados['created_at'], $dados['updated_at']);

        // Sem auditoria: a tabela de auditoria não existe neste teste (o serviço de importação também grava sem ela).
        $this->assertIsInt($repo->inserirSeNovo($dados, false));
        $this->assertNull($repo->inserirSeNovo($dados, false));
        $this->assertSame(1, $this->db->table('fin_extratos_movimentos')->countAllResults());
    }

    public function testMigrationDesce(): void
    {
        (new \App\Database\Migrations\CreateFinanceiroExtratos(Database::forge($this->db)))->down();

        $this->assertFalse($this->db->tableExists('fin_extratos_movimentos'));
        $this->assertFalse($this->db->tableExists('fin_extratos_importacoes'));
    }
}
