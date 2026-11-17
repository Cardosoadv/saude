<?php

namespace Tests\Unit\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * A migration das regras de classificação cria e remove a tabela. Só dados fictícios.
 */
class ExtratosRegrasMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();
        require_once APPPATH . 'Database/Migrations/Financeiro/2026-11-03-140000_CreateFinanceiroExtratosRegras.php';
        Database::forge($this->db)->dropTable('fin_extratos_regras', true);
    }

    protected function tearDown(): void
    {
        Database::forge($this->db)->dropTable('fin_extratos_regras', true);
        parent::tearDown();
    }

    public function testCriaATabelaComOsPadroesEDesce(): void
    {
        $migration = new \App\Database\Migrations\CreateFinanceiroExtratosRegras(Database::forge($this->db));
        $migration->up();

        $this->assertTrue($this->db->tableExists('fin_extratos_regras'));
        $this->db->table('fin_extratos_regras')->insert(['nome' => 'Teste', 'padrao' => 'Papelaria Exemplo']);
        $linha = $this->db->table('fin_extratos_regras')->get()->getRowArray();
        $this->assertSame(['ambos', 'ambos', '1'], [$linha['campo'], $linha['sinal'], (string) $linha['ativo']]);
        $this->assertNull($linha['conta_id']);

        $migration->down();
        $this->assertFalse($this->db->tableExists('fin_extratos_regras'));
    }
}
