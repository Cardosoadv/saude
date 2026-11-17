<?php

namespace Tests\Unit\Repositories;

use App\Repositories\UsuariosRepository;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionClass;

/**
 * Normalização dos campos de perfil e de visibilidade (`camposPerfil`).
 * Não usa o banco: a instância é criada sem o construtor.
 */
class UsuariosRepositoryPerfilTest extends CIUnitTestCase
{
    private UsuariosRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = (new ReflectionClass(UsuariosRepository::class))->newInstanceWithoutConstructor();
    }

    public function testCamposAusentesNaoSaoTocados(): void
    {
        $this->assertSame([], $this->repo->camposPerfil(['username' => 'ana', 'email' => 'a@b.com']));
    }

    public function testNormalizaMarcacoesParaInteiro(): void
    {
        $campos = $this->repo->camposPerfil(['responsavel_tarefas' => '1', 'exibir_site' => 0, 'site_ordem' => '-4']);

        $this->assertSame(1, $campos['responsavel_tarefas']);
        $this->assertSame(0, $campos['exibir_site']);
        $this->assertSame(0, $campos['site_ordem']);
    }

    public function testTextoVazioViraNullEAparaEspacos(): void
    {
        $campos = $this->repo->camposPerfil(['cargo' => '  Sócia  ', 'formacao' => '   ', 'lattes_url' => '']);

        $this->assertSame('Sócia', $campos['cargo']);
        $this->assertNull($campos['formacao']);
        $this->assertNull($campos['lattes_url']);
    }

    public function testIgnoraCamposForaDaListaDePerfil(): void
    {
        $campos = $this->repo->camposPerfil(['password' => 'x', 'is_profissional' => 1, 'cargo' => 'Sócio']);

        $this->assertSame(['cargo' => 'Sócio'], $campos);
    }
}
