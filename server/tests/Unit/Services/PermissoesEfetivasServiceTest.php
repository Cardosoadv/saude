<?php

namespace Tests\Unit\Services;

use App\Services\PermissoesEfetivasService;
use CodeIgniter\Test\CIUnitTestCase;

class PermissoesEfetivasServiceTest extends CIUnitTestCase
{
    private const DECLARADAS = [
        'objetos.access', 'objetos.edit', 'module.access', 'module.edit', 'module.processos', 'clientes.access',
    ];

    public function testExpandeCuringaParaPermissoesDeclaradasDoEscopo(): void
    {
        $efetivas = PermissoesEfetivasService::calcular(['admin'], [], ['admin' => ['module.*']], self::DECLARADAS);

        $this->assertSame(['module.access', 'module.edit', 'module.processos'], $efetivas);
    }

    public function testUneDiretasEGruposSemRepetirEOrdena(): void
    {
        $efetivas = PermissoesEfetivasService::calcular(
            ['advogado', 'estagiario'],
            ['clientes.access', 'Objetos.Edit'],
            [
                'advogado'   => ['objetos.access', 'objetos.edit'],
                'estagiario' => ['objetos.access'],
            ],
            self::DECLARADAS,
        );

        $this->assertSame(['clientes.access', 'objetos.access', 'objetos.edit'], $efetivas);
    }

    public function testCuringaNaoVazaParaEscopoComMesmoPrefixo(): void
    {
        $efetivas = PermissoesEfetivasService::calcular(
            ['g'],
            [],
            ['g' => ['module.*']],
            ['module.access', 'modulex.access'],
        );

        $this->assertSame(['module.access'], $efetivas);
    }

    public function testGrupoDesconhecidoNaoConcedeNada(): void
    {
        $this->assertSame([], PermissoesEfetivasService::calcular(['fantasma'], [], [], self::DECLARADAS));
    }
}
