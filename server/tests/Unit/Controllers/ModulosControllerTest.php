<?php

namespace Tests\Unit\Controllers;

use App\Controllers\ApiV2\Modulos;
use App\Services\ModulosService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\TestResponse;

class ModulosControllerTest extends CIUnitTestCase
{
    use ControllerTestTrait;
    use AuthenticationTesting;

    private ModulosService $service;

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

        $this->service = $this->createMock(ModulosService::class);
    }

    public function testIndexExigeModuleAccess(): void
    {
        $this->service->expects($this->never())->method('listar');

        $this->executar('index', []);

        $this->assertSame(403, $this->resultado->response()->getStatusCode());
    }

    public function testIndexListaModulos(): void
    {
        $modulos = [['id' => 1, 'slug' => 'objetos', 'nome' => 'Objetos', 'ativo' => true]];
        $this->service->method('listar')->willReturn($modulos);

        $this->executar('index', ['module.access']);

        $this->assertSame(200, $this->resultado->response()->getStatusCode());
        $this->assertSame($modulos, $this->corpo()['data']);
    }

    public function testToggleExigeModuleEdit(): void
    {
        $this->service->expects($this->never())->method('alterarStatus');

        $this->executar('toggleStatus', ['module.access'], ['slug' => 'objetos', 'ativo' => false]);

        $this->assertSame(403, $this->resultado->response()->getStatusCode());
    }

    public function testToggleValidaCampos(): void
    {
        $this->service->expects($this->never())->method('alterarStatus');

        $this->executar('toggleStatus', ['module.edit'], ['slug' => '', 'ativo' => 'talvez']);

        $this->assertSame(400, $this->resultado->response()->getStatusCode());
        $erros = $this->corpo()['messages'];
        $this->assertArrayHasKey('slug', $erros);
        $this->assertArrayHasKey('ativo', $erros);
    }

    public function testToggleSlugInexistente(): void
    {
        $this->service->method('alterarStatus')->willReturn(null);

        $this->executar('toggleStatus', ['module.edit'], ['slug' => 'nao-existe', 'ativo' => true]);

        $this->assertSame(404, $this->resultado->response()->getStatusCode());
    }

    public function testToggleModuloEssencial(): void
    {
        $this->service->method('alterarStatus')
            ->willThrowException(new \DomainException('O módulo Administrativo não pode ser desativado.'));

        $this->executar('toggleStatus', ['module.edit'], ['slug' => 'administrativo', 'ativo' => false]);

        $this->assertSame(400, $this->resultado->response()->getStatusCode());
        $this->assertStringContainsString('não pode ser desativado', $this->corpo()['messages']['ativo']);
    }

    public function testToggleDesativaModulo(): void
    {
        $modulo = ['id' => 5, 'slug' => 'objetos', 'nome' => 'Objetos', 'ativo' => false];
        $this->service->expects($this->once())->method('alterarStatus')
            ->with('objetos', false)
            ->willReturn($modulo);

        $this->executar('toggleStatus', ['module.edit'], ['slug' => 'objetos', 'ativo' => false]);

        $this->assertSame(200, $this->resultado->response()->getStatusCode());
        $this->assertSame($modulo, $this->corpo()['data']);
    }

    private TestResponse $resultado;

    private function executar(string $metodo, array $permissoes, ?array $corpo = null): void
    {
        $this->actingAs($this->usuario($permissoes));

        $this->withConfig(config('App'))->controller(Modulos::class);
        $this->controller = new Modulos($this->service);
        $this->controller->initController($this->request, $this->response, $this->logger);

        if ($corpo !== null) {
            $this->request->setHeader('Content-Type', 'application/json');
            $this->withBody(json_encode($corpo));
        }

        $this->resultado = $this->execute($metodo);
    }

    private function corpo(): array
    {
        return json_decode($this->resultado->response()->getBody(), true);
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
}
