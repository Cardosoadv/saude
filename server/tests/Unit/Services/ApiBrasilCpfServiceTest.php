<?php

namespace Tests\Unit\Services;

use App\Services\ApiBrasilCpfService;
use CodeIgniter\Test\CIUnitTestCase;

class ApiBrasilCpfServiceTest extends CIUnitTestCase
{
    private ApiBrasilCpfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ApiBrasilCpfService();
    }

    public function testConsultarCpfWithInvalidCpfLengthReturns422()
    {
        $result = $this->service->consultarCpf('123');

        $this->assertFalse($result['success']);
        $this->assertEquals(422, $result['statusCode']);
        $this->assertStringContainsString('CPF inválido', $result['message']);
    }
}
