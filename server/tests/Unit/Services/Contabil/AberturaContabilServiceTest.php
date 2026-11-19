<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Contabil;

use App\Database\Seeds\ContabilSeeder;
use App\Services\Contabil\AberturaContabilService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CognitioTechny\Contabil\Infrastructure\CodeIgniter\Database\Migrations\CreateCoreTables;
use CognitioTechny\Contabil\Infrastructure\CodeIgniter\Database\Migrations\CreateEntryTables;
use CognitioTechny\Contabil\Domain\Exception\ConflictException;
use CognitioTechny\Contabil\Infrastructure\CodeIgniter\Database\Migrations\CreateSupportTables;
use Config\Database;
use InvalidArgumentException;

/**
 * Testes da abertura contábil (saldos iniciais em um único lançamento).
 *
 * @version 1.0.0
 */
class AberturaContabilServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private AberturaContabilService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $forge = Database::forge($this->db);

        if (! $this->db->tableExists('acc_entities')) {
            require_once dirname(__DIR__, 4) . '/vendor/cognitiotechny/contabil/src/Infrastructure/CodeIgniter/Database/Migrations/2026-10-20-000001_CreateCoreTables.php';
            require_once dirname(__DIR__, 4) . '/vendor/cognitiotechny/contabil/src/Infrastructure/CodeIgniter/Database/Migrations/2026-10-20-000002_CreateEntryTables.php';
            require_once dirname(__DIR__, 4) . '/vendor/cognitiotechny/contabil/src/Infrastructure/CodeIgniter/Database/Migrations/2026-10-20-000003_CreateSupportTables.php';

            (new CreateCoreTables($forge))->up();
            (new CreateEntryTables($forge))->up();
            (new CreateSupportTables($forge))->up();
        }

        $forge->dropTable('settings', true);
        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'class'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'key'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'value'      => ['type' => 'TEXT', 'null' => true],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 31],
            'context'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('settings');

        $this->db->disableForeignKeyChecks();
        foreach (['acc_entry_lines', 'acc_entries', 'acc_balances', 'acc_sequences'] as $tabela) {
            $this->db->table($tabela)->emptyTable();
        }
        $this->db->enableForeignKeyChecks();

        (new ContabilSeeder(config(Database::class), $this->db))->run();

        $this->service = new AberturaContabilService($this->db);
    }

    /**
     * Código de conta com saldo e natureza do plano padrão do pacote (ativo e patrimônio líquido).
     */
    private function codigo(string $tipo): string
    {
        $row = $this->db->table('acc_accounts')
            ->select('code')
            ->where('entity_id', 1)
            ->where('accepts_entries', 1)
            ->where('type', $tipo)
            ->orderBy('code', 'ASC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($row, "Plano sem conta folha do tipo {$tipo}.");

        return (string) $row['code'];
    }

    public function testAbreSaldosEmUmUnicoLancamentoComVariasLinhas(): void
    {
        $resultado = $this->service->abrir([
            ['code' => $this->codigo('ASSET'), 'side' => 'D', 'amount' => 1000.10],
            ['code' => $this->codigo('LIABILITY'), 'side' => 'C', 'amount' => 400.05],
            ['code' => $this->codigo('EQUITY'), 'side' => 'C', 'amount' => 600.05],
        ], '2026-01-01', 1);

        $this->assertTrue($resultado['created']);
        $this->assertSame(100010, $resultado['debitos']);
        $this->assertSame(100010, $resultado['creditos']);
        $this->assertSame('POSTED', $resultado['entry']['status']);
        $this->assertCount(3, $resultado['entry']['lines']);
        $this->assertSame(AberturaContabilService::SOURCE_TYPE, $resultado['entry']['source_type']);
    }

    public function testRepetirAberturaNaMesmaDataNaoDuplica(): void
    {
        $saldos = [
            ['code' => $this->codigo('ASSET'), 'side' => 'D', 'amount' => 50],
            ['code' => $this->codigo('EQUITY'), 'side' => 'C', 'amount' => 50],
        ];

        $primeira = $this->service->abrir($saldos, '2026-01-01', 1);
        $segunda  = $this->service->abrir($saldos, '2026-01-01', 1);

        $this->assertTrue($primeira['created']);
        $this->assertFalse($segunda['created']);
        $this->assertSame($primeira['entry']['id'], $segunda['entry']['id']);
    }

    public function testAberturaComOutrosSaldosExigeSubstituir(): void
    {
        $ativo = $this->codigo('ASSET');
        $pl    = $this->codigo('EQUITY');

        $primeira = $this->service->abrir([
            ['code' => $ativo, 'side' => 'D', 'amount' => 50],
            ['code' => $pl, 'side' => 'C', 'amount' => 50],
        ], '2026-01-01', 1);

        $novos = [
            ['code' => $ativo, 'side' => 'D', 'amount' => 70],
            ['code' => $pl, 'side' => 'C', 'amount' => 70],
        ];

        try {
            $this->service->abrir($novos, '2026-01-01', 1);
            $this->fail('A segunda abertura com outros saldos deveria ser recusada.');
        } catch (ConflictException $e) {
            $this->assertStringContainsString('already posted', $e->getMessage());
        }

        $substituta = $this->service->abrir($novos, '2026-01-01', 1, '', true);

        $this->assertTrue($substituta['created']);
        $this->assertNotSame($primeira['entry']['id'], $substituta['entry']['id']);
        $this->assertSame(7000, $substituta['debitos']);

        $estorno = $this->db->table('acc_entries')->where('reversal_of_id', $primeira['entry']['id'])->countAllResults();
        $this->assertSame(1, $estorno);
    }

    public function testRecusaDataForaDoInicioDoExercicio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('01/01/2026');

        $this->service->abrir([
            ['code' => $this->codigo('ASSET'), 'side' => 'D', 'amount' => 1],
            ['code' => $this->codigo('EQUITY'), 'side' => 'C', 'amount' => 1],
        ], '2026-03-01', 1);
    }

    public function testRecusaContaRepetida(): void
    {
        $ativo = $this->codigo('ASSET');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mais de uma vez');

        $this->service->abrir([
            ['code' => $ativo, 'side' => 'D', 'amount' => 1],
            ['code' => $ativo, 'side' => 'D', 'amount' => 1],
            ['code' => $this->codigo('EQUITY'), 'side' => 'C', 'amount' => 2],
        ], '2026-01-01', 1);
    }

    public function testRecusaSaldosQueNaoFecham(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Os saldos não fecham');

        $this->service->abrir([
            ['code' => $this->codigo('ASSET'), 'side' => 'D', 'amount' => 100],
            ['code' => $this->codigo('EQUITY'), 'side' => 'C', 'amount' => 99.99],
        ], '2026-01-01', 1);
    }

    public function testRecusaContaDeResultadoOuInexistente(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('9.9.99');

        $this->service->abrir([
            ['code' => $this->codigo('ASSET'), 'side' => 'D', 'amount' => 100],
            ['code' => '9.9.99', 'side' => 'C', 'amount' => 100],
        ], '2026-01-01', 1);
    }

    public function testRecusaDataInvalida(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->abrir([
            ['code' => $this->codigo('ASSET'), 'side' => 'D', 'amount' => 1],
            ['code' => $this->codigo('EQUITY'), 'side' => 'C', 'amount' => 1],
        ], '01/01/2026', 1);
    }
}
