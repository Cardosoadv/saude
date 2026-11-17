<?php

namespace Tests\Unit\Filters;

use App\Filters\ModuloAtivoFilter;
use App\Services\ModulosService;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;

class ModuloAtivoFilterTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $modulos = $this->createMock(ModulosService::class);
        $modulos->method('isActive')->willReturnCallback(
            static fn (string $slug) => in_array($slug, ['processos', 'clientes'], true),
        );
        Services::injectMock('modulos', $modulos);
    }

    protected function tearDown(): void
    {
        Services::resetSingle('modulos');
        Services::resetSingle('response');
        parent::tearDown();
    }

    public function testModuloAtivoDeixaPassar(): void
    {
        $this->assertNull($this->filtrar(['processos']));
    }

    public function testModuloInativoDevolve403ComCodigo(): void
    {
        $resposta = $this->filtrar(['objetos']);

        $this->assertInstanceOf(ResponseInterface::class, $resposta);
        $this->assertSame(403, $resposta->getStatusCode());
        $corpo = json_decode($resposta->getBody(), true);
        $this->assertSame('modulo_inativo', $corpo['code']);
        $this->assertSame('objetos', $corpo['modulo']);
        $this->assertFalse($corpo['success']);
    }

    public function testVariosSlugsExigemTodosAtivos(): void
    {
        $this->assertNull($this->filtrar(['processos', 'clientes']));
        $this->assertSame('objetos', json_decode($this->filtrar(['processos', 'objetos'])->getBody(), true)['modulo']);
    }

    public function testSemArgumentosDeixaPassar(): void
    {
        $this->assertNull($this->filtrar(null));
    }

    private function filtrar(?array $argumentos): ?ResponseInterface
    {
        return (new ModuloAtivoFilter())->before(service('request'), $argumentos);
    }
}
