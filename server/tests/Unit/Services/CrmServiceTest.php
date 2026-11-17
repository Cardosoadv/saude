<?php

namespace Tests\Unit\Services;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\CrmService;
use App\Repositories\CrmRepository;

class CrmServiceTest extends CIUnitTestCase
{
    protected $service;
    protected $repository;

    protected function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) { $this->db->query('PRAGMA foreign_keys = OFF;'); }

        $this->repository = $this->createMock(CrmRepository::class);
        $this->service = new CrmService($this->repository);
    }

    public function testGetPipelineCallsRepository()
    {
        $this->repository->expects($this->once())
            ->method('getEtapas')
            ->willReturn([['id' => 1, 'nome' => 'Teste', 'cor' => '#000']]);

        $this->repository->expects($this->once())
            ->method('getOportunidadesAbertas')
            ->willReturn([]);

        $result = $this->service->getPipeline();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('Teste', $result[0]['etapa']['nome']);
    }

    public function testMoverOportunidade()
    {
        $this->repository->expects($this->once())
            ->method('atualizarEtapaOportunidade')
            ->with(123, 2)
            ->willReturn(true);

        $result = $this->service->moverOportunidade(123, 2);
        $this->assertTrue($result);
    }

    public function testExcluirInteracao()
    {
        $this->repository->expects($this->once())
            ->method('excluirInteracao')
            ->with(456)
            ->willReturn(true);

        $result = $this->service->excluirInteracao(456);
        $this->assertTrue($result);
    }
}
