<?php

namespace Tests\Unit\Database;

use App\Database\Migrations\CorrigeTabelaModulos;
use App\Database\Seeds\ModulosSeeder;
use App\Repositories\BaseRepository;
use App\Repositories\ModulosRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * Roda a migration de correção da tabela `modulos` sobre a tabela no formato
 * anterior (sem slug), vazia (instalação nova) ou com os nomes antigos.
 */
class CorrigeTabelaModulosTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Arquivos de migration têm prefixo de data e não são autocarregados.
        require_once APPPATH . 'Database/Migrations/Gestao/2026-10-04-120000_CorrigeTabelaModulos.php';

        $forge = Database::forge($this->db);
        $forge->dropTable('modulos', true);
        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'nome'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'ativo'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('modulos');
    }

    public function testInstalacaoNovaRecebeCatalogoAtivo(): void
    {
        $this->migrar();

        $this->assertTrue($this->db->fieldExists('slug', 'modulos'));
        $this->seeNumRecords(count(ModulosSeeder::CATALOGO), 'modulos', []);
        $this->seeNumRecords(
            count(ModulosSeeder::CATALOGO) - count(ModulosSeeder::DESATIVADOS_POR_PADRAO),
            'modulos',
            ['ativo' => 1],
        );
        $this->dontSeeInDatabase('modulos', ['slug' => 'jurisprudencia']);
        $this->seeInDatabase('modulos', ['slug' => 'objetos', 'nome' => 'Objetos']);
        $this->assertTrue($this->temIndiceUnicoEmSlug());
    }

    public function testInstalacaoAntigaPreservaNomeEStatus(): void
    {
        $agora = date('Y-m-d H:i:s');
        $this->db->table('modulos')->insertBatch([
            ['nome' => 'Processos', 'ativo' => 1, 'deleted_at' => null],
            ['nome' => 'Processos-Objetos-Imoveis', 'ativo' => 1, 'deleted_at' => null],
            ['nome' => 'Google Service', 'ativo' => 1, 'deleted_at' => null],
            ['nome' => 'Tarefas', 'ativo' => 1, 'deleted_at' => null],
            ['nome' => 'Financeiro', 'ativo' => 0, 'deleted_at' => null],
            ['nome' => 'Casos', 'ativo' => 1, 'deleted_at' => $agora],
            ['nome' => 'Módulo Próprio', 'ativo' => 1, 'deleted_at' => null],
        ]);

        $this->migrar();

        $this->seeInDatabase('modulos', ['nome' => 'Processos-Objetos-Imoveis', 'slug' => 'objetos']);
        $this->seeInDatabase('modulos', ['nome' => 'Google Service', 'slug' => 'google']);
        $this->seeInDatabase('modulos', ['nome' => 'Tarefas', 'slug' => 'tarefas']);
        $this->seeInDatabase('modulos', ['nome' => 'Financeiro', 'slug' => 'financeiro', 'ativo' => 0]);
        $this->seeInDatabase('modulos', ['nome' => 'Módulo Próprio', 'slug' => 'modulo-proprio']);

        // Excluído conta como existente: não é recriado.
        $this->seeNumRecords(1, 'modulos', ['slug' => 'casos']);
        $this->seeNumRecords(1, 'modulos', ['slug' => 'objetos']);

        // Catálogo completo + o módulo fora do catálogo.
        $this->seeNumRecords(count(ModulosSeeder::CATALOGO) + 1, 'modulos', []);
    }

    public function testNomesRepetidosRecebemSlugsDistintos(): void
    {
        $this->db->table('modulos')->insertBatch([
            ['nome' => 'Intimacoes', 'ativo' => 1, 'deleted_at' => null],
            ['nome' => 'Intimações', 'ativo' => 0, 'deleted_at' => null],
        ]);

        $this->migrar();

        $slugs = array_column(
            $this->db->table('modulos')->like('nome', 'Intima', 'after')->orderBy('id')->get()->getResultArray(),
            'slug',
        );
        $this->assertSame('intimacoes', $slugs[0]);
        $this->assertMatchesRegularExpression('/^intimacoes-\d+$/', $slugs[1]);
    }

    public function testInvalidaCacheGravadoAntesDaMigration(): void
    {
        $this->db->table('modulos')->insert(['nome' => 'Processos', 'ativo' => 1, 'deleted_at' => null]);
        service('cache')->clean();
        BaseRepository::clearVersions();

        // Cache aquecido com as linhas antigas, sem slug.
        $antes = (new ModulosRepository())->getActiveModules();
        $this->assertArrayNotHasKey('slug', $antes[0]);

        $this->migrar();
        BaseRepository::clearVersions();

        $depois = array_column((new ModulosRepository())->getActiveModules(), 'slug');
        $this->assertContains('processos', $depois);
    }

    public function testReexecutarNaoDuplica(): void
    {
        $this->migrar();
        $this->migrar();

        $this->seeNumRecords(count(ModulosSeeder::CATALOGO), 'modulos', []);
    }

    public function testDownRemoveSlug(): void
    {
        $this->migrar();
        (new CorrigeTabelaModulos(Database::forge($this->db)))->down();
        $this->db->resetDataCache();

        $this->assertFalse($this->db->fieldExists('slug', 'modulos'));
    }

    public function testSlugDoNome(): void
    {
        $this->assertSame('business-canvas', ModulosSeeder::slugDoNome('Business Canvas'));
        $this->assertSame('exportacao', ModulosSeeder::slugDoNome('Exportação'));
        $this->assertSame('objetos', ModulosSeeder::slugDoNome('Processos-Objetos-Imoveis'));
        $this->assertSame('google', ModulosSeeder::slugDoNome('Google Service'));
    }

    private function migrar(): void
    {
        (new CorrigeTabelaModulos(Database::forge($this->db)))->up();
        $this->db->resetDataCache();
    }

    private function temIndiceUnicoEmSlug(): bool
    {
        foreach ($this->db->getIndexData('modulos') as $indice) {
            if ((array) $indice->fields === ['slug'] && $indice->type === 'UNIQUE') {
                return true;
            }
        }

        return false;
    }
}
