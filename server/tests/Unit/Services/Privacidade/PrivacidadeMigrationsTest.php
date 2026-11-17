<?php

namespace Tests\Unit\Services\Privacidade;

use App\Database\Migrations\AddPrivacidadeColumns;
use App\Database\Migrations\CreateAcessosEntidades;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * Roda as migrations da privacidade granular isoladamente sobre as tabelas
 * no formato anterior (sem visibilidade/responsavel_id).
 */
class PrivacidadeMigrationsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Arquivos de migration têm prefixo de data e não são autocarregados.
        foreach (glob(APPPATH . 'Database/Migrations/Privacidade/*.php') as $arquivo) {
            require_once $arquivo;
        }

        $forge = Database::forge($this->db);
        $tabelas = [
            'users'         => ['id' => ['type' => 'INTEGER', 'auto_increment' => true]],
            'processos'     => ['id_processo' => ['type' => 'INTEGER', 'auto_increment' => true], 'responsavel_id' => ['type' => 'INTEGER', 'null' => true]],
            'clientes'      => ['id_cliente' => ['type' => 'INTEGER', 'auto_increment' => true], 'nome' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true]],
            'contratos'     => ['id_contrato' => ['type' => 'INTEGER', 'auto_increment' => true], 'objeto' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true]],
            'configuracoes' => [
                'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
                'nome'       => ['type' => 'VARCHAR', 'constraint' => 100],
                'valor'      => ['type' => 'TEXT', 'null' => true],
                'descricao'  => ['type' => 'TEXT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ],
        ];
        foreach (array_merge(['acessos_entidades'], array_keys($tabelas)) as $tabela) {
            $forge->dropTable($tabela, true);
        }
        foreach ($tabelas as $tabela => $campos) {
            $forge->addField($campos);
            $forge->addPrimaryKey(array_key_first($campos));
            $forge->createTable($tabela);
        }

        $this->db->table('clientes')->insert(['nome' => 'Existente']);
    }

    public function testUpCriaEstruturaERegistrosExistentesFicamRestritos(): void
    {
        (new CreateAcessosEntidades(Database::forge($this->db)))->up();
        (new AddPrivacidadeColumns(Database::forge($this->db)))->up();
        $this->db->resetDataCache();

        $this->assertTrue($this->db->tableExists('acessos_entidades'));
        foreach (['processos', 'clientes', 'contratos'] as $tabela) {
            $this->assertTrue($this->db->fieldExists('visibilidade', $tabela), $tabela);
        }
        $this->assertTrue($this->db->fieldExists('responsavel_id', 'clientes'));
        $this->assertTrue($this->db->fieldExists('responsavel_id', 'contratos'));

        $this->seeInDatabase('clientes', ['nome' => 'Existente', 'visibilidade' => 'restrito']);
        $this->seeInDatabase('configuracoes', ['nome' => 'privacidade_granular_ativa', 'valor' => '0']);

        $this->db->table('contratos')->insert(['objeto' => 'Novo']);
        $this->seeInDatabase('contratos', ['objeto' => 'Novo', 'visibilidade' => 'restrito']);
    }

    public function testUpRetomaAposFalhaParcial(): void
    {
        // Estado deixado por uma execução interrompida: parte das colunas já existe.
        $forge = Database::forge($this->db);
        $forge->addColumn('clientes', ['visibilidade' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'restrito']]);
        $forge->addColumn('clientes', ['responsavel_id' => ['type' => 'INTEGER', 'null' => true]]);

        (new CreateAcessosEntidades(Database::forge($this->db)))->up();
        (new AddPrivacidadeColumns(Database::forge($this->db)))->up();
        (new AddPrivacidadeColumns(Database::forge($this->db)))->up();
        $this->db->resetDataCache();

        $this->assertTrue($this->db->fieldExists('visibilidade', 'processos'));
        $this->assertTrue($this->db->fieldExists('responsavel_id', 'contratos'));
        $this->seeNumRecords(1, 'configuracoes', ['nome' => 'privacidade_granular_ativa']);
    }

    public function testUpNaoDuplicaConfiguracaoEDownRemove(): void
    {
        $this->db->table('configuracoes')->insert(['nome' => 'privacidade_granular_ativa', 'valor' => '1']);

        (new CreateAcessosEntidades(Database::forge($this->db)))->up();
        (new AddPrivacidadeColumns(Database::forge($this->db)))->up();
        $this->seeNumRecords(1, 'configuracoes', ['nome' => 'privacidade_granular_ativa']);
        $this->seeInDatabase('configuracoes', ['nome' => 'privacidade_granular_ativa', 'valor' => '1']);

        (new AddPrivacidadeColumns(Database::forge($this->db)))->down();
        (new CreateAcessosEntidades(Database::forge($this->db)))->down();
        $this->dontSeeInDatabase('configuracoes', ['nome' => 'privacidade_granular_ativa']);
        $this->assertFalse($this->db->tableExists('acessos_entidades'));
    }
}
