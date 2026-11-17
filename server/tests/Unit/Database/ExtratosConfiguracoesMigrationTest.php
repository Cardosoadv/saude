<?php

namespace Tests\Unit\Database;

use App\Services\Financeiro\ExtratoConfig;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * A migration cria as configurações de extratos sem sobrescrever valor já ajustado e sem duplicar.
 */
class ExtratosConfiguracoesMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();

        require_once APPPATH . 'Database/Migrations/Financeiro/2026-11-03-160000_AddConfiguracoesExtratos.php';

        $forge = Database::forge($this->db);
        $forge->dropTable('configuracoes', true);
        $forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nome'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'valor'      => ['type' => 'TEXT', 'null' => true],
            'descricao'  => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('configuracoes');
    }

    protected function tearDown(): void
    {
        Database::forge($this->db)->dropTable('configuracoes', true);
        parent::tearDown();
    }

    private function migration(): \App\Database\Migrations\AddConfiguracoesExtratos
    {
        return new \App\Database\Migrations\AddConfiguracoesExtratos(Database::forge($this->db));
    }

    public function testCriaAsConfiguracoesComOsPadroesEEhIdempotente(): void
    {
        $this->migration()->up();
        $this->migration()->up();

        $linhas = $this->db->table('configuracoes')->get()->getResultArray();
        $this->assertCount(count(ExtratoConfig::DEFINICOES), $linhas);
        $porNome = array_column($linhas, 'valor', 'nome');
        $this->assertSame('5', $porNome[ExtratoConfig::MAX_MB]);
        $this->assertSame('3', $porNome[ExtratoConfig::JANELA_DIAS]);
    }

    public function testNaoSobrescreveValorJaAjustadoEDesceLimpando(): void
    {
        $this->db->table('configuracoes')->insert(['nome' => ExtratoConfig::MAX_MB, 'valor' => '12']);

        $this->migration()->up();

        $this->assertSame('12', $this->db->table('configuracoes')->where('nome', ExtratoConfig::MAX_MB)->get()->getRowArray()['valor']);

        $this->migration()->down();
        $this->assertSame(0, $this->db->table('configuracoes')->countAllResults());
    }
}
