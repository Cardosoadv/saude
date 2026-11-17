<?php

namespace Tests\Unit\Services;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ModulosService;
use App\Repositories\ModulosRepository;

class ModulosServiceTest extends CIUnitTestCase
{
    private $service;
    private $mockRepository;

    protected function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) { $this->db->query('PRAGMA foreign_keys = OFF;'); }

        // Reset the static property before each test
        $reflection = new \ReflectionClass(ModulosService::class);
        $staticProperty = $reflection->getProperty('activeModules');
        $staticProperty->setAccessible(true);
        $staticProperty->setValue(null);
        $reflection->getProperty('configurado')->setValue(null, null);

        $this->mockRepository = $this->createMock(ModulosRepository::class);
        $this->mockRepository->method('contarModulos')->willReturn(3);
        $this->service = new ModulosService($this->mockRepository);
    }

    public function testIsActiveUsesCacheAndIsCaseInsensitive()
    {
        $this->mockRepository->expects($this->once())
            ->method('getActiveModules')
            ->willReturn([
                ['nome' => 'Módulo 1', 'slug' => 'modulo1', 'ativo' => 1],
                ['nome' => 'Módulo 2', 'slug' => 'MODULO2', 'ativo' => 1],
                ['nome' => 'Fornecedores Parceiros', 'slug' => null, 'ativo' => 1],
                ['nome' => 'Agendamentos', 'ativo' => 1],
            ]);

        // First call should trigger repository call
        $this->assertTrue($this->service->isActive('Modulo1'));

        // Second call should use cache (once expectation ensures only one call to repository)
        $this->assertTrue($this->service->isActive('modulo1'));
        $this->assertTrue($this->service->isActive('Modulo2'));
        $this->assertTrue($this->service->isActive('modulo2'));
        $this->assertFalse($this->service->isActive('Modulo3'));
        $this->assertFalse($this->service->isActive('Módulo 1'), 'Compara pelo slug, não pelo nome.');
        // Sem slug (ou sem a coluna): deriva do nome, como o ModulosSeeder.
        $this->assertSame(['modulo1', 'modulo2', 'fornecedores-parceiros', 'agendamentos'], $this->service->getActiveModules());
    }

    public function testSaveClearsCache()
    {
        $this->mockRepository->expects($this->exactly(2))
            ->method('getActiveModules')
            ->willReturnOnConsecutiveCalls(
                [['nome' => 'Modulo1', 'slug' => 'modulo1', 'ativo' => 1]],
                [['nome' => 'Modulo1', 'slug' => 'modulo1', 'ativo' => 1], ['nome' => 'Modulo2', 'slug' => 'modulo2', 'ativo' => 1]]
            );

        $this->assertTrue($this->service->isActive('Modulo1'));
        $this->assertFalse($this->service->isActive('Modulo2'));

        // Mock create return
        $this->mockRepository->method('create')->willReturn(1);

        // Save new module
        $this->service->save(['nome' => 'Modulo2', 'slug' => 'modulo2', 'ativo' => 1]);

        // Should trigger second call to repository because cache was cleared
        $this->assertTrue($this->service->isActive('Modulo2'));
    }

    public function testDeleteClearsCache()
    {
        $this->mockRepository->expects($this->exactly(2))
            ->method('getActiveModules')
            ->willReturnOnConsecutiveCalls(
                [['nome' => 'Modulo1', 'slug' => 'modulo1', 'ativo' => 1]],
                []
            );

        $this->assertTrue($this->service->isActive('Modulo1'));

        // Mock delete return
        $this->mockRepository->method('delete')->willReturn(true);

        // Delete module
        $this->service->delete(1);

        // Should trigger second call to repository because cache was cleared
        $this->assertFalse($this->service->isActive('Modulo1'));
    }

    public function testAlterarStatusSlugInexistente()
    {
        $this->mockRepository->method('getBySlug')->willReturn(null);
        $this->mockRepository->expects($this->never())->method('update');

        $this->assertNull($this->service->alterarStatus('nao-existe', true));
    }

    public function testAlterarStatusNaoDesativaModuloEssencial()
    {
        $this->mockRepository->method('getBySlug')
            ->willReturn(['id' => 1, 'slug' => 'administrativo', 'nome' => 'Administrativo', 'ativo' => 1]);
        $this->mockRepository->expects($this->never())->method('update');

        $this->expectException(\DomainException::class);
        $this->service->alterarStatus('administrativo', false);
    }

    public function testAlterarStatusDesativaELimpaCache()
    {
        $this->mockRepository->method('getActiveModules')->willReturnOnConsecutiveCalls(
            [['slug' => 'fornecedores', 'ativo' => 1]],
            [],
        );
        $this->mockRepository->method('getBySlug')
            ->willReturn(['id' => 7, 'slug' => 'fornecedores', 'nome' => 'Fornecedores', 'ativo' => '1']);
        $this->mockRepository->expects($this->once())->method('update')
            ->with(7, ['ativo' => 0])
            ->willReturn(true);

        $this->assertTrue($this->service->isActive('fornecedores'));

        $modulo = $this->service->alterarStatus('fornecedores', false);

        $this->assertSame(['id' => 7, 'slug' => 'fornecedores', 'nome' => 'Fornecedores', 'ativo' => false], $modulo);
        $this->assertFalse($this->service->isActive('fornecedores'));
    }

    public function testAlterarStatusSemMudancaNaoGrava()
    {
        $this->mockRepository->method('getBySlug')
            ->willReturn(['id' => 7, 'slug' => 'fornecedores', 'nome' => 'Fornecedores', 'ativo' => 1]);
        $this->mockRepository->expects($this->never())->method('update');

        $this->assertTrue($this->service->alterarStatus('fornecedores', true)['ativo']);
    }

    public function testAlterarStatusFalhaAoGravar()
    {
        $this->mockRepository->method('getBySlug')
            ->willReturn(['id' => 7, 'slug' => 'fornecedores', 'nome' => 'Fornecedores', 'ativo' => 1]);
        $this->mockRepository->method('update')->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->service->alterarStatus('fornecedores', false);
    }

    public function testSemModulosCadastradosTudoLiberado()
    {
        $vazio = $this->createMock(ModulosRepository::class);
        $vazio->method('contarModulos')->willReturn(0);
        $vazio->method('getActiveModules')->willReturn([]);
        $service = new ModulosService($vazio);

        $this->assertFalse($service->configurado());
        $this->assertTrue($service->isActive('fornecedores'));
        $this->assertTrue($service->isActive('qualquer'));
    }

    public function testComModulosCadastradosTodosDesativadosBloqueia()
    {
        $this->mockRepository->method('getActiveModules')->willReturn([]);

        $this->assertTrue($this->service->configurado());
        $this->assertFalse($this->service->isActive('fornecedores'));
    }
}
