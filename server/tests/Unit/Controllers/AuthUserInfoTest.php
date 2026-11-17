<?php

namespace Tests\Unit\Controllers;

use App\Controllers\ApiV2\Auth;
use App\Services\ModulosService;
use CodeIgniter\Config\Services;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;

class AuthUserInfoTest extends CIUnitTestCase
{
    use ControllerTestTrait;
    use AuthenticationTesting;

    private bool $configurado = true;

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

        $modulos = $this->createMock(ModulosService::class);
        $modulos->method('configurado')->willReturnCallback(fn () => $this->configurado);
        $modulos->method('getActiveModules')->willReturn(['clientes', 'objetos']);
        Services::injectMock('modulos', $modulos);
    }

    protected function tearDown(): void
    {
        Services::resetSingle('modulos');
        parent::tearDown();
    }

    public function testSemModulosConfiguradosMandaNull(): void
    {
        $this->configurado = false;
        $this->actingAs($this->usuario());

        $result = $this->withConfig(config('App'))->controller(Auth::class)->execute('userInfo');

        $dados = json_decode($result->response()->getBody(), true)['user'];
        $this->assertArrayHasKey('modulos_ativos', $dados);
        $this->assertNull($dados['modulos_ativos']);
    }

    private function usuario(): User
    {
        $user = $this->getMockBuilder(User::class)
            ->setConstructorArgs([['id' => 3, 'username' => 'ana', 'email' => 'ana@example.com']])
            ->onlyMethods(['getGroups', 'getPermissions'])
            ->getMock();
        $user->method('getGroups')->willReturn(['admin']);
        $user->method('getPermissions')->willReturn([]);

        return $user;
    }

    public function testUserInfoTrazPermissoesEModulosAtivos(): void
    {
        $user = $this->getMockBuilder(User::class)
            ->setConstructorArgs([['id' => 3, 'username' => 'ana', 'email' => 'ana@example.com']])
            ->onlyMethods(['getGroups', 'getPermissions'])
            ->getMock();
        $user->method('getGroups')->willReturn(['admin']);
        $user->method('getPermissions')->willReturn(['objetos.access']);
        $this->actingAs($user);

        $result = $this->withConfig(config('App'))->controller(Auth::class)->execute('userInfo');

        $this->assertSame(200, $result->response()->getStatusCode());
        $dados = json_decode($result->response()->getBody(), true)['user'];

        $this->assertSame(['clientes', 'objetos'], $dados['modulos_ativos']);
        $this->assertContains('objetos.access', $dados['permissoes']);
        // admin recebe module.* na matriz do AuthGroups.
        $this->assertContains('module.edit', $dados['permissoes']);
        $this->assertNotContains('module.*', $dados['permissoes']);
    }
}
