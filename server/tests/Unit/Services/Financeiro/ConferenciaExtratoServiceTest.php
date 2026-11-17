<?php

namespace Tests\Unit\Services\Financeiro;

use App\Repositories\Financeiro\ExtratosImportacoesRepository;
use App\Repositories\Financeiro\ExtratosMovimentosRepository;
use App\Services\Financeiro\ConferenciaExtratoService;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Conferência de saldo banco x sistema com repositórios simulados. Só dados fictícios.
 */
class ConferenciaExtratoServiceTest extends CIUnitTestCase
{
    /** @var ExtratosImportacoesRepository&MockObject */
    private $importacoes;
    /** @var ExtratosMovimentosRepository&MockObject */
    private $movimentos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importacoes = $this->createMock(ExtratosImportacoesRepository::class);
        $this->movimentos  = $this->createMock(ExtratosMovimentosRepository::class);
    }

    private function servico(float $saldoSistema): ConferenciaExtratoService
    {
        return new ConferenciaExtratoService(
            $this->importacoes,
            $this->movimentos,
            static fn (int $conta, string $data): float => $saldoSistema
        );
    }

    private function importacaoComSaldo(int $centavos, string $data = '2026-10-30'): void
    {
        $this->importacoes->method('ultimoSaldoNoPeriodo')->with(7, '2026-10-01', '2026-10-31')->willReturn([
            'id_importacao' => 4, 'saldo_final_arquivo' => $centavos, 'saldo_final_data' => $data,
        ]);
    }

    private function movimento(int $id, string $data, int $valor): array
    {
        return ['id_movimento' => $id, 'data' => $data, 'valor_centavos' => $valor, 'memo' => 'Tarifa fictícia', 'nome_contraparte' => null];
    }

    public function testSaldoIgualNaoTemDiferenca(): void
    {
        $this->importacaoComSaldo(123456);
        $this->movimentos->method('listarPendentesAte')->willReturn([]);
        $this->movimentos->method('contarPorStatus')->willReturn([7 => ['pendente' => 0, 'conciliado' => 5, 'lancado' => 2, 'ignorado' => 1]]);

        $r = $this->servico(1234.56)->conferir(7, 2026, 10);

        $this->assertSame('conferido', $r['situacao']);
        $this->assertSame(123456, $r['saldo_banco']);
        $this->assertSame(123456, $r['saldo_sistema']);
        $this->assertSame(0, $r['diferenca']);
        $this->assertSame(0, $r['diferenca_nao_explicada']);
        $this->assertSame('2026-10-30', $r['data_saldo']);
        $this->assertSame([], $r['pendentes']);
        $this->assertSame(['pendentes' => 0, 'ignorados' => 1, 'conciliados' => 5, 'lancados' => 2], $r['conciliacao']);
    }

    public function testDiferencaFechadaPelaSomaDosPendentes(): void
    {
        $this->importacaoComSaldo(100000);
        $this->movimentos->method('listarPendentesAte')->willReturn([
            $this->movimento(11, '2026-10-12', -1590),
            $this->movimento(12, '2026-10-20', 4000),
        ]);
        $this->movimentos->method('contarPorStatus')->willReturn([7 => ['pendente' => 2, 'conciliado' => 0, 'lancado' => 0, 'ignorado' => 0]]);

        $r = $this->servico(975.90)->conferir(7, 2026, 10);

        $this->assertSame('explicada_por_pendentes', $r['situacao']);
        $this->assertSame(2410, $r['diferenca']);
        $this->assertSame(2410, $r['soma_pendentes']);
        $this->assertSame(0, $r['diferenca_nao_explicada']);
        $this->assertSame(2, $r['total_pendentes']);
        $this->assertSame([11, 12], array_column($r['pendentes'], 'id_movimento'));
        $this->assertSame(2, $r['conciliacao']['pendentes']);
    }

    public function testDiferencaQueOsPendentesNaoExplicamFicaDivergente(): void
    {
        $this->importacaoComSaldo(100000);
        $this->movimentos->method('listarPendentesAte')->willReturn([$this->movimento(11, '2026-10-12', -1590)]);
        $this->movimentos->method('contarPorStatus')->willReturn([]);

        $r = $this->servico(980.00)->conferir(7, 2026, 10);

        $this->assertSame('divergente', $r['situacao']);
        $this->assertSame(2000, $r['diferenca']);
        $this->assertSame(-1590, $r['soma_pendentes']);
        $this->assertSame(3590, $r['diferenca_nao_explicada']);
    }

    public function testSemSaldoNoMesNaoCalculaNada(): void
    {
        $this->importacoes->method('ultimoSaldoNoPeriodo')->willReturn(null);
        $this->movimentos->method('contarPorStatus')->willReturn([7 => ['pendente' => 3, 'conciliado' => 0, 'lancado' => 0, 'ignorado' => 0]]);
        $this->movimentos->expects($this->never())->method('listarPendentesAte');

        $r = $this->servico(0.0)->conferir(7, 2026, 10);

        $this->assertSame('sem_saldo_banco', $r['situacao']);
        $this->assertNull($r['saldo_banco']);
        $this->assertNull($r['saldo_sistema']);
        $this->assertNull($r['diferenca']);
        $this->assertSame(3, $r['conciliacao']['pendentes']);
    }

    public function testRejeitaMesEContaInvalidos(): void
    {
        $this->expectException(ExtratoInvalidoException::class);
        $this->servico(0.0)->conferir(7, 2026, 13);
    }

    public function testRejeitaContaSemId(): void
    {
        $this->expectException(ExtratoInvalidoException::class);
        $this->servico(0.0)->conferir(0, 2026, 10);
    }

    public function testResumoDoMesSomaTodasAsContas(): void
    {
        $this->movimentos->method('contarPorStatus')->with('2026-10-01', '2026-10-31', null)->willReturn([
            7 => ['pendente' => 2, 'conciliado' => 5, 'lancado' => 1, 'ignorado' => 1],
            9 => ['pendente' => 1, 'conciliado' => 0, 'lancado' => 4, 'ignorado' => 0],
        ]);

        $r = $this->servico(0.0)->resumoDoMes(2026, 10);

        $this->assertSame(3, $r['pendentes']);
        $this->assertSame(1, $r['ignorados']);
        $this->assertSame(5, $r['conciliados']);
        $this->assertSame(5, $r['lancados']);
        $this->assertCount(2, $r['contas']);
        $this->assertSame(['conta_id' => 9, 'pendentes' => 1, 'ignorados' => 0, 'conciliados' => 0, 'lancados' => 4], $r['contas'][1]);
    }
}
