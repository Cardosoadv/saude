<?php

namespace Tests\App\Services\Financeiro;

use App\Services\Financeiro\PagamentoReceitasService;
use App\Repositories\Financeiro\PagamentoReceitasRepository;
use App\Repositories\Financeiro\ReceitasRepository;
use App\Repositories\Financeiro\PagamentoReceitasRateioRepository;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Testes unitários para o PagamentoReceitasService.
 * Foca na validação da lógica de negócio e transformação de dados.
 */
class PagamentoReceitasServiceTest extends CIUnitTestCase
{
    /**
     * @var PagamentoReceitasService
     */
    protected $service;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $repositoryMock;

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
        
        $this->repositoryMock = $this->createMock(PagamentoReceitasRepository::class);
        $this->receitasRepositoryMock = $this->createMock(ReceitasRepository::class);
        $this->rateioRepositoryMock = $this->createMock(PagamentoReceitasRateioRepository::class);
        
        $this->service = new PagamentoReceitasService(
            $this->repositoryMock,
            $this->receitasRepositoryMock,
            $this->rateioRepositoryMock
        );
    }

    /**
     * Testa se o método create formata corretamente o valor e o rateio.
     */
    public function testCreateFormatsDataCorrect()
    {
        $data = [
            'valor' => '1.500,00',
            'rateio' => [['id' => 1, 'valor' => '100,00']],
            'metodo' => 'Pix'
        ];

        // O serviço deve transformar '1.500,00' em 1500.0 (float)
        $this->repositoryMock->expects($this->once())
            ->method('create')
            ->with($this->callback(function($arg) {
                return $arg['valor'] === 1500.0 && !isset($arg['rateio']);
            }))
            ->willReturn(123);

        // O rateio deve ser inserido formatado
        $this->rateioRepositoryMock->expects($this->once())
            ->method('insertBatch')
            ->with($this->callback(function($batch) {
                return count($batch) === 1 &&
                       $batch[0]['pagto_receita_id'] === 123 &&
                       $batch[0]['user_id'] === 1 &&
                       $batch[0]['valor'] === 100.0;
            }))
            ->willReturn(true);

        $result = $this->service->create($data);
        $this->assertEquals(123, $result);
    }

    /**
     * Testa se o método update formata corretamente os dados.
     */
    public function testUpdateFormatsDataCorrect()
    {
        $data = [
            'valor' => '2.000,50',
            'rateio' => [['id' => 2, 'valor' => '50,00']]
        ];

        $this->repositoryMock->expects($this->once())
            ->method('update')
            ->with(99, $this->callback(function($arg) {
                return $arg['valor'] === 2000.5 && !isset($arg['rateio']);
            }))
            ->willReturn(true);

        $this->rateioRepositoryMock->expects($this->once())
            ->method('deletarPorPagamento')
            ->with(99)
            ->willReturn(true);

        $this->rateioRepositoryMock->expects($this->once())
            ->method('insertBatch')
            ->with($this->callback(function($batch) {
                return count($batch) === 1 &&
                       $batch[0]['pagto_receita_id'] === 99 &&
                       $batch[0]['user_id'] === 2 &&
                       $batch[0]['valor'] === 50.0;
            }))
            ->willReturn(true);

        $result = $this->service->update(99, $data);
        $this->assertTrue($result);
    }

    /**
     * Testa se o método find retorna o rateio associado.
     */
    public function testFindReturnsRateio()
    {
        $dbData = [
            'id_pgto_receita' => 10,
            'valor' => '500.00',
            'rateio' => [['user_id' => 1, 'valor' => 50.0]]
        ];

        $this->repositoryMock->expects($this->once())
            ->method('findById')
            ->with(10)
            ->willReturn($dbData);

        $result = $this->service->find(10);
        
        $this->assertIsArray($result['rateio']);
        $this->assertEquals(1, $result['rateio'][0]['user_id']);
    }

    /**
     * Testa se o resumo por conta formata o valor total corretamente para exibição.
     */
    public function testGetResumoPorContaFormatsCurrency()
    {
        $repoResult = [
            [
                'nome_conta' => 'Conta Corrente',
                'total' => '1500.00',
                'quantidade' => 5
            ]
        ];

        $this->repositoryMock->expects($this->once())
            ->method('getResumoPorConta')
            ->willReturn($repoResult);

        $result = $this->service->getResumoPorConta();

        $this->assertNotEmpty($result);
        $this->assertEquals('1.500,00', $result[0]['total_formatado']);
    }

    /**
     * Testa o método de exclusão.
     */
    public function testDeleteReturnsSuccess()
    {
        $this->repositoryMock->expects($this->once())
            ->method('delete')
            ->with(50)
            ->willReturn(true);

        $result = $this->service->delete(50);
        $this->assertTrue($result);
    }
}
