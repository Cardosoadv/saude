<?php

namespace Tests\Unit\Controllers;

use App\Database\Seeds\ModulosSeeder;
use App\Services\PermissoesEfetivasService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\AuthGroups;

/**
 * Módulo `documentacao` no catálogo e permissões `documentacao.*` na matriz (issue #701).
 */
class DocumentacaoPermissoesTest extends CIUnitTestCase
{
    public function testModuloNoCatalogo(): void
    {
        $this->assertSame('Documentação', ModulosSeeder::CATALOGO['documentacao']);
    }

    public function testPermissoesDeclaradas(): void
    {
        $auth = new AuthGroups();
        $this->assertArrayHasKey('documentacao.access', $auth->permissions);
        $this->assertArrayHasKey('documentacao.editar', $auth->permissions);
    }

    public function testQuemLeEQuemEdita(): void
    {
        $auth = new AuthGroups();

        foreach (['superadmin', 'admin', 'developer'] as $grupo) {
            $efetivas = $this->efetivas($auth, $grupo);
            $this->assertContains('documentacao.access', $efetivas, $grupo);
            $this->assertContains('documentacao.editar', $efetivas, $grupo);
        }

        foreach (['advogado', 'estagiario', 'user'] as $grupo) {
            $efetivas = $this->efetivas($auth, $grupo);
            $this->assertContains('documentacao.access', $efetivas, $grupo);
            $this->assertNotContains('documentacao.editar', $efetivas, "{$grupo} não edita a documentação");
        }

        $this->assertSame([], preg_grep('/^documentacao\./', $this->efetivas($auth, 'cliente')), 'cliente usa só a Área do Cliente');
    }

    /** @return list<string> */
    private function efetivas(AuthGroups $auth, string $grupo): array
    {
        return PermissoesEfetivasService::calcular([$grupo], [], $auth->matrix, array_keys($auth->permissions));
    }
}
