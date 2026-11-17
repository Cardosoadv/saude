<?php

namespace Tests\App\Services\Financeiro;

use App\Services\Financeiro\PagamentoDespesasService;
use App\Repositories\Financeiro\PagamentoDespesasRepository;
use App\Repositories\Financeiro\DespesasRepository;
use App\Repositories\Financeiro\ContasRepository;
use App\Repositories\Financeiro\ReceitasRepository;
use App\Repositories\Financeiro\PagamentoDespesasRateioRepository;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Testes unitários para o PagamentoDespesasService.
 * Valida a codificação/decodificação do rateio e formatação de valores monetários.
 */
class PagamentoDespesasServiceTest extends CIUnitTestCase
{
    /**
     * @var PagamentoDespesasService
     */
    protected $service;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $repositoryMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $despesasRepositoryMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $contasRepositoryMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $receitasRepositoryMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $rateioRepositoryMock;

    /**
     * Configuração inicial dos mocks e do serviço.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repositoryMock = $this->createMock(PagamentoDespesasRepository::class);
        $this->despesasRepositoryMock = $this->createMock(DespesasRepository::class);
        $this->contasRepositoryMock = $this->createMock(ContasRepository::class);
        $this->receitasRepositoryMock = $this->createMock(ReceitasRepository::class);
        $this->rateioRepositoryMock = $this->createMock(PagamentoDespesasRateioRepository::class);
        
        $this->service = new PagamentoDespesasService(
            $this->repositoryMock,
            $this->despesasRepositoryMock,
            $this->contasRepositoryMock,
            $this->receitasRepositoryMock,
            $this->rateioRepositoryMock
        );
    }

    /**
     * Testa se o método create decodifica rateio em formato JSON string e formata os valores.
     */
    public function testCreateDecodesJsonRateioAndFormatsValues()
    {
        $data = [
            'valor' => '2.500,00',
            'rateio' => json_encode([['id' => 10, 'valor' => '250,50']]),
            'metodo' => 'Transferência'
        ];

        // O serviço deve transformar '2.500,00' em 2500.0 (float)
        $this->repositoryMock->expects($this->once())
            ->method('create')
            ->with($this->callback(function($arg) {
                return $arg['valor'] === 2500.0 && !isset($arg['rateio']);
            }))
            ->willReturn(55);

        // O rateio deve ser decodificado e inserido formatado
        $this->rateioRepositoryMock->expects($this->once())
            ->method('insertBatch')
            ->with($this->callback(function($batch) {
                return count($batch) === 1 &&
                       $batch[0]['pagto_despesa_id'] === 55 &&
                       $batch[0]['user_id'] === 10 &&
                       $batch[0]['valor'] === 250.5;
            }))
            ->willReturn(true);

        $result = $this->service->create($data);
        $this->assertEquals(55, $result);
    }

    /**
     * Testa se o método update decodifica o rateio JSON e processa atualizações.
     */
    public function testUpdateDecodesJsonRateioAndDeletesAndInserts()
    {
        $data = [
            'valor' => '500,00',
            'rateio' => json_encode([['id' => 15, 'valor' => '100,00']])
        ];

        $this->repositoryMock->expects($this->once())
            ->method('update')
            ->with(77, $this->callback(function($arg) {
                return $arg['valor'] === 500.0 && !isset($arg['rateio']);
            }))
            ->willReturn(true);

        $this->rateioRepositoryMock->expects($this->once())
            ->method('deletarPorPagamento')
            ->with(77)
            ->willReturn(true);

        $this->rateioRepositoryMock->expects($this->once())
            ->method('insertBatch')
            ->with($this->callback(function($batch) {
                return count($batch) === 1 &&
                       $batch[0]['pagto_despesa_id'] === 77 &&
                       $batch[0]['user_id'] === 15 &&
                       $batch[0]['valor'] === 100.0;
            }))
            ->willReturn(true);

        $result = $this->service->update(77, $data);
        $this->assertTrue($result);
    }

    /**
     * Testa se o método update remove os registros de rateio anteriores se o rateio for limpo (array vazio).
     */
    public function testUpdateCleansRateioWhenArrayIsEmpty()
    {
        $data = [
            'valor' => '750,00',
            'rateio' => []
        ];

        $this->repositoryMock->expects($this->once())
            ->method('update')
            ->with(88, $this->callback(function($arg) {
                return $arg['valor'] === 750.0 && !isset($arg['rateio']);
            }))
            ->willReturn(true);

        $this->rateioRepositoryMock->expects($this->once())
            ->method('deletarPorPagamento')
            ->with(88)
            ->willReturn(true);

        // Não deve inserir nada pois o rateio está vazio
        $this->rateioRepositoryMock->expects($this->never())
            ->method('insertBatch');

        $result = $this->service->update(88, $data);
        $this->assertTrue($result);
    }

    /**
     * Testa se prepararPagamento decodifica corretamente um rateio em JSON.
     */
    public function testPreparePagamentoDecodesJsonRateio()
    {
        $despesa = [
            'id_despesa' => 20,
            'valor' => '1000.00',
            'rateio' => json_encode([['id' => 1, 'valor' => 50]])
        ];

        $this->despesasRepositoryMock->expects($this->once())
            ->method('find')
            ->with(20)
            ->willReturn($despesa);

        $result = $this->service->prepararPagamento(20);

        $this->assertNotNull($result);
        $this->assertEquals(20, $result['despesa_id']);
        $this->assertEquals('1000.00', $result['valor']);
        $this->assertIsArray($result['rateio']);
        $this->assertEquals(1, $result['rateio'][0]['id']);
        $this->assertEquals(500.0, $result['rateio'][0]['valor']);
    }
}
