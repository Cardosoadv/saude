<?php

namespace Tests\App\Services\Financeiro;

use App\Repositories\Financeiro\CashFlowForecastRepository;
use App\Repositories\Financeiro\ContasRepository;
use App\Repositories\Financeiro\SaldoMensalRepository;
use App\Services\Financeiro\CashFlowForecastService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Testes unitários para o CashFlowForecastService.
 * Valida a projeção de saldo (regressão linear sobre o histórico mensal) e a
 * detecção de anomalias por categoria (z-score).
 */
class CashFlowForecastServiceTest extends CIUnitTestCase
{
    protected $repositoryMock;
    protected $contasRepositoryMock;
    protected $saldoMensalRepositoryMock;
    protected CashFlowForecastService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryMock = $this->createMock(CashFlowForecastRepository::class);
        $this->contasRepositoryMock = $this->createMock(ContasRepository::class);
        $this->saldoMensalRepositoryMock = $this->createMock(SaldoMensalRepository::class);

        $this->service = new CashFlowForecastService(
            $this->repositoryMock,
            $this->contasRepositoryMock,
            $this->saldoMensalRepositoryMock
        );
    }

    public function testGetForecastProjetaSaldoComRegressaoLinearSemFechamentoAnterior(): void
    {
        $chaveM2 = date('Y-m', strtotime('-2 months'));
        $chaveM1 = date('Y-m', strtotime('-1 months'));
        $chaveAtual = date('Y-m');

        $this->repositoryMock->method('getTotaisMensaisDespesas')
            ->with(null, 3)
            ->willReturn([$chaveM2 => 100.0, $chaveM1 => 200.0, $chaveAtual => 150.0]);

        $this->repositoryMock->method('getTotaisMensaisReceitas')
            ->with(null, 3)
            ->willReturn([$chaveM2 => 300.0, $chaveM1 => 250.0, $chaveAtual => 400.0]);

        // Sem fechamento anterior: o saldo é relativo (estimado), começando em 0.
        $this->saldoMensalRepositoryMock->method('getUltimoSaldoFechado')->willReturn(null);

        $resultado = $this->service->getForecast(null, 2, 3);

        $this->assertTrue($resultado['saldo_inicial_estimado']);
        $this->assertCount(3, $resultado['historico']);

        // saldo líquido mês a mês: +200, +50, +250 -> acumulado: 200, 250, 500
        $this->assertEquals(200.0, $resultado['historico'][0]['saldo_liquido']);
        $this->assertEquals(200.0, $resultado['historico'][0]['saldo_acumulado']);
        $this->assertEquals(50.0, $resultado['historico'][1]['saldo_liquido']);
        $this->assertEquals(250.0, $resultado['historico'][1]['saldo_acumulado']);
        $this->assertEquals(250.0, $resultado['historico'][2]['saldo_liquido']);
        $this->assertEquals(500.0, $resultado['historico'][2]['saldo_acumulado']);

        $this->assertCount(2, $resultado['projecao']);
        $this->assertEquals(616.67, $resultado['projecao'][0]['saldo_projetado']);
        $this->assertEquals(766.67, $resultado['projecao'][1]['saldo_projetado']);

        $this->assertEquals('alta', $resultado['tendencia']['direcao']);
        $this->assertEquals(150.0, $resultado['tendencia']['inclinacao_mensal']);
    }

    public function testGetForecastUsaSaldoDeFechamentoComoBaseQuandoDisponivel(): void
    {
        $chaveAtual = date('Y-m');

        $this->repositoryMock->method('getTotaisMensaisDespesas')->willReturn([$chaveAtual => 0.0]);
        $this->repositoryMock->method('getTotaisMensaisReceitas')->willReturn([$chaveAtual => 0.0]);

        $this->saldoMensalRepositoryMock->method('getUltimoSaldoFechado')
            ->willReturn(['saldo_acumulado' => 10000.0]);

        $resultado = $this->service->getForecast(5, 3, 1);

        $this->assertFalse($resultado['saldo_inicial_estimado']);
        $this->assertEquals(10000.0, $resultado['saldo_inicial']);
        $this->assertEquals(10000.0, $resultado['historico'][0]['saldo_acumulado']);
    }

    public function testGetAnomaliasSinalizaLancamentoForaDoPadraoDaCategoria(): void
    {
        $itensDespesas = [
            ['id' => 1, 'descricao' => 'Aluguel Jan', 'valor' => 100.0, 'data' => '2026-05-01', 'categoria_id' => 1, 'categoria_nome' => 'Fixas'],
            ['id' => 2, 'descricao' => 'Aluguel Fev', 'valor' => 100.0, 'data' => '2026-06-01', 'categoria_id' => 1, 'categoria_nome' => 'Fixas'],
            ['id' => 3, 'descricao' => 'Aluguel Mar', 'valor' => 100.0, 'data' => '2026-07-01', 'categoria_id' => 1, 'categoria_nome' => 'Fixas'],
            ['id' => 4, 'descricao' => 'Aluguel Abr', 'valor' => 100.0, 'data' => '2026-08-01', 'categoria_id' => 1, 'categoria_nome' => 'Fixas'],
            ['id' => 5, 'descricao' => 'Reforma Emergencial', 'valor' => 500.0, 'data' => '2026-08-10', 'categoria_id' => 1, 'categoria_nome' => 'Fixas'],
        ];

        $this->repositoryMock->method('getLancamentosComCategoria')
            ->willReturnMap([
                ['despesa', 6, $itensDespesas],
                ['receita', 6, []],
            ]);

        $anomalias = $this->service->getAnomalias(6);

        $this->assertCount(1, $anomalias);
        $this->assertEquals(5, $anomalias[0]['id']);
        $this->assertEquals('despesa', $anomalias[0]['tipo']);
        $this->assertEquals(500.0, $anomalias[0]['valor']);
        $this->assertEquals(180.0, $anomalias[0]['media_categoria']);
        $this->assertEquals(2.0, $anomalias[0]['z_score']);
        $this->assertEquals('media', $anomalias[0]['severidade']);
    }

    public function testGetAnomaliasIgnoraCategoriaComAmostraInsuficiente(): void
    {
        $itens = [
            ['id' => 1, 'descricao' => 'Consultoria', 'valor' => 100.0, 'data' => '2026-08-01', 'categoria_id' => 2, 'categoria_nome' => 'Serviços'],
            ['id' => 2, 'descricao' => 'Consultoria Especial', 'valor' => 9000.0, 'data' => '2026-08-05', 'categoria_id' => 2, 'categoria_nome' => 'Serviços'],
        ];

        $this->repositoryMock->method('getLancamentosComCategoria')
            ->willReturnMap([
                ['despesa', 6, $itens],
                ['receita', 6, []],
            ]);

        $anomalias = $this->service->getAnomalias(6);

        // Apenas 2 lançamentos na categoria: amostra insuficiente para estatística confiável.
        $this->assertCount(0, $anomalias);
    }
}
