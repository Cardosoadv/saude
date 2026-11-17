<?php

namespace Tests\App\Services;

use App\Services\CasosService;
use App\Repositories\CasosRepository;
use App\Repositories\ProcessosRepository;
use App\Interfaces\AIServiceInterface;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Testes unitários para o CasosService.
 * Foca na validação da lógica de negócio e integração com o repositório.
 */
class CasosServiceTest extends CIUnitTestCase
{
    /**
     * @var CasosService
     */
    protected $service;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $repositoryMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $processosRepositoryMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $aiServiceMock;

    /**
     * Configuração inicial dos mocks e do serviço.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repositoryMock = $this->createMock(CasosRepository::class);
        $this->processosRepositoryMock = $this->createMock(ProcessosRepository::class);
        $this->aiServiceMock = $this->createMock(AIServiceInterface::class);
        $checklistServiceMock = $this->createMock(\App\Services\Checklist\ChecklistService::class);

        // Injeta os mocks no serviço
        $this->service = new CasosService(
            $this->repositoryMock,
            $this->processosRepositoryMock,
            $this->aiServiceMock,
            $checklistServiceMock
        );
    }

    /**
     * Testa se o método salvarCaso formata corretamente o valor da causa e chama o repositório.
     */
    public function testSalvarCasoFormatsCurrencyAndCallsRepository()
    {
        $data = [
            'titulo' => 'Caso Teste',
            'valor_causa' => '1.234,56'
        ];

        // O serviço deve transformar '1.234,56' em '1234.56'
        $this->repositoryMock->expects($this->once())
            ->method('salvar')
            ->with($this->callback(function($arg) {
                return $arg['valor_causa'] === '1234.56' && $arg['titulo'] === 'Caso Teste';
            }), null)
            ->willReturn(1);

        $result = $this->service->salvarCaso($data);
        $this->assertEquals(1, $result);
    }

    /**
     * Testa se o método salvarCaso chama o repositório corretamente para atualização.
     */
    public function testSalvarCasoWithExistingId()
    {
        $id = 123;
        $data = [
            'titulo' => 'Caso Atualizado'
        ];

        $this->repositoryMock->expects($this->once())
            ->method('salvar')
            ->with($data, $id)
            ->willReturn($id);

        $result = $this->service->salvarCaso($data, $id);
        $this->assertEquals($id, $result);
    }

    /**
     * Testa se o método listarCasos chama o repositório com os parâmetros corretos.
     */
    public function testListarCasosCallsRepository()
    {
        $search = 'teste';
        $sortField = 'titulo';
        $sortOrder = 'ASC';
        $perPage = 10;
        $filtros = ['status' => 'Ativo'];

        $expectedResult = ['casos' => [], 'pager' => null];

        $this->repositoryMock->expects($this->once())
            ->method('buscarCasos')
            ->with($search, $sortField, $sortOrder, $perPage, $filtros)
            ->willReturn($expectedResult);

        $result = $this->service->listarCasos($search, $sortField, $sortOrder, $perPage, $filtros);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Testa se o método buscarCaso chama o repositório corretamente.
     */
    public function testBuscarCasoCallsRepository()
    {
        $id = 1;
        $expectedCase = ['id_caso' => 1, 'titulo' => 'Caso 1'];

        $this->repositoryMock->expects($this->once())
            ->method('buscarPorId')
            ->with($id)
            ->willReturn($expectedCase);

        $result = $this->service->buscarCaso($id);
        $this->assertEquals($expectedCase, $result);
    }

    /**
     * Testa se o método excluirCaso chama o repositório corretamente.
     */
    public function testExcluirCasoCallsRepository()
    {
        $id = 50;

        $this->repositoryMock->expects($this->once())
            ->method('excluir')
            ->with($id)
            ->willReturn(true);

        $result = $this->service->excluirCaso($id);
        $this->assertTrue($result);
    }

    /**
     * Testa se o método transformarEmProcesso lida corretamente com casos inexistentes.
     */
    public function testTransformarEmProcessoThrowsExceptionIfCasoNotFound()
    {
        $this->repositoryMock->method('buscarPorId')->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Caso não encontrado.");

        $this->service->transformarEmProcesso(999, '1234567');
    }

    /**
     * Testa se o método gerarEstudoCaso chama o serviço de IA e salva o resultado.
     */
    public function testGerarEstudoCasoCallsAIServiceAndUpdatesRepository()
    {
        $casoId = 1;
        $caso = ['id_caso' => 1, 'titulo' => 'Caso 1', 'descricao' => 'Desc'];
        $estudo = 'Estudo gerado pela IA';

        $this->repositoryMock->expects($this->once())
            ->method('buscarPorId')
            ->with($casoId)
            ->willReturn($caso);

        $this->aiServiceMock->expects($this->once())
            ->method('generateCaseStudy')
            ->with($caso)
            ->willReturn($estudo);

        $this->repositoryMock->expects($this->once())
            ->method('update')
            ->with($casoId, ['estudo_caso' => $estudo])
            ->willReturn(true);

        $result = $this->service->gerarEstudoCaso($casoId);
        $this->assertEquals($estudo, $result);
    }
}
