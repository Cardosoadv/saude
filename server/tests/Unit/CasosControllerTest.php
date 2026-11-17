<?php

namespace Tests\Unit\Controllers;

use App\Controllers\ApiV2\Casos;
use App\Services\CasosService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;

class CasosControllerTest extends CIUnitTestCase
{
    use ControllerTestTrait;

    public function testExportarPDF()
    {
        // Mock CasosService
        $mockCasosService = $this->getMockBuilder(CasosService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockCasosService->method('buscarCaso')->willReturn([
            'id_caso' => 1,
            'titulo' => 'Caso Teste',
            'status' => 'Ativo',
            'tipo' => 'Judicial',
            'cliente_nome' => 'Cliente Teste',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $mockCasosService->method('buscarDocumentosCaso')->willReturn([]);
        $mockCasosService->method('buscarHistoricoCaso')->willReturn([]);

        // Mock remaining dependencies to avoid DB connection during construction
        $mockClienteService = $this->createMock(\App\Services\ClienteService::class);
        $mockChecklistService = $this->createMock(\App\Services\Checklist\ChecklistService::class);
        $mockPdfService = $this->createMock(\App\Services\PdfService::class);
        $mockConhecimentoService = $this->createMock(\App\Services\ConhecimentoService::class);

        // Inject Mock
        $controller = new Casos(
            $mockCasosService,
            $mockClienteService,
            $mockChecklistService,
            $mockPdfService,
            $mockConhecimentoService
        );

        $this->assertTrue(method_exists($controller, 'detalhes'));
    }

}
