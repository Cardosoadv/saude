<?php

namespace Tests\Unit\Commands;

use App\Commands\DjenSync;
use App\Services\BuscarProcessosService;
use App\Services\DjenService;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\StreamFilterTrait;

/**
 * @internal
 */
final class DjenSyncTest extends CIUnitTestCase
{
    use StreamFilterTrait;

    private function comando(array $advogados, DjenService $djen, bool $bloqueado = false, ?BuscarProcessosService $busca = null): DjenSync
    {
        $busca ??= $this->busca([]);

        return new class (service('logger'), service('commands'), $advogados, $djen, $bloqueado, $busca) extends DjenSync {
            public function __construct($logger, $commands, private array $advs, private DjenService $svc, private bool $bloqueado, private BuscarProcessosService $busca)
            {
                parent::__construct($logger, $commands);
            }

            protected function buscarProcessosService(): BuscarProcessosService
            {
                return $this->busca;
            }

            protected function adquirirLock()
            {
                return $this->bloqueado ? null : fopen('php://memory', 'r+');
            }

            protected function advogados(): array
            {
                return $this->advs;
            }

            protected function djenService(): DjenService
            {
                return $this->svc;
            }
        };
    }

    private function djen(callable $sync, ?callable $syncProcesso = null): DjenService
    {
        $mock = $this->getMockBuilder(DjenService::class)->disableOriginalConstructor()->onlyMethods(['syncForOab', 'syncForProcesso'])->getMock();
        $mock->method('syncForOab')->willReturnCallback($sync);
        $mock->method('syncForProcesso')->willReturnCallback($syncProcesso ?? fn () => ['total_encontrado' => 0, 'total_novo' => 0, 'erro' => null]);

        return $mock;
    }

    /**
     * Serviço da busca automática falso.
     *
     * @param array         $pendentes  Registros devolvidos por separarPendentes().
     * @param array|null    $registros  Recebe [id, numero, sucesso] de cada resultado registrado.
     * @param callable|null $localizado fn(string $numero): ?int — id do processo na carteira.
     */
    private function busca(array $pendentes, ?array &$registros = null, ?callable $localizado = null): BuscarProcessosService
    {
        $registros = [];
        $mock      = $this->getMockBuilder(BuscarProcessosService::class)->disableOriginalConstructor()
            ->onlyMethods(['separarPendentes', 'registrarResultadoSincronizacao'])->getMock();
        $mock->method('separarPendentes')->willReturn(['pendentes' => $pendentes, 'repetidos' => 0]);
        $mock->method('registrarResultadoSincronizacao')->willReturnCallback(
            function (int $id, string $numero, bool $sucesso) use (&$registros, $localizado) {
                $registros[] = [$id, $numero, $sucesso];

                return $sucesso && $localizado ? $localizado($numero) : null;
            }
        );

        return $mock;
    }

    /** @param array<string, scalar|true> $opcoes true = flag sem valor */
    private function executar(DjenSync $cmd, array $opcoes = []): int
    {
        $_SERVER['argv'] = ['spark', 'sync:djen'];
        foreach ($opcoes as $k => $v) {
            $_SERVER['argv'][] = "--{$k}";
            if ($v !== true) {
                $_SERVER['argv'][] = (string) $v;
            }
        }
        CLI::init();

        return (int) $cmd->run([]);
    }

    public function testSucessoRetornaZeroEIgnoraOabRepetida(): void
    {
        $chamadas = [];
        $djen     = $this->djen(function ($oab, $uf) use (&$chamadas) {
            $chamadas[] = "$oab-$uf";

            return ['total_encontrado' => 3, 'total_novo' => 1, 'erro' => null];
        });
        $advs = [
            ['id' => 1, 'username' => 'a', 'oab_numero' => '123', 'oab_uf' => 'sp'],
            ['id' => 2, 'username' => 'b', 'oab_numero' => '123', 'oab_uf' => 'SP'],
            ['id' => 3, 'username' => 'c', 'oab_numero' => '', 'oab_uf' => 'SP'],
        ];

        $this->assertSame(EXIT_SUCCESS, $this->executar($this->comando($advs, $djen)));
        $this->assertSame(['123-SP'], $chamadas);
    }

    public function testFalhaDeUmaOabRetornaErroMasContinuaAsDemais(): void
    {
        $chamadas = [];
        $djen     = $this->djen(function ($oab) use (&$chamadas) {
            $chamadas[] = $oab;

            return $oab === '1'
                ? ['total_encontrado' => 0, 'total_novo' => 0, 'erro' => 'HTTP 500']
                : ['total_encontrado' => 2, 'total_novo' => 2, 'erro' => null];
        });
        $advs = [
            ['id' => 1, 'username' => 'a', 'oab_numero' => '1', 'oab_uf' => 'SP'],
            ['id' => 2, 'username' => 'b', 'oab_numero' => '2', 'oab_uf' => 'SP'],
        ];

        $this->assertSame(EXIT_ERROR, $this->executar($this->comando($advs, $djen)));
        $this->assertSame(['1', '2'], $chamadas);
    }

    public function testSemAdvogadosRetornaErro(): void
    {
        $this->assertSame(EXIT_ERROR, $this->executar($this->comando([], $this->djen(fn () => []))));
    }

    public function testDiasInvalidoRetornaErro(): void
    {
        $cmd = $this->comando([], $this->djen(fn () => []));

        $this->assertSame(EXIT_ERROR, $this->executar($cmd, ['days' => 0]));
        $this->assertSame(EXIT_ERROR, $this->executar($cmd, ['days' => 31]));
        $this->assertSame(EXIT_ERROR, $this->executar($cmd, ['days' => 'abc']));
    }

    public function testOabSemUfRetornaErro(): void
    {
        $this->assertSame(EXIT_ERROR, $this->executar($this->comando([], $this->djen(fn () => [])), ['oab' => '99']));
    }

    public function testOabEUfSincronizaSoEssaOab(): void
    {
        $chamadas = [];
        $djen     = $this->djen(function ($oab, $uf) use (&$chamadas) {
            $chamadas[] = "$oab-$uf";

            return ['total_encontrado' => 0, 'total_novo' => 0, 'erro' => null];
        });

        $this->assertSame(EXIT_SUCCESS, $this->executar($this->comando([], $djen), ['oab' => '99', 'uf' => 'rj']));
        $this->assertSame(['99-rj'], $chamadas);
    }

    public function testExecucaoSobrepostaNaoFazNada(): void
    {
        $djen = $this->getMockBuilder(DjenService::class)->disableOriginalConstructor()->onlyMethods(['syncForOab'])->getMock();
        $djen->expects($this->never())->method('syncForOab');

        $this->assertSame(EXIT_SUCCESS, $this->executar($this->comando([['oab_numero' => '1', 'oab_uf' => 'SP']], $djen, true)));
    }

    public function testBuscaAutomaticaConsultaPendentesForaDoIntervalo(): void
    {
        $consultados = [];
        $djen        = $this->djen(
            fn () => ['total_encontrado' => 0, 'total_novo' => 0, 'erro' => null],
            function ($numero, $inicio, $fim) use (&$consultados) {
                $consultados[] = [$numero, $inicio, $fim];

                return ['total_encontrado' => 2, 'total_novo' => 2, 'erro' => null];
            }
        );
        $pendentes = [
            ['id_busca_processo' => 1, 'numero_processo' => '11111111111111111111', 'data_tentativa' => null],
            ['id_busca_processo' => 2, 'numero_processo' => '22222222222222222222', 'data_tentativa' => date('Y-m-d H:i:s', strtotime('-1 hour'))],
            ['id_busca_processo' => 3, 'numero_processo' => '33333333333333333333', 'data_tentativa' => date('Y-m-d H:i:s', strtotime('-2 days'))],
        ];
        $busca = $this->busca($pendentes, $registros, fn ($n) => $n === '11111111111111111111' ? 99 : null);
        $advs  = [['id' => 1, 'username' => 'a', 'oab_numero' => '1', 'oab_uf' => 'SP']];

        $this->assertSame(EXIT_SUCCESS, $this->executar($this->comando($advs, $djen, false, $busca)));
        $this->assertSame(['11111111111111111111', '33333333333333333333'], array_column($consultados, 0));
        $this->assertSame(BuscarProcessosService::DATA_INICIO_CONSULTA, $consultados[0][1]);
        $this->assertSame(date('Y-m-d'), $consultados[0][2]);
        $this->assertSame([[1, '11111111111111111111', true], [3, '33333333333333333333', true]], $registros);
    }

    public function testIntervaloZeroConsultaTodos(): void
    {
        $consultados = [];
        $djen        = $this->djen(fn () => [], function ($numero) use (&$consultados) {
            $consultados[] = $numero;

            return ['total_encontrado' => 0, 'total_novo' => 0, 'erro' => null];
        });
        $busca = $this->busca([
            ['id_busca_processo' => 1, 'numero_processo' => '1', 'data_tentativa' => date('Y-m-d H:i:s')],
        ]);

        $this->assertSame(EXIT_SUCCESS, $this->executar($this->comando([], $djen, false, $busca), ['so-busca' => true, 'intervalo-busca' => 0]));
        $this->assertSame(['1'], $consultados);
    }

    public function testFalhaNaBuscaAutomaticaRegistraEFalhaOComando(): void
    {
        $djen  = $this->djen(
            fn () => ['total_encontrado' => 0, 'total_novo' => 0, 'erro' => null],
            fn ($numero) => $numero === '1'
                ? ['total_encontrado' => 0, 'total_novo' => 0, 'erro' => 'HTTP 500']
                : ['total_encontrado' => 1, 'total_novo' => 0, 'erro' => null]
        );
        $busca = $this->busca([
            ['id_busca_processo' => 10, 'numero_processo' => '1', 'data_tentativa' => null],
            ['id_busca_processo' => 20, 'numero_processo' => '2', 'data_tentativa' => null],
        ], $registros);
        $advs = [['id' => 1, 'username' => 'a', 'oab_numero' => '1', 'oab_uf' => 'SP']];

        $this->assertSame(EXIT_ERROR, $this->executar($this->comando($advs, $djen, false, $busca)));
        $this->assertSame([[10, '1', false], [20, '2', true]], $registros);
    }

    public function testSoBuscaPulaAsOabs(): void
    {
        $djen = $this->getMockBuilder(DjenService::class)->disableOriginalConstructor()->onlyMethods(['syncForOab', 'syncForProcesso'])->getMock();
        $djen->expects($this->never())->method('syncForOab');
        $djen->expects($this->once())->method('syncForProcesso')->willReturn(['total_encontrado' => 0, 'total_novo' => 0, 'erro' => null]);
        $busca = $this->busca([['id_busca_processo' => 1, 'numero_processo' => '1', 'data_tentativa' => null]]);

        $this->assertSame(EXIT_SUCCESS, $this->executar($this->comando([], $djen, false, $busca), ['so-busca' => true]));
    }

    public function testSemBuscaEOabNaoRodamABuscaAutomatica(): void
    {
        $djen  = $this->djen(fn () => ['total_encontrado' => 0, 'total_novo' => 0, 'erro' => null]);
        $busca = $this->getMockBuilder(BuscarProcessosService::class)->disableOriginalConstructor()->onlyMethods(['separarPendentes'])->getMock();
        $busca->expects($this->never())->method('separarPendentes');
        $advs = [['id' => 1, 'username' => 'a', 'oab_numero' => '1', 'oab_uf' => 'SP']];

        $this->assertSame(EXIT_SUCCESS, $this->executar($this->comando($advs, $djen, false, $busca), ['sem-busca' => true]));
        $this->assertSame(EXIT_SUCCESS, $this->executar($this->comando([], $djen, false, $busca), ['oab' => '9', 'uf' => 'SP']));
    }

    public function testOpcoesDeBuscaInvalidasRetornamErro(): void
    {
        $cmd = $this->comando([], $this->djen(fn () => []));

        $this->assertSame(EXIT_ERROR, $this->executar($cmd, ['sem-busca' => true, 'so-busca' => true]));
        $this->assertSame(EXIT_ERROR, $this->executar($cmd, ['so-busca' => true, 'oab' => '9', 'uf' => 'SP']));
        $this->assertSame(EXIT_ERROR, $this->executar($cmd, ['intervalo-busca' => -1]));
    }
}
