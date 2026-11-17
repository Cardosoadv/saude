<?php

namespace Tests\Unit\Services\Financeiro;

use App\Repositories\Financeiro\ExtratoRepository;
use App\Services\Financeiro\ExtratoService;
use CodeIgniter\Test\CIUnitTestCase;

class ExtratoServiceTest extends CIUnitTestCase
{
    private $mockRepository;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) { $this->db->query('PRAGMA foreign_keys = OFF;'); }
        $this->mockRepository = $this->createMock(ExtratoRepository::class);
        $this->service = new ExtratoService($this->mockRepository);
    }

    public function testGetExtratoPorPeriodoCalculatesCorrectly()
    {
        // 1. Setup Mock Data
        
        // Mock the new optimized balance calculation
        $this->mockRepository->method('getSaldosAnterioresAgrupados')->willReturn([
            'total' => 350.00,
            'rateio' => [
                1 => ['valor' => 400.00, 'nome' => 'U1'],
                'geral' => ['valor' => -50.00, 'nome' => 'Geral']
            ]
        ]);

        // Current transactions (within period)
        $this->mockRepository->method('getDespesasPorContaPeriodo')->willReturn([
            ['data' => '2026-05-15', 'valor' => -200.00, 'rateio' => [['id' => 1, 'nome' => 'U1', 'valor' => -200.00]]]
        ]);
        $this->mockRepository->method('getReceitasPorContaPeriodo')->willReturn([
            ['data' => '2026-05-10', 'valor' => 1000.00, 'rateio' => [['id' => 1, 'nome' => 'U1', 'valor' => 800.00], ['id' => 2, 'nome' => 'U2', 'valor' => 200.00]]]
        ]);
        $this->mockRepository->method('getTransferenciasDePorContaPeriodo')->willReturn([]);
        $this->mockRepository->method('getTransferenciasParaPorContaPeriodo')->willReturn([]);

        // 2. Execute
        $result = $this->service->getExtratoPorPeriodo(1, '2026-05-01', '2026-05-31');

        // 3. Verify
        
        // Opening Balance: -100 + 500 - 50 = 350
        $this->assertEquals(350.00, $result['saldo_anterior_total']);
        
        // Rateio Opening: 
        // U1: -100 + 500 = 400
        // Geral: -50
        $this->assertEquals(400.00, $result['saldo_anterior'][1]);
        $this->assertEquals(-50.00, $result['saldo_anterior']['geral']);

        // Transactions: Ordered by date (2026-05-10 then 2026-05-15)
        $this->assertCount(2, $result['transacoes']);
        $this->assertEquals('2026-05-10', $result['transacoes'][0]['data']);
        $this->assertEquals('2026-05-15', $result['transacoes'][1]['data']);

        // Cumulative Balance:
        // Start: 350
        // Line 1 (+1000): 1350
        // Line 2 (-200): 1150
        $this->assertEquals(1350.00, $result['transacoes'][0]['saldo']);
        $this->assertEquals(1150.00, $result['transacoes'][1]['saldo']);
        $this->assertEquals(1150.00, $result['saldo_final']);

        // Cumulative Rateio (End of period):
        // U1: 400 (start) + 800 (line 1) - 200 (line 2) = 1000
        // U2: 0 (start) + 200 (line 1) = 200
        // Geral: -50 (start) = -50
        $this->assertEquals(1000.00, $result['rateio_acumulado'][1]['rateio_acumulado']);
        $this->assertEquals(200.00, $result['rateio_acumulado'][2]['rateio_acumulado']);
        $this->assertEquals(-50.00, $result['rateio_acumulado']['geral']['rateio_acumulado']);
    }

    public function testGetExtratoPorContaSimple()
    {
        $this->mockRepository->method('getDespesasPorConta')->willReturn([
            ['data' => '2026-01-01', 'valor' => -100.00, 'rateio' => [['id' => 1, 'nome' => 'U1', 'valor' => -100.00]]]
        ]);
        $this->mockRepository->method('getReceitasPorConta')->willReturn([
            ['data' => '2026-01-02', 'valor' => 500.00, 'rateio' => [['id' => 1, 'nome' => 'U1', 'valor' => 500.00]]]
        ]);
        $this->mockRepository->method('getTransferenciasDePorConta')->willReturn([]);
        $this->mockRepository->method('getTransferenciasParaPorConta')->willReturn([]);

        $result = $this->service->getExtratoPorConta(1);

        $this->assertCount(2, $result);
        $this->assertEquals(-100.00, $result[0]['saldo']);
        $this->assertEquals(400.00, $result[1]['saldo']);
        $this->assertEquals(400.00, $result[1]['rateio'][0]['rateio_acumulado']);
    }
}
