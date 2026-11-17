<?php

namespace Tests\Unit\Services;

use App\Services\MigracoesService;
use CodeIgniter\Database\MigrationRunner;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

/**
 * @internal
 */
final class MigracoesServiceTest extends CIUnitTestCase
{
    private string $lockFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lockFile = tempnam(sys_get_temp_dir(), 'mig');
    }

    protected function tearDown(): void
    {
        @unlink($this->lockFile);
        parent::tearDown();
    }

    private function migracao(string $versao, string $classe): object
    {
        return (object) [
            'uid'       => preg_replace('/[^0-9]/', '', $versao) . $classe,
            'version'   => $versao,
            'name'      => $classe,
            'class'     => $classe,
            'namespace' => 'App',
        ];
    }

    private function historico(string $versao, string $classe, int $lote): object
    {
        return (object) ['version' => $versao, 'class' => $classe, 'batch' => $lote, 'time' => 1_700_000_000];
    }

    /**
     * Runner falso que simula um banco: `latest()` aplica tudo que estiver pendente.
     */
    private function runner(array $migracoes, array $historico, bool $latestOk = true): MigrationRunner
    {
        $runner = $this->createMock(MigrationRunner::class);
        $runner->method('getObjectUid')->willReturnCallback(
            static fn ($o) => preg_replace('/[^0-9]/', '', $o->version) . $o->class,
        );
        $runner->method('findMigrations')->willReturnCallback(static function () use ($migracoes) {
            $out = [];
            foreach ($migracoes as $m) {
                $out[$m->uid] = $m;
            }

            return $out;
        });
        $runner->method('getHistory')->willReturnCallback(static function () use (&$historico) {
            return $historico;
        });
        $runner->method('getLastBatch')->willReturn(1);
        $runner->method('getCliMessages')->willReturn(["\e[33mAdded\e[0m x"]);
        $runner->method('latest')->willReturnCallback(static function () use (&$historico, $migracoes, $latestOk) {
            if ($latestOk) {
                foreach ($migracoes as $m) {
                    $historico[] = (object) ['version' => $m->version, 'class' => $m->class, 'batch' => 2, 'time' => 1_700_000_100];
                }
            }

            return $latestOk;
        });

        return $runner;
    }

    public function testStatusMarcaAplicadasEPendentes(): void
    {
        $runner = $this->runner(
            [$this->migracao('2026-01-01-000000', 'A'), $this->migracao('2026-02-01-000000', 'B')],
            [$this->historico('2026-01-01-000000', 'A', 1)],
        );

        $status = (new MigracoesService($runner, $this->lockFile))->status();

        $this->assertSame(1, $status['pendentes']);
        $this->assertSame(1, $status['aplicadas']);
        $this->assertSame('aplicada', $status['migracoes'][0]['status']);
        $this->assertSame(1, $status['migracoes'][0]['lote']);
        $this->assertSame('pendente', $status['migracoes'][1]['status']);
        $this->assertNull($status['migracoes'][1]['aplicada_em']);
    }

    public function testExecutarRetornaMigrationsAplicadasEMensagensSemAnsi(): void
    {
        $runner = $this->runner(
            [$this->migracao('2026-01-01-000000', 'A'), $this->migracao('2026-02-01-000000', 'B')],
            [$this->historico('2026-01-01-000000', 'A', 1)],
        );

        $r = (new MigracoesService($runner, $this->lockFile))->executar();

        $this->assertTrue($r['sucesso']);
        $this->assertSame(['2026-02-01-000000_B'], $r['executadas']);
        $this->assertSame(['Added x'], $r['mensagens']);
    }

    public function testExecutarInformaFalhaQuandoRunnerFalha(): void
    {
        $runner = $this->runner([$this->migracao('2026-01-01-000000', 'A')], [], false);

        $r = (new MigracoesService($runner, $this->lockFile))->executar();

        $this->assertFalse($r['sucesso']);
        $this->assertSame([], $r['executadas']);
    }

    public function testExecutarRecusaExecucaoSimultanea(): void
    {
        $lock = fopen($this->lockFile, 'c');
        flock($lock, LOCK_EX | LOCK_NB);

        try {
            $this->expectException(RuntimeException::class);
            (new MigracoesService($this->runner([], []), $this->lockFile))->executar();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
