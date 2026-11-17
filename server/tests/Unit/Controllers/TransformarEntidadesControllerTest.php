<?php

namespace Tests\Unit\Controllers;

use App\Controllers\ApiV2\Clientes;
use App\Controllers\ApiV2\Fornecedores;
use App\Services\ClienteService;
use App\Services\FornecedorService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\TestResponse;

/**
 * @version 1.0.0
 */
class TransformarEntidadesControllerTest extends CIUnitTestCase
{
    use ControllerTestTrait;
    use AuthenticationTesting;

    private $fornecedorService;
    private $clienteService;
    private TestResponse $resultado;

    protected function setUp(): void
    {
        parent::setUp();

        $db     = \Config\Database::connect();
        $prefix = $db->getPrefix();
        $db->query("CREATE TABLE IF NOT EXISTS {$prefix}settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            class TEXT, key TEXT, value TEXT, type TEXT, context TEXT, created_at DATETIME, updated_at DATETIME
        )");
        $db->query("CREATE TABLE IF NOT EXISTS {$prefix}auth_remember_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            selector TEXT, hashed_validator TEXT, user_id INTEGER, expires DATETIME, created_at DATETIME, updated_at DATETIME
        )");

        $this->fornecedorService = $this->createMock(FornecedorService::class);
        $this->clienteService = $this->createMock(ClienteService::class);
    }

    private function usuario(array $permissoes): User
    {
        $user = $this->getMockBuilder(User::class)
            ->setConstructorArgs([['id' => 1, 'username' => 'teste', 'email' => 'teste@example.com']])
            ->onlyMethods(['can'])
            ->getMock();

        $user->method('can')->willReturnCallback(static fn (string $perm) => in_array($perm, $permissoes, true));

        return $user;
    }

    public function testFornecedorTransformarEmClienteExigePermissao(): void
    {
        $this->fornecedorService->expects($this->never())->method('transformarEmCliente');

        $this->actingAs($this->usuario([]));
        $this->withConfig(config('App'))->controller(Fornecedores::class);
        $this->controller = new Fornecedores($this->fornecedorService);
        $this->controller->initController($this->request, $this->response, $this->logger);

        $this->resultado = $this->execute('transformarEmCliente', 10);

        $this->assertSame(403, $this->resultado->response()->getStatusCode());
    }

    public function testFornecedorTransformarEmClienteComPermissaoSucesso(): void
    {
        $this->fornecedorService->expects($this->once())
            ->method('transformarEmCliente')
            ->with(10, ['inativar_origem' => true])
            ->willReturn([
                'success'    => true,
                'cliente_id' => 99,
                'message'    => 'Fornecedor transformado em cliente com sucesso!'
            ]);

        $this->actingAs($this->usuario(['clientes.create']));
        $this->withConfig(config('App'))->controller(Fornecedores::class);
        $this->controller = new Fornecedores($this->fornecedorService);
        $this->controller->initController($this->request, $this->response, $this->logger);

        $this->request->setHeader('Content-Type', 'application/json');
        $this->withBody(json_encode(['inativar_origem' => true]));

        $this->resultado = $this->execute('transformarEmCliente', 10);

        $this->assertSame(200, $this->resultado->response()->getStatusCode());
        $body = json_decode($this->resultado->response()->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertSame(99, $body['cliente_id']);
    }

    public function testClienteTransformarEmFornecedorExigePermissao(): void
    {
        $this->clienteService->expects($this->never())->method('transformarEmFornecedor');

        $this->actingAs($this->usuario([]));
        $this->withConfig(config('App'))->controller(Clientes::class);
        $this->controller = new Clientes($this->clienteService);
        $this->controller->initController($this->request, $this->response, $this->logger);

        $this->resultado = $this->execute('transformarEmFornecedor', 20);

        $this->assertSame(403, $this->resultado->response()->getStatusCode());
    }

    public function testClienteTransformarEmFornecedorComPermissaoSucesso(): void
    {
        $this->clienteService->expects($this->once())
            ->method('transformarEmFornecedor')
            ->with(20, [
                'inativar_origem' => false,
                'banco'           => 'Banco do Brasil',
                'agencia'         => '1234',
                'conta'           => '5678-9',
                'tipo_conta'      => 'corrente',
                'pix'             => '12345678900',
            ])
            ->willReturn([
                'success'       => true,
                'fornecedor_id' => 88,
                'message'       => 'Cliente transformado em fornecedor com sucesso!'
            ]);

        $this->actingAs($this->usuario(['fornecedores.create']));
        $this->withConfig(config('App'))->controller(Clientes::class);
        $this->controller = new Clientes($this->clienteService);
        $this->controller->initController($this->request, $this->response, $this->logger);

        $this->request->setHeader('Content-Type', 'application/json');
        $this->withBody(json_encode([
            'inativar_origem' => false,
            'banco'           => 'Banco do Brasil',
            'agencia'         => '1234',
            'conta'           => '5678-9',
            'tipo_conta'      => 'corrente',
            'pix'             => '12345678900',
        ]));

        $this->resultado = $this->execute('transformarEmFornecedor', 20);

        $this->assertSame(200, $this->resultado->response()->getStatusCode());
        $body = json_decode($this->resultado->response()->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertSame(88, $body['fornecedor_id']);
    }
}
