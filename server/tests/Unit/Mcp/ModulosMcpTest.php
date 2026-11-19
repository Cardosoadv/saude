<?php

namespace Tests\Unit\Mcp;

use App\Mcp\Support\ModulosMcp;
use App\Services\ModulosService;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use Mcp\Exception\ToolCallException;

/**
 * Sem usuário logado (chamada interna com token de serviço) vale só o módulo.
 */
class ModulosMcpTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $db = \Config\Database::connect();
        $db->query('CREATE TABLE IF NOT EXISTS ' . $db->prefixTable('settings')
            . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, class TEXT, key TEXT, value TEXT, type TEXT, context TEXT,'
            . ' created_at DATETIME, updated_at DATETIME)');

        // Outros testes deixam um usuário logado (actingAs) no serviço de auth.
        Services::resetSingle('auth');
        $_SESSION = [];
        $this->assertNull(auth()->user());

        $modulos = $this->createMock(ModulosService::class);
        $modulos->method('isActive')->willReturnCallback(static fn (string $slug) => $slug === 'clientes');
        Services::injectMock('modulos', $modulos);
    }

    protected function tearDown(): void
    {
        Services::resetSingle('modulos');
        parent::tearDown();
    }

    public function testModuloAtivoSemUsuarioPassa(): void
    {
        ModulosMcp::exigir('clientes', 'processos.access');

        $this->addToAssertionCount(1);
    }

    public function testModuloDesativadoBloqueia(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('desativado');

        ModulosMcp::exigir('tarefas', 'tarefas.access');
    }

    public function testOcultaBlocoDeModuloDesativado(): void
    {
        $dados = ModulosMcp::ocultarBlocos(
            ['clientes' => [1], 'contratos' => [2]],
            ['clientes' => ['clientes', 'processos.access'], 'contratos' => ['contratos', 'intimacoes.access']],
        );

        $this->assertSame(['clientes' => [1], 'contratos' => []], $dados);
    }
}
