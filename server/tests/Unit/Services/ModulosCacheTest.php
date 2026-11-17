<?php

namespace Tests\Unit\Services;

use App\Repositories\BaseRepository;
use App\Services\ModulosService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * Garante que ativar/desativar um módulo invalida o cache persistente do
 * repositório: a listagem e os módulos ativos refletem a mudança na hora.
 */
class ModulosCacheTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();

        $forge = Database::forge($this->db);
        foreach (['modulos', 'auditoria'] as $tabela) {
            $forge->dropTable($tabela, true);
        }

        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'nome'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'slug'       => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'ativo'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('modulos');

        $forge->addField([
            'id'            => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id'       => ['type' => 'INTEGER', 'null' => true],
            'table_name'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'action_type'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'dados_antigos' => ['type' => 'TEXT', 'null' => true],
            'dados_novos'   => ['type' => 'TEXT', 'null' => true],
            'ip_address'    => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('auditoria');

        // user_id() da auditoria consulta as settings do Shield.
        $this->db->query('CREATE TABLE IF NOT EXISTS ' . $this->db->prefixTable('settings')
            . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, class TEXT, key TEXT, value TEXT, type TEXT, context TEXT,'
            . ' created_at DATETIME, updated_at DATETIME)');

        $this->db->table('modulos')->insertBatch([
            ['nome' => 'Objetos', 'slug' => 'objetos', 'ativo' => 1],
            ['nome' => 'Clientes', 'slug' => 'clientes', 'ativo' => 1],
        ]);

        service('cache')->clean();
        BaseRepository::clearVersions();
        $this->limparCacheDeRequisicao();
    }

    public function testDesativarRefleteNaListagemENosAtivos(): void
    {
        $service = new ModulosService();

        // Aquece o cache persistente.
        $this->assertTrue($service->isActive('objetos'));
        $this->assertTrue($this->ativoNaListagem($service, 'objetos'));

        $service->alterarStatus('objetos', false);

        // Nova requisição: só o cache persistente sobrevive.
        $this->limparCacheDeRequisicao();
        $outra = new ModulosService();

        $this->assertFalse($outra->isActive('objetos'));
        $this->assertFalse($this->ativoNaListagem($outra, 'objetos'));
        $this->assertTrue($outra->isActive('clientes'));
        $this->seeInDatabase('auditoria', ['table_name' => 'modulos', 'action_type' => 'UPDATE']);
    }

    private function ativoNaListagem(ModulosService $service, string $slug): bool
    {
        foreach ($service->listar() as $modulo) {
            if ($modulo['slug'] === $slug) {
                return $modulo['ativo'];
            }
        }

        $this->fail("Módulo {$slug} fora da listagem.");
    }

    private function limparCacheDeRequisicao(): void
    {
        $propriedade = new \ReflectionProperty(ModulosService::class, 'activeModules');
        $propriedade->setValue(null, null);
        (new \ReflectionProperty(ModulosService::class, 'configurado'))->setValue(null, null);
        BaseRepository::clearVersions();
    }
}
