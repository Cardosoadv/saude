<?php

namespace Tests\Unit\Services;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ReceberIntimacoes;
use App\Services\DjenService;

class ReceberIntimacoesTest extends CIUnitTestCase
{
    public function testGetIntimacoesWithOabAndUf()
    {
        $mockDjen = $this->getMockBuilder(DjenService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['syncForOab'])
            ->getMock();

        $mockDjen->expects($this->once())
            ->method('syncForOab')
            ->with('12345', 'sp', '2026-05-01', '2026-05-10')
            ->willReturn([
                'oab' => '12345',
                'total_encontrado' => 10,
                'total_novo' => 2,
            ]);

        $service = new ReceberIntimacoes($mockDjen);

        $params = [
            'numeroOab' => '12345',
            'ufOab' => 'sp',
            'dataDisponibilizacaoInicio' => '2026-05-01',
            'dataDisponibilizacaoFim' => '2026-05-10',
        ];

        $result = $service->getIntimacoes($params);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(2, $result['intimacoesProcessadas']);
        $this->assertEquals(10, $result['total_encontrado']);
    }

    public function testGetIntimacoesWithNumeroProcessoArray()
    {
        $mockDjen = $this->getMockBuilder(DjenService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['syncForProcesso'])
            ->getMock();

        $mockDjen->expects($this->once())
            ->method('syncForProcesso')
            ->with('0010020-30.2026.8.13.0000', '2026-05-01', '2026-05-10')
            ->willReturn([
                'numero_processo' => '00100203020268130000',
                'total_encontrado' => 5,
                'total_novo' => 1,
            ]);

        $service = new ReceberIntimacoes($mockDjen);

        $params = [
            'numeroProcesso' => '0010020-30.2026.8.13.0000',
            'dataDisponibilizacaoInicio' => '2026-05-01',
            'dataDisponibilizacaoFim' => '2026-05-10',
        ];

        $result = $service->getIntimacoes($params);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1, $result['intimacoesProcessadas']);
        $this->assertEquals(5, $result['total_encontrado']);
    }

    public function testGetIntimacoesWithNumeroProcessoString()
    {
        $mockDjen = $this->getMockBuilder(DjenService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['syncForProcesso'])
            ->getMock();

        $mockDjen->expects($this->once())
            ->method('syncForProcesso')
            ->with('0010020-30.2026.8.13.0000', null, null)
            ->willReturn([
                'numero_processo' => '00100203020268130000',
                'total_encontrado' => 3,
                'total_novo' => 0,
            ]);

        $service = new ReceberIntimacoes($mockDjen);

        $result = $service->getIntimacoes('0010020-30.2026.8.13.0000');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(0, $result['intimacoesProcessadas']);
        $this->assertEquals(3, $result['total_encontrado']);
    }
}
