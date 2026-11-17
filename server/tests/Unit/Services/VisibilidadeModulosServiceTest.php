<?php

namespace Tests\Unit\Services;

use App\Services\ClienteService;
use App\Services\ModulosService;
use App\Services\ProcessosService;
use App\Services\VisibilidadeModulosService;
use CodeIgniter\Test\CIUnitTestCase;

class VisibilidadeModulosServiceTest extends CIUnitTestCase
{
    private const DETALHE_PROCESSO = [
        'processo'        => ['id_processo' => 1],
        'objetos'         => [['id' => 9]],
        'intimacoes'      => [['id' => 3]],
        'tarefas'         => [['id' => 4]],
        'checklists'      => [['id' => 6]],
    ];

    public function testTudoLiberadoMantemOsDados(): void
    {
        $dados = $this->visibilidade(['objetos', 'intimacoes', 'tarefas', 'checklist'], ['*'])
            ->ocultarBlocos(self::DETALHE_PROCESSO, ProcessosService::BLOCOS_DETALHE);

        $this->assertSame(self::DETALHE_PROCESSO, $dados);
    }

    public function testModuloObjetosDesativadoEsvaziaSoObjetos(): void
    {
        $dados = $this->visibilidade(['intimacoes', 'tarefas', 'checklist'], ['*'])
            ->ocultarBlocos(self::DETALHE_PROCESSO, ProcessosService::BLOCOS_DETALHE);

        $this->assertSame([], $dados['objetos']);
        $this->assertSame(self::DETALHE_PROCESSO['intimacoes'], $dados['intimacoes']);
        $this->assertSame(self::DETALHE_PROCESSO['processo'], $dados['processo']);
    }

    public function testSemPermissaoEsvaziaMesmoComModuloAtivo(): void
    {
        $dados = $this->visibilidade(['objetos', 'intimacoes', 'tarefas', 'checklist'], ['intimacoes.access'])
            ->ocultarBlocos(self::DETALHE_PROCESSO, ProcessosService::BLOCOS_DETALHE);

        $this->assertSame([], $dados['objetos']);
        $this->assertSame([], $dados['tarefas']);
        $this->assertSame(self::DETALHE_PROCESSO['intimacoes'], $dados['intimacoes']);
        // Checklist só depende do módulo: grupos como advogado não têm checklist.*.
        $this->assertSame(self::DETALHE_PROCESSO['checklists'], $dados['checklists']);
    }

    public function testQualquerPermissaoDaListaLibera(): void
    {
        $dados = $this->visibilidade(['processos'], ['processos.exclusive'])
            ->ocultarBlocos(['processos' => [1], 'casos' => [2]], ClienteService::BLOCOS_DETALHE);

        $this->assertSame([1], $dados['processos']);
        $this->assertSame([], $dados['casos']);
    }

    public function testChaveAusenteNaoECriada(): void
    {
        $dados = $this->visibilidade([], [])->ocultarBlocos(['processo' => []], ProcessosService::BLOCOS_DETALHE);

        $this->assertSame(['processo' => []], $dados);
    }

    /**
     * @param list<string> $ativos     Slugs ativos.
     * @param list<string> $permissoes Permissões do usuário ('*' = todas).
     */
    private function visibilidade(array $ativos, array $permissoes): VisibilidadeModulosService
    {
        $modulos = $this->createMock(ModulosService::class);
        $modulos->method('isActive')->willReturnCallback(static fn (string $slug) => in_array($slug, $ativos, true));

        return new VisibilidadeModulosService(
            $modulos,
            static fn (string ...$pedidas) => $permissoes === ['*'] || array_intersect($pedidas, $permissoes) !== [],
        );
    }
}
