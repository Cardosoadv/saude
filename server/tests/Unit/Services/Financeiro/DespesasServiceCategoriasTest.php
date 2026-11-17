<?php

namespace Tests\Unit\Services\Financeiro;

use App\Repositories\ContratosRepository;
use App\Repositories\Financeiro\DespesasRepository;
use App\Services\Financeiro\DespesasService;
use App\Services\Financeiro\PagamentoDespesasService;
use App\Services\Financeiro\SaldoMensalService;
use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;

/**
 * Testa a validação do rateio de categorias (múltiplas categorias por despesa)
 * na camada de serviço, isolando as demais dependências via mocks.
 */
class DespesasServiceCategoriasTest extends CIUnitTestCase
{
    private function criarServico(DespesasRepository $repositoryMock): DespesasService
    {
        return new DespesasService(
            $repositoryMock,
            $this->createMock(SaldoMensalService::class),
            $this->createMock(PagamentoDespesasService::class),
            $this->createMock(ContratosRepository::class)
        );
    }

    public function testCreateRejeitaQuandoSomaDeCategoriasNaoFechaComValor()
    {
        $repositoryMock = $this->createMock(DespesasRepository::class);
        $repositoryMock->expects($this->never())->method('create');

        $service = $this->criarServico($repositoryMock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('precisa ser igual ao valor total');

        $service->create([
            'despesa' => 'Conta de luz e aluguel',
            'vencimento_dt' => date('Y-m-d'),
            'valor' => 100.00,
            'fornecedor' => 1,
            'categorias' => [
                ['categoria_id' => 1, 'valor' => 50.00],
            ],
        ]);
    }

    public function testCreateRejeitaQuandoNenhumaCategoriaInformada()
    {
        $repositoryMock = $this->createMock(DespesasRepository::class);
        $repositoryMock->expects($this->never())->method('create');

        $service = $this->criarServico($repositoryMock);

        $this->expectException(InvalidArgumentException::class);

        $service->create([
            'despesa' => 'Conta sem categoria',
            'vencimento_dt' => date('Y-m-d'),
            'valor' => 100.00,
            'fornecedor' => 1,
        ]);
    }

    public function testCreateAceitaQuandoSomaDeCategoriasFechaComValor()
    {
        $repositoryMock = $this->createMock(DespesasRepository::class);
        $repositoryMock->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data) {
                return $data['valor'] === 100.00
                    && count($data['categorias']) === 2
                    && $data['categorias'][0]['categoria_id'] === 1
                    && $data['categorias'][0]['valor'] === 60.00
                    && $data['categorias'][1]['categoria_id'] === 2
                    && $data['categorias'][1]['valor'] === 40.00;
            }))
            ->willReturn(42);

        $service = $this->criarServico($repositoryMock);

        $id = $service->create([
            'despesa' => 'Conta de luz e aluguel',
            'vencimento_dt' => date('Y-m-d'),
            'valor' => 100.00,
            'fornecedor' => 1,
            'categorias' => [
                ['categoria_id' => 1, 'valor' => 60.00],
                ['categoria_id' => 2, 'valor' => 40.00],
            ],
        ]);

        $this->assertSame(42, $id);
    }

    public function testUpdateRejeitaQuandoSomaDeCategoriasNaoFechaComValorExistente()
    {
        $repositoryMock = $this->createMock(DespesasRepository::class);
        $repositoryMock->method('findById')->willReturn([
            'id_despesa' => 7,
            'valor' => 200.00,
            'conta_id' => null,
            'data' => null,
        ]);
        $repositoryMock->expects($this->never())->method('update');

        $service = $this->criarServico($repositoryMock);

        $this->expectException(InvalidArgumentException::class);

        // Não reenvia 'valor' — validação deve usar o valor já persistido (200.00).
        $service->update(7, [
            'categorias' => [
                ['categoria_id' => 1, 'valor' => 50.00],
            ],
        ]);
    }
}
