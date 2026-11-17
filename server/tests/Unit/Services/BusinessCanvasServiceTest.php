<?php

namespace Tests\Unit\Services;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\BusinessCanvasService;
use App\Repositories\BusinessCanvasRepository;

class BusinessCanvasServiceTest extends CIUnitTestCase
{
    protected $service;
    protected $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createMock(BusinessCanvasRepository::class);
        $this->service = new BusinessCanvasService($this->repository);
    }

    public function testGetCanvasUserId()
    {
        $this->repository->expects($this->once())
            ->method('getCanvasUserId')
            ->with(123)
            ->willReturn(456);

        $result = $this->service->getCanvasUserId(123);
        $this->assertEquals(456, $result);
    }

    public function testCreateCanvasValidation()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('O título do canvas é obrigatório.');

        $this->service->createCanvas(1, '   ', 'Descrição');
    }

    public function testSaveBlockValidation()
    {
        $result = $this->service->saveBlock(1, 'invalid_block', 'content', 1);
        $this->assertFalse($result);
    }

    public function testSaveBlockSuccess()
    {
        $this->repository->expects($this->once())
            ->method('updateCanvasBlock')
            ->with(1, 'value_proposition', 'Nova Proposta')
            ->willReturn(true);

        $result = $this->service->saveBlock(1, 'value_proposition', 'Nova Proposta', 1);
        $this->assertTrue($result);
    }
}
