<?php

namespace Tests\Unit\Services;

use App\Repositories\ClienteRepository;
use App\Repositories\FornecedorRepository;
use App\Services\ClienteService;
use App\Services\FornecedorService;
use CodeIgniter\Test\CIUnitTestCase;
use Exception;

/**
 * @version 1.0.0
 */
class TransformarEntidadesServiceTest extends CIUnitTestCase
{
    private FornecedorService $fornecedorService;
    private ClienteService $clienteService;
    private $fornecedorRepoMock;
    private $clienteRepoMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fornecedorRepoMock = $this->createMock(FornecedorRepository::class);
        $this->clienteRepoMock = $this->createMock(ClienteRepository::class);

        $this->fornecedorService = new FornecedorService($this->fornecedorRepoMock);
        $this->clienteService = new ClienteService($this->clienteRepoMock);

        $this->fornecedorService->setClienteService($this->clienteService);
        $this->clienteService->setFornecedorService($this->fornecedorService);
    }

    public function testTransformarEmClienteLancaExcecaoQuandoFornecedorNaoEncontrado(): void
    {
        $this->fornecedorRepoMock->method('findById')->with(999)->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Fornecedor não encontrado.');

        $this->fornecedorService->transformarEmCliente(999);
    }

    public function testTransformarEmFornecedorLancaExcecaoQuandoClienteNaoEncontrado(): void
    {
        $this->clienteRepoMock->method('findById')->with(888)->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cliente não encontrado.');

        $this->clienteService->transformarEmFornecedor(888);
    }

    public function testTransformarFornecedorEmClienteComSucesso(): void
    {
        $fornecedorData = [
            'id_fornecedor' => 10,
            'tipo_pessoa'   => 'J',
            'nome'          => 'Tech Solucoes LTDA',
            'razao_social'  => 'Tech Solucoes Empresariais LTDA',
            'documento'     => '12.345.678/0001-99',
            'email'         => 'contato@techsolucoes.com',
            'telefone'      => '11999998888',
            'endereco'      => 'Av Paulista',
            'numero'        => '1000',
            'complemento'   => 'Sala 101',
            'cep'           => '01310-100',
            'cidade'        => 'São Paulo',
            'uf'            => 'SP',
            'ativo'         => 1,
        ];

        $this->fornecedorRepoMock->method('findById')->with(10)->willReturn($fornecedorData);

        // Mock ClienteService para criar o cliente
        $clienteServiceMock = $this->createMock(ClienteService::class);
        $clienteServiceMock->expects($this->once())
            ->method('salvarCliente')
            ->with($this->callback(function ($dados) {
                return $dados['nome'] === 'Tech Solucoes LTDA'
                    && $dados['tipo_cliente'] === 'J'
                    && $dados['documento'] === '12.345.678/0001-99'
                    && $dados['email'] === 'contato@techsolucoes.com'
                    && $dados['status_crm'] === 'client';
            }))
            ->willReturn(55);

        $this->fornecedorService->setClienteService($clienteServiceMock);

        $this->fornecedorRepoMock->expects($this->once())
            ->method('update')
            ->with(10, ['ativo' => 0])
            ->willReturn(true);

        $res = $this->fornecedorService->transformarEmCliente(10, ['inativar_origem' => true]);

        $this->assertTrue($res['success']);
        $this->assertSame(55, $res['cliente_id']);
        $this->assertStringContainsString('transformado em cliente com sucesso', $res['message']);
    }

    public function testTransformarClienteEmFornecedorComSucesso(): void
    {
        $clienteData = [
            'id_cliente'    => 20,
            'tipo_cliente'  => 'F',
            'nome'          => 'Carlos da Silva',
            'razao_social'  => null,
            'documento'     => '123.456.789-00',
            'email'         => 'carlos@silva.com',
            'telefone'      => '11988887777',
            'endereco'      => 'Rua das Flores',
            'numero'        => '50',
            'complemento'   => null,
            'cep'           => '01001-000',
            'cidade'        => 'São Paulo',
            'uf'            => 'SP',
            'ativo'         => 1,
        ];

        $this->clienteRepoMock->method('findById')->with(20)->willReturn($clienteData);

        // Mock FornecedorService para criar fornecedor
        $fornecedorServiceMock = $this->createMock(FornecedorService::class);
        $fornecedorServiceMock->expects($this->once())
            ->method('salvarFornecedor')
            ->with($this->callback(function ($dados) {
                return $dados['nome'] === 'Carlos da Silva'
                    && $dados['tipo_pessoa'] === 'F'
                    && $dados['documento'] === '123.456.789-00'
                    && $dados['banco'] === 'Nubank'
                    && $dados['pix'] === 'carlos@silva.com';
            }))
            ->willReturn(77);

        $this->clienteService->setFornecedorService($fornecedorServiceMock);

        $this->clienteRepoMock->expects($this->once())
            ->method('update')
            ->with(20, ['ativo' => 0])
            ->willReturn(true);

        $res = $this->clienteService->transformarEmFornecedor(20, [
            'inativar_origem' => true,
            'banco'           => 'Nubank',
            'pix'             => 'carlos@silva.com'
        ]);

        $this->assertTrue($res['success']);
        $this->assertSame(77, $res['fornecedor_id']);
        $this->assertStringContainsString('transformado em fornecedor com sucesso', $res['message']);
    }

    public function testTransformarFornecedorEmClientePropagaErroDeDuplicidade(): void
    {
        $fornecedorData = [
            'id_fornecedor' => 10,
            'tipo_pessoa'   => 'J',
            'nome'          => 'Tech Solucoes LTDA',
            'documento'     => '12.345.678/0001-99',
            'ativo'         => 1,
        ];

        $this->fornecedorRepoMock->method('findById')->with(10)->willReturn($fornecedorData);

        $clienteServiceMock = $this->createMock(ClienteService::class);
        $clienteServiceMock->method('salvarCliente')
            ->willThrowException(new Exception('Já existe um cliente cadastrado com este documento.'));

        $this->fornecedorService->setClienteService($clienteServiceMock);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Já existe um cliente cadastrado com este documento.');

        $this->fornecedorService->transformarEmCliente(10);
    }

    public function testTransformarClienteEmFornecedorPropagaErroDeDuplicidade(): void
    {
        $clienteData = [
            'id_cliente'    => 20,
            'tipo_cliente'  => 'F',
            'nome'          => 'Carlos da Silva',
            'documento'     => '123.456.789-00',
            'ativo'         => 1,
        ];

        $this->clienteRepoMock->method('findById')->with(20)->willReturn($clienteData);

        $fornecedorServiceMock = $this->createMock(FornecedorService::class);
        $fornecedorServiceMock->method('salvarFornecedor')
            ->willThrowException(new Exception('Já existe um fornecedor cadastrado com este documento.'));

        $this->clienteService->setFornecedorService($fornecedorServiceMock);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Já existe um fornecedor cadastrado com este documento.');

        $this->clienteService->transformarEmFornecedor(20);
    }
}
