<?php

namespace Tests\Unit\Traits;

use App\Traits\FormataValorTrait;
use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;

class FormataValorTraitTest extends CIUnitTestCase
{
    use FormataValorTrait;

    public function testRetornaNullParaValorNuloOuVazio()
    {
        $this->assertNull($this->formatarValorParaBanco(null));
        $this->assertNull($this->formatarValorParaBanco(''));
    }

    public function testAceitaValorJaNumerico()
    {
        $this->assertSame(900.0, $this->formatarValorParaBanco(900));
        $this->assertSame(900.5, $this->formatarValorParaBanco(900.5));
        $this->assertSame(900.0, $this->formatarValorParaBanco('900'));
    }

    public function testConverteValorComVirgulaDecimalSimples()
    {
        $this->assertSame(900.0, $this->formatarValorParaBanco('900,00'));
    }

    public function testConverteValorComSeparadorDeMilharEDecimal()
    {
        $this->assertSame(1234.56, $this->formatarValorParaBanco('1.234,56'));
    }

    public function testConverteValorComPrefixoDeMoeda()
    {
        $this->assertSame(450.0, $this->formatarValorParaBanco('R$ 450,00'));
    }

    public function testConverteValorComPrefixoDeMoedaESeparadorDeMilhar()
    {
        $this->assertSame(1234.56, $this->formatarValorParaBanco('R$ 1.234,56'));
    }

    public function testConverteValorNegativoComPrefixoDeMoeda()
    {
        $this->assertSame(-450.0, $this->formatarValorParaBanco('-R$ 450,00'));
    }

    public function testLancaExcecaoParaValorNaoConversivel()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O valor não pôde ser convertido para float.');

        $this->formatarValorParaBanco('abc');
    }

    public function testLancaExcecaoParaTipoInvalido()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O valor deve ser uma string.');

        $this->formatarValorParaBanco(['valor' => 900]);
    }

    public function testFormataValorCpfCnpjParaCpf()
    {
        $this->assertSame('123.456.789-09', $this->formataValorCpf_cnpj('12345678909'));
    }

    public function testFormataValorCpfCnpjParaCnpj()
    {
        $this->assertSame('12.345.678/0001-95', $this->formataValorCpf_cnpj('12345678000195'));
    }

    public function testTratarRateioRetornaVazioQuandoRateioVazio()
    {
        $this->assertSame([], $this->tratarRateio([], 900.0));
    }

    public function testTratarRateioCalculaValoresProporcionais()
    {
        $rateio = [
            ['id' => 1, 'valor' => 50],
            ['id' => 3, 'valor' => 50],
        ];

        $resultado = $this->tratarRateio($rateio, 900.0);

        $this->assertSame(1, $resultado[0]['id']);
        $this->assertSame(450.0, $resultado[0]['valor']);
        $this->assertSame(3, $resultado[1]['id']);
        $this->assertSame(450.0, $resultado[1]['valor']);
    }
}
