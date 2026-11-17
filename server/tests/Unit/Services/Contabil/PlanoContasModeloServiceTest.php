<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Contabil;

use App\Services\Contabil\PlanoContasModeloService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CognitioTechny\Contabil\Infrastructure\CodeIgniter\Database\Migrations\CreateCoreTables;
use CognitioTechny\Contabil\Infrastructure\CodeIgniter\Database\Migrations\CreateEntryTables;
use CognitioTechny\Contabil\Infrastructure\CodeIgniter\Database\Migrations\CreateSupportTables;
use Config\Database;
use InvalidArgumentException;

/**
 * Importação dos planos de contas modelo do pacote contábil.
 *
 * @version 1.0.0
 */
class PlanoContasModeloServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private PlanoContasModeloService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->db->tableExists('acc_entities')) {
            $pasta = dirname(__DIR__, 4) . '/vendor/cognitiotechny/contabil/src/Infrastructure/CodeIgniter/Database/Migrations/';

            require_once $pasta . '2026-10-20-000001_CreateCoreTables.php';
            require_once $pasta . '2026-10-20-000002_CreateEntryTables.php';
            require_once $pasta . '2026-10-20-000003_CreateSupportTables.php';

            $forge = Database::forge($this->db);
            (new CreateCoreTables($forge))->up();
            (new CreateEntryTables($forge))->up();
            (new CreateSupportTables($forge))->up();
        }

        $this->db->disableForeignKeyChecks();
        if ($this->db->tableExists('acc_entry_lines')) {
            $this->db->table('acc_entry_lines')->emptyTable();
        }
        if ($this->db->tableExists('acc_entries')) {
            $this->db->table('acc_entries')->emptyTable();
        }
        $this->db->table('acc_accounts')->emptyTable();
        $this->db->table('acc_entities')->emptyTable();
        $this->db->enableForeignKeyChecks();

        $this->service = new PlanoContasModeloService();
    }

    public function testListaOsModelosDoPacote(): void
    {
        $ids = array_column($this->service->listarModelos(), 'id');

        $this->assertContains('professional-services', $ids);
        $this->assertContains('generic', $ids);
    }

    public function testImportaCriandoAEntidadeQueFaltava(): void
    {
        $this->assertSame(0, $this->db->table('acc_entities')->countAllResults());

        $resultado = $this->service->importar('professional-services', 1);

        $this->assertGreaterThan(0, $resultado['created']);
        $this->assertSame(0, $resultado['skipped']);
        $this->assertSame(1, $this->db->table('acc_entities')->where('id', 1)->countAllResults());
        $this->assertSame($resultado['created'], $this->db->table('acc_accounts')->where('entity_id', 1)->countAllResults());
    }

    public function testRepetirAImportacaoNaoDuplica(): void
    {
        $primeira = $this->service->importar('generic', 1);
        $segunda  = $this->service->importar('generic', 1);

        $this->assertSame(0, $segunda['created']);
        $this->assertSame($primeira['created'], $segunda['skipped']);
        $this->assertSame($primeira['created'], $this->db->table('acc_accounts')->where('entity_id', 1)->countAllResults());
    }

    public function testModeloDesconhecidoELancaExcecaoSemEscrever(): void
    {
        try {
            $this->service->importar('../../etc/passwd', 1);
            $this->fail('Modelo inválido deveria ser recusado.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, $this->db->table('acc_entities')->countAllResults());
        }
    }
}
