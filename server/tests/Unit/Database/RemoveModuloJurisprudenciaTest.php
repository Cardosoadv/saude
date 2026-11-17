<?php

namespace Tests\Unit\Database;

use App\Database\Migrations\RemoveModuloJurisprudencia;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * Migration de limpeza do módulo de jurisprudência (Issue #682): parte de uma
 * base com as tabelas `juris_*` criadas pelas migrations originais.
 */
class RemoveModuloJurisprudenciaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Arquivos de migration têm prefixo de data e não são autocarregados.
        require_once APPPATH . 'Database/Migrations/Jurisprudencia/2026-10-29-120000_RemoveModuloJurisprudencia.php';

        $forge = Database::forge($this->db);

        // Dependências mínimas, criadas só se a base de testes ainda não as tiver.
        $forge->addField([
            'id_processo' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
        ]);
        $forge->addPrimaryKey('id_processo');
        $forge->createTable('processos', true);

        $forge->addField([
            'user_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'permission' => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('auth_permissions_users', true);

        $forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'entidade_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'entidade_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('kb_favoritos', true);

        $forge->dropTable('modulos', true);
        $forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nome'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'slug'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'ativo'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('modulos');

        // Estado anterior à limpeza: tabelas do módulo criadas pelas migrations originais.
        $this->migration()->down();
        $this->db->resetDataCache();

        $this->db->table('modulos')->insert(['nome' => 'Processos', 'slug' => 'processos', 'ativo' => 1]);
        $this->db->table('auth_permissions_users')->insertBatch([
            ['user_id' => 1, 'permission' => 'jurisprudencias.access'],
            ['user_id' => 1, 'permission' => 'processos.access'],
        ]);
        $this->db->table('kb_favoritos')->insertBatch([
            ['user_id' => 1, 'entidade_type' => 'jurisprudencia', 'entidade_id' => 10],
            ['user_id' => 1, 'entidade_type' => 'modelo', 'entidade_id' => 20],
        ]);
    }

    protected function tearDown(): void
    {
        $this->migrar();
        $this->db->table('auth_permissions_users')->where('user_id', 1)->delete();
        $this->db->table('kb_favoritos')->where('user_id', 1)->delete();

        parent::tearDown();
    }

    public function testDownRecriaTabelasEModuloDesativado(): void
    {
        foreach (RemoveModuloJurisprudencia::TABELAS as $tabela) {
            if ($tabela !== 'juris_sinonimos') {
                $this->assertTrue($this->db->tableExists($tabela), $tabela);
            }
        }
        $this->seeInDatabase('modulos', ['slug' => 'jurisprudencia', 'ativo' => 0]);
    }

    public function testUpRemoveTabelasModuloEPermissoes(): void
    {
        $this->migrar();

        foreach (RemoveModuloJurisprudencia::TABELAS as $tabela) {
            $this->assertFalse($this->db->tableExists($tabela), $tabela);
        }

        // Taxonomia compartilhada com outros módulos.
        $this->assertTrue($this->db->tableExists('juris_ramos_direito'));

        $this->dontSeeInDatabase('modulos', ['slug' => 'jurisprudencia']);
        $this->seeInDatabase('modulos', ['slug' => 'processos']);

        $this->dontSeeInDatabase('auth_permissions_users', ['permission' => 'jurisprudencias.access']);
        $this->seeInDatabase('auth_permissions_users', ['permission' => 'processos.access']);

        $this->dontSeeInDatabase('kb_favoritos', ['entidade_type' => 'jurisprudencia']);
        $this->seeInDatabase('kb_favoritos', ['entidade_type' => 'modelo']);
    }

    public function testReexecutarUpEDownNaoFalha(): void
    {
        $this->migrar();
        $this->migrar();

        $this->migration()->down();
        $this->db->resetDataCache();
        $this->migration()->down();
        $this->db->resetDataCache();

        $this->assertTrue($this->db->tableExists('juris_jurisprudencias'));
        $this->assertTrue($this->db->fieldExists('nup', 'juris_jurisprudencias'));
        $this->assertTrue($this->db->tableExists('juris_jurisprudencias_similares'));
        $this->seeNumRecords(1, 'modulos', ['slug' => 'jurisprudencia']);
    }

    private function migration(): RemoveModuloJurisprudencia
    {
        return new RemoveModuloJurisprudencia(Database::forge($this->db));
    }

    private function migrar(): void
    {
        $this->migration()->up();
        $this->db->resetDataCache();
    }
}
