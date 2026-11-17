<?php

namespace Tests\Unit\Traits;

use App\Traits\ValidaCategoriasRateioTrait;
use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;

class ValidaCategoriasRateioTraitTest extends CIUnitTestCase
{
    use ValidaCategoriasRateioTrait;

    public function testAceitaRateioQueSomaOValorTotal()
    {
        $resultado = $this->validarCategoriasRateio([
            ['categoria_id' => 1, 'valor' => 60.00],
            ['categoria_id' => 2, 'valor' => 40.00],
        ], 100.00);

        $this->assertCount(2, $resultado);
        $this->assertEquals(1, $resultado[0]['categoria_id']);
        $this->assertEquals(60.00, $resultado[0]['valor']);
        $this->assertEquals(2, $resultado[1]['categoria_id']);
        $this->assertEquals(40.00, $resultado[1]['valor']);
    }

    public function testRejeitaQuandoSomaNaoFechaComOValorTotal()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('precisa ser igual ao valor total');

        $this->validarCategoriasRateio([
            ['categoria_id' => 1, 'valor' => 60.00],
            ['categoria_id' => 2, 'valor' => 30.00],
        ], 100.00);
    }

    public function testAceitaPequenaToleranciaDeArredondamento()
    {
        // 33.33 + 33.33 + 33.34 = 100.00 (arredondamentos de divisão em 3 partes)
        $resultado = $this->validarCategoriasRateio([
            ['categoria_id' => 1, 'valor' => 33.33],
            ['categoria_id' => 2, 'valor' => 33.33],
            ['categoria_id' => 3, 'valor' => 33.34],
        ], 100.00);

        $this->assertCount(3, $resultado);
    }

    public function testRejeitaCategoriaDuplicada()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mesma categoria não pode aparecer mais de uma vez');

        $this->validarCategoriasRateio([
            ['categoria_id' => 1, 'valor' => 50.00],
            ['categoria_id' => 1, 'valor' => 50.00],
        ], 100.00);
    }

    public function testRejeitaValorZeroOuNegativo()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->validarCategoriasRateio([
            ['categoria_id' => 1, 'valor' => 0],
        ], 0.00);
    }

    public function testRejeitaItemSemCategoria()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->validarCategoriasRateio([
            ['valor' => 100.00],
        ], 100.00);
    }

    public function testExigeAoMenosUmaCategoriaQuandoObrigatorio()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Selecione ao menos uma categoria');

        $this->validarCategoriasRateio([], 100.00, true);
    }

    public function testPermiteListaVaziaQuandoNaoObrigatorio()
    {
        $resultado = $this->validarCategoriasRateio([], 100.00, false);

        $this->assertSame([], $resultado);
    }
}
